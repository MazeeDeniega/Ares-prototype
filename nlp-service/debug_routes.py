"""
debug_routes.py
---------------
Flask Blueprint for the ARES NLP debug panel.
Registered in nlp_api.py — not imported directly.

Routes:
  GET  /          → interactive debug UI (paste text)
  POST /debug     → JSON endpoint used by the UI
  GET  /debug     → PDF upload debug form
  POST /debug/analyse → PDF upload endpoint
"""

import os
import re
import tempfile

from flask import Blueprint, jsonify, request

# Core functions imported from the main module at registration time.
# We use a lazy reference via the blueprint's app context to avoid
# circular imports.
debug_bp = Blueprint('debug', __name__)


def _core():
    """Return the nlp_api module so we can call its functions."""
    import nlp_api
    return nlp_api


# ---------------------------------------------------------------------------
# Helpers shared across debug routes
# ---------------------------------------------------------------------------
_BULLET_CHARS = ('•', '-', '*', '–', '·', '\uf0a7', '\uf0b7', '\uf0d8', '\uf0fc')

_ACTION_VERBS = [
    'managed', 'led', 'developed', 'designed', 'implemented', 'coordinated',
    'achieved', 'improved', 'created', 'built', 'analyzed', 'delivered',
    'executed', 'established', 'maintained', 'supported', 'resolved',
    'collaborated', 'spearheaded', 'optimized', 'streamlined', 'launched',
    'trained', 'mentored', 'oversaw', 'directed', 'produced', 'increased',
]

_HEADING_RE = re.compile(
    r'^(EDUCATION|EXPERIENCE|SKILLS|PROJECTS?|CERTIFICATIONS?|SUMMARY|OBJECTIVE'
    r'|TRAINING|WORK HISTORY|EMPLOYMENT|PROFESSIONAL|TECHNICAL|RELEVANT'
    r'|AWARDS?|HONORS?|ACTIVITIES|REFERENCES?|PUBLICATIONS?|LANGUAGES?)',
    re.IGNORECASE,
)


def _extract_debug_meta(resume_raw: str, page_count) -> dict:
    """Return the metadata dict shown in the debug panel."""
    lines       = resume_raw.splitlines()
    text        = resume_raw.lower()
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
    return {
        'word_count':         len(text.split()),
        'page_count':         page_count,
        'blank_ratio':        round(len(blank_lines) / max(len(lines), 1), 3),
        'bullet_line_count':  len(bullet_lines),
        'heading_line_count': len(heading_lines),
        'action_verbs_found': found_verbs,
    }


def _score_resume(resume_raw: str, job_raw: str, page_count,
                  kw: int = 40, sem: int = 60) -> dict:
    """Run full scoring and return a flat result dict."""
    m = _core()

    resume = m.normalize_text(resume_raw)
    job    = m.normalize_text(job_raw)

    total_blend    = (kw + sem) or 100
    tfidf_score    = m.compute_tfidf_similarity(resume, job)
    semantic_score = m.compute_semantic_similarity(resume, job)
    combined       = round(
        (tfidf_score * kw / total_blend) + (semantic_score * sem / total_blend), 3
    )
    matched_skills = m.match_skills(resume, job)

    years     = re.findall(r'(\d+)\s+years?', resume)
    years_exp = max(map(int, years)) if years else 0
    if 'project' in resume and years_exp == 0:
        years_exp = 1

    education_score, education_level = 0, 'none detected'
    if 'master'    in resume: education_score, education_level = 1.0, 'master'
    elif 'bachelor' in resume: education_score, education_level = 0.7, 'bachelor'
    elif 'associate' in resume: education_score, education_level = 0.5, 'associate'

    cert_score, cert_level = 0, 'none detected'
    if 'certification' in resume or 'certified' in resume:
        cert_score, cert_level = 1.0, 'certified'
    elif 'training' in resume:
        cert_score, cert_level = 0.5, 'training'

    layout = m.classify_layout(resume_raw, page_count)

    qual_score = round((
        combined      * 0.35 +
        min(years_exp, 5) / 5 * 0.20 +
        education_score * 0.25 +
        cert_score      * 0.10
    ) * 100, 2)

    return {
        'candidate_name':      m.extract_name(resume_raw),
        'tfidf_similarity':    tfidf_score,
        'semantic_similarity': semantic_score,
        'combined_similarity': combined,
        'matched_skills':      matched_skills,
        'years_experience':    years_exp,
        'education_score':     education_score,
        'certification_score': cert_score,
        'qualifications_score': qual_score,
        'presentation_score':  layout['presentation_score'],
        'formatting_score':    layout['formatting_score'],
        'language_score':      layout['language_score'],
        'concise_score':       layout['concise_score'],
        'organization_score':  layout['organization_score'],
        'layout_feedback':     layout['layout_feedback'],
        '_meta': {
            'education_level': education_level,
            'cert_level':      cert_level,
        },
    }


