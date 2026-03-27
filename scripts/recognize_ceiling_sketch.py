#!/usr/bin/env python
import argparse
import json
import math
import re
import sys
from pathlib import Path
from typing import Dict, List, Optional, Tuple

import cv2
import numpy as np
from PIL import Image, ImageFilter, ImageOps
from rapidocr_onnxruntime import RapidOCR


NUMERIC_TOKEN_RE = re.compile(r"(?<![\d])(\d{2,4})(?![\d])")
DECIMAL_TOKEN_RE = re.compile(r"(\d{1,3}(?:[.,]\d{1,2})?)")
AMBIGUOUS_DIGITS = {
    "O": ["0"],
    "Q": ["0"],
    "I": ["1"],
    "L": ["1"],
    "|": ["1"],
    "/": ["1"],
    "\\": ["1"],
    "!": ["1"],
    "Z": ["2"],
    "A": ["4", "1"],
    "H": ["4"],
    "$": ["5"],
    "S": ["5"],
    "G": ["6"],
    "+": ["7"],
    "T": ["7", "1"],
    "Y": ["7"],
    "?": ["7"],
    "B": ["8"],
}


def pil_to_bgr(image: Image.Image) -> np.ndarray:
    rgb = image.convert("RGB")
    return cv2.cvtColor(np.array(rgb), cv2.COLOR_RGB2BGR)


def build_variants(image_path: Path) -> List[Tuple[str, np.ndarray]]:
    source = Image.open(image_path).convert("L")
    source = ImageOps.exif_transpose(source)
    variants: List[Tuple[str, np.ndarray]] = []

    variants.append(("gray", pil_to_bgr(source)))

    autocontrast = ImageOps.autocontrast(source)
    variants.append(("autocontrast", pil_to_bgr(autocontrast)))

    threshold = autocontrast.point(lambda pixel: 255 if pixel > 175 else 0)
    variants.append(("threshold", pil_to_bgr(threshold)))

    inverted = ImageOps.invert(threshold)
    variants.append(("inverted", pil_to_bgr(inverted)))

    sharpened = autocontrast.filter(ImageFilter.SHARPEN)
    variants.append(("sharpened", pil_to_bgr(sharpened)))

    adaptive = cv2.adaptiveThreshold(
        np.array(autocontrast),
        255,
        cv2.ADAPTIVE_THRESH_GAUSSIAN_C,
        cv2.THRESH_BINARY,
        31,
        9,
    )
    variants.append(("adaptive", cv2.cvtColor(adaptive, cv2.COLOR_GRAY2BGR)))

    upscale = autocontrast.resize((source.width * 2, source.height * 2), Image.Resampling.LANCZOS)
    variants.append(("upscale", pil_to_bgr(upscale)))

    upscale_threshold = upscale.point(lambda pixel: 255 if pixel > 170 else 0)
    variants.append(("upscale-threshold", pil_to_bgr(upscale_threshold)))

    return variants


def normalize_text(text: str) -> str:
    normalized = text.replace("\n", " ").replace("\t", " ")
    normalized = normalized.replace("О", "0").replace("O", "0")
    normalized = re.sub(r"\s+", " ", normalized)
    return normalized.strip()


def detect_region(box: List[List[float]], image_width: int, image_height: int) -> str:
    xs = [point[0] for point in box]
    ys = [point[1] for point in box]
    cx = sum(xs) / len(xs)
    cy = sum(ys) / len(ys)

    if cy < image_height * 0.28:
        return "top"
    if cy > image_height * 0.72:
        return "bottom"
    if cx < image_width * 0.35:
        return "left"
    if cx > image_width * 0.65:
        return "right"
    return "center"


def normalize_ocr_result(raw_result) -> List[Dict]:
    if isinstance(raw_result, tuple):
        raw_result = raw_result[0]

    if not isinstance(raw_result, list):
        return []

    lines: List[Dict] = []

    for item in raw_result:
        if not isinstance(item, (list, tuple)) or len(item) < 3:
            continue

        box = item[0]
        text = normalize_text(str(item[1]))
        score = float(item[2]) if isinstance(item[2], (int, float)) else 0.0

        if not text:
            continue

        try:
            normalized_box = [[float(point[0]), float(point[1])] for point in box]
        except Exception:
            continue

        lines.append({
            "box": normalized_box,
            "text": text,
            "score": score,
        })

    return lines


