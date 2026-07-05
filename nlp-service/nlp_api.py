from flask import Flask, request, jsonify
import re
import datetime
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity
from sentence_transformers import SentenceTransformer
import spacy

app = Flask(__name__)

from debug_routes import debug_bp
app.register_blueprint(debug_bp)

# ---------------------------------------------------------------------------
# MODELS
# ---------------------------------------------------------------------------
model = SentenceTransformer('all-MiniLM-L6-v2')

try:
    nlp = spacy.load("en_core_web_trf")
except Exception:
    nlp = spacy.load("en_core_web_sm")

def extract_name(text: str) -> str:
    doc = nlp(text)
    for ent in doc.ents:
        if ent.label_ == "PERSON":
            return ent.text
    lines = text.splitlines()
    for line in lines:
        if len(line.strip()) > 2:
            return line.strip()
    return "Unknown Candidate"


# ---------------------------------------------------------------------------
# SKILLS TAXONOMY
# ---------------------------------------------------------------------------
SKILLS_TAXONOMY = {
    "languages": [
        "python", "java", "javascript", "typescript", "c", "c++", "c#",
        "ruby", "php", "swift", "kotlin", "go", "rust", "scala", "r",
        "matlab", "perl", "bash", "shell", "powershell", "vba",
        "ocaml", "haskell", "elixir", "dart", "lua",
    ],
    "web_frontend": [
        "html", "css", "react", "vue", "angular", "nextjs", "nuxtjs",
        "svelte", "jquery", "bootstrap", "tailwind", "sass", "webpack",
        "vite", "figma", "ui", "ux",
    ],
    "web_backend": [
        "laravel", "django", "flask", "fastapi", "express", "spring",
        "rails", "node", "nodejs", "nestjs", "asp.net", "dotnet",
        "rest", "graphql", "api", "microservice", "soap",
    ],
    "databases": [
        "sql", "mysql", "postgresql", "sqlite", "oracle", "mssql",
        "mongodb", "redis", "elasticsearch", "cassandra", "dynamodb",
        "firebase", "supabase", "database", "nosql",
    ],
    "cloud_devops": [
        "aws", "azure", "gcp", "docker", "kubernetes", "terraform",
        "ansible", "jenkins", "github actions", "ci/cd", "devops",
        "linux", "unix", "nginx", "apache", "serverless", "cloud",
    ],
    "data_ml": [
        "machine learning", "deep learning", "nlp", "tensorflow", "pytorch",
        "scikit-learn", "pandas", "numpy", "spark", "hadoop", "tableau",
        "power bi", "data analysis", "data science", "etl",
        "neural network", "computer vision",
    ],
    "networking_security": [
        "network", "tcp/ip", "dns", "vpn", "firewall", "cybersecurity",
        "penetration testing", "siem", "active directory", "ldap",
        "windows server", "server", "system administration", "it support",
        "helpdesk", "troubleshoot", "hardware", "vmware", "virtualization",
    ],
    "practices": [
        "agile", "scrum", "kanban", "git", "github", "gitlab", "jira",
        "unit testing", "tdd", "code review", "oop", "design pattern",
        "microservice", "mvc", "solid",
    ],
    "business": [
        "project management", "communication", "leadership", "teamwork",
        "problem solving", "analytical", "documentation", "stakeholder",
        "requirement", "business analysis",
    ],
}

WORD_BOUNDARY_SKILLS = {
    'c', 'r', 'go', 'ui', 'ux', 'ai', 'ml', 'ui', 'api',
    'sql', 'css', 'git', 'aws', 'gcp', 'tdd', 'oop', 'mvc',
    'etl', 'nlp', 'dns', 'vpn', 'web', 'php', 'vue', 'c#',
}

SKILLS_FLAT = [skill for skills in SKILLS_TAXONOMY.values() for skill in skills]


# ---------------------------------------------------------------------------
# NORMALIZATION
# ---------------------------------------------------------------------------
ALIAS_MAP = {
    "js":                        "javascript",
    "ts":                        "typescript",
    "py":                        "python",
    "node.js":                   "nodejs",
    "react.js":                  "react",
    "vue.js":                    "vue",
    "next.js":                   "nextjs",
    "express.js":                "express",
    "ruby on rails":             "rails",
    ".net":                      "dotnet",
    "asp.net":                   "dotnet",
    "amazon web services":       "aws",
    "google cloud platform":     "gcp",
    "google cloud":              "gcp",
    "microsoft azure":           "azure",
    "postgres":                  "postgresql",
    "mongo":                     "mongodb",
    "ms sql":                    "mssql",
    "sql server":                "mssql",
    "continuous integration":    "ci/cd",
    "continuous deployment":     "ci/cd",
    "version control":           "git",
    "object oriented":           "oop",
    "object-oriented":           "oop",
    "information technology":    "it support",
    "troubleshooting":           "troubleshoot",
    "networking":                "network",
    "operating systems":         "linux",
    "web development":           "html css javascript",
    "full stack":                "frontend backend",
    "fullstack":                 "frontend backend",
    "artificial intelligence":   "machine learning",
    "ai":                        "machine learning",
    "ml":                        "machine learning",
    "data engineering":          "etl data analysis",
    "business intelligence":     "data analysis power bi tableau",
    "databases":                 "database",
    "networks":                  "network",
}

_ALIAS_REGEXES = [
    (re.compile(r'(?<!\w)' + re.escape(alias) + r'(?!\w)'), canonical)
    for alias, canonical in sorted(ALIAS_MAP.items(), key=lambda x: -len(x[0]))
]

def normalize_text(text: str) -> str:
    """Lower-case and expand known aliases to canonical skill names.

    Uses word-boundary-safe matching (not raw substring replace), so an
    alias like "ai" only matches the standalone token "ai" and never the
    letters "ai" sitting inside unrelated words — e.g. "Aircall", "gmail",
    "maintain", "training" must NOT be touched by the "ai" -> "machine
    learning" alias. Same applies to short aliases "ml", "ts", "py", "js".
    Sort by length descending so longer aliases (e.g. "ruby on rails")
    match before shorter substrings (e.g. "rails").
    """
    text = text.lower()
    for pattern, canonical in _ALIAS_REGEXES:
        text = pattern.sub(canonical, text)
    return text


# ---------------------------------------------------------------------------
# SKILL MATCHING
# ---------------------------------------------------------------------------
def _skill_in_text(skill: str, text: str) -> bool:
    """
    Match a skill against text.
    Short/ambiguous skills use word boundaries; longer ones use substring.
    """
    if skill in WORD_BOUNDARY_SKILLS or len(skill) <= 3:
        return bool(re.search(r'\b' + re.escape(skill) + r'\b', text))
    return skill in text

def extract_skills_from_job(job_text: str) -> list:
    return [s for s in SKILLS_FLAT if _skill_in_text(s, job_text)]

