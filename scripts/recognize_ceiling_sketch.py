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


DECIMAL_TOKEN_RE = re.compile(r"(\d{1,4}(?:[.,]\d{1,2})?)")
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


def build_variants_from_pil(source: Image.Image) -> List[Tuple[str, np.ndarray]]:
    source = ImageOps.exif_transpose(source.convert("L"))
    variants: List[Tuple[str, np.ndarray]] = []

    autocontrast = ImageOps.autocontrast(source)
    variants.append(("gray", pil_to_bgr(source)))
    variants.append(("autocontrast", pil_to_bgr(autocontrast)))

    threshold = autocontrast.point(lambda pixel: 255 if pixel > 175 else 0)
    variants.append(("threshold", pil_to_bgr(threshold)))
    variants.append(("inverted-threshold", pil_to_bgr(ImageOps.invert(threshold))))

    sharpened = autocontrast.filter(ImageFilter.SHARPEN)
    variants.append(("sharpened", pil_to_bgr(sharpened)))

    median = autocontrast.filter(ImageFilter.MedianFilter(size=3))
    variants.append(("median", pil_to_bgr(median)))

    upscale = autocontrast.resize((source.width * 2, source.height * 2), Image.Resampling.LANCZOS)
    variants.append(("upscale", pil_to_bgr(upscale)))

    upscale_threshold = upscale.point(lambda pixel: 255 if pixel > 168 else 0)
    variants.append(("upscale-threshold", pil_to_bgr(upscale_threshold)))

    adaptive = cv2.adaptiveThreshold(
        np.array(autocontrast),
        255,
        cv2.ADAPTIVE_THRESH_GAUSSIAN_C,
        cv2.THRESH_BINARY,
        31,
        8,
    )
    variants.append(("adaptive", cv2.cvtColor(adaptive, cv2.COLOR_GRAY2BGR)))

    adaptive_inv = cv2.bitwise_not(adaptive)
    variants.append(("adaptive-inverted", cv2.cvtColor(adaptive_inv, cv2.COLOR_GRAY2BGR)))

    return variants


def normalize_text(text: str) -> str:
    normalized = text.replace("\n", " ").replace("\t", " ")
    normalized = normalized.replace("Рћ", "0").replace("O", "0")
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

        xs = [point[0] for point in normalized_box]
        ys = [point[1] for point in normalized_box]
        min_x = min(xs)
        max_x = max(xs)
        min_y = min(ys)
        max_y = max(ys)

        lines.append({
            "box": normalized_box,
            "bbox": {
                "x": min_x,
                "y": min_y,
                "width": max_x - min_x,
                "height": max_y - min_y,
            },
            "center": {
                "x": (min_x + max_x) / 2,
                "y": (min_y + max_y) / 2,
            },
            "text": text,
            "score": score,
        })

    return lines


def deduplicate_lines(lines: List[Dict], image_width: int, image_height: int) -> List[Dict]:
    best_by_text: Dict[str, Dict] = {}

    for line_index, line in enumerate(lines):
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

    replacements_map = {
        "B": ["8"],
        "O": ["0"],
        "Q": ["0"],
        "A": ["4"],
        "S": ["5"],
        "$": ["5"],
        "+": ["7"],
        "T": ["7", "1"],
    }

    for source, replacements in replacements_map.items():
        updated: List[Tuple[str, int]] = []
        for current, penalty in variants:
            if source not in current:
                updated.append((current, penalty))
                continue

            for replacement in replacements:
                updated.append((current.replace(source, replacement), penalty + 1))
            updated.append((current.replace(source, ""), penalty + 1))
        variants = sorted(updated, key=lambda item: item[1])[:18]

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

    for line_index, line in enumerate(lines):
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
                digits_only = re.sub(r"\D", "", token)
                has_decimal_separator = "." in token or "," in match.group(1)

                if not has_decimal_separator and len(digits_only) < 3:
                    continue

                if "." not in token and len(digits_only) >= 3:
                    token = f"{digits_only[:-2]}.{digits_only[-2:]}"

                try:
                    value = round(float(token), 2)
                except ValueError:
                    continue

                if value <= 0:
                    continue

                return value

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
        variants = sorted(updated, key=lambda item: item[1])[:18]

    results: List[Tuple[int, int]] = []
    seen = set()
    for digits, penalty in variants:
        if len(digits) < 2 or len(digits) > 4:
            continue
        value_cm = int(digits)
        if value_cm < 40 or value_cm > 2500 or value_cm in seen:
            continue
        seen.add(value_cm)
        results.append((value_cm, penalty))

    return results