def deduplicate_lines(lines: List[Dict], image_width: int, image_height: int) -> List[Dict]:
    best_by_text: Dict[str, Dict] = {}

    for line in lines:
        region = detect_region(line["box"], image_width, image_height)
        key = normalize_text(line["text"]).upper()
        candidate = {
            **line,
            "region": region,
        }

        if key not in best_by_text or candidate["score"] > best_by_text[key]["score"]:
            best_by_text[key] = candidate

    return sorted(best_by_text.values(), key=lambda item: (-item["score"], item["text"]))


def expand_decimal_candidates(text: str) -> List[str]:
    variants = [(text.upper(), 0)]

    for source, replacements in {
        "B": ["8"],
        "O": ["0"],
        "Q": ["0"],
        "A": ["4"],
        "S": ["5"],
        "$": ["5"],
        "+": ["7"],
    }.items():
        updated: List[Tuple[str, int]] = []
        for current, penalty in variants:
            if source not in current:
                updated.append((current, penalty))
                continue

            for replacement in replacements:
                updated.append((current.replace(source, replacement), penalty + 1))

            updated.append((current.replace(source, ""), penalty + 1))
        variants = updated[:12]

    unique: List[str] = []
    seen = set()
    for current, _ in sorted(variants, key=lambda item: item[1]):
        if current in seen:
            continue
        seen.add(current)
        unique.append(current)

    return unique


def parse_marker_decimal(lines: List[Dict], markers: List[str]) -> Optional[float]:
    marker_set = {marker.upper() for marker in markers}

    for line in lines:
        compact = line["text"].upper().replace(" ", "")
        if not compact:
            continue

        for marker in marker_set:
            if marker not in compact:
                continue

            for candidate_text in expand_decimal_candidates(compact.replace(marker, " ", 1)):
                match = DECIMAL_TOKEN_RE.search(candidate_text)
                if not match:
                    continue

                token = match.group(1).replace(",", ".")
                if "." not in token and len(token) >= 3:
                    token = f"{token[:-2]}.{token[-2:]}"

                try:
                    return round(float(token), 2)
                except ValueError:
                    continue

    return None


def expand_integer_candidates(token: str) -> List[Tuple[int, int]]:
    variants: List[Tuple[str, int]] = [("", 0)]

    for char in token.upper():
        if char.isdigit():
            options = [(char, 0)]
        elif char in {"-", "_", "~"}:
            options = [("", 1), ("7", 2)]
        elif char in AMBIGUOUS_DIGITS:
            options = [(replacement, 1) for replacement in AMBIGUOUS_DIGITS[char]]
            options.append(("", 1))
        else:
            options = [("", 1)]

        updated: List[Tuple[str, int]] = []
        for current, penalty in variants:
            for replacement, extra_penalty in options:
                updated.append((current + replacement, penalty + extra_penalty))
        variants = sorted(updated, key=lambda item: item[1])[:16]

    results: List[Tuple[int, int]] = []
    seen = set()
    for digits, penalty in variants:
        if len(digits) < 2 or len(digits) > 4:
            continue
        value_cm = int(digits)
        if value_cm < 60 or value_cm > 2000 or value_cm in seen:
            continue
        seen.add(value_cm)
        results.append((value_cm, penalty))

    return results


def build_dimension_candidates(lines: List[Dict]) -> List[Dict]:
    candidates: List[Dict] = []

    for line in lines:
        compact = line["text"].upper()
        if "S" in compact or "P" in compact or "С" in compact or "Р" in compact:
            continue

        raw_tokens = re.findall(r"[0-9A-Z$+\-_/\\|]{2,5}", compact)
        for token in raw_tokens:
            for value_cm, penalty in expand_integer_candidates(token):
                candidates.append({
                    "value_cm": value_cm,
                    "region": line["region"],
                    "score": max(0.05, float(line["score"]) - (penalty * 0.08)),
                    "text": line["text"],
                })

    if not candidates:
        return []

    candidates.sort(key=lambda item: (-item["score"], item["value_cm"]))
    return candidates