def match_skills(resume_text: str, job_text: str) -> list:
    job_skills = extract_skills_from_job(job_text) if job_text.strip() else SKILLS_FLAT
    return [s for s in job_skills if _skill_in_text(s, resume_text)]


# ---------------------------------------------------------------------------
# TF-IDF SIMILARITY
# ---------------------------------------------------------------------------
def compute_tfidf_similarity(resume_text: str, job_text: str) -> float:
    if not job_text.strip():
        return 0.0
    try:
        vectorizer = TfidfVectorizer(
            stop_words='english',
            ngram_range=(1, 2),
            min_df=1,
            sublinear_tf=True,
        )
        tfidf_matrix = vectorizer.fit_transform([resume_text, job_text])
        similarity = cosine_similarity(tfidf_matrix[0:1], tfidf_matrix[1:2])[0][0]
        return round(float(similarity), 3)
    except Exception:
        return 0.0


def tfidf_top_terms(resume: str, job: str, n: int = 10) -> list:
    """Return top overlapping TF-IDF terms between resume and job as [{term, score}]."""
    try:
        import numpy as np
        vec = TfidfVectorizer(ngram_range=(1, 2), sublinear_tf=True, stop_words='english')
        mat = vec.fit_transform([resume, job])
        names = vec.get_feature_names_out()
        r_vec = mat[0].toarray()[0]
        j_vec = mat[1].toarray()[0]
        both  = np.minimum(r_vec, j_vec)
        top_idx = both.argsort()[::-1][:n]
        return [
            {'term': names[i], 'score': round(float(both[i]), 4)}
            for i in top_idx if both[i] > 0
        ]
    except Exception:
        return []


# ---------------------------------------------------------------------------
# SEMANTIC SIMILARITY
# ---------------------------------------------------------------------------
def compute_semantic_similarity(resume_text: str, job_text: str) -> float:
    if not job_text.strip():
        return 0.0
    try:
        embeddings = model.encode([resume_text, job_text])
        similarity = cosine_similarity([embeddings[0]], [embeddings[1]])[0][0]
        return round(float(similarity), 3)
    except Exception:
        return 0.0


def sem_interpretation(score: float) -> str:
    if score >= 0.75: return 'Very Strong'
    if score >= 0.55: return 'Strong'
    if score >= 0.35: return 'Moderate'
    return 'Weak'


