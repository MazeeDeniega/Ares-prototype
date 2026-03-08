from flask import Flask, request, jsonify
import re
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity
from sentence_transformers import SentenceTransformer
import spacy

app = Flask(__name__)

# SEMANTIC MODEL (lightweight)
model = SentenceTransformer('all-MiniLM-L6-v2')

# # Load spacy model
# try:
#     nlp = spacy.load("en_core_web_trf")
# except:
#     nlp = spacy.load("en_core_web_sm")


# def extract_name(text):
#     doc = nlp(text)
#     for ent in doc.ents:
#         if ent.label_ == "PERSON":
#             return ent.text
#     lines = text.splitlines()
#     for line in lines:
#         if len(line.strip()) > 2:
#             return line.strip()
#     return "Unknown Candidate"


# SKILL LIST (Display Only)
SKILLS = [
    'python','java','laravel','php','sql','javascript','html','css','mysql',
    'linux','windows','network','database','troubleshoot',
    'security','system','it support','web','server','c++','c','mac'
]

# EDUCATION HIERARCHY
EDUCATION_LEVELS = {
    'phd': 4,
    'doctorate': 4,
    'master': 3,
    'masters': 3,
    'bachelor': 2,
    'bs': 2,
    'ba': 2,
    'associate': 1,
    'high_school': 1,
    'hs': 1
}

# EXPERIENCE LEVELS
EXPERIENCE_LEVELS = {
    'intern': 0,
    'entry_level': 0,
    'junior': 1,
    'mid_level': 2,
    'senior': 3,
    'lead': 4,
    'principal': 5,
    'executive': 6
}

# OVERQUALIFICATION KEYWORDS
OVERQUAL_KEYWORDS = [
    'overqualified', 'over-qualified', 'experienced', 'senior',
    'excessive', 'too much', 'exceeds requirements', 'entry level',
    'junior', 'not overqualified', 'no overqualification', 'no experience required'
]

# EXPERIENCE KEYWORDS
EXPERIENCE_KEYWORDS = {
    'intern': ['intern', 'internship', 'no experience', 'entry level', 'fresher', '0 years'],
    'entry_level': ['entry level', 'junior', '1-2 years', '0-2 years', 'beginner'],
    'mid_level': ['mid level', 'mid-level', '3-5 years', '3-5 years experience', 'experienced'],
    'senior': ['senior', 'senior level', '5-8 years', '5+ years', 'senior level'],
    'lead': ['lead', 'team lead', '8-10 years', 'lead position', 'management'],
    'principal': ['principal', 'architect', '10+ years', 'expert', 'principal level'],
    'executive': ['executive', 'director', 'vp', 'c-level', '15+ years']
}

# NORMALIZATION
def normalize_text(text):
    text = text.lower()
    replacements = {
        "databases": "database",
        "networking": "network",
        "networks": "network",
        "troubleshooting": "troubleshoot",
        "operating systems": "linux windows",
        "information technology": "it",
        "web development": "web"
    }
    for k, v in replacements.items():
        text = text.replace(k, v)
    return text


# TF-IDF SIMILARITY (Keyword)
def compute_tfidf_similarity(resume_text, job_text):
    vectorizer = TfidfVectorizer(stop_words='english')
    tfidf_matrix = vectorizer.fit_transform([resume_text, job_text])
    similarity = cosine_similarity(tfidf_matrix[0:1], tfidf_matrix[1:2])[0][0]
    return round(float(similarity), 3)


# SEMANTIC SIMILARITY (Meaning-based)
def compute_semantic_similarity(resume_text, job_text):
    embeddings = model.encode([resume_text, job_text])
    similarity = cosine_similarity([embeddings[0]], [embeddings[1]])[0][0]
    return round(float(similarity), 3)


# EDUCATION LEVEL DETECTION
def detect_education_level(text):
    text = text.lower()
    if any(word in text for word in ['phd', 'doctorate']):
        return 'phd'
    elif any(word in text for word in ['master', 'masters']):
        return 'master'
    elif any(word in text for word in ['bachelor', 'bs', 'ba']):
        return 'bachelor'
    elif any(word in text for word in ['associate']):
        return 'associate'
    elif any(word in text for word in ['high school', 'hs']):
        return 'high_school'
    else:
        return 'any'


