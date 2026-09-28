"""
llm_extractor.py — LLM-based resume/JD extraction for ARES.

Uses OpenRouter's free tier (OpenAI-compatible API) so this costs nothing to
run. Swap MODEL_NAME or the client's base_url if you want to point this at
Groq, Gemini, or a paid provider later — nothing else in this file changes.

This module ONLY extracts structured data and a raw job-fit signal. It does
NOT apply recruiter weights (skills_weight, experience_weight, qual_weight,
etc.) — that stays in nlp_api.py's score_resume()-style weighted combination,
so scores stay reproducible and auditable regardless of which model produced
the extraction.
"""

import os
import json
import logging
from typing import List, Optional

from pydantic import BaseModel, Field, ValidationError
from openai import OpenAI, APIError, APITimeoutError

logger = logging.getLogger(__name__)

# ---------------------------------------------------------------------------
# CONFIG
# ---------------------------------------------------------------------------
# Get a free key at https://openrouter.ai/keys (no credit card required).
# Free-tier models are tagged ":free" — pick one from https://openrouter.ai/models
OPENROUTER_API_KEY = os.environ.get("OPENROUTER_API_KEY")
MODEL_NAME = os.environ.get("LLM_MODEL_NAME", "deepseek/deepseek-chat-v3-0324:free")

_client: Optional[OpenAI] = None


def _get_client() -> OpenAI:
    global _client
    if _client is None:
        if not OPENROUTER_API_KEY:
            raise RuntimeError(
                "OPENROUTER_API_KEY not set. Get a free key at "
                "https://openrouter.ai/keys and add it to your .env"
            )
        _client = OpenAI(
            base_url="https://openrouter.ai/api/v1",
            api_key=OPENROUTER_API_KEY,
        )
    return _client


# ---------------------------------------------------------------------------
# SCHEMA — strict typed output so we never have to hope the model's JSON
# parses cleanly. Mirrors the Pydantic-model approach from Sajjad-Amjad's
# Resume-Parser repo, scoped down to just what ARES's weighting math needs.
# ---------------------------------------------------------------------------
class ResumeExtraction(BaseModel):
    candidate_name: str = Field(description="Full name as it appears on the resume")
    matched_skills: List[str] = Field(
        default_factory=list,
        description="Skills the JOB asks for that the resume genuinely demonstrates",
    )
    skill_gap: List[str] = Field(
        default_factory=list,
        description="Skills the job asks for that the resume does NOT show",
    )
    years_experience: float = Field(
        ge=0, le=50, description="Total years of relevant, paid work experience"
    )
    education_level: str = Field(
        description="One of: none, associate, bachelor, master, doctorate"
    )
    certification_present: bool = Field(
        description="True if any relevant professional certification/license is present"
    )
    job_fit_score: float = Field(
        ge=0, le=1, description="Overall semantic fit between resume and job, 0 to 1"
    )
    presentation_feedback: List[str] = Field(
        default_factory=list,
        description="Short notes on resume formatting/clarity/structure quality",
    )


EDUCATION_LEVELS = {"none", "associate", "bachelor", "master", "doctorate"}

_EXTRACTION_PROMPT = """You are screening a job applicant for a recruiter. Extract \
structured data from the resume and evaluate it against the job description.

Rules:
- Only list a skill as matched if the resume gives real evidence for it (a
  project, a role, a stated proficiency) — not just because it's a common
  skill for the field.
- years_experience means the TOTAL number of years of paid, professional work
  experience the candidate has held across their entire career — regardless
  of whether that experience is in the same field as this job. Add up the
  duration of each listed role from its start/end dates (if two roles
  overlap in time, count that overlapping period only once, not twice). Do
  not count school projects, coursework, unpaid volunteer work, or
  extracurricular activities. A candidate with 8 years of unrelated work
  experience still has years_experience = 8, even if job_fit_score is low
  because that experience doesn't match this specific job — those are two
  separate, independent judgments.
- education_level must be exactly one of: none, associate, bachelor, master, doctorate.
- job_fit_score is your independent judgment of overall fit (0 = no fit,
  1 = ideal fit), not a restatement of years_experience or matched_skills count.
  A candidate can have many years of experience and still score low here if
  that experience is in an unrelated field — years_experience and
  job_fit_score must be scored independently of each other.
- Return ONLY a single JSON object. No markdown fences, no prose before or after,
  no step-by-step reasoning, no explanation of your process. Your entire response
  must start with "{{" and end with "}}" and contain nothing else.

Required JSON shape:
{{
  "candidate_name": string,
  "matched_skills": string[],
  "skill_gap": string[],
  "years_experience": number,
  "education_level": "none" | "associate" | "bachelor" | "master" | "doctorate",
  "certification_present": boolean,
  "job_fit_score": number,
  "presentation_feedback": string[]
}}

JOB DESCRIPTION:
{job_text}

RESUME TEXT:
{resume_text}
"""