def build_dimension_candidates(lines: List[Dict]) -> List[Dict]:
    candidates: List[Dict] = []
    candidate_index = 0

    for line_index, line in enumerate(lines):
        compact = line["text"].upper()
        if "S" in compact or "P" in compact or "РЎ" in compact or "Р " in compact:
            continue

        has_decimal_separator = "." in compact or "," in compact
        raw_tokens = re.findall(r"[0-9A-Z$+\-_/\\|]{2,6}", compact)
        for token_index, token in enumerate(raw_tokens):
            if has_decimal_separator and any(marker in token for marker in ["$", "S", "P"]):
                continue
            for value_cm, penalty in expand_integer_candidates(token):
                candidates.append({
                    "id": f"{line_index}:{token_index}:{candidate_index}",
                    "value_cm": value_cm,
                    "region": line["region"],
                    "score": max(0.05, float(line["score"]) - (penalty * 0.08)),
                    "text": line["text"],
                    "bbox": line.get("bbox"),
                    "center": line.get("center"),
                })
                candidate_index += 1

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
    return round(ratio, 3) if 0.15 <= ratio <= 6.0 else None


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
        warnings.append("Ширина выбрана из общих OCR-чисел без уверенной привязки к верхней или нижней стороне.")
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
            score -= width_candidate["score"] * 0.16
            score -= length_candidate["score"] * 0.16

            if area_m2 is not None:
                calculated_area = round((width_cm * length_cm) / 10000, 2)
                score += abs(calculated_area - area_m2) / max(area_m2, 1)

            if perimeter_m is not None:
                calculated_perimeter = round((2 * (width_cm + length_cm)) / 100, 2)
                score += abs(calculated_perimeter - perimeter_m) / max(perimeter_m, 1)

            if sketch_ratio is not None:
                candidate_ratio = width_cm / max(length_cm, 1)
                score += abs(candidate_ratio - sketch_ratio) * 1.45

            if width_candidate["region"] == length_candidate["region"]:
                score += 0.08

            if best_pair is None or score < best_score:
                best_pair = (width_cm, length_cm)
                best_score = score

    if best_pair is None:
        warnings.append("Не удалось собрать пару размеров для черновика комнаты.")
        return None, None, 0.0, warnings

    confidence = 0.44
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


def preprocess_candidates_binary(image: np.ndarray) -> List[np.ndarray]:
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    blur = cv2.GaussianBlur(gray, (5, 5), 0)

    adaptive = cv2.adaptiveThreshold(
        blur,
        255,
        cv2.ADAPTIVE_THRESH_GAUSSIAN_C,
        cv2.THRESH_BINARY_INV,
        35,
        9,
    )
    _, fixed = cv2.threshold(blur, 185, 255, cv2.THRESH_BINARY_INV)
    edges = cv2.Canny(blur, 45, 140)

    kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (5, 5))
    close_adaptive = cv2.morphologyEx(adaptive, cv2.MORPH_CLOSE, kernel, iterations=2)
    close_fixed = cv2.morphologyEx(fixed, cv2.MORPH_CLOSE, kernel, iterations=2)
    dilated_edges = cv2.dilate(edges, kernel, iterations=1)

    return [close_adaptive, close_fixed, dilated_edges]


def line_orientation_score(roi: np.ndarray) -> Tuple[int, int, float]:
    gray = cv2.cvtColor(roi, cv2.COLOR_BGR2GRAY)
    edges = cv2.Canny(gray, 40, 140)
    min_length = max(min(roi.shape[0], roi.shape[1]) * 0.35, 28)
    lines = cv2.HoughLinesP(edges, 1, np.pi / 180, threshold=28, minLineLength=int(min_length), maxLineGap=18)

    if lines is None:
        return 0, 0, 0.0

    horizontal = 0
    vertical = 0
    total_length = 0.0

    for raw_line in lines:
        x1, y1, x2, y2 = raw_line[0]
        dx = x2 - x1
        dy = y2 - y1
        length = math.hypot(dx, dy)
        if abs(dy) <= max(8, abs(dx) * 0.24):
            horizontal += 1
            total_length += length
        elif abs(dx) <= max(8, abs(dy) * 0.24):
            vertical += 1
            total_length += length

    return horizontal, vertical, total_length


def box_iou(box_a: Dict, box_b: Dict) -> float:
    ax1, ay1 = box_a["x"], box_a["y"]
    ax2, ay2 = ax1 + box_a["width"], ay1 + box_a["height"]
    bx1, by1 = box_b["x"], box_b["y"]
    bx2, by2 = bx1 + box_b["width"], by1 + box_b["height"]

    inter_x1 = max(ax1, bx1)
    inter_y1 = max(ay1, by1)
    inter_x2 = min(ax2, bx2)
    inter_y2 = min(ay2, by2)

    inter_w = max(0, inter_x2 - inter_x1)
    inter_h = max(0, inter_y2 - inter_y1)
    inter_area = inter_w * inter_h
    if inter_area <= 0:
        return 0.0

    area_a = box_a["width"] * box_a["height"]
    area_b = box_b["width"] * box_b["height"]
    return inter_area / max(area_a + area_b - inter_area, 1)