# EXPERIENCE LEVEL DETECTION FROM JOB DESCRIPTION
def detect_experience_requirement(job_text):
    job_lower = job_text.lower()
    for level, keywords in EXPERIENCE_KEYWORDS.items():
        if any(keyword in job_lower for keyword in keywords):
            return level
    years_match = re.search(r'(\d+)\s*[-+]\s*(\d+)\s*years?', job_lower)
    if years_match:
        min_years = int(years_match.group(1))
        if min_years == 0:
            return 'intern'
        elif min_years <= 2:
            return 'entry_level'
        elif min_years <= 5:
            return 'mid_level'
        elif min_years <= 8:
            return 'senior'
        elif min_years <= 10:
            return 'lead'
        else:
            return 'principal'
    years_match = re.search(r'(\d+)\s*years?', job_lower)
    if years_match:
        years = int(years_match.group(1))
        if years == 0:
            return 'intern'
        elif years <= 2:
            return 'entry_level'
        elif years <= 5:
            return 'mid_level'
        elif years <= 8:
            return 'senior'
        elif years <= 10:
            return 'lead'
        else:
            return 'principal'
    return 'entry_level'


# EXPERIENCE LEVEL DETECTION FROM RESUME
def detect_experience_level(resume_text):
    resume_lower = resume_text.lower()
    years = re.findall(r'(\d+)\s+years?', resume_lower)
    years_exp = max(map(int, years)) if years else 0
    if any(word in resume_lower for word in ['intern', 'internship']):
        return 'intern'
    if years_exp == 0:
        return 'entry_level'
    elif years_exp <= 2:
        return 'entry_level'
    elif years_exp <= 5:
        return 'mid_level'
    elif years_exp <= 8:
        return 'senior'
    elif years_exp <= 10:
        return 'lead'
    else:
        return 'principal'


# OVERQUALIFICATION DETECTION
def detect_overqualification_concerns(job_text):
    job_lower = job_text.lower()
    return any(keyword in job_lower for keyword in OVERQUAL_KEYWORDS)


# EDUCATION SCORING (Job-Dependent)
def calculate_education_score(resume_text, job_text):
    candidate_level = detect_education_level(resume_text)
    job_level = detect_education_level(job_text)
    candidate_numeric = EDUCATION_LEVELS.get(candidate_level, 0)
    job_numeric = EDUCATION_LEVELS.get(job_level, 0)
    base_scores = {0: 0, 1: 40, 2: 70, 3: 90, 4: 100}
    base_score = base_scores.get(candidate_numeric, 0)
    meets_requirement = candidate_numeric >= job_numeric
    is_overqualified = candidate_numeric > job_numeric
    is_underqualified = candidate_numeric < job_numeric
    feedback = []
    if is_overqualified:
        level_diff = candidate_numeric - job_numeric
        feedback.append(f"Candidate is overqualified by {level_diff} level(s)")
        if detect_overqualification_concerns(job_text):
            feedback.append("Job description mentions overqualification concerns")
    if is_underqualified:
        level_diff = job_numeric - candidate_numeric
        feedback.append(f"Candidate is underqualified by {level_diff} level(s)")
    if not is_overqualified and not is_underqualified:
        feedback.append("Education level matches job requirements")
    return {
        'score': base_score,
        'base_score': base_score,
        'is_overqualified': is_overqualified,
        'is_underqualified': is_underqualified,
        'meets_requirement': meets_requirement,
        'detected_job_level': job_level,
        'detected_candidate_level': candidate_level,
        'feedback': feedback
    }


# EXPERIENCE SCORING (Job-Dependent)
def calculate_experience_score(resume_text, job_text):
    candidate_level = detect_experience_level(resume_text)
    job_level = detect_experience_requirement(job_text)
    candidate_numeric = EXPERIENCE_LEVELS.get(candidate_level, 0)
    job_numeric = EXPERIENCE_LEVELS.get(job_level, 0)
    base_scores = {0: 50, 1: 70, 2: 85, 3: 95, 4: 100, 5: 100, 6: 100}
    base_score = base_scores.get(candidate_numeric, 50)
    meets_requirement = candidate_numeric >= job_numeric
    is_overqualified = candidate_numeric > job_numeric
    is_underqualified = candidate_numeric < job_numeric
    feedback = []
    if is_overqualified:
        level_diff = candidate_numeric - job_numeric
        feedback.append(f"Candidate is overqualified by {level_diff} level(s)")
        if detect_overqualification_concerns(job_text):
            feedback.append("Job description mentions overqualification concerns")
    if is_underqualified:
        level_diff = job_numeric - candidate_numeric
        feedback.append(f"Candidate is underqualified by {level_diff} level(s)")
    if not is_overqualified and not is_underqualified:
        feedback.append("Experience level matches job requirements")
    return {
        'score': base_score,
        'base_score': base_score,
        'is_overqualified': is_overqualified,
        'is_underqualified': is_underqualified,
        'meets_requirement': meets_requirement,
        'detected_job_level': job_level,
        'detected_candidate_level': candidate_level,
        'feedback': feedback
    }