def llm_extract_and_score(resume_text: str, job_text: str) -> dict:
    """
    Calls the LLM once and returns a dict:
      {"success": True, "data": {...ResumeExtraction fields...}}
      {"success": False, "error": "..."}

    Never raises — callers should fall back to the existing deterministic
    pipeline on failure (rate limit, timeout, malformed JSON, etc.), the same
    way extractTextUltimate() falls through smalot -> cloud_ocr -> heuristic.
    """
    if not resume_text.strip():
        return {"success": False, "error": "empty resume text"}

    prompt = _EXTRACTION_PROMPT.format(
        job_text=job_text.strip() or "(no job description provided)",
        # Free-tier models have small context windows and per-request cost
        # scales with tokens even on "free" models (rate limits are
        # token-aware). Cap resume text defensively.
        resume_text=resume_text.strip()[:12000],
    )

    client = _get_client()
    last_error = "unknown"

    # openrouter/free routes to a different underlying free model per
    # request. Some of those models occasionally ignore the "JSON only"
    # instruction and return moderation/status text instead (e.g. a bare
    # "User Safety: safe" line) or wrap JSON in commentary. Retry once with
    # a stricter, shorter instruction before giving up and falling back to
    # the deterministic pipeline.
    for attempt in range(2):
        try:
            response = client.chat.completions.create(
                model=MODEL_NAME,
                messages=[{"role": "user", "content": prompt}],
                temperature=0.1,
                # reasoning.enabled=False tells supporting models (like
                # Qwen3's hybrid thinking mode) to skip generating hidden
                # reasoning tokens entirely, rather than just excluding them
                # from the response. exclude=True alone still lets the model
                # BURN its max_tokens budget on hidden reasoning, which can
                # leave zero tokens left for the actual JSON answer.
                max_tokens=4000,
                extra_body={"reasoning": {"enabled": False, "exclude": True}},
                timeout=60,
            )
            finish_reason = response.choices[0].finish_reason
            raw = response.choices[0].message.content or ""

            if not raw.strip():
                last_error = f"empty_response (finish_reason={finish_reason})"
                logger.warning("LLM attempt %d returned empty content, finish_reason=%s",
                                attempt + 1, finish_reason)
                continue
            json_str = _extract_json_object(raw)

            if json_str is None:
                last_error = f"no_json_found: response was {raw[:200]!r}"
                logger.warning("LLM attempt %d returned no parseable JSON: %s",
                                attempt + 1, raw[:200])
                prompt += ("\n\nDo not show your reasoning or thinking process. "
                           "Respond with ONLY the JSON object, starting immediately with { "
                           "and nothing else before or after it.")
                continue

            parsed = ResumeExtraction.model_validate_json(json_str)
            if parsed.education_level not in EDUCATION_LEVELS:
                parsed.education_level = "none"

            return {"success": True, "data": parsed.model_dump()}

        except (APIError, APITimeoutError) as e:
            logger.warning("LLM extraction API error (attempt %d): %s", attempt + 1, e)
            last_error = f"api_error: {e}"
            break  # API-level errors won't be fixed by retrying the same way
        except ValidationError as e:
            logger.warning("LLM attempt %d returned invalid JSON shape: %s", attempt + 1, e)
            last_error = f"validation_error: {e}"
        except Exception as e:
            logger.warning("LLM attempt %d unexpected error: %s", attempt + 1, e)
            last_error = f"unexpected: {e}"

    return {"success": False, "error": last_error}


def _extract_json_object(text: str) -> Optional[str]:
    """
    Pulls the first {...} JSON object out of arbitrary model output, since
    some free-tier models prepend/append stray text (moderation notes,
    "Here is the JSON:", markdown fences) despite instructions not to.
    Returns None if no plausible JSON object is found at all.
    """
    text = _strip_json_fences(text).strip()
    if not text:
        return None

    start = text.find("{")
    end = text.rfind("}")
    if start == -1 or end == -1 or end < start:
        return None

    candidate = text[start:end + 1]
    try:
        json.loads(candidate)  # cheap validity check before handing to pydantic
        return candidate
    except json.JSONDecodeError:
        return None


def _strip_json_fences(text: str) -> str:
    """Some free models wrap JSON in ```json ... ``` despite instructions not to."""
    text = text.strip()
    if text.startswith("```"):
        text = text.split("\n", 1)[1] if "\n" in text else text
        if text.endswith("```"):
            text = text.rsplit("```", 1)[0]
    return text.strip()