def merge_candidates(raw_candidates: List[Dict]) -> List[Dict]:
    sorted_candidates = sorted(raw_candidates, key=lambda item: item["score"], reverse=True)
    merged: List[Dict] = []

    for candidate in sorted_candidates:
        if any(box_iou(candidate, existing) > 0.42 for existing in merged):
            continue
        merged.append(candidate)
        if len(merged) >= 8:
            break

    return merged


def detect_room_candidates(image: np.ndarray) -> List[Dict]:
    image_height, image_width = image.shape[:2]
    image_area = image_width * image_height
    raw_candidates: List[Dict] = []

    for binary in preprocess_candidates_binary(image):
        contours, _ = cv2.findContours(binary, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)

        for contour in contours:
            x, y, width, height = cv2.boundingRect(contour)
            area = width * height
            area_ratio = area / max(image_area, 1)
            aspect_ratio = width / max(height, 1)

            if width < 70 or height < 70:
                continue
            if area_ratio < 0.01 or area_ratio > 0.72:
                continue
            if aspect_ratio < 0.18 or aspect_ratio > 5.5:
                continue

            margin_x = int(width * 0.08)
            margin_y = int(height * 0.08)
            ex1 = max(0, x - margin_x)
            ey1 = max(0, y - margin_y)
            ex2 = min(image_width, x + width + margin_x)
            ey2 = min(image_height, y + height + margin_y)

            roi = image[ey1:ey2, ex1:ex2]
            horizontal, vertical, line_length = line_orientation_score(roi)
            if horizontal == 0 or vertical == 0:
                continue

            score = min(
                0.99,
                0.18
                + min(area_ratio * 1.8, 0.34)
                + min((horizontal + vertical) * 0.03, 0.22)
                + min(line_length / max((width + height) * 3.4, 1), 0.18),
            )

            raw_candidates.append({
                "x": ex1,
                "y": ey1,
                "width": ex2 - ex1,
                "height": ey2 - ey1,
                "score": round(score, 3),
            })

    merged = merge_candidates(raw_candidates)
    candidates: List[Dict] = []
    for index, candidate in enumerate(merged):
        candidates.append({
            "index": index,
            "x": round(candidate["x"] / image_width, 5),
            "y": round(candidate["y"] / image_height, 5),
            "width": round(candidate["width"] / image_width, 5),
            "height": round(candidate["height"] / image_height, 5),
            "score": candidate["score"],
        })

    return candidates


def clamp_crop(crop: Dict) -> Optional[Dict]:
    try:
        x = float(crop["x"])
        y = float(crop["y"])
        width = float(crop["width"])
        height = float(crop["height"])
    except Exception:
        return None

    x = max(0.0, min(0.98, x))
    y = max(0.0, min(0.98, y))
    width = max(0.0, min(1.0 - x, width))
    height = max(0.0, min(1.0 - y, height))

    if width < 0.02 or height < 0.02:
        return None

    return {
        "x": round(x, 5),
        "y": round(y, 5),
        "width": round(width, 5),
        "height": round(height, 5),
    }


def crop_image(image: np.ndarray, crop: Optional[Dict]) -> Tuple[np.ndarray, Optional[Dict]]:
    normalized = clamp_crop(crop) if crop else None
    if not normalized:
        return image, None

    image_height, image_width = image.shape[:2]
    x1 = max(0, int(round(normalized["x"] * image_width)))
    y1 = max(0, int(round(normalized["y"] * image_height)))
    x2 = min(image_width, int(round((normalized["x"] + normalized["width"]) * image_width)))
    y2 = min(image_height, int(round((normalized["y"] + normalized["height"]) * image_height)))

    if x2 - x1 < 20 or y2 - y1 < 20:
        return image, None

    return image[y1:y2, x1:x2].copy(), normalized


def collect_ocr_lines(image: np.ndarray) -> List[Dict]:
    pil_image = Image.fromarray(cv2.cvtColor(image, cv2.COLOR_BGR2RGB))
    ocr = RapidOCR()
    all_lines: List[Dict] = []

    for _, variant in build_variants_from_pil(pil_image):
        try:
            result = ocr(variant)
        except Exception:
            continue

        all_lines.extend(normalize_ocr_result(result))

    return deduplicate_lines(all_lines, image.shape[1], image.shape[0])


def simplify_polygon(points: List[Tuple[float, float]]) -> List[Tuple[float, float]]:
    simplified: List[Tuple[float, float]] = []

    for x, y in points:
        if simplified and math.hypot(simplified[-1][0] - x, simplified[-1][1] - y) < 6:
            continue
        simplified.append((x, y))

    if len(simplified) > 1 and math.hypot(simplified[0][0] - simplified[-1][0], simplified[0][1] - simplified[-1][1]) < 6:
        simplified.pop()

    return simplified


