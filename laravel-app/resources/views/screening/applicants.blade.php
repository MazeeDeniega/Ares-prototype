<!DOCTYPE html>
<html>
<head><title>Screening - {{ $job->title }}</title></head>
<body>
    <script>
    window.__LARAVEL__ = {
        csrf: "{{ csrf_token() }}",
        job: @json($job->load(['applications']))
    };
    </script>
    @viteReactRefresh
    @vite(['resources/js/app.jsx'])
    <div id="app"></div>
    {{-- <p><a href="/recruiter">&larr; Back to Dashboard</a></p>
    <h2>Applicants for: {{ $job->title }}</h2>

    <hr>

    @if($job->applications->count() > 0)
        <form method="POST" action="/screening/{{ $job->id }}/evaluate">
            @csrf
            <button type="submit" style="padding:10px 20px; background:green; color:white;">Evaluate All Applicants</button>
        </form>

        <h3>Applicant List ({{ $job->applications->count() }})</h3>
        <table border="1" cellpadding="5">
            <tr>
                <th>#</th>
                <th>Candidate Name</th>
                <th>Email</th>
                <th>Status</th>
                <th>Resume</th>
            </tr>
            @foreach($job->applications as $index => $app)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $app->first_name }} {{ $app->last_name }}</td>
                <td>{{ $app->email }}</td>
                <td>{{ $app->status }}</td>
                <td>
                    @if($app->resume_path)
                        <a href="/files/{{ $app->id }}/resume" target="_blank">View Resume</a>
                    @else
                        No Resume
                    @endif
                </td>
            </tr>
            @endforeach
        </table>
    @else
        <p>No applicants yet.</p>
    @endif --}}
</body>
</html>