# ---------------------------------------------------------------------------
# Route: GET /  →  paste-text debug UI
# ---------------------------------------------------------------------------
@debug_bp.route('/', methods=['GET'])
def debug_ui():
    return '''<!DOCTYPE html>
<html>
<head>
<title>ARES NLP Debug</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; background: #f3f4f6; padding: 24px; }
  h1 { font-size: 1.4em; margin-bottom: 20px; color: #111; }
  .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  .card { background: white; border-radius: 8px; padding: 16px; box-shadow: 0 1px 3px #0001; }
  .card h2 { font-size: 0.85em; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; margin-bottom: 10px; }
  textarea { width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 10px; font-size: 0.85em; resize: vertical; font-family: monospace; }
  button { margin-top: 12px; padding: 10px 24px; background: #2563eb; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 0.95em; width: 100%; }
  button:hover { background: #1d4ed8; }
  button:disabled { background: #93c5fd; cursor: not-allowed; }
  .section { margin-top: 16px; }
  .section h3 { font-size: 0.9em; font-weight: bold; color: #374151; margin-bottom: 8px; padding-bottom: 4px; border-bottom: 1px solid #e5e7eb; }
  .score-row { display: flex; align-items: center; gap: 10px; margin: 6px 0; }
  .score-label { width: 160px; font-size: 0.85em; color: #4b5563; flex-shrink: 0; }
  .bar-bg { flex: 1; background: #e5e7eb; border-radius: 4px; height: 10px; }
  .bar-fill { height: 10px; border-radius: 4px; background: #2563eb; transition: width .4s; }
  .score-val { width: 42px; text-align: right; font-size: 0.85em; font-weight: bold; color: #111; }
  .tag-list { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 4px; }
  .tag { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; padding: 2px 8px; border-radius: 12px; font-size: 0.78em; }
  .kv { display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid #f3f4f6; font-size: 0.85em; }
  .kv span:last-child { font-weight: bold; color: #111; }
  .fb-list { list-style: none; }
  .fb-list li { font-size: 0.82em; color: #b45309; padding: 3px 0 3px 14px; position: relative; }
  .fb-list li:before { content: "⚠"; position: absolute; left: 0; }
  #status { font-size: 0.82em; color: #6b7280; margin-top: 8px; min-height: 1.2em; }
  .spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid #93c5fd; border-top-color: #2563eb; border-radius: 50%; animation: spin .7s linear infinite; vertical-align: middle; margin-right: 6px; }
  @keyframes spin { to { transform: rotate(360deg); } }
  #results { display: none; }
  .nav { margin-bottom: 16px; font-size: 0.85em; }
  .nav a { color: #2563eb; text-decoration: none; margin-right: 16px; }
  .nav a:hover { text-decoration: underline; }
</style>
</head>
<body>
<h1>🔍 ARES NLP Debug</h1>
<div class="nav">
  <a href="/">📋 Paste Text</a>
  <a href="/debug">📄 Upload PDF</a>
</div>
<div class="grid">
  <div class="card">
    <h2>Resume Text</h2>
    <textarea id="resume" rows="18" placeholder="Paste extracted resume text here..."></textarea>
  </div>
  <div class="card">
    <h2>Job Description (optional)</h2>
    <textarea id="job" rows="18" placeholder="Paste job description here..."></textarea>
  </div>
</div>
<div class="card" style="margin-top:16px">
  <button id="btn" onclick="analyze()">Analyze</button>
  <div id="status"></div>
</div>

<div id="results">
  <div class="grid" style="margin-top:16px">
    <div class="card">
      <h2>Qualifications</h2>
      <div class="section" id="no-job-notice" style="display:none">
        <p style="font-size:0.82em;color:#6b7280;font-style:italic">No job description — similarity scores not available.</p>
      </div>
      <div class="section" id="similarity-section">
        <div class="score-row"><span class="score-label">TF-IDF Similarity</span><div class="bar-bg"><div class="bar-fill" id="b-tfidf"></div></div><span class="score-val" id="v-tfidf"></span></div>
        <div class="score-row"><span class="score-label">Semantic Similarity</span><div class="bar-bg"><div class="bar-fill" id="b-sem"></div></div><span class="score-val" id="v-sem"></span></div>
        <div class="score-row"><span class="score-label">Combined</span><div class="bar-bg"><div class="bar-fill" id="b-comb"></div></div><span class="score-val" id="v-comb"></span></div>
      </div>
      <div class="section">
        <h3>Sub-scores</h3>
        <div class="score-row"><span class="score-label">Education</span><div class="bar-bg"><div class="bar-fill" id="b-edu"></div></div><span class="score-val" id="v-edu"></span></div>
        <div class="score-row"><span class="score-label">Certification</span><div class="bar-bg"><div class="bar-fill" id="b-cert"></div></div><span class="score-val" id="v-cert"></span></div>
        <div class="score-row"><span class="score-label">Experience</span><div class="bar-bg"><div class="bar-fill" id="b-exp"></div></div><span class="score-val" id="v-exp"></span></div>
      </div>
      <div class="section">
        <h3>Extracted</h3>
        <div class="kv"><span>Candidate Name</span><span id="v-name"></span></div>
        <div class="kv"><span>Years Experience</span><span id="v-yrs"></span></div>
        <div class="kv"><span>Education Level</span><span id="v-edlvl"></span></div>
        <div class="kv"><span>Certification</span><span id="v-certlvl"></span></div>
        <h3 style="margin-top:10px">Matched Skills</h3>
        <div class="tag-list" id="v-skills"></div>
      </div>
    </div>

    <div class="card">
      <h2>Presentation Quality</h2>
      <div class="section">
        <div class="score-row"><span class="score-label"><strong>Overall</strong></span><div class="bar-bg"><div class="bar-fill" id="b-pres"></div></div><span class="score-val" id="v-pres"></span></div>
        <div style="padding-left:12px;border-left:3px solid #e5e7eb;margin-left:6px;margin-top:4px">
          <div class="score-row"><span class="score-label" style="color:#6b7280">Formatting</span><div class="bar-bg"><div class="bar-fill" id="b-fmt"></div></div><span class="score-val" id="v-fmt"></span></div>
          <div class="score-row"><span class="score-label" style="color:#6b7280">Language</span><div class="bar-bg"><div class="bar-fill" id="b-lang"></div></div><span class="score-val" id="v-lang"></span></div>
          <div class="score-row"><span class="score-label" style="color:#6b7280">Conciseness</span><div class="bar-bg"><div class="bar-fill" id="b-conc"></div></div><span class="score-val" id="v-conc"></span></div>
          <div class="score-row"><span class="score-label" style="color:#6b7280">Organization</span><div class="bar-bg"><div class="bar-fill" id="b-org"></div></div><span class="score-val" id="v-org"></span></div>
        </div>
      </div>
      <div class="section">
        <h3>Extracted Metadata</h3>
        <div class="kv"><span>Word Count</span><span id="v-wc"></span></div>
        <div class="kv"><span>Page Count</span><span id="v-pc"></span></div>
        <div class="kv"><span>Blank Line Ratio</span><span id="v-blr"></span></div>
        <div class="kv"><span>Bullet Lines</span><span id="v-bl"></span></div>
        <div class="kv"><span>Heading Lines</span><span id="v-hl"></span></div>
        <div class="kv"><span>Action Verbs Found</span><span id="v-av"></span></div>
      </div>
      <div class="section">
        <h3>Feedback</h3>
        <ul class="fb-list" id="v-feedback"></ul>
      </div>
    </div>
  </div>
</div>

<script>
async function analyze() {
  const resume = document.getElementById('resume').value.trim();
  const job    = document.getElementById('job').value.trim();
  if (!resume) { alert('Paste resume text first.'); return; }

  const btn = document.getElementById('btn');
  const status = document.getElementById('status');
  btn.disabled = true;
  status.innerHTML = '<span class="spinner"></span>Analyzing...';
  document.getElementById('results').style.display = 'none';

  try {
    const res = await fetch('/debug', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ resume, job })
    });
    const d = await res.json();
    if (d.error) { status.textContent = 'Error: ' + d.error; return; }

    const pct = v => Math.round(v * 100);
    const bar = (id, v) => {
      document.getElementById('b-' + id).style.width = pct(v) + '%';
      document.getElementById('v-' + id).textContent = pct(v);
    };

    const hasJob = job.length > 0;
    document.getElementById('similarity-section').style.display = hasJob ? '' : 'none';
    document.getElementById('no-job-notice').style.display      = hasJob ? 'none' : '';
    if (hasJob) {
      bar('tfidf', d.tfidf_similarity);
      bar('sem',   d.semantic_similarity);
      bar('comb',  d.combined_similarity);
    }

    bar('edu',  d.education_score);
    bar('cert', d.certification_score);
    bar('exp',  Math.min(d.years_experience, 5) / 5);
    bar('pres', d.presentation_score);
    bar('fmt',  d.formatting_score);
    bar('lang', d.language_score);
    bar('conc', d.concise_score);
    bar('org',  d.organization_score);

    const dbg = d.debug;
    document.getElementById('v-name').textContent  = d.candidate_name || '—';
    document.getElementById('v-yrs').textContent   = d.years_experience + ' yr(s)';
    document.getElementById('v-edlvl').textContent = dbg.education_level || '—';
    document.getElementById('v-certlvl').textContent = dbg.cert_level || '—';
    document.getElementById('v-wc').textContent    = dbg.word_count;
    document.getElementById('v-pc').textContent    = dbg.page_count ?? 'not provided';
    document.getElementById('v-blr').textContent   = (dbg.blank_ratio * 100).toFixed(1) + '%';
    document.getElementById('v-bl').textContent    = dbg.bullet_line_count;
    document.getElementById('v-hl').textContent    = dbg.heading_line_count;
    document.getElementById('v-av').textContent    = dbg.action_verbs_found.join(', ') || '—';

    document.getElementById('v-skills').innerHTML = d.matched_skills.length
      ? d.matched_skills.map(s => `<span class="tag">${s}</span>`).join('')
      : '<span style="color:#9ca3af;font-size:.82em">None matched</span>';

    const all = Object.values(d.layout_feedback).flat();
    document.getElementById('v-feedback').innerHTML = all.length
      ? all.map(f => `<li>${f}</li>`).join('')
      : '<li style="color:#16a34a;list-style:none">✓ No presentation issues detected</li>';

    document.getElementById('results').style.display = 'block';
    status.textContent = 'Done.';
  } catch(e) {
    status.textContent = 'Request failed: ' + e.message;
  } finally {
    btn.disabled = false;
  }
}
</script>
</body>
</html>'''


