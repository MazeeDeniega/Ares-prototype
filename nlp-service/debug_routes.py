"""
debug_routes.py
---------------
Flask Blueprint for the ARES NLP debug panel.
Registered in nlp_api.py — not imported directly.

Routes:
  GET  /              → interactive debug UI (paste text)
  POST /debug         → JSON endpoint used by the UI
  GET  /debug         → PDF upload debug form
  POST /debug/analyse → PDF upload endpoint
"""

import os
import re
import tempfile

from flask import Blueprint, jsonify, request

debug_bp = Blueprint('debug', __name__)


def _core():
    import sys, os
    here = os.path.dirname(os.path.abspath(__file__))
    if here not in sys.path:
        sys.path.insert(0, here)
    import nlp_api
    return nlp_api


# ---------------------------------------------------------------------------
# Shared constants
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


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------
def _extract_debug_meta(resume_raw: str, page_count) -> dict:
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

    years_found  = re.findall(r'\b(20\d{2}|19\d{2})\b', resume_raw)
    years_int    = list(map(int, years_found)) if years_found else []

    found_informal      = [m for m in _INFORMAL_MARKERS if m in text]
    content_lines       = [l.strip() for l in lines if len(l.strip().split()) > 2]
    long_lines_count    = sum(1 for l in content_lines if len(l.split()) > 35)
    special_chars_count = len(re.findall(r'[█▓▒░▄▀■□◆◇★☆]', resume_raw))

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
        'special_chars_count':    special_chars_count,
    }


def _score_resume(resume_raw: str, job_raw: str, page_count,
                  kw: int = 40, sem: int = 60) -> dict:
    m = _core()

    resume = m.normalize_text(resume_raw)
    job    = m.normalize_text(job_raw)

    total_blend    = (kw + sem) or 100
    tfidf_score    = m.compute_tfidf_similarity(resume, job)
    semantic_score = m.compute_semantic_similarity(resume, job)
    combined       = round(
        (tfidf_score * kw / total_blend) + (semantic_score * sem / total_blend), 3
    )

    matched_skills       = m.match_skills(resume, job)
    job_skills_extracted = m.extract_skills_from_job(job) if job.strip() else []
    skill_gap            = [s for s in job_skills_extracted if s not in resume]

    exp_years_raw = re.findall(r'(\d+)\s+years?', resume)
    years_exp     = max(map(int, exp_years_raw)) if exp_years_raw else 0
    if 'project' in resume and years_exp == 0:
        years_exp = 1

    education_score, education_level = 0, 'none detected'
    if 'master'      in resume: education_score, education_level = 1.0, 'master'
    elif 'bachelor'  in resume: education_score, education_level = 0.7, 'bachelor'
    elif 'associate' in resume: education_score, education_level = 0.5, 'associate'

    cert_score, cert_level = 0, 'none detected'
    if 'certification' in resume or 'certified' in resume:
        cert_score, cert_level = 1.0, 'certified'
    elif 'training' in resume:
        cert_score, cert_level = 0.5, 'training'

    layout = m.classify_layout(resume_raw, page_count)

    sim_contrib  = round(combined              * 0.35 * 100, 2)
    exp_contrib  = round(min(years_exp, 5) / 5 * 0.20 * 100, 2)
    edu_contrib  = round(education_score       * 0.25 * 100, 2)
    cert_contrib = round(cert_score            * 0.10 * 100, 2)
    qual_score   = round(sim_contrib + exp_contrib + edu_contrib + cert_contrib, 2)

    return {
        'candidate_name':       m.extract_name(resume_raw),
        'tfidf_similarity':     tfidf_score,
        'semantic_similarity':  semantic_score,
        'combined_similarity':  combined,
        'matched_skills':       matched_skills,
        'skill_gap':            skill_gap,
        'job_skills_extracted': job_skills_extracted,
        'years_experience':     years_exp,
        'exp_years_detected':   list(map(int, exp_years_raw)),
        'education_score':      education_score,
        'certification_score':  cert_score,
        'qualifications_score': qual_score,
        'score_breakdown': {
            'similarity':    sim_contrib,
            'experience':    exp_contrib,
            'education':     edu_contrib,
            'certification': cert_contrib,
        },
        'presentation_score':  layout['presentation_score'],
        'formatting_score':    layout['formatting_score'],
        'language_score':      layout['language_score'],
        'concise_score':       layout['concise_score'],
        'organization_score':  layout['organization_score'],
        'layout_feedback':     layout['layout_feedback'],
        'resume_normalized':   resume,
        '_meta': {
            'education_level': education_level,
            'cert_level':      cert_level,
        },
    }