def polygon_area(points: List[Dict]) -> float:
    if len(points) < 3:
        return 0.0
    area = 0.0
    for index, point in enumerate(points):
        next_point = points[(index + 1) % len(points)]
        area += point["x"] * next_point["y"] - next_point["x"] * point["y"]
    return abs(area) / 2


def simplify_orthogonal_points(points: List[Tuple[float, float]]) -> List[Tuple[float, float]]:
    if len(points) < 3:
        return points

    cleaned: List[Tuple[float, float]] = []
    for point in points:
        if cleaned and math.hypot(cleaned[-1][0] - point[0], cleaned[-1][1] - point[1]) < 3:
            cleaned[-1] = point
        else:
            cleaned.append(point)

    if len(cleaned) > 1 and math.hypot(cleaned[0][0] - cleaned[-1][0], cleaned[0][1] - cleaned[-1][1]) < 3:
        cleaned.pop()

    changed = True
    while changed and len(cleaned) >= 4:
        changed = False
        simplified: List[Tuple[float, float]] = []
        total = len(cleaned)
        for index in range(total):
            prev_point = cleaned[index - 1]
            point = cleaned[index]
            next_point = cleaned[(index + 1) % total]

            same_x = abs(prev_point[0] - point[0]) < 3 and abs(point[0] - next_point[0]) < 3
            same_y = abs(prev_point[1] - point[1]) < 3 and abs(point[1] - next_point[1]) < 3

            if same_x or same_y:
                changed = True
                continue

            simplified.append(point)
        cleaned = simplified if len(simplified) >= 4 else cleaned

    return cleaned


def orthogonalize_polygon(points: List[Tuple[float, float]]) -> List[Tuple[float, float]]:
    if len(points) < 4:
        return points

    orthogonal: List[Tuple[float, float]] = [points[0]]
    current_x, current_y = points[0]

    for next_point in points[1:]:
        next_x, next_y = next_point
        if abs(next_x - current_x) >= abs(next_y - current_y):
            current_x = next_x
        else:
            current_y = next_y
        orthogonal.append((current_x, current_y))

    first_x, first_y = orthogonal[0]
    last_x, last_y = orthogonal[-1]
    if abs(last_x - first_x) >= abs(last_y - first_y):
        orthogonal[-1] = (first_x, last_y)
    else:
        orthogonal[-1] = (last_x, first_y)

    return simplify_orthogonal_points(orthogonal)


def segment_orientation(point_a: Tuple[float, float], point_b: Tuple[float, float]) -> str:
    return "horizontal" if abs(point_b[0] - point_a[0]) >= abs(point_b[1] - point_a[1]) else "vertical"


def segment_direction(point_a: Tuple[float, float], point_b: Tuple[float, float]) -> str:
    if abs(point_b[0] - point_a[0]) >= abs(point_b[1] - point_a[1]):
        return "right" if point_b[0] >= point_a[0] else "left"
    return "down" if point_b[1] >= point_a[1] else "up"


def polygon_signed_area_points(points: List[Tuple[float, float]]) -> float:
    if len(points) < 3:
        return 0.0

    area = 0.0
    for index, point in enumerate(points):
        next_point = points[(index + 1) % len(points)]
        area += point[0] * next_point[1] - next_point[0] * point[1]

    return area / 2.0


def point_inside_polygon(contour: np.ndarray, x: float, y: float) -> bool:
    return cv2.pointPolygonTest(contour, (float(x), float(y)), False) >= 0


def point_to_segment_distance(point_x: float, point_y: float, point_a: Tuple[float, float], point_b: Tuple[float, float]) -> float:
    ax, ay = point_a
    bx, by = point_b
    dx = bx - ax
    dy = by - ay

    if dx == 0 and dy == 0:
        return math.hypot(point_x - ax, point_y - ay)

    projection = ((point_x - ax) * dx + (point_y - ay) * dy) / ((dx * dx) + (dy * dy))
    projection = max(0.0, min(1.0, projection))
    closest_x = ax + projection * dx
    closest_y = ay + projection * dy

    return math.hypot(point_x - closest_x, point_y - closest_y)


def segment_label(index: int, total: int) -> str:
    first = chr(65 + (index % 26))
    second = chr(65 + ((index + 1) % 26))
    return f"{first}{second}"