def merge_candidate_pools(primary: List[Dict], fallback: List[Dict], limit: int = 8) -> List[Dict]:
    merged: List[Dict] = []
    seen = set()

    for item in [*primary, *fallback]:
        key = (item["value_cm"], item["region"], item["text"])
        if key in seen:
            continue
        seen.add(key)
        merged.append(item)
        if len(merged) >= limit:
            break

    return merged


def estimate_room_ratio(image: np.ndarray) -> Optional[float]:
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    gray = cv2.GaussianBlur(gray, (5, 5), 0)
    edges = cv2.Canny(gray, 45, 150)
    min_length = max(min(image.shape[0], image.shape[1]) * 0.18, 60)
    lines = cv2.HoughLinesP(edges, 1, np.pi / 180, threshold=55, minLineLength=int(min_length), maxLineGap=28)

    if lines is None:
        return None

    horizontals: List[float] = []
    verticals: List[float] = []

    for raw_line in lines:
        x1, y1, x2, y2 = raw_line[0]
        dx = x2 - x1
        dy = y2 - y1
        length = math.hypot(dx, dy)
        if length < min_length:
            continue

        if abs(dy) <= max(10, abs(dx) * 0.2):
            horizontals.append(length)
        elif abs(dx) <= max(10, abs(dy) * 0.2):
            verticals.append(length)

    if not horizontals or not verticals:
        return None

    width_px = float(np.median(sorted(horizontals, reverse=True)[:4]))
    height_px = float(np.median(sorted(verticals, reverse=True)[:4]))
    if height_px <= 0:
        return None

    ratio = width_px / height_px
    return round(ratio, 3) if 0.2 <= ratio <= 4.0 else None


def select_rectangle_dimensions(
    candidates: List[Dict],
    area_m2: Optional[float],
    perimeter_m: Optional[float],
    sketch_ratio: Optional[float] = None,
) -> Tuple[Optional[int], Optional[int], float, List[str]]:
    warnings: List[str] = []
    if not candidates:
        warnings.append("Не удалось выделить числовые размеры стен.")
        return None, None, 0.0, warnings

    horizontal = [item for item in candidates if item["region"] in {"top", "bottom"}]
    vertical = [item for item in candidates if item["region"] in {"left", "right"}]

    if not horizontal:
        warnings.append("Ширина выбрана из общих OCR-чисел без уверенной привязки к верхней/нижней стороне.")

    if not vertical:
        warnings.append("Высота выбрана из общих OCR-чисел без уверенной привязки к боковой стороне.")

    horizontal = merge_candidate_pools(horizontal, candidates)
    vertical = merge_candidate_pools(vertical, candidates)

    best_pair: Optional[Tuple[int, int]] = None
    best_score = float("inf")

    for width_candidate in horizontal[:8]:
        for length_candidate in vertical[:8]:
            width_cm = int(width_candidate["value_cm"])
            length_cm = int(length_candidate["value_cm"])

            score = 0.0
            score -= width_candidate["score"] * 0.15
            score -= length_candidate["score"] * 0.15

            if area_m2 is not None:
                calculated_area = round((width_cm * length_cm) / 10000, 2)
                score += abs(calculated_area - area_m2) / max(area_m2, 1)

            if perimeter_m is not None:
                calculated_perimeter = round((2 * (width_cm + length_cm)) / 100, 2)
                score += abs(calculated_perimeter - perimeter_m) / max(perimeter_m, 1)

            if sketch_ratio is not None:
                candidate_ratio = width_cm / max(length_cm, 1)
                score += abs(candidate_ratio - sketch_ratio) * 1.4

            if width_candidate["region"] == length_candidate["region"]:
                score += 0.08

            if best_pair is None or score < best_score:
                best_pair = (width_cm, length_cm)
                best_score = score

    if best_pair is None:
        warnings.append("Не удалось собрать пару размеров для прямоугольной комнаты.")
        return None, None, 0.0, warnings

    confidence = 0.45
    if best_score < 0.05:
        confidence = 0.92
    elif best_score < 0.12:
        confidence = 0.82
    elif best_score < 0.25:
        confidence = 0.68
    elif best_score < 0.40:
        confidence = 0.58

    if area_m2 is None:
        warnings.append("Контрольная площадь S не распознана.")
    if perimeter_m is None:
        warnings.append("Контрольный периметр P не распознан.")

    return best_pair[0], best_pair[1], confidence, warnings