# ---------------------------------------------------------------------------
# Shared CSS + JS snippets reused by both UIs
# ---------------------------------------------------------------------------
_SHARED_CSS = '''
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; background: #f3f4f6; padding: 24px; }
  h1   { font-size: 1.4em; margin-bottom: 20px; color: #111; }

  .nav { margin-bottom: 16px; font-size: 0.85em; }
  .nav a { color: #2563eb; text-decoration: none; margin-right: 16px; }
  .nav a:hover { text-decoration: underline; }

  /* Layout */
  .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  .card    { background: white; border-radius: 8px; padding: 16px;
             box-shadow: 0 1px 3px #0001; margin-top: 16px; }
  .card-header { font-size: 0.78em; text-transform: uppercase; letter-spacing: .06em;
                 color: #6b7280; margin-bottom: 12px; font-weight: bold; }

  /* Section grouping inside a card */
  .section       { margin-top: 14px; }
  .section-title { font-size: 0.82em; font-weight: bold; color: #111; margin-bottom: 6px;
                   padding-bottom: 4px; border-bottom: 2px solid #e5e7eb; display: flex;
                   align-items: baseline; gap: 6px; }
  .section-title .contrib { font-size: 0.85em; font-weight: normal; color: #2563eb; margin-left: auto; }
  .section-title .weight  { font-size: 0.8em;  font-weight: normal; color: #9ca3af; }

  /* Progress bars */
  .bar-row   { display: flex; align-items: center; gap: 8px; margin: 5px 0; }
  .bar-lbl   { font-size: 0.82em; color: #4b5563; flex-shrink: 0; width: 150px; }
  .bar-lbl.sub { color: #9ca3af; padding-left: 12px; }
  .bar-bg    { flex: 1; background: #e5e7eb; border-radius: 4px; height: 9px; }
  .bar-fill  { height: 9px; border-radius: 4px; background: #2563eb; transition: width .4s; }
  .bar-fill.muted { background: #93c5fd; }
  .bar-val   { font-size: 0.82em; font-weight: bold; color: #111; width: 30px; text-align: right; }

  /* Key-value rows */
  .kv       { display: flex; justify-content: space-between; padding: 4px 0;
              border-bottom: 1px solid #f3f4f6; font-size: 0.82em; }
  .kv span:last-child { font-weight: bold; color: #111; }
  .kv-sub   { display: flex; justify-content: space-between; padding: 3px 0 3px 14px;
              border-bottom: 1px dashed #f3f4f6; font-size: 0.80em; color: #6b7280; }
  .kv-sub span:last-child { color: #2563eb; font-weight: bold; }
  .kv-total { display: flex; justify-content: space-between; padding: 6px 0 3px;
              border-top: 2px solid #e5e7eb; font-size: 0.83em; font-weight: bold; }

  /* Tags */
  .tags     { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 5px; }
  .tag      { padding: 2px 8px; border-radius: 12px; font-size: 0.76em; }
  .tag-blue { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }
  .tag-red  { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; }
  .tag-gray { background:#f9fafb; color:#6b7280; border:1px solid #e5e7eb; }

  /* Badges */
  .badge       { display:inline-flex; align-items:center; gap:3px; padding:2px 8px;
                 border-radius:12px; font-size:0.76em; font-weight:bold; }
  .badge-green { background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; }
  .badge-red   { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; }

  /* Feedback list */
  .fb-list    { list-style:none; margin-top:4px; }
  .fb-list li { font-size:0.81em; color:#b45309; padding:3px 0 3px 14px; position:relative; }
  .fb-list li:before { content:"⚠"; position:absolute; left:0; }

  /* Misc */
  .none-msg    { font-size:0.81em; color:#9ca3af; }
  .ok-msg      { font-size:0.81em; color:#16a34a; }
  .inline-row  { display:flex; gap:6px; flex-wrap:wrap; margin-top:4px; }
  .small-gray  { font-size:0.79em; color:#6b7280; margin-top:3px; }

  /* Normalize preview */
  .norm-toggle { font-size:0.82em; color:#2563eb; cursor:pointer;
                 user-select:none; font-weight:normal; text-transform:none;
                 letter-spacing:0; }
  .norm-toggle:hover { text-decoration:underline; }
  .norm-text   { display:none; background:#1e1e1e; color:#d4d4d4; font-family:monospace;
                 font-size:0.76em; padding:10px; border-radius:6px; white-space:pre-wrap;
                 max-height:220px; overflow-y:auto; margin-top:8px; }

  /* Extracted text (PDF page) */
  .extracted   { background:#1e1e1e; color:#d4d4d4; font-family:monospace; font-size:0.76em;
                 padding:12px; border-radius:6px; white-space:pre-wrap;
                 max-height:300px; overflow-y:auto; margin-top:8px; }

  /* Button / status */
  textarea  { width:100%; border:1px solid #d1d5db; border-radius:6px; padding:10px;
              font-size:0.85em; resize:vertical; font-family:monospace; }
  button    { margin-top:12px; padding:10px 24px; background:#2563eb; color:white;
              border:none; border-radius:6px; cursor:pointer; font-size:0.95em; width:100%; }
  button:hover    { background:#1d4ed8; }
  button:disabled { background:#93c5fd; cursor:not-allowed; }
  #status   { font-size:0.82em; color:#6b7280; margin-top:8px; min-height:1.2em; }
  .spinner  { display:inline-block; width:14px; height:14px; border:2px solid #93c5fd;
              border-top-color:#2563eb; border-radius:50%;
              animation:spin .7s linear infinite; vertical-align:middle; margin-right:6px; }
  @keyframes spin { to { transform: rotate(360deg); } }
  #results  { display:none; }
  input[type=file] { width:100%; border:1px solid #d1d5db; border-radius:6px;
                     padding:8px; font-size:0.85em; margin-bottom:12px; }
  label { display:block; font-size:0.85em; color:#374151; margin-bottom:4px; font-weight:bold; }

  /* Score overview boxes (PDF page) */
  .score-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:12px; margin-top:8px; }
  .score-box  { background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px;
                padding:14px; text-align:center; }
  .score-box .val { font-size:2em; font-weight:bold; color:#1e40af; }
  .score-box .lbl { font-size:0.78em; color:#555; margin-top:4px; }
'''

