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
model.max_seq_length = 384

try:
    nlp = spacy.load("en_core_web_trf")
except Exception:
    nlp = spacy.load("en_core_web_sm")

_ADDRESS_FOLLOWING_RE = re.compile(
    r'^\s*,\s*[A-Za-z].{0,60}?(city|province|barangay|brgy)\b', re.IGNORECASE | re.DOTALL
)

def extract_name(text: str) -> str:
    """
    Returns the candidate's name via spaCy PERSON NER, with one safeguard:
    a PERSON entity is rejected if it looks like it's actually part of a
    street address rather than the candidate's own name -- e.g. "12 Felix
    Manalo, Pinagkaisahan, Quezon City" is a real Quezon City street
    address ("Felix Manalo" being a very common Philippine street name,
    named after the Iglesia ni Cristo founder), but spaCy's NER confidently
    tags "Felix Manalo" as PERSON since it's also a well-known real name.
    Without this check, a resume whose actual name text got lost/garbled
    during PDF extraction (leaving no other PERSON entity in the document)
    would silently return this address fragment as candidate_name instead
    of failing more visibly.

    Two independent signals catch this, either being sufficient:
      1. A house/lot number immediately precedes the entity (e.g. "12 ").
      2. A comma immediately follows the entity, then a barangay/city-
         shaped fragment within the next ~40 characters (e.g.
         ", Pinagkaisahan, Quezon City").
    """
    doc = nlp(text)

    candidates = []
    for ent in doc.ents:
        if ent.label_ != "PERSON":
            continue
        preceding = text[max(0, ent.start_char - 6):ent.start_char]
        following = text[ent.end_char:ent.end_char + 60]
        looks_like_address = (
            re.search(r'\d\s*$', preceding) or
            _ADDRESS_FOLLOWING_RE.search(following)
        )
        if looks_like_address:
            continue
        candidates.append((ent.start_char, ent.text))

    if candidates:
        candidates.sort(key=lambda c: c[0])
        return candidates[0][1]

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
    "office_productivity": [
        "excel", "microsoft excel", "google workspace", "google sheets",
        "google docs", "google drive", "airtable", "notion", "zapier",
        "fillout", "sis", "student information system",
    ],
    "education_admin": [
        "registrar", "registrar operations", "student records",
        "school administration", "enrollment services", "enrollment",
        "document processing", "academic support", "academic records",
        "academic documentation", "curriculum", "graduation requirements",
        "transcripts", "transfer credentials", "enrollment verification",
        "office administration", "information management",
        "ched", "deped", "tesda", "accreditation", "compliance", "business administration",
    ],
    "career_hr": [
        "recruitment", "human resources", "career development",
        "graduate placement", "employability", "career readiness",
        "career coaching", "mock interview", "alumni affairs",
        "alumni engagement", "alumni relations", "job matching",
        "labor market research", "exit interview", "database management",
        "research", "critical thinking", "relationship building",
        "marketing", "communications", "multimedia", "game development",
        "digital arts", "traditional arts",
        "talent acquisition", "campus recruitment", "campus recruiter",
        "candidate sourcing", "sourcing", "screening", "onboarding",
        "applicant tracking system", "job fair", "career fair",
        "employer branding", "hiring", "interview coordination",
        "background check", "reference verification", "employee records",
        "compensation management", "organizational development",
        "stakeholder engagement", "data storytelling",
        "memorandum of agreement", "background check", "reference verification", 
        "employee 201 file", "recruitment platform", "applicant tracking",
    ],
    "civic_community_service": [
        "civic education", "civic responsibility", "civic engagement",
        "nation building", "nation-building", "volunteerism", "volunteer work",
        "community development", "community engagement", "community-based learning",
        "youth development", "peace education", "human rights",
        "environmental sustainability", "environmental awareness",
        "environmental science", "government service",
        "non-government organization", "ngo", "public service",
        "social work", "sociology", "criminology", "political science",
        "public administration", "public health", "nursing",
    ],
    "disaster_risk_management": [
        "disaster risk reduction and management", "drrm", "disaster preparedness",
        "disaster response", "emergency response", "emergency management",
        "basic life support", "triage", "distress tolerance",
        "risk reduction", "hazard mitigation", "crisis management",
    ],
    "instruction_pedagogy": [
        "lesson plan", "lesson planning", "curriculum development",
        "classroom management", "instruction", "teaching", "facilitation",
        "mentoring", "coaching", "student-centered learning",
        "learner-centered", "faculty development", "academic instruction",
        "training facilitation",
    ],
}