# ---------------------------------------------------------------------------
# LAYOUT / PRESENTATION ANALYSIS
# ---------------------------------------------------------------------------
def classify_layout(text_raw: str, page_count=None, presentation_weights=None) -> dict:
    """
    page_count           – int, actual PDF page count (preferred over word count).
    presentation_weights – optional dict { formatting_weight, language_weight,
                           concise_weight, organization_weight } summing to 100.
    """
    if presentation_weights is None:
        presentation_weights = {}

    text       = text_raw.lower()
    words      = text.split()
    word_count = len(words)
    lines      = text_raw.splitlines()
    total_lines = len(lines)
    BULLET_CHARS = ("•", "-", "*", "–", "·", "\uf0a7", "\uf0b7", "\uf0d8", "\uf0fc")
    bullet_lines = [l for l in lines if l.strip().startswith(BULLET_CHARS)]
    feedback   = {}

    # 1. FORMATTING AND VISUALS
    formatting_score = 0

    blank_lines = [l for l in lines if l.strip() == ""]
    blank_ratio = len(blank_lines) / max(total_lines, 1)
    if blank_ratio >= 0.10:
        formatting_score += 0.30
    else:
        feedback.setdefault("formatting", []).append("Resume lacks spacing between sections, reducing visual clarity.")

    special_chars = len(re.findall(r'[█▓▒░▄▀■□◆◇★☆]', text_raw))
    if special_chars == 0:
        formatting_score += 0.25
    else:
        feedback.setdefault("formatting", []).append("Resume contains decorative symbols that may affect readability.")

    if bullet_lines:
        bullet_symbols = set(l.strip()[0] for l in bullet_lines if l.strip())
        if len(bullet_symbols) <= 2:
            formatting_score += 0.20
        else:
            feedback.setdefault("formatting", []).append("Resume uses inconsistent bullet styles throughout.")
    else:
        formatting_score += 0.20

    header_text = ' '.join(lines[:15]).lower()
    has_email   = bool(re.search(r'[\w.\-]+@[\w.\-]+\.\w+', header_text))
    has_phone   = bool(re.search(r'[\d\s\-\+\(\)]{7,}', header_text))
    if has_email or has_phone:
        formatting_score += 0.25
    else:
        feedback.setdefault("formatting", []).append("No contact details (email or phone) detected near the top of the resume.")

    formatting_score = round(min(formatting_score, 1), 3)

    # 2. LANGUAGE QUALITY
    language_score = 0

    action_verbs = [
        'managed', 'led', 'developed', 'designed', 'implemented', 'coordinated',
        'achieved', 'improved', 'created', 'built', 'analyzed', 'delivered',
        'executed', 'established', 'maintained', 'supported', 'resolved',
        'collaborated', 'spearheaded', 'optimized', 'streamlined', 'launched',
        'trained', 'mentored', 'oversaw', 'directed', 'produced', 'increased',
    ]
    found_action_verbs = [v for v in action_verbs if v in text]
    if len(found_action_verbs) >= 5:
        language_score += 0.4
    elif len(found_action_verbs) >= 2:
        language_score += 0.2
    else:
        feedback.setdefault("language", []).append("Resume lacks action verbs; limited evidence of initiative or ownership.")

    informal_markers = ['i am', "i'm", "i've", "i'll", 'gonna', 'wanna', 'kinda',
                        'lol', 'btw', 'etc etc', 'stuff', 'things', 'lots of']
    if sum(1 for m in informal_markers if m in text) == 0:
        language_score += 0.35
    else:
        feedback.setdefault("language", []).append("Resume contains informal or casual language, which may affect professionalism.")

    content_lines = [l.strip() for l in lines if len(l.strip().split()) > 2]
    long_lines    = sum(1 for l in content_lines if len(l.split()) > 35)
    if long_lines == 0 and content_lines:
        language_score += 0.25
    else:
        feedback.setdefault("language", []).append("Resume contains overly long bullet points that may be difficult to scan.")

    language_score = round(min(language_score, 1), 3)

    # 3. CONCISENESS
    concise_score = 0

    if page_count is not None:
        if page_count == 1:
            concise_score += 0.5
        elif page_count == 2:
            concise_score += 0.3
            feedback.setdefault("concise", []).append("Resume is 2 pages; a single page is preferred for most roles.")
        else:
            feedback.setdefault("concise", []).append(f"Resume is {page_count} pages; recommended length is 1–2 pages.")
    else:
        if word_count < 100:
            feedback.setdefault("concise", []).append("Resume appears sparse; candidate may lack sufficient detail to evaluate.")
        elif word_count <= 400:
            concise_score += 0.5
        elif word_count <= 550:
            concise_score += 0.3
            feedback.setdefault("concise", []).append("Resume may be too long; a single page is preferred for most roles.")
        else:
            feedback.setdefault("concise", []).append("Resume is too long; consider requesting a condensed version.")

    current_year = datetime.datetime.now().year
    recent_years = [str(y) for y in range(current_year - 10, current_year + 1)]
    if any(yr in text_raw for yr in recent_years):
        concise_score += 0.3
    else:
        feedback.setdefault("concise", []).append("No recent dates detected; recency of experience and education is unclear.")

    word_freq    = {}
    for w in words:
        if len(w) > 4:
            word_freq[w] = word_freq.get(w, 0) + 1
    if not [w for w, c in word_freq.items() if c > 8]:
        concise_score += 0.2
    else:
        feedback.setdefault("concise", []).append("Resume contains heavily repeated words, suggesting limited content variety.")

    concise_score = round(min(concise_score, 1), 3)

    # 4. ORGANIZATION AND STRUCTURE
    organization_score = 0

    heading_pattern = re.compile(
        r'^(EDUCATION|EXPERIENCE|SKILLS|PROJECTS?|CERTIFICATIONS?|SUMMARY|OBJECTIVE'
        r'|TRAINING|WORK HISTORY|EMPLOYMENT|PROFESSIONAL|TECHNICAL|RELEVANT'
        r'|INTERNSHIP|AWARDS?|HONORS?|ACTIVITIES|REFERENCES?|PUBLICATIONS?|LANGUAGES?)',
        re.IGNORECASE
    )
    heading_lines = [
        l for l in lines if l.strip() and (
            heading_pattern.match(l.strip()) or
            (l.strip().isupper() and 2 <= len(l.strip().split()) <= 5
             and not re.search(r'[\d@]', l))
        )
    ]
    heading_text = ' '.join(heading_lines).lower()

    expected_sections = [
        'experience', 'education', 'skills', 'project',
        'certification', 'summary', 'objective', 'training',
        'internship', 'employment', 'work'
    ]
    section_hits = sum(1 for s in expected_sections if s in heading_text)

    non_blank_lines  = [l for l in lines if l.strip()]
    avg_words_per_line = (word_count / len(non_blank_lines)) if non_blank_lines else 0
    structure_confidence = 'low' if avg_words_per_line > 40 else 'high'

    if section_hits == 0 and structure_confidence == 'low':
        fallback_hits = sum(
            1 for s in expected_sections if re.search(r'\b' + re.escape(s) + r'\b', text)
        )
        if fallback_hits > section_hits:
            section_hits = fallback_hits
            feedback.setdefault("organization", []).append(
                "Section keywords were found in the text, but line breaks appear to "
                "have been lost during extraction (e.g. OCR or flattened PDF) — "
                "heading detection used a lower-confidence text-wide fallback."
            )

    if section_hits >= 3:
        organization_score += 0.4
    elif section_hits >= 1:
        organization_score += 0.2
        feedback.setdefault("organization", []).append("Resume has few clearly labelled sections; structure may be hard to follow.")
    else:
        feedback.setdefault("organization", []).append("Resume is missing key sections (Experience, Education, Skills).")

    if len(heading_lines) >= 2:
        organization_score += 0.25
    else:
        feedback.setdefault("organization", []).append("Resume lacks standalone section headings, making it harder to navigate.")

    indented_lines = [
        l for l in lines if l and (
            (l[0] == ' ' and l.strip()) or l.strip().startswith(BULLET_CHARS)
        )
    ]
    if len(indented_lines) >= 3:
        organization_score += 0.2
    else:
        feedback.setdefault("organization", []).append("Resume has little indentation or bullet structure; visual hierarchy is weak.")

    years_found = re.findall(r'\b(20\d{2}|19\d{2})\b', text_raw)
    years_int   = list(map(int, years_found))
    if len(years_int) >= 2:
        descending_pairs = sum(
            1 for i in range(len(years_int) - 1) if years_int[i] >= years_int[i + 1]
        )
        if descending_pairs / max(len(years_int) - 1, 1) >= 0.6:
            organization_score += 0.15
        else:
            feedback.setdefault("organization", []).append("Experience entries do not appear to follow reverse-chronological order.")

    organization_score = round(min(organization_score, 1), 3)

    w_fmt = presentation_weights.get("formatting_weight",   25)
    w_lng = presentation_weights.get("language_weight",     25)
    w_con = presentation_weights.get("concise_weight",      25)
    w_org = presentation_weights.get("organization_weight", 25)
    total_w = (w_fmt + w_lng + w_con + w_org) or 100

    presentation_score = round(
        (formatting_score   * w_fmt / total_w) +
        (language_score     * w_lng / total_w) +
        (concise_score      * w_con / total_w) +
        (organization_score * w_org / total_w),
        3
    )

    return {
        "presentation_score":   presentation_score,
        "formatting_score":     formatting_score,
        "language_score":       language_score,
        "concise_score":        concise_score,
        "organization_score":   organization_score,
        "layout_feedback":      feedback,
        "structure_confidence": structure_confidence,
        "avg_words_per_line":   round(avg_words_per_line, 1),
    }

_SECTION_HEADING_PATTERN = re.compile(
    r'^(EDUCATION|EXPERIENCES?|WORK\s+EXPERIENCES?|PROFESSIONAL\s+EXPERIENCES?|SKILLS|PROJECTS?'
    r'|CERTIFICATIONS?|CERTIFICATES?|SUMMARY|OBJECTIVE'
    r'|TRAINING|WORK\s+HISTORY|EMPLOYMENT'
    r'|PROFESSIONAL\s+(?:SUMMARY|SKILLS|PROFILE)|TECHNICAL\s+SKILLS'
    r'|RELEVANT\s+(?:COURSEWORK|EXPERIENCE|SKILLS|PROJECTS?)'
    r'|INTERNSHIP|ORGANIZATIONAL|AWARDS?|HONORS?|ACTIVITIES|REFERENCES?|PUBLICATIONS?|LANGUAGES?)\b',
    re.IGNORECASE
)

