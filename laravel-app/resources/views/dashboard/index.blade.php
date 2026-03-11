<!DOCTYPE html>
<html>
<head>
    @viteReactRefresh
    @vite(['resources/js/app.jsx'])
</head>
<body>
    <script>
    window.__LARAVEL__ = {
        user: {
            name: "{{ Auth::user()->name }}",
            role: "{{ Auth::user()->role }}"
        },
        // jobs: @json($jobs->load('applications')),
        jobs: @json($jobs),
        csrf: "{{ csrf_token() }}"
    };
    </script>
    <div id="app"></div>
    {{-- <h2>Recruiter Dashboard</h2>
    <p>Welcome, {{ Auth::user()->name }} | <a href="/preferences/edit">Preferences</a> | <form action="/logout" method="POST" style="display:inline">@csrf 
        <button type="submit">Logout</button></form></p>

    <hr>
    <h3>Add New Job</h3>
    <form method="POST" action="/jobs">
        @csrf
        <input type="text" name="title" placeholder="Job Title (e.g. Backend Dev)" required><br><br>
        <textarea name="description" rows="4" cols="50" placeholder="Job Description" required></textarea><br><br>
        <button type="submit">Save Job</button>
    </form>

    <hr>
    <h3>Your Jobs (Click to Screen Applicants)</h3>
    <table border="1" cellpadding="5">
        <tr>
            <th>Job Title</th>
            <th>Description</th>
            <th>Applicants</th>
            <th>Action</th>
        </tr>
        @foreach($jobs as $job)
        <tr style="cursor:pointer; background-color:#f9f9f9;" onclick="window.location='/screening/{{ $job->id }}'">
            <td>{{ $job->title }}</td>
            <td>{{ Str::limit($job->description, 50) }}</td>
            <td>{{ $job->applications->count() }}</td>
            <td onclick="event.stopPropagation();">
                <a href="/jobs/{{ $job->id }}/preferences">edit preference</a>
                <form action="/jobs/{{ $job->id }}" method="POST">@csrf @method('DELETE') <button type="submit">Delete</button></form>
            </td>
        </tr>
        @endforeach
    </table> --}}
</body>
</html>