def build_segment_measurements(
    pixel_points: List[Tuple[float, float]],
    scaled_points: List[Dict],
    dimension_candidates: List[Dict],
    image_shape: Tuple[int, int, int],
) -> List[Dict]:
    if len(pixel_points) < 2 or len(scaled_points) < 2:
        return []

    contour = np.array(pixel_points, dtype=np.float32).reshape((-1, 1, 2))
    image_height, image_width = image_shape[:2]
    polygon_is_ccw = polygon_signed_area_points(pixel_points) > 0
    segment_candidates: List[List[Dict]] = []
    segments: List[Dict] = []

    for index, point_a in enumerate(pixel_points):
        point_b = pixel_points[(index + 1) % len(pixel_points)]
        scaled_a = scaled_points[index]
        scaled_b = scaled_points[(index + 1) % len(scaled_points)]
        orientation = segment_orientation(point_a, point_b)
        direction = segment_direction(point_a, point_b)
        raw_length_px = math.hypot(point_b[0] - point_a[0], point_b[1] - point_a[1])
        scaled_length_cm = round(math.hypot(scaled_b["x"] - scaled_a["x"], scaled_b["y"] - scaled_a["y"]) * 100)
        midpoint_x = (point_a[0] + point_b[0]) / 2
        midpoint_y = (point_a[1] + point_b[1]) / 2

        candidates_for_segment: List[Dict] = []
        for candidate in dimension_candidates:
            center = candidate.get("center")
            if not isinstance(center, dict):
                continue

            candidate_x = float(center.get("x", 0))
            candidate_y = float(center.get("y", 0))
            distance = point_to_segment_distance(candidate_x, candidate_y, point_a, point_b)
            normalized_distance = distance / max(raw_length_px, 1.0)
            inside_penalty = 0.75 if point_inside_polygon(contour, candidate_x, candidate_y) else 0.0
            candidate_region = str(candidate.get("region", "center"))
            cross_value = ((point_b[0] - point_a[0]) * (candidate_y - point_a[1])) - ((point_b[1] - point_a[1]) * (candidate_x - point_a[0]))

            span_penalty = 0.0
            if orientation == "horizontal":
                segment_min = min(point_a[0], point_b[0]) - raw_length_px * 0.18
                segment_max = max(point_a[0], point_b[0]) + raw_length_px * 0.18
                if not (segment_min <= candidate_x <= segment_max):
                    span_penalty += 0.35
            else:
                segment_min = min(point_a[1], point_b[1]) - raw_length_px * 0.18
                segment_max = max(point_a[1], point_b[1]) + raw_length_px * 0.18
                if not (segment_min <= candidate_y <= segment_max):
                    span_penalty += 0.35

            orientation_penalty = 0.0
            if orientation == "horizontal":
                if candidate_region in {"left", "right"}:
                    orientation_penalty += 0.42
                elif candidate_region == "center":
                    orientation_penalty += 0.18
                else:
                    orientation_penalty -= 0.06
            else:
                if candidate_region in {"top", "bottom"}:
                    orientation_penalty += 0.42
                elif candidate_region == "center":
                    orientation_penalty += 0.18
                else:
                    orientation_penalty -= 0.06

            outside_penalty = 0.0
            if abs(cross_value) > 8:
                is_outside = cross_value < 0 if polygon_is_ccw else cross_value > 0
                if not is_outside:
                    outside_penalty += 0.55
                else:
                    outside_penalty -= 0.08
            else:
                outside_penalty += 0.12

            length_penalty = abs(int(candidate["value_cm"]) - scaled_length_cm) / max(scaled_length_cm, 1)
            score = normalized_distance + inside_penalty + span_penalty + orientation_penalty + outside_penalty + (length_penalty * 0.16) - (float(candidate["score"]) * 0.18)
            candidates_for_segment.append({
                "candidate_id": candidate.get("id"),
                "value_cm": int(candidate["value_cm"]),
                "text": candidate["text"],
                "score": round(score, 3),
                "ocr_score": round(float(candidate["score"]), 3),
                "region": candidate_region,
            })

        candidates_for_segment.sort(key=lambda item: (item["score"], -item["ocr_score"], abs(item["value_cm"] - scaled_length_cm)))
        top_candidates = candidates_for_segment[:3]
        segment_candidates.append(top_candidates)

        segments.append({
            "index": index,
            "label": segment_label(index, len(pixel_points)),
            "orientation": orientation,
            "direction": direction,
            "ocr_value_cm": None,
            "approx_value_cm": scaled_length_cm,
            "confidence": None,
            "candidates": top_candidates,
            "midpoint": {
                "x": round(midpoint_x / max(image_width, 1), 5),
                "y": round(midpoint_y / max(image_height, 1), 5),
            },
        })

    assignments: Dict[int, Dict] = {}
    used_candidates = set()
    global_ranked = sorted(
        [
            (segment_index, candidate)
            for segment_index, candidates_for_segment in enumerate(segment_candidates)
            for candidate in candidates_for_segment
            if candidate["score"] <= 1.35
        ],
        key=lambda item: (
            item[1]["score"],
            -item[1]["ocr_score"],
            abs(item[1]["value_cm"] - segments[item[0]]["approx_value_cm"]),
        ),
    )

    for segment_index, candidate in global_ranked:
        candidate_id = candidate.get("candidate_id")
        if segment_index in assignments:
            continue
        if candidate_id and candidate_id in used_candidates:
            continue

        assignments[segment_index] = candidate
        if candidate_id:
            used_candidates.add(candidate_id)

    for segment_index, segment in enumerate(segments):
        best = assignments.get(segment_index)
        if not best:
            continue

        segment["ocr_value_cm"] = best["value_cm"]
        segment["confidence"] = round(max(0.12, min(0.95, 1.05 - best["score"])), 2)

    return segments