# LAYOUT ANALYSIS (Categorized)
def classify_layout(text_raw):
    text = text_raw.lower()
    words = text.split()
    lines = text_raw.splitlines()
    total_lines = len(lines)
    word_count = len(words)
    feedback = {}
    formatting_score = 0
    if not any(c in text for c in ['#', 'color', 'rgb']):
        formatting_score += 0.3
    if len(set([l.strip()[:10] for l in lines if l.strip()])) > 5:
        formatting_score += 0.3
    if total_lines > 20:
        formatting_score += 0.3
    formatting_score = round(min(formatting_score, 1), 3)
    language_score = 0
    action_verbs = ['led', 'managed', 'developed', 'created', 'implemented', 'achieved']
    if any(v in text for v in action_verbs):
        language_score += 0.4
    formal_words = ['professional', 'experience', 'expertise', 'qualified']
    if any(w in text for w in formal_words):
        language_score += 0.3
    if word_count > 100:
        language_score += 0.3
    language_score = round(min(language_score, 1), 3)
    conciseness_score = 0
    if 350 <= word_count <= 900:
        conciseness_score += 0.4
    elif word_count < 250:
        feedback.setdefault("concise", []).append("Resume may be too short.")
    elif word_count > 1100:
        feedback.setdefault("concise", []).append("Resume may be too long.")
    if total_lines <= 25:
        conciseness_score += 0.3
    conciseness_score = round(min(conciseness_score, 1), 3)
    org_score = 0
    sections = ['experience', 'education', 'skills', 'projects', 'summary']
    section_hits = sum([1 for s in sections if s in text])
    if section_hits >= 3:
        org_score += 0.4
    if section_hits >= 4:
        org_score += 0.3
    org_score = round(min(org_score, 1), 3)
    return {
        "formatting_score": formatting_score,
        "language_score": language_score,
        "conciseness_score": conciseness_score,
        "organization_score": org_score,
        "layout_feedback": feedback
    }

@app.route('/analyze', methods=['POST'])
def analyze():
    data = request.get_json()
    resume_raw = data['resume']
    job_raw = data['job']
    resume = normalize_text(resume_raw)
    job = normalize_text(job_raw)
    
    # TF-IDF
    tfidf_score = compute_tfidf_similarity(resume, job)
    
    # Semantic
    semantic_score = compute_semantic_similarity(resume, job)
    
    # Combined
    combined_similarity = round((0.4 * tfidf_score) + (0.6 * semantic_score), 3)
    
    # Skills
    matched_skills = [s for s in SKILLS if s in resume]
    
    # Years
    years = re.findall(r'(\d+)\s+years?', resume)
    years_exp = max(map(int, years)) if years else 0
    if "project" in resume and years_exp == 0:
        years_exp = 1
    
    # Education
    education_result = calculate_education_score(resume, job)
    
    # Experience
    experience_result = calculate_experience_score(resume, job)
    
    # Certification
    cert_score = 0
    if 'certification' in resume or 'certified' in resume:
        cert_score = 1.0
    elif 'training' in resume:
        cert_score = 0.5
    
    # Layout
    layout_data = classify_layout(resume_raw)
    
    # # Name
    # candidate_name = extract_name(resume_raw)
    

    
    # Return JSON response
    return jsonify({
        # "candidate_name": candidate_name,
        "tfidf_similarity": tfidf_score,
        "semantic_similarity": semantic_score,
        "combined_similarity": combined_similarity,
        "matched_skills": matched_skills,
        "years_experience": years_exp,
        "education": education_result,
        "experience": experience_result,
        "certification_score": cert_score,
        "layout": layout_data
    })


@app.route('/health', methods=['GET'])
def health():
    return jsonify({"status": "healthy", "version": "1.0.0"})


if __name__ == '__main__':
    app.run(port=5000)