# Canonical, whitespace-free heading keywords. Some PDF extractions render a
# stylized/letter-spaced heading like "S K I L L S" or "S KILL S" as literal
# spaced-out characters. _SECTION_HEADING_PATTERN can't catch that since it
# expects the keyword contiguous at the start of the line, so as a second
# check we strip ALL whitespace from a short line and compare it exactly
# against these canonical forms.
#
# NOTE: bare "PROFESSIONAL", "TECHNICAL", "RELEVANT" are intentionally NOT
# included here (or in _SECTION_HEADING_PATTERN above) as standalone tokens.
# They're common ordinary words that show up as the first word of a wrapped
# bullet-continuation line — e.g. "technical troubleshooting." from a bullet
# that wrapped across two lines — and would get misclassified as a section
# heading, incorrectly toggling the experience recorder off mid-job. Only
# their compound forms (PROFESSIONAL EXPERIENCE, TECHNICAL SKILLS, etc.) are
# distinctive enough to safely treat as real headings.
_CANONICAL_HEADINGS = {
    'EDUCATION', 'EXPERIENCE', 'EXPERIENCES', 'WORKEXPERIENCE', 'WORKEXPERIENCES',
    'PROFESSIONALEXPERIENCE', 'PROFESSIONALEXPERIENCES', 'SKILLS', 'PROJECT', 'PROJECTS',
    'CERTIFICATIONS', 'CERTIFICATION', 'CERTIFICATES', 'CERTIFICATE', 'SUMMARY', 'OBJECTIVE',
    'TRAINING', 'WORKHISTORY', 'EMPLOYMENT',
    'INTERNSHIP', 'ORGANIZATIONAL', 'ORGANIZATIONALEXPERIENCE', 'AWARDS', 'AWARD', 'HONORS',
    'HONOR', 'ACTIVITIES', 'REFERENCES', 'REFERENCE', 'PUBLICATIONS', 'PUBLICATION', 'LANGUAGES', 'LANGUAGE',
}

# Which of the canonical/keyword forms above count as "this is paid work
# experience" (vs. a non-experience heading like Education or Skills).
_EXPERIENCE_TOKENS = {
    'EXPERIENCE', 'EXPERIENCES', 'WORKEXPERIENCE', 'WORKEXPERIENCES',
    'PROFESSIONALEXPERIENCE', 'PROFESSIONALEXPERIENCES', 'EMPLOYMENT', 'WORKHISTORY',
    'INTERNSHIP',
}

# NOTE: "ORGANIZATIONAL" (as in "Organizational Experience") is intentionally
# recognized as a heading here but deliberately left OUT of _EXPERIENCE_TOKENS.
# Resumes use "Organizational Experience" to mean club/professional-society
# membership, not paid employment — so it should turn the experience recorder
# OFF like any other non-work heading, not get merged into years-of-experience
# math alongside real jobs.

_EXPERIENCE_KEYWORD_PATTERN = re.compile(
    r'^(EXPERIENCES?|WORK\s+EXPERIENCES?|PROFESSIONAL\s+EXPERIENCES?|EMPLOYMENT|WORK\s+HISTORY|INTERNSHIP)\b',
    re.IGNORECASE
)

# Case-SENSITIVE (no re.IGNORECASE) search for a heading keyword occurring in
# literal ALL CAPS anywhere within a line. This exists to catch a specific PDF
# extraction artifact: when a styled section header (rendered as a bold/caps
# text box) visually overlaps the tail end of a preceding paragraph, the text
# extractor can merge them onto one line, e.g. "Campaigns. WORK EXPERIENCE" or
# "SUMMARY <lowercase paragraph text...>". In that case the keyword isn't at
# the start of the line, so the anchored check above misses it entirely — and
# if it's missed for every job heading, the experience section never gets
# marked and the isolator falls back to returning the WHOLE resume (including
# Education dates), wildly inflating years-of-experience.
# Requiring literal uppercase is what keeps this safe: a casual, lowercase
# mention like "hands-on experience running livestreams" in a summary
# paragraph will never match, only a genuinely capitalized heading token will.
_CAPS_HEADING_SEARCH = re.compile(
    r'\b(EDUCATION|WORK\s+EXPERIENCES?|PROFESSIONAL\s+EXPERIENCES?|EXPERIENCES?|EMPLOYMENT|WORK\s+HISTORY'
    r'|SKILLS|PROJECTS?|CERTIFICATIONS?|CERTIFICATES?|SUMMARY|OBJECTIVE'
    r'|PROFESSIONAL\s+(?:SUMMARY|SKILLS|PROFILE)|TECHNICAL\s+SKILLS|RELEVANT\s+(?:COURSEWORK|EXPERIENCE|SKILLS|PROJECTS?)'
    r'|ORGANIZATIONAL|AWARDS?|HONORS?|ACTIVITIES|REFERENCES?|PUBLICATIONS?|LANGUAGES?)\b'
)
# NOTE: bare "TRAINING" and bare "INTERNSHIP" are intentionally NOT included
# above (unlike in _SECTION_HEADING_PATTERN, where they're safe because they
# must be the line's very first word). Searched anywhere in a longer
# ALL-CAPS line, they're too likely to be part of an ordinary job title
# rather than a section heading — e.g. "ON-THE-JOB TRAINING" is a common
# internship designation (OJT), not a section boundary, and would otherwise
# get misclassified as one, incorrectly cutting off the experience section
# right in the middle of a candidate's job history.

def _classify_line(line_clean: str):
    """Classify a (already-stripped) line as an 'experience' heading, an
    'other' (non-experience) heading, or None if it's not a heading at all.

    Tries three checks in order of specificity:
      1. Anchored keyword at the start of the line (the normal case).
      2. Whitespace-stripped exact match, for OCR artifacts like "S KILL S"
         where a short heading gets letter-spaced.
      3. A literal ALL-CAPS keyword occurring anywhere in a longer line, for
         cases where a heading textbox visually merged with adjacent
         paragraph text during PDF extraction (see _CAPS_HEADING_SEARCH).
    """
    m = _SECTION_HEADING_PATTERN.match(line_clean)
    if m:
        return 'experience' if _EXPERIENCE_KEYWORD_PATTERN.match(line_clean) else 'other'

    if len(line_clean) <= 25:
        compact = re.sub(r'\s+', '', line_clean).upper()
        if compact in _CANONICAL_HEADINGS:
            return 'experience' if compact in _EXPERIENCE_TOKENS else 'other'

    if len(line_clean) > 15:
        cm = _CAPS_HEADING_SEARCH.search(line_clean)
        if cm:
            token = re.sub(r'\s+', '', cm.group(1)).upper()
            return 'experience' if token in _EXPERIENCE_TOKENS else 'other'

    return None

# Last-resort anchor for when the "WORK EXPERIENCE" / "SUMMARY" heading text
# doesn't survive extraction AT ALL — not merged, not letter-spaced, just
# genuinely gone (seen with OCR misreading a stylized/colored header bar
# while plainer headings like EDUCATION extract fine right below it). In
# that case there is no heading anywhere to turn the recorder on, so instead
# we look for the shape of an actual job date range, e.g. "Oct 2024 - Present"
# or "November 2023 to November 2025". This is deliberately a `search`
# (not anchored to line start, and not requiring company text on the same
# line) because some resume layouts put the company/title on one line and
# the date range on the very next line by itself — requiring both on one
# line would miss that shape entirely.
_JOB_ENTRY_LINE = re.compile(
    r'(?:[A-Za-z]{3,9}\.?\s+)?(?:19|20)\d{2}(?:-(?:0[1-9]|1[0-2]))?\s*(?:[-\u2013\u2014]|to)\s*'
    r'(?:(?:[A-Za-z]{3,9}\.?\s+)?(?:19|20)\d{2}(?:-(?:0[1-9]|1[0-2]))?|Present|Current|Now|Ongoing)\b',
    re.IGNORECASE
)