_SHARED_JS = '''
function toggleNorm(id) {
  const el = document.getElementById(id || 'v-norm');
  const btn = document.getElementById('norm-btn');
  if (el.style.display === 'block') {
    el.style.display = 'none';
    if (btn) btn.textContent = '▶ show';
  } else {
    el.style.display = 'block';
    if (btn) btn.textContent = '▼ hide';
  }
}

function renderResults(d, hasJob) {
  const el  = id => document.getElementById(id);
  const pct = v  => Math.round(v * 100);

  const bar = (id, v, muted) => {
    const p = pct(v);
    const fill = el('b-' + id);
    if (fill) { fill.style.width = p + '%'; if (muted) fill.classList.add('muted'); }
    const val = el('v-' + id);
    if (val) val.textContent = p;
  };

  const tags = (id, arr, cls, empty) => {
    const c = el(id);
    if (!c) return;
    c.innerHTML = arr && arr.length
      ? arr.map(s => `<span class="tag ${cls}">${s}</span>`).join('')
      : `<span class="${empty.startsWith('✓') ? 'ok-msg' : 'none-msg'}">${empty}</span>`;
  };

  // ── Candidate name + qualifications total ──────────────────────────────
  if (el('v-name'))  el('v-name').textContent  = d.candidate_name || '—';
  if (el('v-qual-total')) el('v-qual-total').textContent = d.qualifications_score ?? '—';

  // ── 1. Job Match ────────────────────────────────────────────────────────
  const jmSection = el('jm-section');
  if (jmSection) jmSection.style.display = hasJob ? '' : 'none';
  const noJobMsg = el('no-job-msg');
  if (noJobMsg) noJobMsg.style.display = hasJob ? 'none' : '';

  if (hasJob) {
    bar('comb',  d.combined_similarity);
    bar('tfidf', d.tfidf_similarity,  true);
    bar('sem',   d.semantic_similarity, true);
    if (el('v-sb-sim')) el('v-sb-sim').textContent = d.score_breakdown?.similarity ?? '—';
  }

  tags('v-skills',    d.matched_skills       || [], 'tag-blue', 'None matched');
  tags('v-skill-gap', d.skill_gap            || [], 'tag-red',  hasJob ? '✓ No missing skills' : '—');
  tags('v-job-skills',d.job_skills_extracted || [], 'tag-gray', 'None found');
  if (el('v-skills-count'))  el('v-skills-count').textContent  = '(' + (d.matched_skills?.length  || 0) + ')';
  if (el('v-gap-count'))     el('v-gap-count').textContent     = '(' + (d.skill_gap?.length        || 0) + ')';
  if (el('v-jskills-count')) el('v-jskills-count').textContent = '(' + (d.job_skills_extracted?.length || 0) + ')';
  const gapWrap = el('gap-wrap');
  const jsWrap  = el('js-wrap');
  if (gapWrap) gapWrap.style.display = hasJob ? '' : 'none';
  if (jsWrap)  jsWrap.style.display  = hasJob ? '' : 'none';

  // ── 2. Experience ───────────────────────────────────────────────────────
  bar('exp', Math.min(d.years_experience, 5) / 5);
  if (el('v-yrs'))    el('v-yrs').textContent    = d.years_experience + ' yr(s)';
  if (el('v-expyrs')) el('v-expyrs').textContent = (d.exp_years_detected || []).join(', ') || 'none found';
  if (el('v-sb-exp')) el('v-sb-exp').textContent = d.score_breakdown?.experience ?? '—';

  // ── 3. Education ────────────────────────────────────────────────────────
  bar('edu', d.education_score);
  if (el('v-edlvl'))  el('v-edlvl').textContent  = d.debug?.education_level || '—';
  if (el('v-sb-edu')) el('v-sb-edu').textContent = d.score_breakdown?.education ?? '—';

  // ── 4. Certification ────────────────────────────────────────────────────
  bar('cert', d.certification_score);
  if (el('v-certlvl'))  el('v-certlvl').textContent  = d.debug?.cert_level || '—';
  if (el('v-sb-cert'))  el('v-sb-cert').textContent  = d.score_breakdown?.certification ?? '—';

  // ── Presentation ────────────────────────────────────────────────────────
  bar('pres', d.presentation_score);
  bar('fmt',  d.formatting_score);
  bar('lang', d.language_score);
  bar('conc', d.concise_score);
  bar('org',  d.organization_score);

  const dbg = d.debug || {};
  if (el('v-wc'))  el('v-wc').textContent  = dbg.word_count;
  if (el('v-pc'))  el('v-pc').textContent  = dbg.page_count ?? 'not provided';
  if (el('v-blr')) el('v-blr').textContent = ((dbg.blank_ratio || 0) * 100).toFixed(1) + '%';
  if (el('v-bl'))  el('v-bl').textContent  = dbg.bullet_line_count;
  if (el('v-hl'))  el('v-hl').textContent  = dbg.heading_line_count;
  if (el('v-av'))  el('v-av').textContent  = (dbg.action_verbs_found || []).join(', ') || '—';
  if (el('v-ll'))  el('v-ll').textContent  = dbg.long_lines_count;
  if (el('v-sc'))  el('v-sc').textContent  = dbg.special_chars_count;

  // Contact badges
  ['email','phone'].forEach(k => {
    const b = el('v-' + k + '-badge');
    if (!b) return;
    const ok = k === 'email' ? dbg.has_email : dbg.has_phone;
    b.className   = 'badge ' + (ok ? 'badge-green' : 'badge-red');
    b.textContent = (ok ? '✓ ' : '✗ ') + k.charAt(0).toUpperCase() + k.slice(1);
  });

  // Detected sections
  tags('v-sections', dbg.detected_sections || [], 'tag-gray', 'None detected');

  // Date range
  const dr = dbg.date_range || {};
  if (el('v-daterange')) el('v-daterange').textContent = (dr.earliest && dr.latest)
    ? dr.earliest + ' → ' + dr.latest : 'None found';
  if (el('v-datelist')) el('v-datelist').textContent = dr.all?.length
    ? 'All: ' + dr.all.join(', ') : '';

  // Informal markers
  if (el('v-informal')) {
    const inf = dbg.informal_markers_found || [];
    el('v-informal').innerHTML = inf.length
      ? inf.map(m => `<span class="tag tag-red">${m}</span>`).join('')
      : '<span class="ok-msg">✓ None found</span>';
  }

  // Feedback
  if (el('v-feedback')) {
    const all = Object.values(d.layout_feedback || {}).flat();
    el('v-feedback').innerHTML = all.length
      ? all.map(f => `<li>${f}</li>`).join('')
      : '<li class="ok-msg" style="list-style:none">✓ No presentation issues detected</li>';
  }

  // Normalized text
  if (el('v-norm')) el('v-norm').textContent = d.resume_normalized || '';
}
'''