WORD_BOUNDARY_SKILLS = {
    'c', 'r', 'go', 'ui', 'ux', 'ai', 'ml', 'ui', 'api',
    'sql', 'css', 'git', 'aws', 'gcp', 'tdd', 'oop', 'mvc',
    'etl', 'nlp', 'dns', 'vpn', 'web', 'php', 'vue', 'c#',
    # FIXED: 'perl' is 4 chars, so it previously fell through to the raw
    # substring branch of _skill_in_text (only skills <=3 chars or already
    # listed here get word-boundary matching). That meant "perl" matched as
    # a substring of ordinary words like "properly" ("pro-PERL-y"), producing
    # a false "required skill" that then also showed up in skill_gap for
    # candidates who never claimed to know Perl. Adding it here forces a
    # \bperl\b match instead.
    'perl',
    # 'sis' is already <=3 chars so it gets boundary matching automatically,
    # but listed here too for clarity/documentation purposes.
    'sis',
    # NEW: institutional acronyms (CHED, DepEd, TESDA) and 'hr' -- short
    # enough that a bare substring check risks false positives (e.g. "hr"
    # inside "chr"/other words), so they get \b-anchored matching too.
    'ched', 'deped', 'tesda', 'hr', 'drrm', 'ngo'
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
    # NEW: common ways these office/productivity tools get written.
    "ms excel":                  "excel",
    "excel spreadsheets":        "excel",
    "gsuite":                    "google workspace",
    "g suite":                   "google workspace",
    "google apps":               "google workspace",
    "student information systems": "sis",
    "student information system": "sis",
    # NEW: common variants seen in HR/registrar JDs.
    "human resources management": "human resources",
    "hrm":                        "human resources",
    "human resource":             "human resources",
    "public admin":               "public administration",
    "drrm":                       "disaster risk reduction and management",
    "relationship-building":     "relationship building",
    "job placement":             "graduate placement",
    "career placement":          "graduate placement",
    "moa":                       "memorandum of agreement",
    "ats":                       "applicant tracking system",
    "hr":                        "human resources",
    "201 file":                  "employee 201 file",
    "employee records":          "employee 201 file",
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
        r'^(EDUCATION|EXPERIENCE|JOB\s+EXPERIENCE|SKILLS|PROJECTS?|CERTIFICATIONS?|SUMMARY|OBJECTIVE'
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
    r'^(EDUCATION|EXPERIENCES?|WORK\s+EXPERIENCES?|PROFESSIONAL\s+EXPERIENCES?|JOB\s+EXPERIENCES?|SKILLS|PROJECTS?'
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
    'PROFESSIONALEXPERIENCE', 'PROFESSIONALEXPERIENCES',
    # FIXED: "JOB EXPERIENCE" is a very common heading variant (seen on the
    # Dominique Lee resume) that previously matched NONE of the heading
    # checks: it doesn't start with a recognized keyword (starts with
    # "JOB"), and wasn't in this canonical set either. That silently failed
    # saw_experience_heading, which threw the isolator into its last-resort
    # date-shape fallback — which then anchored on the candidate's
    # EDUCATION date range instead of her actual jobs, merging years of
    # school/extracurriculars into "years of experience".
    'JOBEXPERIENCE', 'JOBEXPERIENCES',
    'SKILLS', 'PROJECT', 'PROJECTS',
    'CERTIFICATIONS', 'CERTIFICATION', 'CERTIFICATES', 'CERTIFICATE', 'SUMMARY', 'OBJECTIVE',
    'TRAINING', 'WORKHISTORY', 'EMPLOYMENT',
    'INTERNSHIP', 'ORGANIZATIONAL', 'ORGANIZATIONALEXPERIENCE', 'AWARDS', 'AWARD', 'HONORS',
    'HONOR', 'ACTIVITIES', 'REFERENCES', 'REFERENCE', 'PUBLICATIONS', 'PUBLICATION', 'LANGUAGES', 'LANGUAGE',
}