def isolate_experience_section(resume_raw: str) -> str:
    """Extracts only the text belonging to Experience/Employment sections.

    Headings are recognized via _classify_line, which tries (in order): an
    anchored keyword at the start of a line; a whitespace-stripped exact
    match for letter-spaced OCR headings; and a case-sensitive ALL-CAPS
    search anywhere in a line to catch headings that got merged with
    adjacent paragraph text during PDF extraction. This intentionally avoids
    failure modes seen on real resumes:
      1. Scanning the whole raw text for a bare keyword like "EDUCATION"
         with only a \\b boundary can false-positive on ordinary content —
         e.g. a company name like "Origin Migration & Education Visa
         Specialists" contains the standalone word "Education" and would
         wrongly be treated as the start of the Education section,
         truncating the experience section right in the middle of a job.
      2. A generic "ALL CAPS + short line" heuristic (used in an earlier
         version of this function) can't distinguish a real section heading
         from a job-title subheading inside the experience section itself
         (e.g. "LEGAL ASSISTANT", "HR OFFICER - SITE" are just as short,
         all-caps, and digit-free as a real heading). That heuristic ended
         up shutting off recording right after the first job title, wiping
         out nearly the entire experience section.
      3. Requiring the heading keyword strictly at position 0 misses cases
         where a styled section-header textbox visually overlaps the tail of
         a preceding paragraph in the PDF, merging onto one extracted line
         like "Campaigns. WORK EXPERIENCE" — the anchored check alone would
         never see this as a heading, the recorder would never turn on for
         the entire job history, and the empty-result fallback below would
         return the WHOLE resume (Education dates included), wildly
         inflating years-of-experience.
      4. Sometimes a heading can't anchor the section correctly at all —
         either the heading text doesn't survive extraction (OCR misreads a
         stylized/colored header bar into garbage while a plainer heading
         like EDUCATION right below it extracts cleanly), or the heading
         does extract but lands in the wrong place relative to the actual
         job content (a layout that extracts company/dates/bullets in one
         place and the heading + bare job titles somewhere else, so the
         recorder turns on only after all the real dated content has
         already gone by). Either way, no amount of heading-pattern-matching
         finds usable content, so as a last resort we look for the *shape*
         of an actual job date range instead (see _JOB_ENTRY_LINE) and
         anchor the start of the experience section there.
    """
    lines = resume_raw.splitlines()
    exp_text = []
    in_exp_section = False
    saw_experience_heading = False

    for line in lines:
        line_clean = line.strip()

        cls = _classify_line(line_clean) if line_clean else None

        if cls == 'experience':
            in_exp_section = True
            saw_experience_heading = True
        elif cls == 'other':
            in_exp_section = False

        # If the recorder is on, save the text
        if in_exp_section:
            exp_text.append(line)

    # Decide whether to trust the heading-based result above, or re-anchor
    # using the shape of an actual job entry instead. Two situations call
    # for the fallback:
    #   (a) No 'experience' heading was found anywhere in the document at
    #       all (heading text didn't survive extraction — see case 4 in the
    #       docstring above).
    #   (b) A heading WAS found, but the text captured under it contains no
    #       year/date at all. This happens when a PDF's layout extracts the
    #       heading and bare job-title labels in one place, but the actual
    #       company names, date ranges, and bullets earlier in the raw text
    #       — meaning by the time the recorder turns on at the heading, all
    #       the real dated content has already gone by. The isolated result
    #       looks "successful" (non-empty, has an experience heading) but is
    #       actually just a list of job titles with zero usable dates.
    exp_text_has_dates = bool(re.search(r'\b(?:19|20)\d{2}\b', "\n".join(exp_text)))

    if not saw_experience_heading or not exp_text_has_dates:
        start_idx = None
        for i, line in enumerate(lines):
            if _JOB_ENTRY_LINE.search(line.strip()):
                start_idx = i
                break

        if start_idx is not None:
            # Include one line of leading context (typically the company
            # name, when the date sits on its own line right below it).
            start_idx = max(0, start_idx - 1)
            candidate_exp_text = []
            in_exp_section = True
            for line in lines[start_idx:]:
                line_clean = line.strip()
                cls = _classify_line(line_clean) if line_clean else None
                if cls == 'other':
                    in_exp_section = False
                if in_exp_section:
                    candidate_exp_text.append(line)

            # Only prefer this content-based result if it actually found
            # dates the heading-based pass missed. Otherwise keep whatever
            # the heading-based pass produced (don't make things worse for
            # resumes where the heading-based result was already correct
            # but just happens to be date-free for some other reason).
            if re.search(r'\b(?:19|20)\d{2}\b', "\n".join(candidate_exp_text)):
                exp_text = candidate_exp_text

    # Fallback: If we couldn't find any clear sections (weird formatting/bad OCR),
    # return the whole resume so we don't accidentally score them a 0.
    if not exp_text:
        return resume_raw

    return "\n".join(exp_text)