# ---------------------------------------------------------------------------
# Shared HTML blocks for the qualifications and presentation panels
# (used by both the paste-text UI and the PDF UI so they stay in sync)
# ---------------------------------------------------------------------------
def _qual_panel_html():
    return '''
    <div class="card">
      <div class="card-header">Qualifications &ensp; <span id="v-qual-total" style="font-size:1.4em;font-weight:bold;color:#111">—</span><span style="font-size:0.85em;color:#888">/100</span></div>

      <!-- Candidate -->
      <div class="kv" style="margin-bottom:6px"><span>Candidate</span><span id="v-name">—</span></div>

      <!-- NO JOB notice -->
      <div id="no-job-msg" class="small-gray" style="display:none;font-style:italic;margin-bottom:8px">
        No job description — Job Match scores not available.
      </div>

      <!-- 1. JOB MATCH / SKILLS -->
      <div class="section" id="jm-section">
        <div class="section-title">
          1 · Job Match &amp; Skills
          <span class="weight">(×35%)</span>
          <span class="contrib" id="v-sb-sim">—</span>
        </div>

        <!-- Combined score -->
        <div class="bar-row">
          <span class="bar-lbl">Match Score</span>
          <div class="bar-bg"><div class="bar-fill" id="b-comb"></div></div>
          <span class="bar-val" id="v-comb"></span>
        </div>
        <!-- Keyword + Semantic as sub-detail -->
        <div class="bar-row">
          <span class="bar-lbl sub">↳ Keyword (TF-IDF)</span>
          <div class="bar-bg"><div class="bar-fill muted" id="b-tfidf"></div></div>
          <span class="bar-val" id="v-tfidf"></span>
        </div>
        <div class="bar-row">
          <span class="bar-lbl sub">↳ Semantic</span>
          <div class="bar-bg"><div class="bar-fill muted" id="b-sem"></div></div>
          <span class="bar-val" id="v-sem"></span>
        </div>

        <!-- Matched skills -->
        <div style="margin-top:10px;font-size:0.81em;font-weight:bold;color:#374151">
          Matched Skills <span id="v-skills-count" style="font-weight:normal;color:#9ca3af"></span>
        </div>
        <div class="tags" id="v-skills"></div>

        <!-- Skill gap -->
        <div id="gap-wrap">
          <div style="margin-top:8px;font-size:0.81em;font-weight:bold;color:#374151">
            Skill Gap <span id="v-gap-count" style="font-weight:normal;color:#9ca3af"></span>
            <span style="font-weight:normal;color:#9ca3af"> — in job, missing from resume</span>
          </div>
          <div class="tags" id="v-skill-gap"></div>
        </div>

        <!-- Job skills detected -->
        <div id="js-wrap">
          <div style="margin-top:8px;font-size:0.81em;font-weight:bold;color:#374151">
            Job Skills Detected <span id="v-jskills-count" style="font-weight:normal;color:#9ca3af"></span>
          </div>
          <div class="tags" id="v-job-skills"></div>
        </div>
      </div>

      <!-- 2. EXPERIENCE -->
      <div class="section">
        <div class="section-title">
          2 · Experience
          <span class="weight">(×20%)</span>
          <span class="contrib" id="v-sb-exp">—</span>
        </div>
        <div class="bar-row">
          <span class="bar-lbl">Years (capped at 5)</span>
          <div class="bar-bg"><div class="bar-fill" id="b-exp"></div></div>
          <span class="bar-val" id="v-exp"></span>
        </div>
        <div class="kv" style="margin-top:6px"><span>Years Detected</span><span id="v-yrs">—</span></div>
        <div class="kv"><span>Raw Values Found</span><span id="v-expyrs" style="color:#6b7280;font-size:0.9em">—</span></div>
      </div>

      <!-- 3. EDUCATION -->
      <div class="section">
        <div class="section-title">
          3 · Education
          <span class="weight">(×25%)</span>
          <span class="contrib" id="v-sb-edu">—</span>
        </div>
        <div class="bar-row">
          <span class="bar-lbl">Education Score</span>
          <div class="bar-bg"><div class="bar-fill" id="b-edu"></div></div>
          <span class="bar-val" id="v-edu"></span>
        </div>
        <div class="kv" style="margin-top:6px"><span>Level Detected</span><span id="v-edlvl">—</span></div>
      </div>

      <!-- 4. CERTIFICATION -->
      <div class="section">
        <div class="section-title">
          4 · Certification
          <span class="weight">(×10%)</span>
          <span class="contrib" id="v-sb-cert">—</span>
        </div>
        <div class="bar-row">
          <span class="bar-lbl">Certification Score</span>
          <div class="bar-bg"><div class="bar-fill" id="b-cert"></div></div>
          <span class="bar-val" id="v-cert"></span>
        </div>
        <div class="kv" style="margin-top:6px"><span>Level Detected</span><span id="v-certlvl">—</span></div>
      </div>

    </div>
'''