def reconcile_segment_lengths(segments: List[Dict]) -> List[Dict]:
    if not segments:
        return []

    resolved: List[Dict] = []
    for segment in segments:
        approx_value = float(segment.get("approx_value_cm") or 0)
        ocr_value = segment.get("ocr_value_cm")
        confidence = float(segment.get("confidence") or 0.0)
        resolved_value = approx_value
        locked = False

        if isinstance(ocr_value, (int, float)) and ocr_value > 0:
            if confidence >= 0.62:
                resolved_value = float(ocr_value)
                locked = True
            elif approx_value > 0:
                blend_ratio = max(0.18, min(0.58, confidence))
                resolved_value = (approx_value * (1.0 - blend_ratio)) + (float(ocr_value) * blend_ratio)
            else:
                resolved_value = float(ocr_value)

        resolved.append({
            **segment,
            "resolved_value_cm": round(max(8.0, resolved_value)),
            "locked": locked,
        })

    def distribute_delta(pool: List[Dict], delta_cm: float, mode: str) -> bool:
        remaining = abs(delta_cm)
        candidates = sorted(
            pool,
            key=lambda item: (
                1 if item.get("locked") else 0,
                float(item.get("confidence") or 0.0),
                -float(item.get("resolved_value_cm") or 0.0),
            ),
        )
        changed = False

        while remaining > 0.5 and candidates:
            progressed = False
            share = max(1.0, remaining / max(len(candidates), 1))

            for segment in candidates:
                current_value = float(segment.get("resolved_value_cm") or 0.0)
                if mode == "decrease":
                    capacity = max(0.0, current_value - 8.0)
                    if capacity <= 0:
                        continue
                    change = min(capacity, share, remaining)
                    segment["resolved_value_cm"] = round(current_value - change)
                else:
                    change = min(share, remaining)
                    segment["resolved_value_cm"] = round(current_value + change)

                remaining -= change
                progressed = True
                changed = True
                if remaining <= 0.5:
                    break

            if not progressed:
                break

        return changed or remaining <= 0.5

    for orientation in ["horizontal", "vertical"]:
        orientation_segments = [segment for segment in resolved if segment.get("orientation") == orientation]
        if len(orientation_segments) < 2:
            continue

        positive_directions = {"right"} if orientation == "horizontal" else {"down"}
        positive_segments = [segment for segment in orientation_segments if segment.get("direction") in positive_directions]
        negative_segments = [segment for segment in orientation_segments if segment.get("direction") not in positive_directions]

        positive_total = sum(float(segment.get("resolved_value_cm") or 0.0) for segment in positive_segments)
        negative_total = sum(float(segment.get("resolved_value_cm") or 0.0) for segment in negative_segments)
        delta = round(positive_total - negative_total)

        if abs(delta) <= 1:
            continue

        over_segments = positive_segments if delta > 0 else negative_segments
        under_segments = negative_segments if delta > 0 else positive_segments
        over_flexible = [segment for segment in over_segments if not segment.get("locked")]
        under_flexible = [segment for segment in under_segments if not segment.get("locked")]

        if over_flexible and distribute_delta(over_flexible, abs(delta), "decrease"):
            continue

        if under_flexible and distribute_delta(under_flexible, abs(delta), "increase"):
            continue

        fallback_pool = sorted(
            orientation_segments,
            key=lambda item: (
                float(item.get("confidence") or 0.0),
                1 if item.get("locked") else 0,
            ),
        )
        if delta > 0:
            distribute_delta(fallback_pool[:1], abs(delta), "decrease")
        else:
            distribute_delta(fallback_pool[:1], abs(delta), "increase")

    return resolved