def _extract_experience_years(resume_raw: str, resume_normalized: str) -> dict:
    current_year = datetime.datetime.now().year
    
    # 1. ISOLATE THE TEXT: Only look at the Experience section!
    exp_section_text = isolate_experience_section(resume_raw)
    text = exp_section_text.lower()
    
    end_kw = r'(?:current|present|now|ongoing|till\s+date|to\s+date)'

    # Separator between the two years/keywords in a date range. Covers
    # "-", en dash, em dash, and the word "to".
    sep = r'\s*(?:[-\u2013\u2014]|to)\s*'
    # Many resumes write ranges as "December 2025 - March 2026" rather than
    # bare "2025 - 2026". A single month token (name or abbreviation) can sit
    # right after the separator, before the second year/keyword — allow for it.
    month_tok = r'(?:[A-Za-z]{3,9}\.?\s+)?'
    # Some resumes (often from resume-builder/ATS templates) write dates as
    # ISO year-month, e.g. "2025-02 - 2025-04" rather than "Feb 2025 - Apr
    # 2025". The numeric month is glued directly to the year with a hyphen
    # and no space, which — before this fix — was getting misread as the
    # range separator itself: "2025" matched as a year, then the very next
    # character "-02" got consumed as if it were the separator, but "02"
    # isn't a valid 4-digit year, so the whole match failed right there,
    # never reaching the real separator further along. This optional
    # trailing group lets a year atomically absorb its own "-MM" suffix
    # (01-12) before the regex looks for the actual range separator, so
    # "2025-02 – 2025-04" now parses as (2025, 2025) instead of not
    # matching at all.
    iso_month_suffix = r'(?:-(?:0[1-9]|1[0-2]))?'

    # 2. Find all ranges (now guaranteed to mostly be work history)
    open_starts = [
        int(y) for y in re.findall(
            r'\b((?:19|20)\d{2})' + iso_month_suffix + sep + month_tok + end_kw, text, re.IGNORECASE)
        if 1950 <= int(y) <= current_year
    ]
    
    closed_pairs = [
        (int(s), int(e))
        for s, e in re.findall(
            r'\b((?:19|20)\d{2})' + iso_month_suffix + sep + month_tok
            + r'((?:19|20)\d{2})' + iso_month_suffix + r'\b', text)
        if 1950 <= int(s) <= current_year and int(s) <= int(e) <= current_year + 1
    ]

    # Some entries (freelance/gig/contract work especially) list only a
    # single bare year with no range at all, e.g. "...Minsan Studios 2023
    # (Hybrid)" rather than "2023 - 2024". These never match either pattern
    # above and were previously invisible to the calculation entirely. When
    # a bare year is tagged with a work-arrangement qualifier like this, it's
    # a strong, low-false-positive signal that it's a real (if imprecisely
    # dated) job stint — count it as one year. This intentionally does NOT
    # try to catch every bare year in the text (e.g. a year mentioned in a
    # company name or an award), only ones with this specific qualifier
    # pattern next to them.
    standalone_years = [
        int(y) for y in re.findall(
            r'\b((?:19|20)\d{2})\s*\((?:hybrid|remote|freelance|contract|part[\s-]?time'
            r'|full[\s-]?time|on[\s-]?site|temporary|seasonal|internship|consulting|gig)\)',
            text, re.IGNORECASE)
        if 1950 <= int(y) <= current_year
    ]

    # 3. Convert all to a standard list of [start, end] intervals
    intervals = []
    for s in open_starts:
        intervals.append([s, current_year])
    for s, e in closed_pairs:
        intervals.append([s, e])
    for y in standalone_years:
        # A single-year stint with no explicit end date -> count as one year.
        intervals.append([y, y + 1])

    # 4. Merge overlapping intervals to handle concurrent jobs and gaps safely
    years_from_ranges = 0
    if intervals:
        intervals.sort(key=lambda x: x[0]) # Sort by start year
        
        merged = [intervals[0]]
        for current in intervals[1:]:
            prev = merged[-1]
            if current[0] <= prev[1]:
                # Overlapping jobs (e.g., freelance + full-time) -> merge them
                merged[-1] = [prev[0], max(prev[1], current[1])]
            else:
                # Distinct job with a gap before it
                merged.append(current)
        
        # Sum the total valid, non-overlapping durations
        for s, e in merged:
            years_from_ranges += (e - s)

    # 5. Explicit "N years" fallback (scans the whole text just in case)
    no_age = re.sub(r'\b\d+\s+years?\s+(?:old|of\s+age)\b', '', resume_normalized)
    explicit_raw = re.findall(r'(\d+)\+?\s*years?', no_age)
    years_from_explicit = max(map(int, explicit_raw)) if explicit_raw else 0

    years_exp = years_from_ranges if years_from_ranges > 0 else years_from_explicit
    years_exp = min(years_exp, 40) # Cap at 40 to prevent regex hallucination bugs
    
    if years_exp == 0 and 'project' in resume_normalized:
        years_exp = 1

    return {
        'years':               years_exp,
        'explicit_raw':        explicit_raw,
        'open_ranges':         [(y, current_year) for y in open_starts],
        'closed_ranges':       closed_pairs,
        'standalone_years':    standalone_years,
        'years_from_ranges':   years_from_ranges,
        'years_from_explicit': years_from_explicit,
    }

# ---------------------------------------------------------------------------
# EXPERIENCE EXTRACTION
# ---------------------------------------------------------------------------
_MONTH_NAMES = {
    'jan': 1, 'january': 1, 'feb': 2, 'february': 2, 'mar': 3, 'march': 3,
    'apr': 4, 'april': 4, 'may': 5, 'jun': 6, 'june': 6, 'jul': 7, 'july': 7,
    'aug': 8, 'august': 8, 'sep': 9, 'sept': 9, 'september': 9, 'oct': 10,
    'october': 10, 'nov': 11, 'november': 11, 'dec': 12, 'december': 12,
}

_WORD_NUMBERS = {
    'one': 1, 'two': 2, 'three': 3, 'four': 4, 'five': 5, 'six': 6,
    'seven': 7, 'eight': 8, 'nine': 9, 'ten': 10, 'eleven': 11, 'twelve': 12,
    'thirteen': 13, 'fourteen': 14, 'fifteen': 15, 'sixteen': 16,
    'seventeen': 17, 'eighteen': 18, 'nineteen': 19, 'twenty': 20,
}
_NUM_ALT = r'\d{1,2}(?:\.\d)?|' + '|'.join(_WORD_NUMBERS.keys())

# Numbers whose surrounding sentence signals they're NOT tenure — budget
# size, team headcount, working hours/week, someone's age.
_NON_TENURE_CONTEXT = re.compile(
    r'\$|\bmillion\b|\bbillion\b|\bbudget\b|\bteam of\b|\bpeople\b|\bstaff\b|'
    r'\bemployees\b|\bmembers\b|\bhours?\b|\bdays? a week\b|\bweeks?\b|'
    r'\bage(?:d)?\b|\byears? old\b',
    re.IGNORECASE,
)

# A "summary" style claim, e.g. "8 years of experience in software
# development". Requires the word experience/exp nearby so a stray
# "3 years" elsewhere in the document isn't mistaken for the candidate's
# headline claim.
_SUMMARY_YEARS_RE = re.compile(
    r'(?P<num>' + _NUM_ALT + r')\+?\s*(?:years?|yrs?)\s*'
    r'(?:of\s+)?(?:\w+\s+){0,3}?(?:experience|exp\.?\b)',
    re.IGNORECASE,
)

# A per-role duration, e.g. "Backend Developer, Acme Corp (3 years)" or
# "Acme Corp — 4 yrs". No "experience" keyword required, since resume
# bullets rarely repeat that word per job — but it must be attached to a
# dash/parenthesis pattern rather than floating in prose.
_PER_ROLE_YEARS_RE = re.compile(
    r'(?:[-–—(]\s*)(?P<num>' + _NUM_ALT + r')\+?\s*(?:years?|yrs?)\s*\)?',
    re.IGNORECASE,
)

_SENTENCE_SPLIT_RE = re.compile(r'(?<=[.!?])\s+|\n+')

_DATE_RANGE_RE = re.compile(
    r'(?P<start_month>[A-Za-z]{3,9})?\.?\s*(?P<start_year>(?:19|20)\d{2})\s*'
    r'(?:-|–|—|to)\s*'
    r'(?:(?P<end_month>[A-Za-z]{3,9})?\.?\s*(?P<end_year>(?:19|20)\d{2})|'
    r'(?P<present>present|current|now|ongoing))',
    re.IGNORECASE,
)