def _pres_panel_html():
    return '''
    <div class="card">
      <div class="card-header">Presentation Quality</div>

      <!-- Overall + sub-scores -->
      <div class="section">
        <div class="section-title">Overall Score</div>
        <div class="bar-row">
          <span class="bar-lbl"><strong>Overall</strong></span>
          <div class="bar-bg"><div class="bar-fill" id="b-pres"></div></div>
          <span class="bar-val" id="v-pres"></span>
        </div>
        <div style="padding-left:14px;border-left:3px solid #e5e7eb;margin:6px 0 0 4px">
          <div class="bar-row">
            <span class="bar-lbl sub">Formatting</span>
            <div class="bar-bg"><div class="bar-fill muted" id="b-fmt"></div></div>
            <span class="bar-val" id="v-fmt"></span>
          </div>
          <div class="bar-row">
            <span class="bar-lbl sub">Language</span>
            <div class="bar-bg"><div class="bar-fill muted" id="b-lang"></div></div>
            <span class="bar-val" id="v-lang"></span>
          </div>
          <div class="bar-row">
            <span class="bar-lbl sub">Conciseness</span>
            <div class="bar-bg"><div class="bar-fill muted" id="b-conc"></div></div>
            <span class="bar-val" id="v-conc"></span>
          </div>
          <div class="bar-row">
            <span class="bar-lbl sub">Organization</span>
            <div class="bar-bg"><div class="bar-fill muted" id="b-org"></div></div>
            <span class="bar-val" id="v-org"></span>
          </div>
        </div>
      </div>

      <!-- Document metadata -->
      <div class="section">
        <div class="section-title">Document Metadata</div>
        <div class="kv"><span>Word Count</span><span id="v-wc">—</span></div>
        <div class="kv"><span>Page Count</span><span id="v-pc">—</span></div>
        <div class="kv"><span>Blank Line Ratio</span><span id="v-blr">—</span></div>
        <div class="kv"><span>Bullet Lines</span><span id="v-bl">—</span></div>
        <div class="kv"><span>Heading Lines</span><span id="v-hl">—</span></div>
        <div class="kv"><span>Long Lines (&gt;35 words)</span><span id="v-ll">—</span></div>
        <div class="kv"><span>Special/Decorative Chars</span><span id="v-sc">—</span></div>
      </div>

      <!-- Contact info -->
      <div class="section">
        <div class="section-title">Contact Info</div>
        <div class="inline-row" style="margin-top:6px">
          <span id="v-email-badge" class="badge">—</span>
          <span id="v-phone-badge" class="badge">—</span>
        </div>
      </div>

      <!-- Structure -->
      <div class="section">
        <div class="section-title">Detected Sections</div>
        <div class="tags" id="v-sections"></div>
      </div>

      <!-- Dates -->
      <div class="section">
        <div class="section-title">Date Range</div>
        <div class="kv"><span>Earliest → Latest</span><span id="v-daterange">—</span></div>
        <div class="small-gray" id="v-datelist"></div>
      </div>

      <!-- Language signals -->
      <div class="section">
        <div class="section-title">Language Signals</div>
        <div class="kv"><span>Action Verbs Found</span><span id="v-av" style="font-size:0.85em;text-align:right;max-width:200px">—</span></div>
        <div style="margin-top:6px;font-size:0.81em;color:#374151;font-weight:bold;margin-bottom:4px">Informal Markers</div>
        <div id="v-informal"></div>
      </div>

      <!-- Feedback -->
      <div class="section">
        <div class="section-title">Feedback</div>
        <ul class="fb-list" id="v-feedback"></ul>
      </div>
    </div>
'''


