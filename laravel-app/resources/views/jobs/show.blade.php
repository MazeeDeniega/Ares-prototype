<!DOCTYPE html>
<html>
<head><title>{{ $job->title }}</title></head>
<body style="padding:20px">
    <p><a href="/jobs">&larr; Back to Jobs</a></p>
    
    <h2>{{ $job->title }}</h2>
    <p><strong>Recruiter:</strong> {{ $job->user->name }}</p>
    <hr>
    <p>{{ $job->description }}</p>
    <hr>
    
    @auth
        @if(Auth::user()->role === 'applicant')
            <a href="/apply/{{ $job->id }}" style="background:green; color:white; padding:10px 20px; text-decoration:none;">Apply Now</a>
        @endif
    @else
        <p><a href="/login">Login to Apply</a></p>
    @endauth
</body>
</html>