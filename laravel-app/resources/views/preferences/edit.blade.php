<!DOCTYPE html>
<html>
<head>
    <title>Default Preferences</title>
    {{-- <style>
        body { font-family: Arial, sans-serif; padding: 24px; max-width: 700px; }
        h2 { margin-bottom: 2px; }
        .subtitle { color: #6b7280; font-size: 0.88em; margin-bottom: 20px; }
        h3 { margin: 28px 0 4px; font-size: 1em; color: #111; border-bottom: 1px solid #e5e7eb; padding-bottom: 5px; }
        h4 { margin: 16px 0 4px; font-size: 0.9em; color: #374151; }
        label { font-weight: 600; font-size: 0.88em; display: block; margin-bottom: 3px; color: #374151; }
        .hint { font-size: 0.77em; color: #6b7280; margin: 0 0 10px; }
        .field-row { display: flex; align-items: center; gap: 10px; margin-bottom: 2px; }
        .field-row input[type=range]  { flex: 1; accent-color: #2563eb; }
        .field-row input[type=number] { width: 64px; padding: 4px 6px; border: 1px solid #d1d5db; border-radius: 5px; font-size: 0.9em; }
        .field-row .derived { width: 64px; padding: 4px 6px; background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 5px; font-size: 0.9em; color: #6b7280; text-align: center; }
        .total-row { font-size: 0.83em; color: #374151; margin: 4px 0 0; }
        .ok  { color: #16a34a; font-weight: 600; }
        .bad { color: #dc2626; font-weight: 600; }

        /* Presentation checkboxes */
        .check-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin: 8px 0 4px; }
        .check-card { border: 2px solid #e5e7eb; border-radius: 8px; padding: 10px 12px; cursor: pointer; transition: border-color .15s, background .15s; }
        .check-card:has(input:checked) { border-color: #2563eb; background: #eff6ff; }
        .check-card input[type=checkbox] { margin-right: 7px; accent-color: #2563eb; width: 15px; height: 15px; vertical-align: middle; }
        .check-card strong { font-size: 0.88em; vertical-align: middle; }
        .check-card small { display: block; color: #6b7280; font-size: 0.76em; margin-top: 3px; }
        .pres-note { font-size: 0.8em; color: #2563eb; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 6px 10px; margin: 6px 0 0; }

        .section-indent { margin-left: 16px; border-left: 3px solid #e5e7eb; padding-left: 14px; margin-top: 8px; }
        .error { color: #dc2626; background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 8px 12px; margin-bottom: 16px; font-size: 0.88em; }
        .success { color: #16a34a; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 8px 12px; margin-bottom: 16px; font-size: 0.88em; }
        button[type=submit] { background: #2563eb; color: white; border: none; padding: 10px 24px; border-radius: 6px; font-size: 0.95em; cursor: pointer; margin-top: 20px; }
        button[type=submit]:hover { background: #1d4ed8; }
    </style> --}}
</head>
<body>

    <script>
    window.__LARAVEL__ = {
        csrf: "{{ csrf_token() }}",
        pref: @json($pref ?? null),
        flash: {
            success: "{{ session('success') }}"
        }
    };
    </script>
    @viteReactRefresh
    @vite(['resources/js/app.jsx'])
    <div id="app"></div>
    {{-- <p><a href="/recruiter">&larr; Back</a></p>
    <h2>Default Preferences</h2>
    <p class="subtitle">Applied to all jobs unless overridden by a job-specific preference.</p>

    @if($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif
    @if(session('success'))
        <div class="success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="/preferences">
        @csrf --}}

        {{-- ═══ FINAL SCORE ══════════════════════════════════════════ --}}
        {{-- <h3>Final Score Weights</h3>
        <p class="hint">How much each component contributes to the final ranking score (must total 100%).</p>

        <label>Qualifications (%)</label>
        <div class="field-row">
            <input type="range" min="0" max="100"
                   value="{{ old('qual_weight', $pref->qual_weight ?? 100) }}"
                   oninput="setQualWeight(this.value)">
            <input type="number" name="qual_weight" id="qual_weight"
                   value="{{ old('qual_weight', $pref->qual_weight ?? 100) }}" min="0" max="100"
                   oninput="setQualWeight(this.value)">
        </div>

        <label style="margin-top:8px">Presentation (%)</label>
        <div class="field-row">
            <input type="range" min="0" max="100" id="pres_range"
                   value="{{ 100 - old('qual_weight', $pref->qual_weight ?? 100) }}" disabled
                   style="opacity:0.5">
            <div class="derived" id="pres_display">{{ 100 - old('qual_weight', $pref->qual_weight ?? 100) }}</div>
        </div>
        <p class="hint">Presentation = 100 − Qualifications (auto-set).</p> --}}

        {{-- ═══ QUALIFICATIONS ════════════════════════════════════════ --}}
        {{-- <h3>Qualifications</h3>

        <h4>Skills Matching — TF-IDF + Semantic <small style="font-weight:normal;color:#6b7280">(must total 100%)</small></h4>
        <div class="section-indent">
            <label>TF-IDF (Keyword) (%)</label>
            <div class="field-row">
                <input type="range" id="keyword_weight_range" min="0" max="100"
                       value="{{ old('keyword_weight', $pref->keyword_weight ?? 40) }}"
                       oninput="syncFromSlider('keyword_weight', this.value)">
                <input type="number" name="keyword_weight" id="keyword_weight"
                       value="{{ old('keyword_weight', $pref->keyword_weight ?? 40) }}" min="0" max="100"
                       oninput="syncFromNumber('keyword_weight', this.value)">
            </div>

            <label>Semantic (AI) (%)</label>
            <div class="field-row">
                <input type="range" id="semantic_weight_range" min="0" max="100"
                       value="{{ old('semantic_weight', $pref->semantic_weight ?? 60) }}"
                       oninput="syncFromSlider('semantic_weight', this.value)">
                <input type="number" name="semantic_weight" id="semantic_weight"
                       value="{{ old('semantic_weight', $pref->semantic_weight ?? 60) }}" min="0" max="100"
                       oninput="syncFromNumber('semantic_weight', this.value)">
            </div>
            <p class="total-row">Total: <span id="blend_total"></span>% <span id="blend_status"></span></p>
        </div>

        <h4 style="margin-top:16px">Qualification Sub-weights <small style="font-weight:normal;color:#6b7280">(must total 100%)</small></h4>
        <div class="section-indent">
            @foreach([
                ['skills_weight',     'Skills Match',   35],
                ['experience_weight', 'Experience',     20],
                ['education_weight',  'Education',      25],
                ['cert_weight',       'Certification',  10],
            ] as [$name, $label, $default])
            <label>{{ $label }} (%)</label>
            <div class="field-row">
                <input type="range" id="{{ $name }}_range" min="0" max="100"
                       value="{{ old($name, $pref->$name ?? $default) }}"
                       oninput="syncFromSlider('{{ $name }}', this.value)">
                <input type="number" name="{{ $name }}" id="{{ $name }}"
                       value="{{ old($name, $pref->$name ?? $default) }}" min="0" max="100"
                       oninput="syncFromNumber('{{ $name }}', this.value)">
            </div>
            @endforeach
            <p class="total-row">Total: <span id="qual_total"></span>% <span id="qual_status"></span></p>
        </div> --}}

        {{-- ═══ PRESENTATION ══════════════════════════════════════════ --}}
        {{-- <h3>Presentation</h3>
        <p class="hint">Check which categories to score. Checked categories split 100% equally. If none are checked, all four share 25% each.</p>

        <div class="check-grid">
            <label class="check-card">
                <input type="checkbox" name="pref_formatting" value="1"
                       {{ old('pref_formatting', $pref->pref_formatting ?? false) ? 'checked' : '' }}
                       onchange="updatePresNote()">
                <strong>Formatting &amp; Visuals</strong>
                <small>Section spacing, B&amp;W layout</small>
            </label>
            <label class="check-card">
                <input type="checkbox" name="pref_language" value="1"
                       {{ old('pref_language', $pref->pref_language ?? false) ? 'checked' : '' }}
                       onchange="updatePresNote()">
                <strong>Language Quality</strong>
                <small>Action verbs, formal tone, no typos</small>
            </label>
            <label class="check-card">
                <input type="checkbox" name="pref_conciseness" value="1"
                       {{ old('pref_conciseness', $pref->pref_conciseness ?? false) ? 'checked' : '' }}
                       onchange="updatePresNote()">
                <strong>Conciseness</strong>
                <small>Word count, page length, minimal repetition</small>
            </label>
            <label class="check-card">
                <input type="checkbox" name="pref_organization" value="1"
                       {{ old('pref_organization', $pref->pref_organization ?? false) ? 'checked' : '' }}
                       onchange="updatePresNote()">
                <strong>Organization &amp; Structure</strong>
                <small>Sections, margins, reverse-chronological order</small>
            </label>
        </div>
        <div class="pres-note" id="pres_note"></div>

        <button type="submit">Save Preferences</button>
    </form>

    <script>
    function syncFromSlider(name, val) {
        document.getElementById(name).value = val;
        updateTotals();
    }
    function syncFromNumber(name, val) {
        document.getElementById(name + '_range').value = val;
        updateTotals();
    }
    function setQualWeight(val) {
        val = Math.min(100, Math.max(0, parseInt(val) || 0));
        document.getElementById('qual_weight').value = val;
        document.querySelector('input[type=range][oninput="setQualWeight(this.value)"]').value = val;
        document.getElementById('pres_range').value   = 100 - val;
        document.getElementById('pres_display').textContent = 100 - val;
    }
    function sum(...ids) {
        return ids.reduce((acc, id) => acc + (parseInt(document.getElementById(id)?.value) || 0), 0);
    }
    function setTotal(spanId, statusId, total) {
        document.getElementById(spanId).textContent = total;
        const s = document.getElementById(statusId);
        s.textContent = total === 100 ? '✓' : '(must equal 100%)';
        s.className   = total === 100 ? 'ok' : 'bad';
    }
    function updateTotals() {
        setTotal('blend_total', 'blend_status', sum('keyword_weight', 'semantic_weight'));
        setTotal('qual_total',  'qual_status',  sum('skills_weight', 'experience_weight', 'education_weight', 'cert_weight'));
    }
    function updatePresNote() {
        const labels = {
            pref_formatting:  'Formatting',
            pref_language:    'Language',
            pref_conciseness: 'Conciseness',
            pref_organization:'Organization',
        };
        const checked = Object.keys(labels).filter(k => document.querySelector(`input[name=${k}]`)?.checked);
        const note = document.getElementById('pres_note');
        if (checked.length === 0) {
            note.textContent = 'No selection — all four categories will be weighted equally at 25% each.';
        } else {
            const each = Math.floor(100 / checked.length);
            const parts = checked.map((k, i) => `${labels[k]}: ${each + (i === 0 ? 100 % checked.length : 0)}%`);
            note.textContent = 'Active split → ' + parts.join(', ');
        }
    }
    updateTotals();
    updatePresNote();
    </script> --}}
</body>
</html>