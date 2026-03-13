#!/usr/bin/env python3
import argparse
import json
import os
import sys
from typing import List, Optional


def prepare_ffmpeg() -> None:
    try:
        import imageio_ffmpeg  # type: ignore
    except Exception:
        return

    try:
        ffmpeg_exe = imageio_ffmpeg.get_ffmpeg_exe()
    except Exception:
        return

    ffmpeg_dir = os.path.dirname(ffmpeg_exe)
    if ffmpeg_dir:
        os.environ["PATH"] = ffmpeg_dir + os.pathsep + os.environ.get("PATH", "")


def transcribe_with_faster_whisper(audio_path: str, model: str, language: Optional[str], model_dir: Optional[str]) -> str:
    from faster_whisper import WhisperModel  # type: ignore

    kwargs = {
        "device": "cpu",
        "compute_type": "int8",
    }
    if model_dir:
        kwargs["download_root"] = model_dir

    whisper_model = WhisperModel(model, **kwargs)
    segments, _ = whisper_model.transcribe(audio_path, language=language)
    return " ".join(segment.text.strip() for segment in segments if getattr(segment, "text", "").strip()).strip()


def transcribe_with_openai_whisper(audio_path: str, model: str, language: Optional[str], model_dir: Optional[str]) -> str:
    import whisper  # type: ignore

    whisper_model = whisper.load_model(model, download_root=model_dir)
    result = whisper_model.transcribe(audio_path, language=language, fp16=False)
    return str(result.get("text", "")).strip()


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("audio_path")
    parser.add_argument("--model", default="small")
    parser.add_argument("--language", default=None)
    parser.add_argument("--model-dir", default=None)
    args = parser.parse_args()

    audio_path = os.path.abspath(args.audio_path)
    if not os.path.isfile(audio_path):
        raise FileNotFoundError(f"Audio file not found: {audio_path}")

    model_dir = os.path.abspath(args.model_dir) if args.model_dir else None
    if model_dir:
        os.makedirs(model_dir, exist_ok=True)

    prepare_ffmpeg()

    text = None
    errors: List[str] = []

    try:
        text = transcribe_with_faster_whisper(audio_path, args.model, args.language, model_dir)
    except Exception as exc:
        errors.append(f"faster_whisper: {exc}")

    if text is None:
        try:
            text = transcribe_with_openai_whisper(audio_path, args.model, args.language, model_dir)
        except Exception as exc:
            errors.append(f"openai_whisper: {exc}")

    if text is None:
        raise RuntimeError("; ".join(errors) or "No transcription backend available")

    print(json.dumps({"text": text}, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as exc:
        print(json.dumps({"error": str(exc)}, ensure_ascii=False), file=sys.stderr)
        raise SystemExit(1)
