<!DOCTYPE html>
<html>
<head><title>Apply for {{ $job->title }}</title></head>
<body style="padding:20px">
    <p><a href="/jobs">&larr; Back to Jobs</a></p>
    <h2>Apply for: {{ $job->title }}</h2>
    <p><strong>Company:</strong> {{ $job->user->name }}</p>
    <hr>
    
    @if(session('success'))
        <p style="color:green">{{ session('success') }}</p>
    @endif
    
    <form method="POST" action="/apply/{{ $job->id }}" enctype="multipart/form-data">
        @csrf
        
        <h3>Personal Information</h3>
        <label>First Name *</label><br>
        <input type="text" name="first_name" required><br><br>
        
        <label>Last Name *</label><br>
        <input type="text" name="last_name" required><br><br>
        
        <label>Phone *</label><br>
        <input type="text" name="phone" required><br><br>
        
        <label>Address *</label><br>
        <input type="text" name="address" required><br><br>
        
        <label>City *</label><br>
        <input type="text" name="city" required><br><br>
        
        <label>Province *</label><br>
        <input type="text" name="province" required><br><br>
        
        <label>Postal Code *</label><br>
        <input type="text" name="postal_code" required><br><br>
        
        <label>Country *</label><br>
        <input type="text" name="country" required><br><br>
        
        <h3>Documents</h3>
        <label>Resume (PDF) *</label><br>
        <input type="file" name="resume" accept="application/pdf" required><br><br>
        
        <label>TOR (PDF) - Optional</label><br>
        <input type="file" name="tor" accept="application/pdf"><br><br>
        
        <label>Certificates (PDF) - Optional</label><br>
        <input type="file" name="cert" accept="application/pdf"><br><br>
        
        <h3>Employment Details</h3>
        <label>Date Available</label><br>
        <input type="date" name="date_available"><br><br>
        
        <label>Desired Pay</label><br>
        <input type="text" name="desired_pay"><br><br>
        
        <label>Highest Education</label><br>
        <select name="highest_education">
            <option value="">Select</option>
            <option value="high_school">High School</option>
            <option value="associate">Associate</option>
            <option value="bachelor">Bachelor</option>
            <option value="master">Master</option>
            <option value="doctorate">Doctorate</option>
        </select><br><br>
        
        <label>College/University</label><br>
        <input type="text" name="college_university"><br><br>
        
        <label>Who referred you?</label><br>
        <input type="text" name="referred_by"><br><br>
        
        <label>References</label><br>
        <textarea name="references" rows="3"></textarea><br><br>
        
        <label>Engagement Type</label><br>
        <select name="engagement_type">
            <option value="">Select</option>
            <option value="full_time">Full Time</option>
            <option value="part_time">Part Time</option>
        </select><br><br>
        
        <button type="submit">Submit Application</button>
    </form>
</body>
</html>