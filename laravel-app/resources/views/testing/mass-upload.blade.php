<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>ARES — Mass Resume Upload (Testing)</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; background: #f3f4f6; padding: 24px; max-width: 820px; margin: 0 auto; }
  h1   { font-size: 1.4em; color: #111; margin-bottom: 4px; }
  h1 .sub { display:block; font-size:0.6em; font-weight:normal; color:#6b7280; margin-top:4px; }

  .card { background: white; border-radius: 8px; padding: 20px;
          box-shadow: 0 1px 3px #0001; margin-top: 16px; }
  .card-header { font-size: 0.78em; text-transform: uppercase; letter-spacing:.06em;
                 color: #6b7280; margin-bottom: 14px; font-weight: bold; }

  label { display:block; font-size:0.85em; font-weight:bold; color:#374151; margin-bottom:5px; }
  select, input[type=text] {
    width:100%; padding:9px 10px; border:1px solid #d1d5db; border-radius:6px;
    font-size:0.9em; margin-bottom:14px;
  }

  .drop-zone {
    border: 2px dashed #d1d5db; border-radius: 8px; padding: 40px 20px;
    text-align: center; cursor: pointer; transition: border-color .2s, background .2s;
    color: #6b7280; font-size: 0.9em; position: relative; margin-bottom: 14px;
  }
  .drop-zone:hover, .drop-zone.dragover { border-color: #2563eb; background: #eff6ff; color: #1d4ed8; }
  .drop-zone input[type=file] {
    position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%;
  }
  .drop-zone .icon { font-size: 2.5em; margin-bottom: 8px; }
  .drop-zone .hint { font-size: 0.8em; color: #9ca3af; margin-top: 4px; }

  .file-list { margin-top: 10px; display: flex; flex-direction: column; gap: 6px; }
  .file-item {
    display: flex; align-items: center; gap: 10px; padding: 8px 10px;
    background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px;
    font-size: 0.83em;
  }
  .file-item .name  { flex:1; color: #374151; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .file-item .size  { color: #9ca3af; flex-shrink:0; }
  .file-item .badge { padding:2px 8px; border-radius:10px; font-weight:bold; font-size:0.85em; flex-shrink:0; }
  .badge-wait   { background:#f3f4f6; color:#6b7280; border:1px solid #e5e7eb; }
  .badge-ok     { background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; }
  .badge-fail   { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; }
  .badge-label  { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }

  button.submit {
    width:100%; padding:12px; background:#2563eb; color:white; border:none;
    border-radius:8px; font-size:1em; font-weight:600; cursor:pointer; margin-top:4px;
  }
  button.submit:hover    { background:#1d4ed8; }
  button.submit:disabled { background:#93c5fd; cursor:not-allowed; }

  .btn-clear {
    padding:7px 16px; background:#fef2f2; color:#dc2626; border:1px solid #fecaca;
    border-radius:6px; font-size:0.85em; font-weight:bold; cursor:pointer;
  }
  .btn-clear:hover { background:#fee2e2; }

  .status { font-size:0.85em; color:#6b7280; margin-top:10px; min-height:1.2em; }
  .spinner { display:inline-block; width:14px; height:14px; border:2px solid #93c5fd;
             border-top-color:#2563eb; border-radius:50%;
             animation:spin .7s linear infinite; vertical-align:middle; margin-right:6px; }
  @keyframes spin { to { transform:rotate(360deg); } }

  #results { display:none; }
  .result-row { display:flex; gap:10px; align-items:center; padding:7px 0;
                border-bottom:1px solid #f3f4f6; font-size:0.84em; }
  .result-row:last-child { border-bottom:none; }
  .result-label { font-weight:bold; width:120px; flex-shrink:0; }
  .result-file  { color:#6b7280; flex:1; }
  .result-id    { color:#9ca3af; font-size:0.9em; }

  .eval-link {
    display:block; margin-top:16px; padding:12px; background:#2563eb; color:white;
    border-radius:8px; text-align:center; font-weight:bold; text-decoration:none;
    font-size:1em;
  }
  .eval-link:hover { background:#1d4ed8; }

  .note { font-size:0.78em; color:#9ca3af; margin-top:8px; }
  code  { background:#f3f4f6; padding:2px 6px; border-radius:4px; font-size:0.95em; }
</style>
</head>
<body>

<h1> Mass Resume Upload
  <span class="sub">Testing tool — creates placeholder applicants with real resume PDFs</span>
</h1>

<div class="card">
  <div class="card-header">Upload Settings</div>

  <label>Target Job</label>
  <select id="job-select">
    <option value="">— select a job —</option>
    @foreach($jobs as $job)
      <option value="{{ $job->id }}">{{ $job->title }} (ID {{ $job->id }})</option>
    @endforeach
  </select>

  <label>Candidate Name Prefix</label>
  <input type="text" id="prefix" value="Candidate" placeholder="e.g. Candidate, Applicant, Test">
  <p class="note">Each upload gets a sequential label: <strong>Candidate A</strong>, <strong>Candidate B</strong>, etc.</p>

  <div class="drop-zone" id="drop-zone">
    <input type="file" id="file-input" accept="application/pdf" multiple>
    <div class="icon">📄</div>
    <div><strong>Click to browse or drag PDFs here</strong></div>
    <div class="hint">PDF only · Multiple files · Max 10MB each</div>
  </div>

  <div class="file-list" id="file-list"></div>

  <button class="submit" id="upload-btn" onclick="upload()" disabled>Upload Resumes</button>
  <div class="status" id="status"></div>
</div>

<div class="card" id="results">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
    <div class="card-header" style="margin-bottom:0">Created Applicants</div>
    <button class="btn-clear" id="clear-btn" onclick="clearTest()">🗑 Clear Test Data for this Job</button>
  </div>
  <div id="result-list"></div>
  <a id="eval-link" href="#" class="eval-link">▶ Evaluate These Applicants</a>
</div>

<div class="card" style="margin-top:16px;">
  <div class="card-header">Command-line Alternative (Artisan)</div>
  <p style="font-size:0.85em; color:#374151; margin-bottom:10px;">
    If your PDFs are already on the server, drop them into
    <code>storage/app/test-resumes/</code> and run:
  </p>
  <pre style="background:#1e1e1e; color:#d4d4d4; padding:14px; border-radius:6px; font-size:0.85em; overflow-x:auto;">
<span style="color:#9cdcfe">php artisan</span> <span style="color:#ce9178">ares:seed-resumes</span> <span style="color:#b5cea8">{job_id}</span>

<span style="color:#6a9955"># Options:</span>
<span style="color:#9cdcfe">php artisan</span> ares:seed-resumes 1 --prefix="Applicant" --clear
<span style="color:#9cdcfe">php artisan</span> ares:seed-resumes 1 --path="C:/path/to/pdfs"</pre>
</div>

<script>
let selectedFiles = [];
let lastJobId = null;

const dropZone   = document.getElementById('drop-zone');
const fileInput  = document.getElementById('file-input');
const fileList   = document.getElementById('file-list');
const uploadBtn  = document.getElementById('upload-btn');

fileInput.addEventListener('change', () => setFiles(Array.from(fileInput.files)));

dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('dragover'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
dropZone.addEventListener('drop', e => {
  e.preventDefault();
  dropZone.classList.remove('dragover');
  const pdfs = Array.from(e.dataTransfer.files).filter(f => f.type === 'application/pdf');
  setFiles(pdfs);
});

function setFiles(files) {
  selectedFiles = files;
  fileList.innerHTML = '';
  files.forEach((f, i) => {
    const el = document.createElement('div');
    el.className = 'file-item';
    el.id = `fi-${i}`;
    el.innerHTML = `
      <span class="name">📄 ${f.name}</span>
      <span class="size">${(f.size/1024/1024).toFixed(2)} MB</span>
      <span class="badge badge-wait" id="badge-${i}">pending</span>`;
    fileList.appendChild(el);
  });
  uploadBtn.disabled = files.length === 0;
}

async function upload() {
  const jobId  = document.getElementById('job-select').value;
  const prefix = document.getElementById('prefix').value.trim() || 'Candidate';

  if (!jobId)           { alert('Select a job first.');   return; }
  if (!selectedFiles.length) { alert('Add PDF files first.'); return; }

  uploadBtn.disabled = true;
  document.getElementById('status').innerHTML =
    '<span class="spinner"></span>Uploading ' + selectedFiles.length + ' file(s)…';
  document.getElementById('results').style.display = 'none';

  const fd = new FormData();
  fd.append('job_id', jobId);
  fd.append('prefix', prefix);
  selectedFiles.forEach(f => fd.append('resumes[]', f));

  // Mark all as uploading
  selectedFiles.forEach((_, i) => {
    document.getElementById(`badge-${i}`).textContent = '⏳';
    document.getElementById(`badge-${i}`).className = 'badge badge-wait';
  });

  try {
    const res = await fetch('{{ route("testing.mass-upload.store") }}', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
      body: fd,
    });
    const d = await res.json();

    if (!d.success) {
      document.getElementById('status').textContent = 'Error: ' + (d.message || 'Unknown error');
      uploadBtn.disabled = false;
      return;
    }

    // Mark per-file status
    const createdNames = d.created.map(c => c.filename);
    selectedFiles.forEach((f, i) => {
      const badge = document.getElementById(`badge-${i}`);
      const match = d.created.find(c => c.filename === f.name);
      if (match) {
        badge.textContent = match.label;
        badge.className = 'badge badge-label';
      } else {
        badge.textContent = '✗ failed';
        badge.className = 'badge badge-fail';
      }
    });

    // Results panel
    lastJobId = d.job_id;
    const list = document.getElementById('result-list');
    list.innerHTML = d.created.map(c => `
      <div class="result-row">
        <span class="result-label">📄 ${c.label}</span>
        <span class="result-file">${c.filename}</span>
        <span class="result-id">#${c.id}</span>
      </div>`).join('');

    if (d.failed?.length) {
      list.innerHTML += d.failed.map(f =>
        `<div class="result-row" style="color:#dc2626">✗ ${f.filename} — ${f.error}</div>`
      ).join('');
    }

    document.getElementById('eval-link').href = d.evaluate_url;
    document.getElementById('results').style.display = 'block';
    document.getElementById('status').textContent =
      `✓ Created ${d.total} applicant(s) for "${d.job_title}".`;

  } catch (e) {
    document.getElementById('status').textContent = 'Request failed: ' + e.message;
  } finally {
    uploadBtn.disabled = false;
  }
}

async function clearTest() {
  if (!lastJobId) { alert('Upload something first so I know which job to clear.'); return; }
  if (!confirm('Delete all test (@test.local) applications for this job? This cannot be undone.')) return;

  const res = await fetch(`/testing/mass-upload/clear/${lastJobId}`, {
    method: 'DELETE',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      'Accept': 'application/json',
    },
  });
  const d = await res.json();
  if (d.success) {
    document.getElementById('results').style.display = 'none';
    document.getElementById('status').textContent = `✓ Deleted ${d.deleted} test application(s).`;
    setFiles([]);
    lastJobId = null;
  }
}
</script>
</body>
</html>