# ---------------------------------------------------------------------------
# Route: POST /debug  →  JSON endpoint used by the paste-text UI
# ---------------------------------------------------------------------------
@debug_bp.route('/debug', methods=['POST'])
def debug_analyze():
    try:
        data       = request.get_json()
        resume_raw = data['resume']
        job_raw    = data.get('job', '')
        page_count = data.get('page_count', None)
        kw         = int(data.get('keyword_weight',  40))
        sem        = int(data.get('semantic_weight', 60))

        result = _score_resume(resume_raw, job_raw, page_count, kw, sem)
        meta   = _extract_debug_meta(resume_raw, page_count)

        return jsonify({
            **result,
            'debug': {
                **meta,
                'education_level': result['_meta']['education_level'],
                'cert_level':      result['_meta']['cert_level'],
            },
        })

    except Exception as e:
        return jsonify({'error': str(e)})


# ---------------------------------------------------------------------------
# Route: GET /debug  →  PDF upload form
# ---------------------------------------------------------------------------
@debug_bp.route('/debug', methods=['GET'])
def debug_form():
    return '''<!DOCTYPE html>
<html>
<head>
<title>ARES NLP Debug — Upload PDF</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; background: #f3f4f6; padding: 24px; max-width: 960px; margin: 0 auto; }
  h1 { font-size: 1.4em; margin-bottom: 20px; color: #111; }
  .nav { margin-bottom: 16px; font-size: 0.85em; }
  .nav a { color: #2563eb; text-decoration: none; margin-right: 16px; }
  .nav a:hover { text-decoration: underline; }
  .card { background: white; border-radius: 8px; padding: 16px; box-shadow: 0 1px 3px #0001; margin-bottom: 16px; }
  .card h2 { font-size: 0.85em; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; margin-bottom: 12px; }
  label { display: block; font-size: 0.85em; color: #374151; margin-bottom: 4px; font-weight: bold; }
  input[type=file], textarea { width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 8px; font-size: 0.85em; margin-bottom: 12px; }
  textarea { resize: vertical; font-family: monospace; }
  button { padding: 10px 24px; background: #2563eb; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 0.95em; }
  button:hover { background: #1d4ed8; }
  button:disabled { background: #93c5fd; }
  .score-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-top: 8px; }
  .score-box { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 14px; text-align: center; }
  .score-box .val { font-size: 2em; font-weight: bold; color: #1e40af; }
  .score-box .lbl { font-size: 0.78em; color: #555; margin-top: 4px; }
  .bar-wrap { margin: 6px 0; }
  .bar-bg { background: #e5e7eb; border-radius: 4px; height: 10px; }
  .bar-fill { height: 10px; border-radius: 4px; background: #2563eb; }
  .bar-label { display: flex; justify-content: space-between; font-size: 0.8em; color: #6b7280; }
  .sub { padding-left: 12px; border-left: 3px solid #e5e7eb; margin-left: 6px; margin-top: 4px; }
  .kv { display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid #f3f4f6; font-size: 0.85em; }
  .kv span:last-child { font-weight: bold; }
  .tag { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; padding: 2px 8px; border-radius: 12px; font-size: 0.78em; margin: 2px; display: inline-block; }
  .fb-list li { font-size: 0.82em; color: #b45309; padding: 3px 0 3px 14px; position: relative; list-style: none; }
  .fb-list li:before { content: "⚠"; position: absolute; left: 0; }
  .extracted { background: #1e1e1e; color: #d4d4d4; font-family: monospace; font-size: 0.78em; padding: 12px; border-radius: 6px; white-space: pre-wrap; max-height: 300px; overflow-y: auto; margin-top: 8px; }
  #status { font-size: 0.82em; color: #6b7280; margin-top: 8px; }
  .spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid #93c5fd; border-top-color: #2563eb; border-radius: 50%; animation: spin .7s linear infinite; vertical-align: middle; margin-right: 6px; }
  @keyframes spin { to { transform: rotate(360deg); } }
  #results { display: none; }
</style>
</head>
<body>
<h1>🔍 ARES NLP Debug</h1>
<div class="nav">
  <a href="/">📋 Paste Text</a>
  <a href="/debug">📄 Upload PDF</a>
</div>

<div class="card">
  <h2>Upload Resume PDF</h2>
  <label>Resume PDF</label>
  <input type="file" id="pdfFile" accept="application/pdf">
  <label>Job Description (optional)</label>
  <textarea id="jobDesc" rows="5" placeholder="Paste job description here..."></textarea>
  <button id="btn" onclick="upload()">Analyse</button>
  <div id="status"></div>
</div>

<div id="results">
  <div class="card">
    <h2>Scores Overview</h2>
    <div class="score-grid">
      <div class="score-box"><div class="val" id="s-qual">—</div><div class="lbl">Qualifications /100</div></div>
      <div class="score-box"><div class="val" id="s-pres">—</div><div class="lbl">Presentation /100</div></div>
    </div>
  </div>

  <div class="card">
    <h2>Qualifications Detail</h2>
    <div id="sim-section">
      <div class="bar-label"><span>TF-IDF Similarity</span><span id="l-tfidf"></span></div>
      <div class="bar-wrap"><div class="bar-bg"><div class="bar-fill" id="b-tfidf"></div></div></div>
      <div class="bar-label"><span>Semantic Similarity</span><span id="l-sem"></span></div>
      <div class="bar-wrap"><div class="bar-bg"><div class="bar-fill" id="b-sem"></div></div></div>
      <div class="bar-label"><span>Combined</span><span id="l-comb"></span></div>
      <div class="bar-wrap"><div class="bar-bg"><div class="bar-fill" id="b-comb"></div></div></div>
    </div>
    <div id="no-job" style="display:none;font-size:0.82em;color:#6b7280;font-style:italic;margin-bottom:8px">No job description — similarity scores not available.</div>
    <div class="bar-label" style="margin-top:8px"><span>Education</span><span id="l-edu"></span></div>
    <div class="bar-wrap"><div class="bar-bg"><div class="bar-fill" id="b-edu"></div></div></div>
    <div class="bar-label"><span>Certification</span><span id="l-cert"></span></div>
    <div class="bar-wrap"><div class="bar-bg"><div class="bar-fill" id="b-cert"></div></div></div>
    <div class="bar-label"><span>Experience</span><span id="l-exp"></span></div>
    <div class="bar-wrap"><div class="bar-bg"><div class="bar-fill" id="b-exp"></div></div></div>
    <div style="margin-top:12px">
      <div class="kv"><span>Candidate</span><span id="v-name"></span></div>
      <div class="kv"><span>Education Level</span><span id="v-edu"></span></div>
      <div class="kv"><span>Cert Level</span><span id="v-cert"></span></div>
      <div class="kv"><span>Years Experience</span><span id="v-exp"></span></div>
    </div>
    <div style="margin-top:8px"><strong style="font-size:0.85em">Matched Skills</strong><br><div id="v-skills" style="margin-top:6px"></div></div>
  </div>

  <div class="card">
    <h2>Presentation Detail</h2>
    <div class="bar-label"><span><strong>Overall</strong></span><span id="l-pres"></span></div>
    <div class="bar-wrap"><div class="bar-bg"><div class="bar-fill" id="b-pres"></div></div></div>
    <div class="sub">
      <div class="bar-label"><span>Formatting</span><span id="l-fmt"></span></div>
      <div class="bar-wrap"><div class="bar-bg"><div class="bar-fill" id="b-fmt"></div></div></div>
      <div class="bar-label"><span>Language</span><span id="l-lang"></span></div>
      <div class="bar-wrap"><div class="bar-bg"><div class="bar-fill" id="b-lang"></div></div></div>
      <div class="bar-label"><span>Conciseness</span><span id="l-conc"></span></div>
      <div class="bar-wrap"><div class="bar-bg"><div class="bar-fill" id="b-conc"></div></div></div>
      <div class="bar-label"><span>Organization</span><span id="l-org"></span></div>
      <div class="bar-wrap"><div class="bar-bg"><div class="bar-fill" id="b-org"></div></div></div>
    </div>
    <div style="margin-top:12px">
      <div class="kv"><span>Word Count</span><span id="v-wc"></span></div>
      <div class="kv"><span>Page Count</span><span id="v-pc"></span></div>
      <div class="kv"><span>Blank Line Ratio</span><span id="v-blr"></span></div>
      <div class="kv"><span>Bullet Lines</span><span id="v-bl"></span></div>
      <div class="kv"><span>Heading Lines</span><span id="v-hl"></span></div>
      <div class="kv"><span>Action Verbs</span><span id="v-av"></span></div>
    </div>
    <div style="margin-top:10px"><strong style="font-size:0.85em">Feedback</strong><ul class="fb-list" id="v-feedback" style="margin-top:4px"></ul></div>
  </div>

  <div class="card">
    <h2>Extracted Text</h2>
    <div class="extracted" id="v-text"></div>
  </div>
</div>

<script>
async function upload() {
  const file = document.getElementById('pdfFile').files[0];
  if (!file) { alert('Select a PDF first.'); return; }
  const btn = document.getElementById('btn');
  btn.disabled = true;
  document.getElementById('status').innerHTML = '<span class="spinner"></span>Analysing...';
  document.getElementById('results').style.display = 'none';

  const fd = new FormData();
  fd.append('pdf', file);
  fd.append('job', document.getElementById('jobDesc').value.trim());

  try {
    const res = await fetch('/debug/analyse', { method: 'POST', body: fd });
    const d   = await res.json();
    if (d.error) { document.getElementById('status').textContent = 'Error: ' + d.error; return; }

    const pct = v => Math.round(v * 100);
    const bar = (id, v) => {
      const p = pct(v);
      document.getElementById('b-' + id).style.width = p + '%';
      if (document.getElementById('l-' + id))
        document.getElementById('l-' + id).textContent = p + '/100';
    };

    document.getElementById('s-qual').textContent = Math.round(d.qualifications_score);
    document.getElementById('s-pres').textContent = pct(d.presentation_score);

    const hasJob = document.getElementById('jobDesc').value.trim().length > 0;
    document.getElementById('sim-section').style.display = hasJob ? '' : 'none';
    document.getElementById('no-job').style.display      = hasJob ? 'none' : '';
    if (hasJob) { bar('tfidf', d.tfidf_similarity); bar('sem', d.semantic_similarity); bar('comb', d.combined_similarity); }

    bar('edu', d.education_score); bar('cert', d.certification_score);
    bar('exp', Math.min(d.years_experience, 5) / 5);
    bar('pres', d.presentation_score); bar('fmt', d.formatting_score);
    bar('lang', d.language_score); bar('conc', d.concise_score); bar('org', d.organization_score);

    document.getElementById('v-name').textContent = d.candidate_name || '—';
    document.getElementById('v-edu').textContent  = d.debug.education_level || '—';
    document.getElementById('v-cert').textContent = d.debug.cert_level || '—';
    document.getElementById('v-exp').textContent  = d.years_experience + ' yr(s)';
    document.getElementById('v-wc').textContent   = d.debug.word_count;
    document.getElementById('v-pc').textContent   = d.debug.page_count ?? '—';
    document.getElementById('v-blr').textContent  = (d.debug.blank_ratio * 100).toFixed(1) + '%';
    document.getElementById('v-bl').textContent   = d.debug.bullet_line_count;
    document.getElementById('v-hl').textContent   = d.debug.heading_line_count;
    document.getElementById('v-av').textContent   = (d.debug.action_verbs_found || []).join(', ') || '—';

    document.getElementById('v-skills').innerHTML = (d.matched_skills || []).length
      ? d.matched_skills.map(s => `<span class="tag">${s}</span>`).join('')
      : '<span style="color:#9ca3af;font-size:.82em">None matched</span>';

    const all = Object.values(d.layout_feedback || {}).flat();
    document.getElementById('v-feedback').innerHTML = all.length
      ? all.map(f => `<li>${f}</li>`).join('')
      : '<li style="color:#16a34a">✓ No issues detected</li>';

    document.getElementById('v-text').textContent = d.extracted_text || '';
    document.getElementById('results').style.display = 'block';
    document.getElementById('status').textContent = 'Done.';
  } catch(e) {
    document.getElementById('status').textContent = 'Request failed: ' + e.message;
  } finally {
    btn.disabled = false;
  }
}
</script>
</body>
</html>'''


