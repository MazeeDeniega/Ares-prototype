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


# LAYOUT ANALYSIS (unchanged)
def classify_layout(text_raw):

    text = text_raw.lower()
    words = text.split()
    word_count = len(words)
    lines = text_raw.splitlines()
    total_lines = len(lines)

    feedback = {}

    # CONCISE
    concise_score = 0

    if 350 <= word_count <= 900:
        concise_score += 0.4
    elif word_count < 250:
        feedback.setdefault("concise", []).append("Resume may be too short.")
    elif word_count > 1100:
        feedback.setdefault("concise", []).append("Resume may be too long.")

    sentences = re.split(r'[.!?]', text)
    sentence_lengths = [len(s.split()) for s in sentences if len(s.split()) > 3]
    avg_sentence_length = sum(sentence_lengths) / max(len(sentence_lengths), 1)

    if avg_sentence_length <= 22:
        concise_score += 0.3
    else:
        feedback.setdefault("concise", []).append("Sentences are lengthy.")

    word_freq = {}
    for w in words:
        if len(w) > 4:
            word_freq[w] = word_freq.get(w, 0) + 1

    repeated_words = [w for w, c in word_freq.items() if c > 8]

    if not repeated_words:
        concise_score += 0.3
    else:
        feedback.setdefault("concise", []).append("Excessive repetition detected.")

    concise_score = round(min(concise_score, 1), 3)

    # CLEAN
    clean_score = 0

    if total_lines > 25:
        clean_score += 0.4
    else:
        feedback.setdefault("clean", []).append("Layout appears compressed.")

    bullet_lines = [l for l in lines if l.strip().startswith(("-", "•", "*"))]
    bullet_ratio = len(bullet_lines) / max(total_lines, 1)

    if bullet_ratio >= 0.15:
        clean_score += 0.3
    else:
        feedback.setdefault("clean", []).append("Limited bullet usage.")

    long_blocks = 0
    block = 0
    for l in lines:
        if l.strip() == "":
            block = 0
        else:
            block += 1
            if block > 8:
                long_blocks += 1

    if long_blocks == 0:
        clean_score += 0.3
    else:
        feedback.setdefault("clean", []).append("Large text blocks detected.")

    clean_score = round(min(clean_score, 1), 3)

    # PROFESSIONAL
    professional_score = 0

    sections = ['experience', 'education', 'skills', 'projects', 'certification']
    section_hits = sum([1 for s in sections if s in text])

    if section_hits >= 3:
        professional_score += 0.4
    else:
        feedback.setdefault("professional", []).append("Missing key sections.")

    section_lines = [l for l in lines if l.strip().lower() in sections]
    if len(section_lines) >= 2:
        professional_score += 0.3
    else:
        feedback.setdefault("professional", []).append("Sections not clearly separated.")

    bullet_symbols = set([l.strip()[0] for l in bullet_lines if l.strip()])
    if len(bullet_symbols) <= 2:
        professional_score += 0.3
    else:
        feedback.setdefault("professional", []).append("Inconsistent formatting.")

    professional_score = round(min(professional_score, 1), 3)

    return {
        "concise_score": concise_score,
        "clean_score": clean_score,
        "professional_score": professional_score,
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