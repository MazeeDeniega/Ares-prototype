<!DOCTYPE html>
<html>
<head><title>My Applications</title></head>
<body style="padding:20px">
    <h2>My Applications</h2>
    <p><a href="/jobs">Browse More Jobs</a> | <form action="/logout" method="POST" style="display:inline">@csrf <button type="submit">Logout</button></form></p>

    <table border="1" cellpadding="5">
        <tr><th>Job Title</th><th>Company</th><th>Status</th><th>Applied At</th></tr>
        @foreach($applications as $app)
        <tr>
            <td>{{ $app->job->title }}</td>
            <td>{{ $app->job->user->name }}</td>
            <td>{{ $app->status }}</td>
            <td>{{ $app->created_at }}</td>
        </tr>
        @endforeach
    </table>
</body>
</html>