# ---------------------------------------------------------------------------
# Route: GET /  →  paste-text debug UI
# ---------------------------------------------------------------------------
@debug_bp.route('/', methods=['GET'])
def debug_ui():
    return f'''<!DOCTYPE html>
<html>
<head>
<title>ARES NLP Debug</title>
<style>{_SHARED_CSS}</style>
</head>
<body>
<h1>🔍 ARES NLP Debug</h1>
<div class="nav">
  <a href="/">📋 Paste Text</a>
  <a href="/debug">📄 Upload PDF</a>
</div>

<div class="two-col" style="margin-top:0">
  <div class="card" style="margin-top:0">
    <div class="card-header">Resume Text</div>
    <textarea id="resume" rows="18" placeholder="Paste extracted resume text here..."></textarea>
  </div>
  <div class="card" style="margin-top:0">
    <div class="card-header">Job Description (optional)</div>
    <textarea id="job" rows="18" placeholder="Paste job description here..."></textarea>
  </div>
</div>
<div class="card">
  <button id="btn" onclick="analyze()">Analyze</button>
  <div id="status"></div>
</div>

<div id="results">
  <div class="two-col">
    {_qual_panel_html()}
    {_pres_panel_html()}
  </div>

  <!-- Normalized text preview -->
  <div class="card">
    <div class="card-header">
      Normalized Text Preview &ensp;
      <span class="norm-toggle" id="norm-btn" onclick="toggleNorm('v-norm')">▶ show</span>
    </div>
    <div class="norm-text" id="v-norm"></div>
  </div>
</div>

<script>
{_SHARED_JS}

async function analyze() {{
  const resume = document.getElementById('resume').value.trim();
  const job    = document.getElementById('job').value.trim();
  if (!resume) {{ alert('Paste resume text first.'); return; }}

  const btn = document.getElementById('btn');
  btn.disabled = true;
  document.getElementById('status').innerHTML = '<span class="spinner"></span>Analyzing...';
  document.getElementById('results').style.display = 'none';

  try {{
    const res = await fetch('/debug', {{
      method: 'POST',
      headers: {{ 'Content-Type': 'application/json' }},
      body: JSON.stringify({{ resume, job }})
    }});
    const d = await res.json();
    if (d.error) {{ document.getElementById('status').textContent = 'Error: ' + d.error; return; }}

    renderResults(d, job.length > 0);
    document.getElementById('results').style.display = 'block';
    document.getElementById('status').textContent = 'Done.';
  }} catch(e) {{
    document.getElementById('status').textContent = 'Request failed: ' + e.message;
  }} finally {{
    btn.disabled = false;
  }}
}}
</script>
</body>
</html>'''