def rebuild_shape_from_segments(shape_points: List[Dict], segments: List[Dict]) -> Tuple[List[Dict], List[Dict]]:
    if len(shape_points) < 3 or len(segments) != len(shape_points):
        return shape_points, segments

    resolved_segments = reconcile_segment_lengths(segments)
    x = 0.0
    y = 0.0
    rebuilt_points: List[Dict] = [{"x": 0.0, "y": 0.0}]

    for segment in resolved_segments:
        length_m = float(segment.get("resolved_value_cm") or segment.get("approx_value_cm") or 0) / 100.0
        direction = segment.get("direction")

        if direction == "right":
            x += length_m
        elif direction == "left":
            x -= length_m
        elif direction == "down":
            y += length_m
        else:
            y -= length_m

        rebuilt_points.append({
            "x": round(x, 2),
            "y": round(y, 2),
        })

    if len(rebuilt_points) < 4:
        return shape_points, resolved_segments

    rebuilt_points = rebuilt_points[:-1]
    min_x = min(point["x"] for point in rebuilt_points)
    min_y = min(point["y"] for point in rebuilt_points)
    normalized_points = [
        {
            "x": round(point["x"] - min_x, 2),
            "y": round(point["y"] - min_y, 2),
        }
        for point in rebuilt_points
    ]

    if polygon_area(normalized_points) <= 0.15:
        return shape_points, resolved_segments

    for index, segment in enumerate(resolved_segments):
        point_a = normalized_points[index]
        point_b = normalized_points[(index + 1) % len(normalized_points)]
        segment["approx_value_cm"] = round(math.hypot(point_b["x"] - point_a["x"], point_b["y"] - point_a["y"]) * 100)

    return normalized_points, resolved_segments


def contour_binary(image: np.ndarray) -> np.ndarray:
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    blur = cv2.GaussianBlur(gray, (5, 5), 0)
    adaptive = cv2.adaptiveThreshold(
        blur,
        255,
        cv2.ADAPTIVE_THRESH_GAUSSIAN_C,
        cv2.THRESH_BINARY_INV,
        31,
        7,
    )
    kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (5, 5))
    return cv2.morphologyEx(adaptive, cv2.MORPH_CLOSE, kernel, iterations=2)