def _word_to_num(token: str):
    token = token.lower()
    if token in _WORD_NUMBERS:
        return _WORD_NUMBERS[token]
    try:
        return float(token)
    except ValueError:
        return None


def _extract_date_range_years(text: str):
    """
    Primary signal. Finds explicit date ranges (e.g. "Jan 2018 - Dec 2023",
    "2019 - Present") and merges overlapping/concurrent intervals before
    summing, so two jobs held at the same time don't inflate the total and
    unlisted gaps between jobs aren't counted.
    """
    today = datetime.date.today()
    intervals = []

    for m in _DATE_RANGE_RE.finditer(text):
        try:
            start_year = int(m.group('start_year'))
            start_month = _MONTH_NAMES.get((m.group('start_month') or '').lower()[:3], 1)

            if m.group('present'):
                end_year, end_month = today.year, today.month
            else:
                end_year = int(m.group('end_year'))
                end_month = _MONTH_NAMES.get((m.group('end_month') or '').lower()[:3], 12)

            start = datetime.date(start_year, start_month, 1)
            end = datetime.date(end_year, end_month, 1)

            # Reject reversed or out-of-range spans (e.g. a phone number or
            # zip code that happened to look like two 4-digit years).
            if end < start or start.year < 1950 or end.year > today.year + 1:
                continue

            intervals.append((start, end))
        except (ValueError, TypeError):
            continue

    if not intervals:
        return 0.0, []

    intervals.sort(key=lambda iv: iv[0])
    merged = [intervals[0]]
    for start, end in intervals[1:]:
        last_start, last_end = merged[-1]
        if start <= last_end:                       # overlap/concurrent -> merge
            merged[-1] = (last_start, max(last_end, end))
        else:
            merged.append((start, end))

    total_days = sum((end - start).days for start, end in merged)
    total_years = round(total_days / 365.25, 1)
    return total_years, merged


def _extract_explicit_years(text: str):
    """
    Fallback, only used when no date ranges were found at all. Every
    context check is scoped to the sentence the number appears in (not a
    raw character window, which can bleed context from a neighboring
    sentence). Summary claims ("8 years of experience") are taken once;
    distinct per-role mentions ("3 years" at job A, "4 years" at job B)
    are summed.
    """
    summary_values = []
    per_role_values = []

    for sentence in _SENTENCE_SPLIT_RE.split(text):
        if _NON_TENURE_CONTEXT.search(sentence):
            continue

        for m in _SUMMARY_YEARS_RE.finditer(sentence):
            num = _word_to_num(m.group('num'))
            if num and 0 < num <= 60:
                summary_values.append(num)

        for m in _PER_ROLE_YEARS_RE.finditer(sentence):
            num = _word_to_num(m.group('num'))
            if num and 0 < num <= 60:
                per_role_values.append(num)

    if summary_values:
        return max(set(summary_values))

    if per_role_values:
        total = sum(set(per_role_values))
        return min(total, 50.0)

    return 0.0


def extract_years_experience(resume_text: str) -> dict:
    """
    Returns:
        {
          'years_experience': float,
          'method': 'date_ranges' | 'explicit_statement' | 'none',
          'intervals': [(date, date), ...]   # only for 'date_ranges'
        }

    No "'project' in resume -> 1 year" guessing: "project" appears on
    nearly every resume and says nothing about tenure. Undetectable cases
    return 0 with method='none' rather than a fabricated number.
    """
    date_range_years, intervals = _extract_date_range_years(resume_text)
    if intervals:
        return {
            'years_experience': date_range_years,
            'method': 'date_ranges',
            'intervals': intervals,
        }

    explicit_years = _extract_explicit_years(resume_text)
    if explicit_years > 0:
        return {
            'years_experience': explicit_years,
            'method': 'explicit_statement',
            'intervals': [],
        }

    return {
        'years_experience': 0.0,
        'method': 'none',
        'intervals': [],
    }


# ---------------------------------------------------------------------------
# SHARED CORE SCORING  (used by both /analyze and debug_routes)
# ---------------------------------------------------------------------------
def score_resume(resume_raw: str, job_raw: str, page_count,
                 kw: int = 40, sem: int = 60,
                 presentation_weights: dict = None) -> dict:
    """
    Single source of truth for all resume scoring.
    Returns the full payload consumed by both /analyze and the debug panel.
    """
    if presentation_weights is None:
        presentation_weights = {}

    resume = normalize_text(resume_raw)
    job    = normalize_text(job_raw)

    has_job     = bool(job.strip())
    total_blend = (kw + sem) or 100

    tfidf_score    = compute_tfidf_similarity(resume, job)
    semantic_score = compute_semantic_similarity(resume, job)
    combined       = round(
        (tfidf_score * kw / total_blend) + (semantic_score * sem / total_blend), 3
    )

    tfidf_contrib = round(tfidf_score    * kw  / total_blend, 4)
    sem_contrib   = round(semantic_score * sem / total_blend, 4)

    sim_steps = {
        'has_job':           has_job,
        'tfidf_raw':         round(tfidf_score,    4),
        'tfidf_weight':      kw,
        'tfidf_contrib':     tfidf_contrib,
        'semantic_raw':      round(semantic_score, 4),
        'semantic_weight':   sem,
        'semantic_contrib':  sem_contrib,
        'combined':          combined,
        'semantic_label':    sem_interpretation(semantic_score) if has_job else '—',
        'top_terms':         tfidf_top_terms(resume, job) if has_job else [],
        'resume_word_count': len(resume.split()),
        'job_word_count':    len(job.split()) if has_job else 0,
    }

    matched_skills       = match_skills(resume, job)
    job_skills_extracted = extract_skills_from_job(job) if job.strip() else []
    skill_gap            = [s for s in job_skills_extracted if s not in resume]

    _resume_for_exp = re.sub(r'\b\d+\s+years?\s+(?:old|of\s+age)\b', '', resume)
    _exp          = _extract_experience_years(resume_raw, resume)
    years_exp     = _exp['years']
    exp_years_raw = _exp['explicit_raw']   # kept for exp_years_detected field below

    education_score, education_level = 0, 'none detected'
    if re.search(r"\bmaster'?s?\b|\bmaster of\b|\bm\.s\.c\b|\bm\.sc\b", resume):
        education_score, education_level = 1.0, 'master'
    elif re.search(r"\bbachelor'?s?\b|\bb\.?s\.?\b|\bb\.?a\.?\b|\bbachelor of\b", resume):
        education_score, education_level = 0.7, 'bachelor'
    elif re.search(r"\bassociate'?s?\b|\bassociate of\b|\bassociate degree\b", resume):
        education_score, education_level = 0.5, 'associate'

    cert_score, cert_level = 0, 'none detected'
    if 'certification' in resume or 'certified' in resume:
        cert_score, cert_level = 1.0, 'certified'
    elif 'training' in resume:
        cert_score, cert_level = 0.5, 'training'

    layout = classify_layout(resume_raw, page_count, presentation_weights)

    # Qualifications score (fixed weights — matches ScreeningController defaults)
    sim_contrib  = round(combined              * 0.35 * 100, 2)
    exp_contrib  = round(min(years_exp, 5) / 5 * 0.20 * 100, 2)
    edu_contrib  = round(education_score       * 0.25 * 100, 2)
    cert_contrib = round(cert_score            * 0.10 * 100, 2)
    qual_score   = round(sim_contrib + exp_contrib + edu_contrib + cert_contrib, 2)

    return {
        # Identity
        'candidate_name':        extract_name(resume_raw),
        # Similarity
        'tfidf_similarity':      tfidf_score,
        'semantic_similarity':   semantic_score,
        'combined_similarity':   combined,
        'sim_steps':             sim_steps,
        # Skills
        'matched_skills':        matched_skills,
        'skill_gap':             skill_gap,
        'job_skills_extracted':  job_skills_extracted,
        # Experience
        'years_experience':      years_exp,
        'exp_years_detected':    list(map(int, exp_years_raw)) if exp_years_raw else [],
        # Education / Cert
        'education_score':       education_score,
        'certification_score':   cert_score,
        # Qualifications roll-up
        'qualifications_score':  qual_score,
        'score_breakdown': {
            'similarity':    sim_contrib,
            'experience':    exp_contrib,
            'education':     edu_contrib,
            'certification': cert_contrib,
        },
        # Presentation
        'presentation_score':    layout['presentation_score'],
        'formatting_score':      layout['formatting_score'],
        'language_score':        layout['language_score'],
        'concise_score':         layout['concise_score'],
        'organization_score':    layout['organization_score'],
        'layout_feedback':       layout['layout_feedback'],
        # Extraction-quality signal (new) — lets callers / debug UI tell
        # when presentation scoring ran on text that likely lost its
        # original line structure during extraction (OCR, etc).
        'structure_confidence':  layout['structure_confidence'],
        'avg_words_per_line':    layout['avg_words_per_line'],
        # Normalized text (useful for debug)
        'resume_normalized':     resume,
        # Internal metadata (used by debug panel; safe to expose)
        '_meta': {
            'education_level': education_level,
            'cert_level':      cert_level,
        },
    }


