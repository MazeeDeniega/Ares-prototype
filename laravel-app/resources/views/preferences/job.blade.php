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
        
        <h3>Layout Score (Optional)</h3>
        <label>Layout Weight (%):</label><br>
        <input type="number" name="layout_weight" value="{{ $pref->layout_weight }}" min="0" max="100" placeholder="0 = disabled"><br><br>
        
        <button type="submit">Save</button>
    </form>
</body>
</html>