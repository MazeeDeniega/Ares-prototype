<!DOCTYPE html>
<html>
<head>
    <title>Screening</title>
    <style>
        body { font-family: sans-serif; padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background: #f2f2f2; }
    </style>
</head>
<body>
    <p><a href="/dashboard">&larr; Back to Dashboard</a></p>
    <h2>Resume Screening</h2>

    @if(session('error'))<p style="color:red">{{ session('error') }}</p>@endif

    <form method="POST" action="{{ route('screen') }}" enctype="multipart/form-data">
        @csrf
        <label><strong>Select Job:</strong></label><br>
        <select name="job_select" id="jobSelect" onchange="toggleJobInput()">
            <option value="">-- Select Saved Job --</option>
            @isset($jobs)
                @foreach($jobs as $job)
                    <option value="{{ $job->description }}">{{ $job->title }}</option>
                @endforeach
            @endisset
        </select>
        <br><br>
        <label><strong>Or Enter Job Description Manually:</strong></label><br>
        <textarea name="job" id="jobInput" rows="6" cols="60"></textarea><br><br>

        <label>Upload Resumes (PDF):</label><br>
        <input type="file" name="resume_pdf[]" multiple accept="application/pdf" required><br><br>
        <button type="submit">Evaluate Candidates</button>
    </form>

    <script>
        function toggleJobInput() {
            var select = document.getElementById('jobSelect');
            var input = document.getElementById('jobInput');
            if(select.value) {
                input.value = select.value;
            }
        }
    </script>

    @if(isset($results))
    <hr>
    <h3>Candidate Ranking</h3>
    <table>
        <tr><th>Rank</th><th>Candidate</th><th>Score</th><th>Skills</th><th>Experience</th><th>Feedback</th></tr>
        @foreach($results as $index => $r)
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>{{ $r['name'] }}</td>
            <td>{{ $r['score'] }}%</td>
            <td>{{ implode(', ', $r['skills']) }}</td>
            <td>{{ $r['experience'] }} yrs</td>
            <td>
                <ul>@foreach($r['feedback'] as $f)<li>{{ $f }}</li>@endforeach</ul>
            </td>
        </tr>
        @endforeach
    </table>
    @endif
</body>
</html>