# ---------------------------------------------------------------------------
# Route: POST /debug/analyse  →  PDF upload endpoint
# ---------------------------------------------------------------------------
@debug_bp.route('/debug/analyse', methods=['POST'])
def debug_analyse():
    try:
        from pdfminer.high_level import extract_text

        pdf_file = request.files.get('pdf')
        job_text = request.form.get('job', '')
        kw       = int(request.form.get('keyword_weight',  40))
        sem      = int(request.form.get('semantic_weight', 60))

        if not pdf_file:
            return jsonify({'error': 'No PDF uploaded'})

        with tempfile.NamedTemporaryFile(suffix='.pdf', delete=False) as tmp:
            pdf_file.save(tmp.name)
            tmp_path = tmp.name

        try:
            extracted_text = extract_text(tmp_path)
            with open(tmp_path, 'rb') as f:
                raw = f.read()
            page_matches = re.findall(rb'/Type\s*/Page[^s]', raw)
            page_count   = max(len(page_matches), 1)
        finally:
            os.unlink(tmp_path)

        result = _score_resume(extracted_text, job_text, page_count, kw, sem)
        meta   = _extract_debug_meta(extracted_text, page_count)

        return jsonify({
            **result,
            'extracted_text': extracted_text,
            'debug': {
                **meta,
                'education_level': result['_meta']['education_level'],
                'cert_level':      result['_meta']['cert_level'],
            },
        })

    except Exception as e:
        return jsonify({'error': str(e)})