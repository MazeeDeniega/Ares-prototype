<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Laravel Production Pipeline Sandbox</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8fafc; color: #1e293b; padding: 30px; }
        .header { margin-bottom: 25px; background: #1e293b; color: white; padding: 20px; border-radius: 8px; }
        .container { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; max-width: 1600px; margin: 0 auto; }
        .card { background: white; border-radius: 8px; padding: 25px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; }
        h2 { margin-bottom: 15px; font-size: 1.25rem; color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 8px; }
        h3 { margin: 15px 0 8px; font-size: 1rem; color: #334155; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.85rem; color: #475569; }
        textarea, input[type="file"], input[type="number"] { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem; }
        textarea { height: 180px; font-family: monospace; }
        .weight-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; background: #f8fafc; padding: 12px; border-radius: 6px; border: 1px dashed #cbd5e1; }
        button { background: #2563eb; color: white; padding: 12px; border: none; border-radius: 6px; font-weight: bold; width: 100%; cursor: pointer; font-size: 1rem; }
        button:hover { background: #1d4ed8; }
        pre { background: #0f172a; color: #38bdf8; padding: 15px; border-radius: 6px; overflow-x: auto; font-size: 0.85rem; max-height: 450px; }
        .metric-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px; }
        .metric-box { background: #eff6ff; border-left: 4px solid #2563eb; padding: 12px; border-radius: 0 6px 6px 0; }
        .metric-label { font-size: 0.75rem; font-weight: bold; color: #1e40af; text-transform: uppercase; }
        .metric-value { font-size: 1.25rem; font-weight: bold; color: #1e293b; margin-top: 2px; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 9999px; font-size: 0.8rem; font-weight: bold; margin-right: 5px; background: #f1f5f9; }
        .badge-alert { background: #fee2e2; color: #991b1b; }
        .badge-success { background: #dcfce7; color: #166534; }
    </style>
</head>
<body>

<div class="header">
    <h1>ARES Production Environment Pipeline Diagnostic Sandbox</h1>
    <p style="font-size: 0.9rem; color: #94a3b8; margin-top: 4px;">Testing Core Stack: Laravel Web Routing ➔ Smalot PDF Parser ➔ HTTP Guzzle Hook ➔ Port 5000 Flask API Microservice</p>
</div>

<div class="container">
    <div class="card">
        <h2>Ad-Hoc Request Parameters</h2>
        <form id="sandboxForm" enctype="multipart/form-data">
            <div class="form-group">
                <label>Upload Test Resume Binary File (.pdf)</label>
                <input type="file" name="pdf" accept=".pdf" required>
            </div>
            
            <div class="form-group">
                <label>Job Criteria Description Text Block Target</label>
                <textarea name="job_description" placeholder="Paste the exact structural evaluation criteria details here..." required></textarea>
            </div>

            <h3>Recruiter Formula Configuration Simulation Profiles</h3>
            <div class="weight-grid" style="margin-bottom: 15px;">
                <div><label>Qual Split (%)</label><input type="number" name="qual_weight" value="100"></div>
                <div><label>Layout Split (%)</label><input type="number" name="layout_weight" value="0"></div>
                <div><label>Keyword Wt</label><input type="number" name="keyword_weight" value="40"></div>
                <div><label>Semantic Wt</label><input type="number" name="semantic_weight" value="60"></div>
                <div><label>Skills SubWt</label><input type="number" name="skills_weight" value="35"></div>
                <div><label>Exp SubWt</label><input type="number" name="experience_weight" value="20"></div>
                <div><label>Edu SubWt</label><input type="number" name="education_weight" value="25"></div>
                <div><label>Cert SubWt</label><input type="number" name="cert_weight" value="10"></div>
            </div>

            <button type="button" id="submitBtn" onclick="runProductionSandbox()">Execute Sandbox Diagnostic Pipeline</button>
        </form>
    </div>

    <div class="card">
        <h2>Production Pipeline Infrastructure Telemetry</h2>
        <div id="outputContainer" style="display: none;">
            <div class="metric-grid">
                <div class="metric-box">
                    <div class="metric-label">Simulated Final Score</div>
                    <div id="mFinalScore" class="metric-value" style="color: #2563eb; font-size: 1.5rem;">0.00</div>
                </div>
                <div class="metric-box" style="background: #f0fdf4; border-left-color: #16a34a;">
                    <div class="metric-label">PHP Pipeline Latency</div>
                    <div id="mLatency" class="metric-value" style="color: #166534;">0ms</div>
                </div>
                <div class="metric-box" style="background: #fff7ed; border-left-color: #ea580c;">
                    <div class="metric-label">Parser Applied</div>
                    <div id="mParser" class="metric-value" style="color: #9a3412; font-size: 0.95rem; word-break: break-all;">-</div>
                </div>
                <div class="metric-box">
                    <div class="metric-label">Extracted Chars / Pages</div>
                    <div id="mChars" class="metric-value">-</div>
                </div>
            </div>

            <h3>Generated Production Decision Flags</h3>
            <div id="feedbackContainer" style="margin-bottom: 15px;"></div>

            <h3>Extracted Text Sample Passed to NLP Endpoint</h3>
            <div id="textPreview" style="background: #f8fafc; padding: 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.85rem; max-height: 140px; overflow-y: auto; margin-bottom: 15px; white-space: pre-wrap; color: #475569;"></div>

            <h3>Raw Telemetry Payload Trace JSON</h3>
            <pre><code id="jsonOutput"></code></pre>
        </div>
        <div id="placeholderText" style="color: #64748b; font-style: italic; text-align: center; padding-top: 80px;">
            Configure parameters and execute pipeline to view environment diagnostics.
        </div>
    </div>
</div>

<script>
async function runProductionSandbox() {
    const form = document.getElementById('sandboxForm');
    if(!form.checkValidity()) {
        alert("Please upload a PDF and populate the Job Description textarea.");
        return;
    }

    const btn = document.getElementById('submitBtn');
    const placeholder = document.getElementById('placeholderText');
    const output = document.getElementById('outputContainer');
    
    btn.disabled = true;
    btn.innerText = "Processing Environment Stack Match...";
    placeholder.style.display = 'none';
    output.style.display = 'block';
    
    document.getElementById('jsonOutput').innerText = "Awaiting response payload...";
    document.getElementById('textPreview').innerText = "";
    document.getElementById('feedbackContainer').innerHTML = "";

    const formData = new FormData(form);

    try {
        const response = await fetch("{{ route('screening.sandbox.analyze') }}", {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.meta = document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
            body: formData
        });

        const data = await response.json();
        btn.disabled = false;
        btn.innerText = "Execute Sandbox Diagnostic Pipeline";

        if(!data.success) {
            document.getElementById('jsonOutput').innerText = JSON.stringify(data, null, 2);
            return;
        }

        // Hydrate Telemetry Values
        document.getElementById('mFinalScore').innerText = data.calculated_scores.final_score + " / 100";
        document.getElementById('mLatency').innerText = data.php_execution_latency_ms + " ms";
        document.getElementById('mParser').innerText = data.parser_used;
        document.getElementById('mChars').innerText = data.extracted_char_count + " chars / " + data.page_count + " pg";
        document.getElementById('textPreview').innerText = data.extracted_text_preview;

        // Render Decision Badges
        let badges = "";
        data.generated_decision_feedback.forEach(f => {
            let cls = f.includes('🎯') || f.includes('Good') ? 'badge-success' : 'badge-alert';
            badges += `<span class="badge ${cls}">${f}</span>`;
        });
        document.getElementById('feedbackContainer').innerHTML = badges;
        document.getElementById('jsonOutput').innerText = JSON.stringify(data, null, 2);

    } catch (e) {
        btn.disabled = false;
        btn.innerText = "Execute Sandbox Diagnostic Pipeline";
        document.getElementById('jsonOutput').innerText = "Network Fault or Server Crash: " + e;
    }
}
</script>
</body>
</html>