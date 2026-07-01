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

# ---------------------------------------------------------------------------
# EXPERIENCE EXTRACTION AND CALCULATION
# ---------------------------------------------------------------------------

def extract_experience_section(resume_text: str) -> str:
    """Isolates the text strictly under the Experience/Employment sections."""
    lines = resume_text.splitlines()
    
    # Mirrors the heading logic from your existing classify_layout function
    heading_pattern = re.compile(
        r'^(EDUCATION|EXPERIENCE|SKILLS|PROJECTS?|CERTIFICATIONS?|SUMMARY|OBJECTIVE'
        r'|TRAINING|WORK HISTORY|EMPLOYMENT|PROFESSIONAL|TECHNICAL|RELEVANT'
        r'|INTERNSHIP|AWARDS?|HONORS?|ACTIVITIES|REFERENCES?|PUBLICATIONS?|LANGUAGES?)',
        re.IGNORECASE
    )
    
    exp_pattern = re.compile(r'^(EXPERIENCE|WORK HISTORY|EMPLOYMENT|PROFESSIONAL|INTERNSHIP)', re.IGNORECASE)
    
    in_experience_section = False
    experience_lines = []
    
    for line in lines:
        stripped = line.strip()
        if not stripped and not in_experience_section:
            continue
            
        # Identify if the current line is a section heading
        is_heading = False
        if heading_pattern.match(stripped):
            is_heading = True
        # Catch all-caps fallback headings without numbers/emails
        elif stripped.isupper() and 2 <= len(stripped.split()) <= 5 and not re.search(r'[\d@]', stripped):
            is_heading = True
            
        if is_heading:
            # Is it the experience section?
            if exp_pattern.match(stripped) or "EXPERIENCE" in stripped.upper() or "EMPLOYMENT" in stripped.upper():
                in_experience_section = True
                experience_lines.append(line)
            else:
                # If we are already in the experience section and hit a NEW heading, stop parsing.
                if in_experience_section:
                    break
        else:
            if in_experience_section:
                experience_lines.append(line)
                
    extracted = '\n'.join(experience_lines).strip()
    
    # Fallback: if no valid headings were detected (e.g. flattened PDF), return full text
    return extracted if extracted else resume_text

def _parse_date(date_str: str):
    """Converts a date string (e.g., 'Jan 2020', '12/2019', '2018', 'Present') to a datetime object."""
    date_str = date_str.lower().strip()
    if date_str in ('present', 'current', 'now'):
        return datetime.datetime.now() # <--- Updated here

    # Match Month Year (e.g., Jan 2020, October 2018)
    match = re.search(r'(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\s+(\d{4})', date_str)
    if match:
        month_str, year = match.groups()
        month_map = {'jan':1, 'feb':2, 'mar':3, 'apr':4, 'may':5, 'jun':6, 
                     'jul':7, 'aug':8, 'sep':9, 'oct':10, 'nov':11, 'dec':12}
        return datetime.datetime(int(year), month_map[month_str], 1) # <--- Updated here

    # Match MM/YYYY (e.g., 05/2019)
    match = re.search(r'(\d{1,2})/(\d{4})', date_str)
    if match:
        m, y = match.groups()
        if 1 <= int(m) <= 12:
            return datetime.datetime(int(y), int(m), 1) # <--- Updated here

    # Match YYYY (e.g., 2018)
    match = re.search(r'^(\d{4})$', date_str)
    if match:
        return datetime.datetime(int(match.group(1)), 1, 1) # <--- Updated here

    return None

def calculate_experience_from_dates(resume_text: str) -> float:
    """Parses date ranges, merges overlapping timelines, and returns total years."""
    date_regex = r'((?:(?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\s+\d{4})|(?:\d{1,2}/\d{4})|\b(?:19|20)\d{2}\b)'
    separator = r'\s*(?:-|–|—|to)\s*'
    end_regex = r'((?:(?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\s+\d{4})|(?:\d{1,2}/\d{4})|\b(?:19|20)\d{2}\b|present|current|now)'

    pattern = re.compile(date_regex + separator + end_regex, re.IGNORECASE)
    matches = pattern.findall(resume_text)

    intervals = []
    current_year = datetime.datetime.now().year # <--- Updated here

    for start_str, end_str in matches:
        start_date = _parse_date(start_str)
        end_date = _parse_date(end_str)

        if start_date and end_date and start_date <= end_date:
            if 1960 <= start_date.year <= current_year and 1960 <= end_date.year <= current_year + 1:
                intervals.append((start_date, end_date))

    if not intervals:
        return 0.0

    intervals.sort(key=lambda x: x[0])
    merged = [intervals[0]]

    for current_start, current_end in intervals[1:]:
        last_start, last_end = merged[-1]
        if current_start <= last_end:
            merged[-1] = (last_start, max(last_end, current_end))
        else:
            merged.append((current_start, current_end))

    total_days = sum((end - start).days for start, end in merged)
    return round(total_days / 365.25, 1)

def extract_explicit_experience(resume_text: str) -> float:
    """Extracts explicit mentions of experience strictly bound to context (e.g., 'five years of experience')."""
    word_to_num = {'one': 1, 'two': 2, 'three': 3, 'four': 4, 'five': 5, 
                   'six': 6, 'seven': 7, 'eight': 8, 'nine': 9, 'ten': 10}

    # Strictly looks for "[Digit/Word] years of [professional] experience"
    pattern = re.compile(
        r'(?:(\d+)|(one|two|three|four|five|six|seven|eight|nine|ten))\+?\s*(?:yrs|years?)\s*(?:of)?\s*(?:professional|industry)?\s*experience', 
        re.IGNORECASE
    )
    matches = pattern.findall(resume_text)

    max_years = 0.0
    for digit_match, word_match in matches:
        val = int(digit_match) if digit_match else word_to_num.get(word_match.lower(), 0)
        # Cap at 40 years to ignore crazy outliers
        if 0 < val <= 40:
            max_years = max(max_years, float(val))

    return max_years

def get_total_years_experience(resume_text: str) -> float:
    """
    Returns the higher of calculated timeline experience (strictly from the Experience section) 
    vs explicit summary experience (searched globally).
    """
    exp_section_text = extract_experience_section(resume_text)
    
    # 1. Parse dates ONLY from the Experience section (ignores Education/Projects)
    calc_years = calculate_experience_from_dates(exp_section_text)
    
    # 2. Parse explicit "X years of experience" from the FULL text (to catch the Summary block)
    explicit_years = extract_explicit_experience(resume_text)
    
    return max(calc_years, explicit_years)


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

    # Replaced flawed regex with comprehensive date parsing and contextual fallback
    years_exp            = get_total_years_experience(resume_raw) 
    
    # Cleaned up exp_years_detected for backward compatibility with your JSON output
    exp_years_detected   = [int(years_exp)] if years_exp > 0 else []

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
        'exp_years_detected':    exp_years_detected,
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