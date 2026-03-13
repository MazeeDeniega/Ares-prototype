<!DOCTYPE html>
<html>
<head><title>Jobs</title></head>
<body>
    <script>
    window.__LARAVEL__ = {
        @auth
        user: {
            name: "{{ Auth::user()->name }}",
            role: "{{ Auth::user()->role }}"
        },
        csrf: "{{ csrf_token() }}",
        @else
        user: null,
        csrf: null,
        @endauth
        jobs: @json($jobs->load('user'))
    };
    </script>
    @viteReactRefresh
    @vite(['resources/js/app.jsx'])
    <div id="app"></div>
    {{-- <h2>Available Jobs</h2>
    @auth
        <p>Welcome, {{ Auth::user()->name }} | <a href="/my-applications">My Applications</a> | <form action="/logout" method="POST" style="display:inline">@csrf <button type="submit">Logout</button></form></p>
    @else
        <p><a href="/login">Login to Apply</a> | <a href="/register">Register</a></p>
    @endauth

    <hr>
    
    @if($jobs->count() > 0)
        @foreach($jobs as $job)
            <div style="border:1px solid #ccc; padding:10px; margin:10px 0">
                <h3>{{ $job->title }}</h3>
                <p><strong>Posted by:</strong> {{ $job->user->name }}</p>
                <p>{{ Str::limit($job->description, 100) }}</p>
                <a href="/jobs/{{ $job->id }}">View Details</a> | 
                <a href="/apply/{{ $job->id }}">Apply Now</a>
            </div>
        @endforeach
    @else
        <p>No jobs available.</p>
    @endif --}}
</body>
</html>