<!DOCTYPE html>
<html>
<head><title>Job Preferences</title></head>
<body style="padding:20px">
    <p><a href="/dashboard">&larr; Back</a></p>
    <h2>Preferences for: {{ $job->title }}</h2>
    
    @if($errors->any())
        <p style="color:red">{{ $errors->first() }}</p>
    @endif
    
    <form method="POST" action="/jobs/{{ $job->id }}/preferences">
        @csrf
        
        <h3>Scoring Method (Must equal 100%)</h3>
        <label>Keyword (TF-IDF) Weight (%):</label><br>
        <input type="number" name="keyword_weight" value="{{ $pref->keyword_weight }}" min="0" max="100"><br><br>
        
        <label>Semantic (AI) Weight (%):</label><br>
        <input type="number" name="semantic_weight" value="{{ $pref->semantic_weight }}" min="0" max="100"><br><br>
        
        <hr>
        
        <h3>Content Weights</h3>
        <label>Skills Weight (%):</label><br>
        <input type="number" name="skills_weight" value="{{ $pref->skills_weight }}" min="0" max="100"><br><br>
        
        <label>Experience Weight (%):</label><br>
        <input type="number" name="experience_weight" value="{{ $pref->experience_weight }}" min="0" max="100"><br><br>
        
        <label>Education Weight (%):</label><br>
        <input type="number" name="education_weight" value="{{ $pref->education_weight }}" min="0" max="100"><br><br>
        
        <label>Certification Weight (%):</label><br>
        <input type="number" name="cert_weight" value="{{ $pref->cert_weight }}" min="0" max="100"><br><br>
        
        <hr>
        
        <h3>Layout Categories (Select Max 2)</h3>
        <label><input type="checkbox" name="pref_formatting" value="1" {{ $pref->pref_formatting ? 'checked' : '' }}> Formatting and Visuals</label><br>
        <small>Space between sections, B&W, Font consistency, Indents</small><br><br>
        
        <label><input type="checkbox" name="pref_language" value="1" {{ $pref->pref_language ? 'checked' : '' }}> Language Quality</label><br>
        <small>No typos, Coherent, Formal words, Action verbs</small><br><br>
        
        <label><input type="checkbox" name="pref_conciseness" value="1" {{ $pref->pref_conciseness ? 'checked' : '' }}> Conciseness</label><br>
        <small>Low word count, Pages amount, Relevant info</small><br><br>
        
        <label><input type="checkbox" name="pref_organization" value="1" {{ $pref->pref_organization ? 'checked' : '' }}> Organization and Structure</label><br>
        <small>Font hierarchy, Margins, Sectioned info, Logical progression</small><br><br>
        
        <hr>
        
        <h3>Layout Score Weight (Optional)</h3>
        <label>Layout Weight (%):</label><br>
        <input type="number" name="layout_weight" value="{{ $pref->layout_weight }}" min="0" max="100" placeholder="0 = disabled"><br><br>
        
        <button type="submit">Save</button>
    </form>
</body>
</html>