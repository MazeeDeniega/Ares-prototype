<!DOCTYPE html>
<html>
<head>
    <title>Job Preferences — {{ $job->title }}</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 620px; }
        h3 { margin-top: 28px; border-bottom: 1px solid #ddd; padding-bottom: 4px; }
        label { display: block; margin-top: 12px; font-weight: bold; }
        input[type=number] { width: 80px; padding: 4px 6px; margin-top: 4px; border: 1px solid #ccc; border-radius: 4px; }
        .hint { font-size: 0.82em; color: #666; margin: 2px 0 0; }
        .total-row { margin-top: 10px; font-weight: bold; }
        .ok    { color: #16a34a; }
        .error { color: #dc2626; }
        .alert { background: #fee2e2; border: 1px solid #f87171; padding: 10px 14px; border-radius: 4px; margin-bottom: 16px; }
        button { margin-top: 24px; padding: 10px 28px; background: #2563eb; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1em; }
        button:hover { background: #1d4ed8; }
        .field-row { display: flex; align-items: center; gap: 10px; margin-top: 4px; }
        input[type=range] { flex: 1; accent-color: #2563eb; }
    </style>
</head>
<body>
    <p><a href="/dashboard">&larr; Back to Dashboard</a></p>
    <h2>Preferences for: {{ $job->title }}</h2>

    @if($errors->any())
        <div class="alert">
            @foreach($errors->all() as $e)
                <p style="margin:0" class="error">{{ $e }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="/jobs/{{ $job->id }}/preferences">
        @csrf

        <h3>Similarity Method <small style="font-weight:normal;color:#777">(must total 100%)</small></h3>

        <label>Keyword / TF-IDF Weight (%)</label>
        <div class="field-row">
            <input type="range" id="keyword_weight_range" min="0" max="100"
                   value="{{ old('keyword_weight', $pref->keyword_weight) }}"
                   oninput="syncFromSlider('keyword_weight', this.value)">
            <input type="number" name="keyword_weight" id="keyword_weight"
                   value="{{ old('keyword_weight', $pref->keyword_weight) }}" min="0" max="100"
                   oninput="syncFromNumber('keyword_weight', this.value)">
        </div>
        <p class="hint">Scores exact keyword matches against the job description.</p>

        <label>Semantic / AI Weight (%)</label>
        <div class="field-row">
            <input type="range" id="semantic_weight_range" min="0" max="100"
                   value="{{ old('semantic_weight', $pref->semantic_weight) }}"
                   oninput="syncFromSlider('semantic_weight', this.value)">
            <input type="number" name="semantic_weight" id="semantic_weight"
                   value="{{ old('semantic_weight', $pref->semantic_weight) }}" min="0" max="100"
                   oninput="syncFromNumber('semantic_weight', this.value)">
        </div>
        <p class="hint">Scores meaning-based similarity (catches synonyms and related terms).</p>

        <p class="total-row">Total: <span id="blend_total"></span>% <span id="blend_status"></span></p>

        <h3>Qualifications Weights <small style="font-weight:normal;color:#777">(must total 100%)</small></h3>

        <label>Job Match / Skills (%)</label>
        <div class="field-row">
            <input type="range" id="skills_weight_range" min="0" max="100"
                   value="{{ old('skills_weight', $pref->skills_weight) }}"
                   oninput="syncFromSlider('skills_weight', this.value)">
            <input type="number" name="skills_weight" id="skills_weight"
                   value="{{ old('skills_weight', $pref->skills_weight) }}" min="0" max="100"
                   oninput="syncFromNumber('skills_weight', this.value)">
        </div>

        <label>Experience (%)</label>
        <div class="field-row">
            <input type="range" id="experience_weight_range" min="0" max="100"
                   value="{{ old('experience_weight', $pref->experience_weight) }}"
                   oninput="syncFromSlider('experience_weight', this.value)">
            <input type="number" name="experience_weight" id="experience_weight"
                   value="{{ old('experience_weight', $pref->experience_weight) }}" min="0" max="100"
                   oninput="syncFromNumber('experience_weight', this.value)">
        </div>

        <label>Education (%)</label>
        <div class="field-row">
            <input type="range" id="education_weight_range" min="0" max="100"
                   value="{{ old('education_weight', $pref->education_weight) }}"
                   oninput="syncFromSlider('education_weight', this.value)">
            <input type="number" name="education_weight" id="education_weight"
                   value="{{ old('education_weight', $pref->education_weight) }}" min="0" max="100"
                   oninput="syncFromNumber('education_weight', this.value)">
        </div>

        <label>Certification (%)</label>
        <div class="field-row">
            <input type="range" id="cert_weight_range" min="0" max="100"
                   value="{{ old('cert_weight', $pref->cert_weight) }}"
                   oninput="syncFromSlider('cert_weight', this.value)">
            <input type="number" name="cert_weight" id="cert_weight"
                   value="{{ old('cert_weight', $pref->cert_weight) }}" min="0" max="100"
                   oninput="syncFromNumber('cert_weight', this.value)">
        </div>

        <p class="total-row">Total: <span id="qual_total"></span>% <span id="qual_status"></span></p>

        <h3>Presentation Quality Weights <small style="font-weight:normal;color:#777">(must total 100%)</small></h3>
        <p class="hint" style="margin-top:0">Controls how each Ch.3 dimension contributes to the <strong>Presentation Score</strong>.</p>

        <label>Formatting &amp; Visuals (%)</label>
        <div class="field-row">
            <input type="range" id="formatting_weight_range" min="0" max="100"
                   value="{{ old('formatting_weight', $pref->formatting_weight ?? 25) }}"
                   oninput="syncFromSlider('formatting_weight', this.value)">
            <input type="number" name="formatting_weight" id="formatting_weight"
                   value="{{ old('formatting_weight', $pref->formatting_weight ?? 25) }}" min="0" max="100"
                   oninput="syncFromNumber('formatting_weight', this.value)">
        </div>
        <p class="hint">Section spacing, font consistency, black-and-white layout.</p>

        <label>Language Quality (%)</label>
        <div class="field-row">
            <input type="range" id="language_weight_range" min="0" max="100"
                   value="{{ old('language_weight', $pref->language_weight ?? 25) }}"
                   oninput="syncFromSlider('language_weight', this.value)">
            <input type="number" name="language_weight" id="language_weight"
                   value="{{ old('language_weight', $pref->language_weight ?? 25) }}" min="0" max="100"
                   oninput="syncFromNumber('language_weight', this.value)">
        </div>
        <p class="hint">Action verbs, formal tone, coherent sentence length.</p>

        <label>Conciseness (%)</label>
        <div class="field-row">
            <input type="range" id="concise_weight_range" min="0" max="100"
                   value="{{ old('concise_weight', $pref->concise_weight ?? 25) }}"
                   oninput="syncFromSlider('concise_weight', this.value)">
            <input type="number" name="concise_weight" id="concise_weight"
                   value="{{ old('concise_weight', $pref->concise_weight ?? 25) }}" min="0" max="100"
                   oninput="syncFromNumber('concise_weight', this.value)">
        </div>
        <p class="hint">Word count, recency of dates, minimal repetition.</p>

        <label>Organization &amp; Structure (%)</label>
        <div class="field-row">
            <input type="range" id="organization_weight_range" min="0" max="100"
                   value="{{ old('organization_weight', $pref->organization_weight ?? 25) }}"
                   oninput="syncFromSlider('organization_weight', this.value)">
            <input type="number" name="organization_weight" id="organization_weight"
                   value="{{ old('organization_weight', $pref->organization_weight ?? 25) }}" min="0" max="100"
                   oninput="syncFromNumber('organization_weight', this.value)">
        </div>
        <p class="hint">Section headings, indentation, reverse-chronological order.</p>

        <p class="total-row">Total: <span id="pres_total"></span>% <span id="pres_status"></span></p>

        <button type="submit">Save Preferences</button>
    </form>

    <script>
        const GROUPS = {
            blend: ['keyword_weight', 'semantic_weight'],
            qual:  ['skills_weight', 'experience_weight', 'education_weight', 'cert_weight'],
            pres:  ['formatting_weight', 'language_weight', 'concise_weight', 'organization_weight'],
        };
        const FIELD_GROUP = {};
        for (const [g, fields] of Object.entries(GROUPS)) fields.forEach(f => FIELD_GROUP[f] = g);

        function updateTotal(group) {
            const fields = GROUPS[group];
            const total = fields.reduce((sum, id) => sum + (parseInt(document.getElementById(id).value) || 0), 0);
            document.getElementById(group + '_total').textContent = total;
            const el = document.getElementById(group + '_status');
            if (total === 100) { el.textContent = '✓'; el.className = 'ok'; }
            else { el.textContent = '(must equal 100)'; el.className = 'error'; }
        }

        function syncFromSlider(name, val) {
            document.getElementById(name).value = val;
            updateTotal(FIELD_GROUP[name]);
        }

        function syncFromNumber(name, val) {
            document.getElementById(name + '_range').value = val;
            updateTotal(FIELD_GROUP[name]);
        }

        // Initialise totals on load
        Object.keys(GROUPS).forEach(updateTotal);
    </script>
</body>
</html>