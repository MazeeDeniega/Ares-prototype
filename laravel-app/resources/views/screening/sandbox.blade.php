<!DOCTYPE html>
<html lang="en">
<head>
{{-- <meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"> --}}
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>ARES Sandbox — Laravel Extraction Pipeline</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; background: #f3f4f6; padding: 24px; max-width: 1100px; margin: 0 auto; }
  h1   { font-size: 1.4em; margin-bottom: 4px; color: #111; }
  h1 .sub { display:block; font-size:0.6em; font-weight:normal; color:#6b7280; margin-top:4px; }

  .card    { background: white; border-radius: 8px; padding: 16px;
             box-shadow: 0 1px 3px #0001; margin-top: 16px; }
  .card-header { font-size: 0.78em; text-transform: uppercase; letter-spacing: .06em;
                 color: #6b7280; margin-bottom: 12px; font-weight: bold; }
  .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

  .section       { margin-top: 14px; }
  .section-title { font-size: 0.82em; font-weight: bold; color: #111; margin-bottom: 6px;
                   padding-bottom: 4px; border-bottom: 2px solid #e5e7eb; display: flex;
                   align-items: baseline; gap: 6px; }
  .section-title .weight { font-size: 0.8em; font-weight: normal; color: #9ca3af; }

  .bar-row   { display: flex; align-items: center; gap: 8px; margin: 5px 0; }
  .bar-lbl   { font-size: 0.82em; color: #4b5563; flex-shrink: 0; width: 150px; }
  .bar-lbl.sub { color: #9ca3af; padding-left: 12px; }
  .bar-bg    { flex: 1; background: #e5e7eb; border-radius: 4px; height: 9px; }
  .bar-fill  { height: 9px; border-radius: 4px; background: #2563eb; transition: width .4s; }
  .bar-fill.muted { background: #93c5fd; }
  .bar-val   { font-size: 0.82em; font-weight: bold; color: #111; width: 36px; text-align: right; }

  .kv       { display: flex; justify-content: space-between; padding: 4px 0;
              border-bottom: 1px solid #f3f4f6; font-size: 0.82em; }
  .kv span:last-child { font-weight: bold; color: #111; }

  .tags     { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 5px; }
  .tag      { padding: 2px 8px; border-radius: 12px; font-size: 0.76em; }
  .tag-blue { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }
  .tag-gray { background:#f9fafb; color:#6b7280; border:1px solid #e5e7eb; }

  .badge        { display:inline-flex; align-items:center; gap:4px; padding:4px 12px;
                  border-radius:14px; font-size:0.82em; font-weight:bold; }
  .badge-smalot   { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }
  .badge-cloud_ocr{ background:#f5f3ff; color:#6d28d9; border:1px solid #ddd6fe; }
  .badge-heuristic{ background:#fffbeb; color:#b45309; border:1px solid #fde68a; }
  .badge-all_failed,.badge-error { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; }

  .pill { display:inline-block; font-size:0.8em; color:#374151; background:#f3f4f6;
          border:1px solid #e5e7eb; border-radius:6px; padding:4px 10px; }
  .pill b { color:#111; }
  .inline-row { display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-top:4px; }

  .fb-list    { list-style:none; margin-top:4px; }
  .fb-list li { font-size:0.85em; color:#374151; padding:4px 0 4px 18px; position:relative; }
  .fb-list li:before { content:"›"; position:absolute; left:0; color:#9ca3af; }

  .trace-item { display:flex; align-items:flex-start; gap:8px; padding:7px 0;
                border-bottom:1px solid #f3f4f6; font-size:0.85em; }
  .trace-item:last-child { border-bottom:none; }
  .trace-icon { flex-shrink:0; font-weight:bold; width:16px; text-align:center; }
  .trace-icon.pass { color:#16a34a; }
  .trace-icon.fail { color:#dc2626; }
  .trace-method { font-weight:bold; color:#111; text-transform:lowercase; min-width:90px; flex-shrink:0; }
  .trace-reason { color:#6b7280; }


  .extracted   { background:#1e1e1e; color:#d4d4d4; font-family:monospace; font-size:0.9em;
                 line-height:1.55; padding:14px; border-radius:6px; white-space:pre-wrap;
                 max-height:420px; overflow-y:auto; margin-top:8px; }

  .blob-warn  { font-size:0.85em; padding:10px 12px; border-radius:6px; margin-bottom:10px;
                font-weight:bold; }
  .blob-warn.bad  { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; }
  .blob-warn.good { background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; }

  .score-grid { display:grid; grid-template-columns:repeat(5,1fr); gap:10px; margin-top:8px; }
  .score-box  { background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px;
                padding:12px 6px; text-align:center; }
  .score-box .val { font-size:1.5em; font-weight:bold; color:#1e40af; }
  .score-box .lbl { font-size:0.7em; color:#555; margin-top:4px; }

  .adv-toggle { font-size:0.82em; color:#2563eb; cursor:pointer; user-select:none;
                margin-top:10px; font-weight:bold; }
  .adv-toggle:hover { text-decoration:underline; }
  .adv-grid   { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-top:10px; }
  .adv-grid label { display:block; font-size:0.74em; color:#6b7280; margin-bottom:3px; }
  .adv-grid input { width:100%; padding:5px 6px; border:1px solid #d1d5db; border-radius:5px;
                    font-size:0.82em; }

  .norm-toggle { font-size:0.82em; color:#2563eb; cursor:pointer; user-select:none; font-weight:normal; }
  .norm-toggle:hover { text-decoration:underline; }
  .norm-text   { display:none; background:#1e1e1e; color:#d4d4d4; font-family:monospace;
                 font-size:0.82em; line-height:1.5; padding:14px; border-radius:6px;
                 white-space:pre-wrap; max-height:340px; overflow-y:auto; margin-top:8px; }

  textarea  { width:100%; border:1px solid #d1d5db; border-radius:6px; padding:10px;
              font-size:0.85em; resize:vertical; font-family:monospace; }
  button    { margin-top:14px; padding:10px 24px; background:#2563eb; color:white;
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
</style>
</head>
<body>

<h1>🧪 ARES Sandbox<span class="sub">Production extraction pipeline — Smalot → Cloud OCR (overlay) → Heuristic</span></h1>

<div class="card" style="margin-top:0">
  <div class="card-header">Upload Resume PDF</div>
  <label>Resume PDF</label>
  <input type="file" id="pdfFile" accept="application/pdf">
  <label>Job Description</label>
  <textarea id="jobDesc" rows="5" placeholder="Paste job description here..."></textarea>

  <div class="adv-toggle" id="adv-btn" onclick="toggleAdv()">▶ advanced weights</div>
  <div class="adv-grid" id="adv-grid" style="display:none">
    <div><label>keyword_weight</label><input type="number" id="w-keyword" value="40"></div>
    <div><label>semantic_weight</label><input type="number" id="w-semantic" value="60"></div>
    <div><label>qual_weight</label><input type="number" id="w-qual" value="100"></div>
    <div><label>layout_weight (presentation)</label><input type="number" id="w-layout" value="0"></div>
    <div><label>skills_weight</label><input type="number" id="w-skills" value="35"></div>
    <div><label>experience_weight</label><input type="number" id="w-experience" value="20"></div>
    <div><label>education_weight</label><input type="number" id="w-education" value="25"></div>
    <div><label>cert_weight</label><input type="number" id="w-cert" value="10"></div>
    <div><label>formatting_weight</label><input type="number" id="w-formatting" value="25"></div>
    <div><label>language_weight</label><input type="number" id="w-language" value="25"></div>
    <div><label>concise_weight</label><input type="number" id="w-concise" value="25"></div>
    <div><label>organization_weight</label><input type="number" id="w-organization" value="25"></div>
  </div>
  <div class="small-gray" style="font-size:0.78em;color:#9ca3af;margin-top:4px">
    layout_weight defaults to 0, so final_score = qualifications only. Bump it up if you want
    final_score to actually reflect the presentation score you're testing.
  </div>

  <button id="btn" onclick="analyze()">Run Extraction + Analysis</button>
  <div id="status"></div>
</div>

<div id="results">

  <div class="card">
    <div class="card-header">Extraction Result</div>
    <div class="inline-row">
      <span id="method-badge" class="badge">—</span>
      <span class="pill">Chars: <b id="r-chars">—</b></span>
      <span class="pill">Pages: <b id="r-pages">—</b></span>
      <span class="pill">Latency: <b id="r-latency">—</b> ms</span>
    </div>
  </div>

  <div class="card">
    <div class="card-header">Pipeline Trace — what each tier tried, and why it succeeded/failed</div>
    <ul class="fb-list" id="r-trace" style="list-style:none"></ul>
  </div>

  <div class="card">
    <div class="card-header">Layout / Structure Diagnostics</div>
    <div id="blob-warning"></div>
    <div class="score-grid">
      <div class="score-box"><div class="val" id="t-lines">—</div><div class="lbl">Lines</div></div>
      <div class="score-box"><div class="val" id="t-blank">—</div><div class="lbl">Blank Lines</div></div>
      <div class="score-box"><div class="val" id="t-indent">—</div><div class="lbl">Indented Lines</div></div>
      <div class="score-box"><div class="val" id="t-avglen">—</div><div class="lbl">Avg Line Len</div></div>
      <div class="score-box"><div class="val" id="t-longest">—</div><div class="lbl">Longest Line</div></div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">Extracted Text (raw, verbatim — this is exactly what the scorer sees)</div>
    <div class="extracted" id="r-text"></div>
  </div>

  <div class="two-col">
    <div class="card" style="margin-top:0">
      <div class="card-header">Qualifications &ensp;<span id="r-qual" style="font-size:1.4em;color:#111">—</span><span style="font-size:0.85em;color:#888">/100</span></div>

      <div class="section">
        <div class="section-title">Skills / Job Match <span class="weight">(×skills_weight)</span></div>
        <div style="margin-top:4px;font-size:0.81em;font-weight:bold;color:#374151">Matched Skills</div>
        <div class="tags" id="r-skills"></div>
      </div>

      <div class="section">
        <div class="section-title">Experience</div>
        <div class="kv"><span>Years Detected</span><span id="r-years">—</span></div>
      </div>

      <div class="section">
        <div class="section-title">Education</div>
        <div class="kv"><span>Raw Score</span><span id="r-edu">—</span></div>
      </div>

      <div class="section">
        <div class="section-title">Certification</div>
        <div class="kv"><span>Raw Score</span><span id="r-cert">—</span></div>
      </div>
    </div>

    <div class="card" style="margin-top:0">
      <div class="card-header">Presentation &ensp;<span id="r-pres" style="font-size:1.4em;color:#111">—</span><span style="font-size:0.85em;color:#888">/100</span></div>

      <div class="bar-row">
        <span class="bar-lbl sub">Formatting</span>
        <div class="bar-bg"><div class="bar-fill muted" id="b-fmt"></div></div>
        <span class="bar-val" id="v-fmt">—</span>
      </div>
      <div class="bar-row">
        <span class="bar-lbl sub">Language</span>
        <div class="bar-bg"><div class="bar-fill muted" id="b-lang"></div></div>
        <span class="bar-val" id="v-lang">—</span>
      </div>
      <div class="bar-row">
        <span class="bar-lbl sub">Conciseness</span>
        <div class="bar-bg"><div class="bar-fill muted" id="b-conc"></div></div>
        <span class="bar-val" id="v-conc">—</span>
      </div>
      <div class="bar-row">
        <span class="bar-lbl sub">Organization</span>
        <div class="bar-bg"><div class="bar-fill muted" id="b-org"></div></div>
        <span class="bar-val" id="v-org">—</span>
      </div>

      <div class="kv" style="margin-top:10px"><span>Final Score</span><span id="r-final">—</span></div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">Feedback</div>
    <ul class="fb-list" id="r-feedback"></ul>
  </div>

  <div class="card">
    <div class="card-header">
      Raw Flask Response &ensp;
      <span class="norm-toggle" id="raw-btn" onclick="toggleRaw()">▶ show</span>
    </div>
    <pre class="norm-text" id="r-raw"></pre>
  </div>

</div>

<script>
function toggleAdv() {
  const grid = document.getElementById('adv-grid');
  const btn  = document.getElementById('adv-btn');
  const open = grid.style.display === 'grid';
  grid.style.display = open ? 'none' : 'grid';
  btn.textContent = (open ? '▶' : '▼') + ' advanced weights';
}

function toggleRaw() {
  const el  = document.getElementById('r-raw');
  const btn = document.getElementById('raw-btn');
  const open = el.style.display === 'block';
  el.style.display = open ? 'none' : 'block';
  btn.textContent = (open ? '▶' : '▼') + ' show';
}

function bar(id, pct) {
  const fill = document.getElementById('b-' + id);
  const val  = document.getElementById('v-' + id);
  const p = Math.max(0, Math.min(100, Math.round(pct || 0)));
  if (fill) fill.style.width = p + '%';
  if (val)  val.textContent  = p;
}

function tags(id, arr, cls, empty) {
  const c = document.getElementById(id);
  if (!c) return;
  c.innerHTML = (arr && arr.length)
    ? arr.map(s => `<span class="tag ${cls}">${s}</span>`).join('')
    : `<span style="font-size:0.81em;color:#9ca3af">${empty}</span>`;
}

function renderResults(d) {
  // ── Extraction header ────────────────────────────────────────────────
  const methodBadge = document.getElementById('method-badge');
  const method = d.extraction_method || 'error';
  methodBadge.textContent = ({
    smalot:     '📄 smalot (text layer)',
    cloud_ocr:  '☁️ cloud_ocr (overlay)',
    heuristic:  '🩹 heuristic (metadata fallback)',
    all_failed: '✗ all extraction methods failed',
  })[method] || method;
  methodBadge.className = 'badge badge-' + method;

  document.getElementById('r-chars').textContent   = d.extracted_char_count ?? '—';
  document.getElementById('r-pages').textContent   = d.page_count ?? '—';
  document.getElementById('r-latency').textContent = d.php_execution_latency_ms ?? '—';

  // ── Pipeline trace — why each tier passed or failed ─────────────────────
  const trace = d.pipeline_trace || [];
  document.getElementById('r-trace').innerHTML = trace.length
    ? trace.map(t => `
        <li class="trace-item">
          <span class="trace-icon ${t.success ? 'pass' : 'fail'}">${t.success ? '✓' : '✗'}</span>
          <span class="trace-method">${t.method}</span>
          <span class="trace-reason">${t.reason}</span>
        </li>`).join('')
    : '<li class="trace-item"><span class="trace-reason">No trace available.</span></li>';

  // ── Structure diagnostics — the actual answer to "one blob vs layout" ──
  const s = d.text_structure || {};
  document.getElementById('t-lines').textContent   = s.line_count ?? '—';
  document.getElementById('t-blank').textContent   = s.blank_line_count ?? '—';
  document.getElementById('t-indent').textContent  = s.indented_line_count ?? '—';
  document.getElementById('t-avglen').textContent  = s.avg_line_length ?? '—';
  document.getElementById('t-longest').textContent = s.longest_line_length ?? '—';

  const warnEl = document.getElementById('blob-warning');
  if (s.looks_like_single_blob) {
    warnEl.innerHTML = '<div class="blob-warn bad">⚠ Looks like one undifferentiated blob — '
      + 'line breaks / indentation were NOT preserved. Presentation/organization scoring '
      + 'on this extraction will not be meaningful.</div>';
  } else {
    warnEl.innerHTML = `<div class="blob-warn good">✓ Real line structure detected — `
      + `${s.line_count ?? 0} lines, ${s.blank_line_count ?? 0} blank, `
      + `${s.indented_line_count ?? 0} indented.</div>`;
  }

  // ── Raw extracted text, verbatim ────────────────────────────────────────
  document.getElementById('r-text').textContent = d.extracted_text || '(empty)';

  // ── Qualifications ──────────────────────────────────────────────────────
  const meta = d.extracted_candidate_meta || {};
  const cs   = d.calculated_scores || {};
  document.getElementById('r-qual').textContent  = cs.qualifications_score ?? '—';
  document.getElementById('r-final').textContent = cs.final_score ?? '—';
  document.getElementById('r-years').textContent = (meta.years_experience ?? '—') + ' yr(s)';
  document.getElementById('r-edu').textContent   = meta.education_raw_score ?? '—';
  document.getElementById('r-cert').textContent  = meta.certification_raw_score ?? '—';
  tags('r-skills', meta.skills_detected || [], 'tag-blue', 'None matched');

  // ── Presentation ─────────────────────────────────────────────────────────
  document.getElementById('r-pres').textContent = cs.presentation_score ?? '—';
  bar('fmt',  cs.formatting_score);
  bar('lang', cs.language_score);
  bar('conc', cs.concise_score);
  bar('org',  cs.organization_score);

  // ── Feedback ─────────────────────────────────────────────────────────────
  const fb = d.generated_decision_feedback || [];
  document.getElementById('r-feedback').innerHTML = fb.length
    ? fb.map(f => `<li>${f}</li>`).join('')
    : '<li>—</li>';

  // ── Raw Flask response, for anything not surfaced above ─────────────────
  document.getElementById('r-raw').textContent = JSON.stringify(d.raw_flask_json_response || {}, null, 2);
}

async function analyze() {
  const file = document.getElementById('pdfFile').files[0];
  const job  = document.getElementById('jobDesc').value.trim();
  if (!file) { alert('Select a PDF first.'); return; }
  if (!job)  { alert('Paste a job description first — the endpoint requires it.'); return; }

  const btn = document.getElementById('btn');
  btn.disabled = true;
  document.getElementById('status').innerHTML = '<span class="spinner"></span>Running extraction pipeline...';
  document.getElementById('results').style.display = 'none';

  const fd = new FormData();
  fd.append('pdf', file);
  fd.append('job_description', job);
  fd.append('keyword_weight',      document.getElementById('w-keyword').value);
  fd.append('semantic_weight',     document.getElementById('w-semantic').value);
  fd.append('qual_weight',         document.getElementById('w-qual').value);
  fd.append('layout_weight',       document.getElementById('w-layout').value);
  fd.append('skills_weight',       document.getElementById('w-skills').value);
  fd.append('experience_weight',   document.getElementById('w-experience').value);
  fd.append('education_weight',    document.getElementById('w-education').value);
  fd.append('cert_weight',         document.getElementById('w-cert').value);
  fd.append('formatting_weight',   document.getElementById('w-formatting').value);
  fd.append('language_weight',     document.getElementById('w-language').value);
  fd.append('concise_weight',      document.getElementById('w-concise').value);
  fd.append('organization_weight', document.getElementById('w-organization').value);

  try {
    // NOTE: adjust this URL if analyzeSandbox() is wired to a different
    // route/name in routes/web.php — this assumes POST /screening/sandbox/analyze.
    const res = await fetch('{{ url("/screening/sandbox/analyze") }}', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
      body: fd
    });
    const d = await res.json();

    if (d.success === false) {
      document.getElementById('status').textContent = 'Error: ' + (d.error || 'unknown error');
      return;
    }

    renderResults(d);
    document.getElementById('results').style.display = 'block';
    document.getElementById('status').textContent = 'Done.';
  } catch (e) {
    document.getElementById('status').textContent = 'Request failed: ' + e.message;
  } finally {
    btn.disabled = false;
  }
}
</script>
</body>
</html>