# Which of the canonical/keyword forms above count as "this is paid work
# experience" (vs. a non-experience heading like Education or Skills).
_EXPERIENCE_TOKENS = {
    'EXPERIENCE', 'EXPERIENCES', 'WORKEXPERIENCE', 'WORKEXPERIENCES',
    'PROFESSIONALEXPERIENCE', 'PROFESSIONALEXPERIENCES', 'JOBEXPERIENCE', 'JOBEXPERIENCES',
    'EMPLOYMENT', 'WORKHISTORY',
    'INTERNSHIP',
}

# NOTE: "ORGANIZATIONAL" (as in "Organizational Experience") is intentionally
# recognized as a heading here but deliberately left OUT of _EXPERIENCE_TOKENS.
# Resumes use "Organizational Experience" to mean club/professional-society
# membership, not paid employment — so it should turn the experience recorder
# OFF like any other non-work heading, not get merged into years-of-experience
# math alongside real jobs.

_EXPERIENCE_KEYWORD_PATTERN = re.compile(
    r'^(EXPERIENCES?|WORK\s+EXPERIENCES?|PROFESSIONAL\s+EXPERIENCES?|JOB\s+EXPERIENCES?|EMPLOYMENT|WORK\s+HISTORY|INTERNSHIP)\b',
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
    r'\b(EDUCATION|WORK\s+EXPERIENCES?|PROFESSIONAL\s+EXPERIENCES?|JOB\s+EXPERIENCES?|EXPERIENCES?|EMPLOYMENT|WORK\s+HISTORY'
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

_EDU_LINE_SIGNAL = re.compile(
    r'\b(bachelor|master|ph\.?d|associate\s+degree|associate\s+in|diploma|elementary\s+school'
    r'|high\s+school|senior\s+high|junior\s+high|college|university|degree'
    r'|major\s+in|latin\s+honors?|barangay|brgy\.?)\b',
    re.IGNORECASE
)

def _strip_education_lines(text: str) -> str:
    """Remove lines that are clearly education content (degree/school
    keywords), plus a small window of lines around each one, since the
    actual date range for a school entry is usually on its own separate
    line rather than the same line as the degree/school-name keyword.

    This exists because some PDF layouts extract ALL section content before
    ANY section heading labels (which cluster together separately elsewhere
    in the raw text). When that happens, the "EDUCATION" heading that's
    supposed to stop the experience recorder can appear in the extracted
    text AFTER the actual education entries (school name, degree, dates)
    rather than before them — so a school's date range gets swept into the
    "experience" text and miscounted as work experience. A job bullet is
    very unlikely to mention "Bachelor of Arts" or "Elementary School", so
    filtering on this content is a safe, targeted way to catch it regardless
    of where the section boundaries actually landed.
    """
    lines = text.splitlines()
    excluded_idx = set()
    for i, line in enumerate(lines):
        # FIXED: some resumes format job entries as "Title | Company", e.g.
        # "Clerical Aide | Aklan State University" or "Administrative
        # Assistant | College of Computer Studies...". The employer name
        # containing "university"/"college" is NOT the candidate's own
        # education -- it's just where they worked -- but _EDU_LINE_SIGNAL
        # can't tell the difference and was flagging these lines, deleting
        # a +/-2 line window that usually swallowed the real job's date
        # line sitting right next to it (e.g. "July 2019 - April 2023"
        # directly above the title line). A pipe character reliably marks
        # this "Title | Company" job-entry shape in this resume style, so
        # skip the strip trigger entirely for any line containing one.
        if '|' in line:
            continue
        if _EDU_LINE_SIGNAL.search(line):
            # FIXED: originally a symmetric +/-2 window. In badly scrambled
            # two-column extractions, a degree keyword can sit several
            # lines BEFORE its own date token with unrelated content in
            # between -- e.g. "Associate in Computer Science\nJamiatul
            # Philippines Al-Islamia\n-\nSchool Registrar\n2010-2012" puts
            # the date 4 lines after the keyword. A +/-2 window missed it,
            # leaving the degree's date range uncaught and miscounted as
            # work experience. Extending the forward reach to 4 (keeping
            # lookback at 2, since a degree keyword rarely needs to reach
            # backward that far to find its own date) catches this without
            # widening the backward direction where false-positive risk
            # (deleting real job content) is higher.
            for j in range(max(0, i - 2), min(len(lines), i + 5)):
                excluded_idx.add(j)
    return "\n".join(line for i, line in enumerate(lines) if i not in excluded_idx)

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
    #       usable (non-education) year/date at all. This happens when a
    #       PDF's layout extracts the heading and bare job-title labels in
    #       one place, but the actual company names, date ranges, and
    #       bullets earlier in the raw text — meaning by the time the
    #       recorder turns on at the heading, all the real dated content has
    #       already gone by. The isolated result looks "successful"
    #       (non-empty, has an experience heading) but is actually just a
    #       list of job titles with zero usable dates — or, worse, the
    #       *next* stop-heading (e.g. EDUCATION) is ALSO too late relative
    #       to its own content, so what got captured is actually a stretch
    #       of leaked education entries rather than real job dates. Checking
    #       against the education-stripped text (not the raw captured text)
    #       is what catches this second case — the raw text technically
    #       "has a year" in it, just not one that belongs to a real job.
    exp_text_has_dates = bool(re.search(r'\b(?:19|20)\d{2}\b', _strip_education_lines("\n".join(exp_text))))

    if not saw_experience_heading or not exp_text_has_dates:
        start_idx = None
        for i, line in enumerate(lines):
            if _JOB_ENTRY_LINE.search(line.strip()):
                # FIXED: a date-shape match can land on an EDUCATION entry
                # instead of a job -- e.g. a scrambled two-column extraction
                # can break every real job's "2019 - 2023" into three
                # separate lines (year / dash / year) with unrelated
                # content between them, while a degree date like
                # "2021-2023" survives as one clean compact token right
                # next to "Master of Arts in Education". Without this
                # check, the anchor would lock onto that degree date and
                # the isolator would report a school's date range as "work
                # experience". Look at a small window around the match for
                # education-signal words before accepting it as the start
                # of real job content.
                # Look back further than forward: in scrambled two-column
                # extractions, the degree-signal keyword (e.g. "Associate
                # in Computer Science") often sits several lines BEFORE the
                # compact date token itself (e.g. "...Associate in Computer
                # Science\nJamiatul Philippines Al-Islamia\n-\nSchool
                # Registrar\n2010-2012" -- 3 lines separate the keyword from
                # the date), while forward context rarely needs as much
                # slack. A narrow +/-2 window missed this case entirely.
                window = "\n".join(lines[max(0, i - 4):i + 3])
                if _EDU_LINE_SIGNAL.search(window):
                    continue
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
            # usable (non-education) dates the heading-based pass missed.
            # Otherwise keep whatever the heading-based pass produced (don't
            # make things worse for resumes where the heading-based result
            # was already correct but just happens to be date-free for some
            # other reason).
            if re.search(r'\b(?:19|20)\d{2}\b', _strip_education_lines("\n".join(candidate_exp_text))):
                exp_text = candidate_exp_text

    # Fallback: If we couldn't find any clear sections (weird formatting/bad OCR),
    # return the whole resume so we don't accidentally score them a 0 --
    # but still strip education lines first. Previously this path bypassed
    # _strip_education_lines() entirely (it was only applied on the normal
    # return below), so a badly-scrambled resume where NO job dates could
    # be isolated at all would count every degree's date range (Bachelor's,
    # Master's, Associate's) as "years of experience" instead of falling
    # through to a safer 0/explicit-statement result.
    if not exp_text:
        return _strip_education_lines(resume_raw)

    return _strip_education_lines("\n".join(exp_text))

def _extract_experience_years(resume_raw: str, resume_normalized: str) -> dict:
    current_year = datetime.datetime.now().year
    
    # 1. ISOLATE THE TEXT: Only look at the Experience section! (education
    # lines are already stripped out inside isolate_experience_section)
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
    # FIXED: dates written as "July 18, 2019 – April 15, 2023" (a day-of-
    # month number between the month name and the year) previously broke
    # every closed-range match. month_tok would consume "April ", then the
    # regex demanded a year immediately -- but the actual next text is
    # "15, 2023", so the whole match failed right there. This optional
    # token absorbs a day number (with optional st/nd/rd/th suffix and a
    # trailing comma) between the month and the year.
    day_tok = r'(?:\d{1,2}(?:st|nd|rd|th)?,?\s+)?'
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
            r'\b((?:19|20)\d{2})' + iso_month_suffix + sep + month_tok + day_tok + end_kw, text, re.IGNORECASE)
        if 1950 <= int(y) <= current_year
    ]
    
    closed_pairs = [
        (int(s), int(e))
        for s, e in re.findall(
            r'\b((?:19|20)\d{2})' + iso_month_suffix + sep + month_tok + day_tok
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

    # 3b. MONTH-PRECISION FRACTIONAL CREDIT for stints that start and end
    # within the SAME calendar year, e.g. "2025-02 - 2025-04" (Feb-Apr 2025)
    # or "Aug 2021 - Aug 2021". The whole-year interval math above computes
    # (end_year - start_year), which is always 0 for these — a short
    # internship/gig gets literally zero credit even though it was real,
    # dated work. This block finds any range where BOTH the start and end
    # month are explicitly given, and where they land in the same year, and
    # credits it as a fraction of a year based on the actual month span.
    # Cross-year ranges are untouched here (they already get sensible whole-
    # year credit above); this only fills the specific gap where whole-year
    # math structurally can't produce anything but zero.
    _month_tok_named = r'(?:(?P<{0}m>[A-Za-z]{{3,9}})\.?\s+)?(?:\d{{1,2}}(?:st|nd|rd|th)?,?\s+)?(?P<{0}y>(?:19|20)\d{{2}})(?:-(?P<{0}iso>0[1-9]|1[0-2]))?'
    _same_year_closed_re = re.compile(
        r'\b' + _month_tok_named.format('s') + sep + _month_tok_named.format('e') + r'\b',
        re.IGNORECASE
    )
    _same_year_open_re = re.compile(
        r'\b' + _month_tok_named.format('s') + sep + end_kw,
        re.IGNORECASE
    )

    def _resolve_month(name, iso):
        if iso:
            return int(iso)
        if name:
            return _MONTH_NAMES.get(name.lower().rstrip('.'))
        return None

    same_year_month_intervals = {}  # year -> list of [start_month, end_month]
    current_month = datetime.datetime.now().month

    for m in _same_year_closed_re.finditer(text):
        try:
            s_year, e_year = int(m.group('sy')), int(m.group('ey'))
        except (TypeError, ValueError):
            continue
        if s_year != e_year or not (1950 <= s_year <= current_year):
            continue
        s_month = _resolve_month(m.group('sm'), m.group('siso'))
        e_month = _resolve_month(m.group('em'), m.group('eiso'))
        if s_month is None or e_month is None or e_month < s_month:
            # Not enough info (or malformed) to safely credit a fraction —
            # leave it at the existing 0 rather than guess.
            continue
        same_year_month_intervals.setdefault(s_year, []).append([s_month, e_month])

    for m in _same_year_open_re.finditer(text):
        try:
            s_year = int(m.group('sy'))
        except (TypeError, ValueError):
            continue
        if s_year != current_year:
            continue  # a genuinely multi-year open range already gets credit above
        s_month = _resolve_month(m.group('sm'), m.group('siso'))
        if s_month is None or s_month > current_month:
            continue
        same_year_month_intervals.setdefault(s_year, []).append([s_month, current_month])

    # 4. Merge overlapping WHOLE-YEAR intervals to handle concurrent jobs
    # and gaps safely. Computed here (before the same-year fractional total
    # below) because the same-year credit needs to know which years are
    # already fully covered by a continuous multi-year job span, so it
    # doesn't double-count time that's already accounted for.
    merged = []
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

    def _year_covered_by_merged_range(year):
        # True if this calendar year sits strictly inside an existing
        # continuous whole-year job span (not just touching its edge) --
        # meaning that year's employment is already counted above, so a
        # same-year fractional stint within it would be double-counting.
        return any(s <= year < e for s, e in merged)

    # Merge same-year month intervals per year so overlapping/concurrent
    # same-year stints don't get double-counted, then sum total months --
    # skipping any year that's already covered by a continuous whole-year
    # job span from the merge above.
    total_same_year_months = 0
    for year, ivs in same_year_month_intervals.items():
        if _year_covered_by_merged_range(year):
            continue
        ivs.sort()
        merged_months = [ivs[0]]
        for cur in ivs[1:]:
            if cur[0] <= merged_months[-1][1] + 1:
                merged_months[-1][1] = max(merged_months[-1][1], cur[1])
            else:
                merged_months.append(cur)
        for sm, em in merged_months:
            total_same_year_months += (em - sm + 1)

    years_from_same_year_months = round(total_same_year_months / 12.0, 2)



    # 5. Explicit "N years" fallback (scans the whole text just in case)
    no_age = re.sub(r'\b\d+\s+years?\s+(?:old|of\s+age)\b', '', resume_normalized)
    explicit_raw = re.findall(r'(\d+)\+?\s*years?', no_age)
    years_from_explicit = max(map(int, explicit_raw)) if explicit_raw else 0

    # FIXED: "over eight years of work experience" was previously invisible
    # to this fallback entirely, since \d+ only matches digits, not spelled-
    # out numbers. This matters most for exactly the cases that need a
    # fallback: badly-OCR'd/scrambled resumes where no job date range could
    # be isolated at all, and a summary line spelling out tenure in words is
    # the only remaining signal. Requires "experience" within a few words
    # (like _SUMMARY_YEARS_RE elsewhere) so a stray "seven days" or similar
    # doesn't get mistaken for a tenure claim.
    _word_num_pattern = '|'.join(_WORD_NUMBERS.keys())
    explicit_word_raw = re.findall(
        r'\b(' + _word_num_pattern + r')\+?\s*years?\s*(?:of\s+)?(?:\w+\s+){0,3}?experience\b',
        no_age, re.IGNORECASE
    )
    if explicit_word_raw:
        years_from_explicit = max(
            years_from_explicit,
            max(_WORD_NUMBERS[w.lower()] for w in explicit_word_raw)
        )

    # Combine the whole-year total (cross-year ranges, open ranges,
    # standalone-year entries) with the month-precision fractional credit
    # for same-year stints computed above. years_precise keeps the decimal
    # detail (useful for debugging/the sandbox); years is the rounded whole
    # number used everywhere else, preserving the existing integer contract.
    years_precise = round(years_from_ranges + years_from_same_year_months, 2)

    years_exp = years_precise if years_precise > 0 else years_from_explicit
    years_exp = round(years_exp)
    years_exp = min(years_exp, 40) # Cap at 40 to prevent regex hallucination bugs

    if years_exp == 0 and 'project' in resume_normalized:
        years_exp = 1

    return {
        'years':                    years_exp,
        'years_precise':            years_precise,
        'explicit_raw':             explicit_raw,
        'open_ranges':              [(y, current_year) for y in open_starts],
        'closed_ranges':            closed_pairs,
        'standalone_years':         standalone_years,
        'years_from_ranges':        years_from_ranges,
        'years_from_same_year_months': years_from_same_year_months,
        'years_from_explicit':      years_from_explicit,
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

# FIXED: "AB Psychology" is the Philippine convention for a Bachelor's degree
# (Latin "Artium Baccalaureus" — the letters reversed from the US "BA"). The
# original bachelor's regex only recognized "ba"/"b.a."/"bachelor", so a
# genuine bachelor's degree written as "AB" scored education_level as "none
# detected", silently zeroing out 25% of qualifications_score.
#
# Bare "ab" is too ambiguous to match on its own (e.g. "A/B testing", "Plan
# AB", initials) so this is scoped to the shape an actual degree line takes:
# "ab" as the first word of a line, with "university" or "college" appearing
# later on that same line — matching patterns like "AB Psychology, Ateneo de
# Manila University" without matching stray "AB" mentions elsewhere in the
# document.
_AB_DEGREE_LINE = re.compile(r'^\s*ab\b.*\b(university|college)\b', re.IGNORECASE)

def _has_bachelor_degree(resume_normalized: str, resume_raw: str) -> bool:
    if re.search(r"\bbachelor'?s?\b|\bb\.?s\.?\b|\bb\.?a\.?\b|\bbachelor of\b", resume_normalized):
        return True
    return any(_AB_DEGREE_LINE.match(line.strip()) for line in resume_raw.splitlines())


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
    text_similarity = round(
        (tfidf_score * kw / total_blend) + (semantic_score * sem / total_blend), 3
    )

    matched_skills       = match_skills(resume, job)
    job_skills_extracted = extract_skills_from_job(job) if job.strip() else []
    skill_gap            = [s for s in job_skills_extracted if s not in resume]

    skill_coverage = (
        len(matched_skills) / len(job_skills_extracted)
        if job_skills_extracted else 0.0
    )

    combined = round(text_similarity * 0.7 + skill_coverage * 0.3, 3) if has_job else 0.0

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

    _resume_for_exp = re.sub(r'\b\d+\s+years?\s+(?:old|of\s+age)\b', '', resume)
    _exp          = _extract_experience_years(resume_raw, resume)
    years_exp     = _exp['years']
    exp_years_raw = _exp['explicit_raw']   # kept for exp_years_detected field below

    education_score, education_level = 0, 'none detected'
    if re.search(r"\bmaster'?s?\b|\bmaster of\b|\bm\.s\.c\b|\bm\.sc\b", resume):
        education_score, education_level = 1.0, 'master'
    elif _has_bachelor_degree(resume, resume_raw):
        education_score, education_level = 0.7, 'bachelor'
    elif re.search(r"\bassociate'?s?\b|\bassociate of\b|\bassociate degree\b", resume):
        education_score, education_level = 0.5, 'associate'

    cert_score, cert_level = 0, 'none detected'
    if re.search(r'\bcertificat\w*\b|\blicens\w*\b|\bregistered\s+(?:professional|psychometrician|nurse|counselor|criminologist|social\s+worker)\b|\bboard\s+passer\b', resume):
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
    r'^(EDUCATION|EXPERIENCE|JOB\s+EXPERIENCE|SKILLS|PROJECTS?|CERTIFICATIONS?|SUMMARY|OBJECTIVE'
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