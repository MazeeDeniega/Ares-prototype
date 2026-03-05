from flask import Flask, request, jsonify
import re
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity
from sentence_transformers import SentenceTransformer
import spacy

app = Flask(__name__)

# SEMANTIC MODEL (lightweight)
model = SentenceTransformer('all-MiniLM-L6-v2')


# Load spacy model
try:
    nlp = spacy.load("en_core_web_trf")
except:
    nlp = spacy.load("en_core_web_sm")


def extract_name(text):
    doc = nlp(text)
    for ent in doc.ents:
        if ent.label_ == "PERSON":
            return ent.text
    # fallback
    lines = text.splitlines()
    for line in lines:
        if len(line.strip()) > 2:
            return line.strip()
    return "Unknown Candidate"

# SKILL LIST (Display Only)
SKILLS = [
    'python','java','laravel','php','sql','javascript','html','css','mysql',
    'linux','windows','network','database','troubleshoot',
    'security','system','it support','web','server','c++','c','mac'
]

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

    similarity = cosine_similarity(
        tfidf_matrix[0:1],
        tfidf_matrix[1:2]
    )[0][0]

    return round(float(similarity), 3)


# SEMANTIC SIMILARITY (Meaning-based)
def compute_semantic_similarity(resume_text, job_text):
    embeddings = model.encode([resume_text, job_text])
    similarity = cosine_similarity([embeddings[0]], [embeddings[1]])[0][0]
    return round(float(similarity), 3)


# LAYOUT ANALYSIS (Categorized)
def classify_layout(text_raw):
    text = text_raw.lower()
    words = text.split()
    lines = text_raw.splitlines()
    total_lines = len(lines)
    word_count = len(words)

    feedback = {}

    # 1. FORMATTING AND VISUALS
    formatting_score = 0
    # Check for black and white (no color codes)
    if not any(c in text for c in ['#', 'color', 'rgb']):
        formatting_score += 0.3
    # Check font consistency (simple check for repeated patterns)
    if len(set([l.strip()[:10] for l in lines if l.strip()])) > 5:
        formatting_score += 0.3
    # Check spacing
    if total_lines > 20:
        formatting_score += 0.3
    formatting_score = round(min(formatting_score, 1), 3)

    # 2. LANGUAGE QUALITY
    language_score = 0
    # Check for action verbs
    action_verbs = ['led', 'managed', 'developed', 'created', 'implemented', 'achieved']
    if any(v in text for v in action_verbs):
        language_score += 0.4
    # Check for formal words
    formal_words = ['professional', 'experience', 'expertise', 'qualified']
    if any(w in text for w in formal_words):
        language_score += 0.3
    # Check for typos (simple check)
    if word_count > 100:
        language_score += 0.3
    language_score = round(min(language_score, 1), 3)

    # 3. CONCISENESS
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

    # 4. ORGANIZATION AND STRUCTURE
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

    # HYBRID SCORING
    tfidf_score = compute_tfidf_similarity(resume, job)
    semantic_score = compute_semantic_similarity(resume, job)

    # Combine (tune weights as needed)
    combined_similarity = round((0.4 * tfidf_score) + (0.6 * semantic_score), 3)

    # SIMPLE SKILL EXTRACTION (display only)
    matched_skills = [s for s in SKILLS if s in resume]

    # EXPERIENCE
    years = re.findall(r'(\d+)\s+years?', resume)
    years_exp = max(map(int, years)) if years else 0

    if "project" in resume and years_exp == 0:
        years_exp = 1

    # EDUCATION
    education_score = 0
    if 'master' in resume:
        education_score = 1.0
    elif 'bachelor' in resume:
        education_score = 0.7
    elif 'associate' in resume:
        education_score = 0.5

    # CERTIFICATION
    cert_score = 0
    if 'certification' in resume or 'certified' in resume:
        cert_score = 1.0
    elif 'training' in resume:
        cert_score = 0.5

    # LAYOUT
    layout_data = classify_layout(resume_raw)

    candidate_name = extract_name(resume_raw)

    return jsonify({
        "candidate_name": candidate_name,
        "tfidf_similarity": tfidf_score,
        "semantic_similarity": semantic_score,
        "combined_similarity": combined_similarity,
        "matched_skills": matched_skills,
        "years_experience": years_exp,
        "education_score": education_score,
        "certification_score": cert_score,
        "layout": layout_data
    })


if __name__ == '__main__':
    app.run(port=5000)