# ---------------------------------------------------------------------------
# SHARED DEBUG METADATA  (document-level stats for the debug panel)
# ---------------------------------------------------------------------------
_BULLET_CHARS = ('•', '-', '*', '–', '·', '\uf0a7', '\uf0b7', '\uf0d8', '\uf0fc')

_ACTION_VERBS = [
    'managed', 'led', 'developed', 'designed', 'implemented', 'coordinated',
    'achieved', 'improved', 'created', 'built', 'analyzed', 'delivered',
    'executed', 'established', 'maintained', 'supported', 'resolved',
    'collaborated', 'spearheaded', 'optimized', 'streamlined', 'launched',
    'trained', 'mentored', 'oversaw', 'directed', 'produced', 'increased',
]

_INFORMAL_MARKERS = [
    'i am', "i'm", "i've", "i'll", 'gonna', 'wanna', 'kinda',
    'lol', 'btw', 'etc etc', 'stuff', 'things', 'lots of',
]

_HEADING_RE = re.compile(
    r'^(EDUCATION|EXPERIENCE|SKILLS|PROJECTS?|CERTIFICATIONS?|SUMMARY|OBJECTIVE'
    r'|TRAINING|WORK HISTORY|EMPLOYMENT|PROFESSIONAL|TECHNICAL|RELEVANT'
    r'|INTERNSHIP|AWARDS?|HONORS?|ACTIVITIES|REFERENCES?|PUBLICATIONS?|LANGUAGES?)',
    re.IGNORECASE,
)

def extract_debug_meta(resume_raw: str, page_count) -> dict:
    """Document-level statistics used exclusively by the debug panel."""
    lines        = resume_raw.splitlines()
    text         = resume_raw.lower()
    bullet_lines = [l for l in lines if l.strip().startswith(_BULLET_CHARS)]
    blank_lines  = [l for l in lines if l.strip() == '']
    found_verbs  = [v for v in _ACTION_VERBS if v in text]

    heading_lines = [
        l for l in lines if l.strip() and (
            _HEADING_RE.match(l.strip()) or
            (l.strip().isupper() and 2 <= len(l.strip().split()) <= 5
             and not re.search(r'[\d@]', l))
        )
    ]

    header_text = ' '.join(lines[:15]).lower()
    has_email   = bool(re.search(r'[\w.\-]+@[\w.\-]+\.\w+', header_text))
    has_phone   = bool(re.search(r'[\d\s\-\+\(\)]{7,}', header_text))

    years_found = re.findall(r'\b(20\d{2}|19\d{2})\b', resume_raw)
    years_int   = list(map(int, years_found)) if years_found else []

    found_informal   = [m for m in _INFORMAL_MARKERS if m in text]
    content_lines    = [l.strip() for l in lines if len(l.strip().split()) > 2]
    long_lines_count = sum(1 for l in content_lines if len(l.split()) > 35)
    special_chars    = len(re.findall(r'[█▓▒░▄▀■□◆◇★☆]', resume_raw))

    return {
        'word_count':             len(text.split()),
        'page_count':             page_count,
        'blank_ratio':            round(len(blank_lines) / max(len(lines), 1), 3),
        'bullet_line_count':      len(bullet_lines),
        'heading_line_count':     len(heading_lines),
        'action_verbs_found':     found_verbs,
        'has_email':              has_email,
        'has_phone':              has_phone,
        'detected_sections':      [l.strip() for l in heading_lines],
        'date_range': {
            'earliest': min(years_int) if years_int else None,
            'latest':   max(years_int) if years_int else None,
            'all':      sorted(set(years_int)),
        },
        'informal_markers_found': found_informal,
        'long_lines_count':       long_lines_count,
        'special_chars_count':    special_chars,
    }


# ---------------------------------------------------------------------------
# /analyze  — main Laravel endpoint
# ---------------------------------------------------------------------------
@app.route('/analyze', methods=['POST'])
def analyze():
    data = request.get_json()

    resume_raw = data.get('resume', '')
    job_raw    = data.get('job', '')
    page_count = data.get('page_count', None)
    kw         = int(data.get('keyword_weight',  40))
    sem        = int(data.get('semantic_weight', 60))
    pres_w     = data.get('presentation_weights', {})

    result = score_resume(resume_raw, job_raw, page_count, kw, sem, pres_w)

    # Strip internal-only key before sending to Laravel
    result.pop('_meta', None)

    return jsonify(result)


@app.route('/health', methods=['GET'])
def health():
    return jsonify({"status": "healthy", "version": "1.0.0"})


if __name__ == '__main__':
    app.run(port=5000, debug=True)