def build_polygon_points(image: np.ndarray, width_cm: int, length_cm: int) -> Optional[Dict]:
    binary = contour_binary(image)
    contours, _ = cv2.findContours(binary, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    if not contours:
        return None

    image_area = image.shape[0] * image.shape[1]
    best_contour = None
    best_score = 0.0

    for contour in contours:
        area = cv2.contourArea(contour)
        if area < image_area * 0.02:
            continue
        perimeter = cv2.arcLength(contour, True)
        if perimeter <= 0:
            continue
        score = area / perimeter
        if score > best_score:
            best_score = score
            best_contour = contour

    if best_contour is None:
        return None

    perimeter = cv2.arcLength(best_contour, True)
    approx = None
    for epsilon_ratio in [0.008, 0.012, 0.018, 0.026, 0.036]:
        candidate = cv2.approxPolyDP(best_contour, epsilon_ratio * perimeter, True)
        points = simplify_polygon([(float(point[0][0]), float(point[0][1])) for point in candidate])
        points = orthogonalize_polygon(points)
        if 4 <= len(points) <= 12:
            approx = points
            break

    if not approx or len(approx) < 4:
        return None

    xs = [point[0] for point in approx]
    ys = [point[1] for point in approx]
    min_x, max_x = min(xs), max(xs)
    min_y, max_y = min(ys), max(ys)

    box_width = max(max_x - min_x, 1)
    box_height = max(max_y - min_y, 1)
    width_m = width_cm / 100
    length_m = length_cm / 100

    scaled_points = [
        {
            "x": round(((x - min_x) / box_width) * width_m, 2),
            "y": round(((y - min_y) / box_height) * length_m, 2),
        }
        for x, y in approx
    ]

    if polygon_area(scaled_points) <= 0.15:
        return None

    start_index = min(range(len(scaled_points)), key=lambda idx: (scaled_points[idx]["y"], scaled_points[idx]["x"]))
    scaled_points = scaled_points[start_index:] + scaled_points[:start_index]
    raw_points = approx[start_index:] + approx[:start_index]

    return {
        "scaled_points": scaled_points,
        "raw_points": raw_points,
    }


def build_room_draft(
    image: np.ndarray,
    width_cm: Optional[int],
    length_cm: Optional[int],
    area_m2: Optional[float],
    perimeter_m: Optional[float],
    dimension_candidates: List[Dict],
) -> Tuple[Optional[Dict], Dict, List[Dict]]:
    if not width_cm or not length_cm:
        return None, {"type": "unknown"}, []

    polygon = build_polygon_points(image, width_cm, length_cm)
    shape_points = polygon["scaled_points"] if polygon else None
    raw_points = polygon["raw_points"] if polygon else [
        (0.0, 0.0),
        (float(image.shape[1]), 0.0),
        (float(image.shape[1]), float(image.shape[0])),
        (0.0, float(image.shape[0])),
    ]
    shape_type = "polygon" if shape_points and len(shape_points) > 4 else "rectangle"

    if not shape_points:
        shape_points = build_rectangle_points(width_cm, length_cm)

    segments = build_segment_measurements(raw_points, shape_points, dimension_candidates, image.shape)
    shape_points, segments = rebuild_shape_from_segments(shape_points, segments)
    draft_width_m = round(max(point["x"] for point in shape_points) - min(point["x"] for point in shape_points), 2)
    draft_length_m = round(max(point["y"] for point in shape_points) - min(point["y"] for point in shape_points), 2)

    room_draft = {
        "name": f"Черновик {width_cm}x{length_cm} см",
        "shape_points": shape_points,
        "width_m": draft_width_m,
        "length_m": draft_length_m,
        "manual_area_m2": area_m2,
        "manual_perimeter_m": perimeter_m,
        "segment_measurements": segments,
    }

    return room_draft, {
        "type": shape_type,
        "source": "contour_scaled" if shape_type == "polygon" else "ocr_dimensions",
    }, segments


def recognize_region(image: np.ndarray, crop: Optional[Dict], candidates: List[Dict]) -> Dict:
    lines = collect_ocr_lines(image)
    if not lines:
        return {
            "success": False,
            "message": "OCR не распознал текст в выбранной области.",
            "stage": "recognize",
            "crop": crop,
            "candidates": candidates,
            "warnings": ["В выбранной области не найден читаемый текст."],
            "shape": {"type": "unknown"},
        }

    text = "\n".join(line["text"] for line in lines)
    area_m2 = parse_marker_decimal(lines, ["S", "$"])
    perimeter_m = parse_marker_decimal(lines, ["P", "Р"])
    dimension_candidates = build_dimension_candidates(lines)
    sketch_ratio = estimate_room_ratio(image)
    width_cm, length_cm, confidence, warnings = select_rectangle_dimensions(
        dimension_candidates,
        area_m2,
        perimeter_m,
        sketch_ratio,
    )

    room_draft, shape, segments = build_room_draft(
        image,
        width_cm,
        length_cm,
        area_m2,
        perimeter_m,
        dimension_candidates,
    )
    ocr_confidence = round(sum(line["score"] for line in lines) / max(len(lines), 1), 3)

    return {
        "success": room_draft is not None,
        "message": "Эскиз распознан." if room_draft is not None else "Не удалось уверенно собрать черновик комнаты.",
        "engine": "rapidocr-onnxruntime",
        "stage": "recognize",
        "text": text,
        "confidence": round((confidence + ocr_confidence) / 2, 2) if room_draft is not None else round(ocr_confidence / 2, 2),
        "shape": shape,
        "crop": crop,
        "candidates": candidates,
        "measurements": {
            "width_cm": width_cm,
            "length_cm": length_cm,
            "area_m2": area_m2,
            "perimeter_m": perimeter_m,
            "sketch_ratio": sketch_ratio,
            "wall_candidates_cm": sorted({item["value_cm"] for item in dimension_candidates}),
        },
        "segments": segments,
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


def inspect_sheet(image: np.ndarray) -> Dict:
    candidates = detect_room_candidates(image)
    warnings: List[str] = []
    message = "Лист проанализирован. Выберите одну комнату и запустите OCR."

    if not candidates:
        warnings.append("Автокандидаты не найдены. Область можно выделить вручную.")
        message = "Автокандидаты не найдены. Область можно выделить вручную."

    return {
        "success": True,
        "message": message,
        "stage": "inspect",
        "candidates": candidates,
        "warnings": warnings,
        "shape": {"type": "unknown"},
        "confidence": round(candidates[0]["score"], 2) if candidates else 0.0,
    }


def recognize(image_path: Path, mode: str, crop: Optional[Dict]) -> Dict:
    if not image_path.is_file():
        return {"success": False, "message": "Файл изображения не найден."}

    image = cv2.imread(str(image_path))
    if image is None:
        return {"success": False, "message": "Не удалось открыть изображение."}

    candidates = detect_room_candidates(image)

    if mode == "inspect":
        return inspect_sheet(image)

    cropped_image, applied_crop = crop_image(image, crop)
    if not applied_crop and candidates:
        applied_crop = clamp_crop(candidates[0])
        cropped_image, applied_crop = crop_image(image, applied_crop)

    if not applied_crop:
        return {
            "success": False,
            "message": "Сначала выделите одну комнату на листе.",
            "stage": "recognize",
            "candidates": candidates,
            "warnings": ["Перед OCR нужно выбрать область одной комнаты."],
            "shape": {"type": "unknown"},
        }

    return recognize_region(cropped_image, applied_crop, candidates)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--image", required=True)
    parser.add_argument("--mode", choices=["inspect", "recognize"], default="recognize")
    parser.add_argument("--crop", required=False)
    parser.add_argument("--project-id", required=False)
    args = parser.parse_args()

    crop = None
    if args.crop:
        try:
            crop = json.loads(args.crop)
        except Exception:
            crop = None

    try:
        result = recognize(Path(args.image), args.mode, crop)
    except Exception as error:
        result = {
            "success": False,
            "message": f"Ошибка распознавания: {error}",
            "stage": args.mode,
        }

    json.dump(result, sys.stdout, ensure_ascii=False)

    if args.mode == "inspect":
        return 0

    return 0 if result.get("success") else 1


if __name__ == "__main__":
    raise SystemExit(main())