# ---------------------------------------------------------------------------
# Route: POST /debug  →  JSON endpoint
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
    return f'''<!DOCTYPE html>
<html>
<head>
<title>ARES NLP Debug — Upload PDF</title>
<style>{_SHARED_CSS}
  body {{ max-width: 1060px; margin: 0 auto; }}
</style>
</head>
<body>
<h1>🔍 ARES NLP Debug</h1>
<div class="nav">
  <a href="/">📋 Paste Text</a>
  <a href="/debug">📄 Upload PDF</a>
</div>

<div class="card" style="margin-top:0">
  <div class="card-header">Upload Resume PDF</div>
  <label>Resume PDF</label>
  <input type="file" id="pdfFile" accept="application/pdf">
  <label>Job Description (optional)</label>
  <textarea id="jobDesc" rows="5" placeholder="Paste job description here..."></textarea>
  <button id="btn" onclick="upload()">Analyse</button>
  <div id="status"></div>
</div>

<div id="results">

  <!-- Score overview -->
  <div class="card">
    <div class="card-header">Scores Overview</div>
    <div class="score-grid">
      <div class="score-box"><div class="val" id="s-qual">—</div><div class="lbl">Qualifications /100</div></div>
      <div class="score-box"><div class="val" id="s-pres">—</div><div class="lbl">Presentation /100</div></div>
    </div>
  </div>

  <div class="two-col">
    {_qual_panel_html()}
    {_pres_panel_html()}
  </div>

  <!-- Extracted text -->
  <div class="card">
    <div class="card-header">Extracted Text</div>
    <div class="extracted" id="v-text"></div>
  </div>

  <!-- Normalized text preview -->
  <div class="card">
    <div class="card-header">
      Normalized Text Preview &ensp;
      <span class="norm-toggle" id="norm-btn" onclick="toggleNorm('v-norm')">▶ show</span>
    </div>
    <div class="norm-text" id="v-norm"></div>
  </div>
</div>

<script>
{_SHARED_JS}

async function upload() {{
  const file = document.getElementById('pdfFile').files[0];
  if (!file) {{ alert('Select a PDF first.'); return; }}
  const btn = document.getElementById('btn');
  btn.disabled = true;
  document.getElementById('status').innerHTML = '<span class="spinner"></span>Analysing...';
  document.getElementById('results').style.display = 'none';

  const fd = new FormData();
  fd.append('pdf', file);
  fd.append('job', document.getElementById('jobDesc').value.trim());

  try {{
    const res = await fetch('/debug/analyse', {{ method: 'POST', body: fd }});
    const d   = await res.json();
    if (d.error) {{ document.getElementById('status').textContent = 'Error: ' + d.error; return; }}

    const pct = v => Math.round(v * 100);
    document.getElementById('s-qual').textContent = Math.round(d.qualifications_score);
    document.getElementById('s-pres').textContent = pct(d.presentation_score);

    const hasJob = document.getElementById('jobDesc').value.trim().length > 0;
    renderResults(d, hasJob);

    const vt = document.getElementById('v-text');
    if (vt) vt.textContent = d.extracted_text || '';

    document.getElementById('results').style.display = 'block';
    document.getElementById('status').textContent = 'Done.';
  }} catch(e) {{
    document.getElementById('status').textContent = 'Request failed: ' + e.message;
  }} finally {{
    btn.disabled = false;
  }}
}}
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