def build_rectangle_points(width_cm: int, length_cm: int) -> List[Dict]:
    width_m = round(width_cm / 100, 2)
    length_m = round(length_cm / 100, 2)

    return [
        {"x": 0.0, "y": 0.0},
        {"x": width_m, "y": 0.0},
        {"x": width_m, "y": length_m},
        {"x": 0.0, "y": length_m},
    ]


def recognize(image_path: Path) -> Dict:
    if not image_path.is_file():
        return {"success": False, "message": "Файл изображения не найден."}

    image = cv2.imread(str(image_path))
    if image is None:
        return {"success": False, "message": "Не удалось открыть изображение."}

    image_height, image_width = image.shape[:2]
    ocr = RapidOCR()
    all_lines: List[Dict] = []

    for _, variant in build_variants(image_path):
        try:
            result = ocr(variant)
        except Exception:
            continue
        all_lines.extend(normalize_ocr_result(result))

    lines = deduplicate_lines(all_lines, image_width, image_height)
    if not lines:
        return {"success": False, "message": "OCR не распознал текст на эскизе."}

    text = "\n".join(line["text"] for line in lines)
    area_m2 = parse_marker_decimal(lines, ["S", "$"])
    perimeter_m = parse_marker_decimal(lines, ["P", "Р"])
    candidates = build_dimension_candidates(lines)
    sketch_ratio = estimate_room_ratio(image)
    width_cm, length_cm, confidence, warnings = select_rectangle_dimensions(candidates, area_m2, perimeter_m, sketch_ratio)

    room_draft = None
    shape = {"type": "unknown"}
    if width_cm and length_cm:
        shape_points = build_rectangle_points(width_cm, length_cm)
        room_draft = {
            "name": f"Черновик {width_cm}x{length_cm} см",
            "shape_points": shape_points,
            "width_m": round(width_cm / 100, 2),
            "length_m": round(length_cm / 100, 2),
            "manual_area_m2": area_m2,
            "manual_perimeter_m": perimeter_m,
        }
        shape = {
            "type": "rectangle",
            "source": "ocr_dimensions",
        }

    confidence = round(confidence, 2)
    ocr_confidence = round(sum(line["score"] for line in lines) / max(len(lines), 1), 3)

    return {
        "success": room_draft is not None,
        "message": "Эскиз распознан." if room_draft is not None else "Не удалось уверенно собрать комнату из OCR.",
        "engine": "rapidocr-onnxruntime",
        "text": text,
        "confidence": round((confidence + ocr_confidence) / 2, 2) if room_draft is not None else round(ocr_confidence / 2, 2),
        "shape": shape,
        "measurements": {
            "width_cm": width_cm,
            "length_cm": length_cm,
            "area_m2": area_m2,
            "perimeter_m": perimeter_m,
            "sketch_ratio": sketch_ratio,
            "wall_candidates_cm": sorted({item["value_cm"] for item in candidates}),
        },
        "warnings": warnings,
        "room_draft": room_draft,
        "ocr_lines": [
            {
                "text": line["text"],
                "score": round(float(line["score"]), 3),
                "region": line["region"],
            }
            for line in lines
        ],
    }


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--image", required=True)
    parser.add_argument("--project-id", required=False)
    args = parser.parse_args()

    try:
        result = recognize(Path(args.image))
    except Exception as error:
        result = {
            "success": False,
            "message": f"Ошибка распознавания: {error}",
        }

    json.dump(result, sys.stdout, ensure_ascii=False)
    return 0 if result.get("success") else 1


if __name__ == "__main__":
    raise SystemExit(main())
