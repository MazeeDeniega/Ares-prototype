<!DOCTYPE html>
<html>
<head>
    <title>Results - {{ $job->title }}</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; vertical-align: top; }
        th { background: #f2f2f2; }
        .score { font-size: 1.2em; font-weight: bold; }
        .documents a { display: block; margin: 5px 0; }
    </style>
</head>
<body>
    <p><a href="/screening/{{ $job->id }}">&larr; Back to Applicants</a></p>
    <h2>Ranking Results - {{ $job->title }}</h2>

    <table>
        <tr>
            <th>Rank</th>
            <th>Candidate</th>
            <th>Contact</th>
            <th>Score</th>
            <th>TF-IDF</th>
            <th>Semantic</th>
            <th>Details</th>
            <th>Documents</th>
        </tr>
        @foreach($results as $index => $r)
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>
                <strong>{{ $r['first_name'] ?? '' }} {{ $r['last_name'] ?? '' }}</strong>
                <br><small>{{ $r['engagement_type'] ?? '' }}</small>
            </td>
            <td>
                {{ $r['email'] }}<br>
                {{ $r['phone'] ?? '' }}<br>
                {{ $r['city'] ?? '' }}, {{ $r['province'] ?? '' }}
            </td>
            <td class="score">{{ $r['score'] }}%</td>
            <td>{{ $r['tfidf_similarity'] ?? '' }}%</td>
            <td>{{ $r['semantic_similarity'] ?? '' }}%</td>

            <td>
                <strong>Skills:</strong> {{ implode(', ', $r['skills']) }}<br>
                <strong>Experience:</strong> {{ $r['experience'] }} yrs<br>
                <strong>Education:</strong> {{ $r['highest_education'] ?? 'N/A' }}<br>
                <strong>Available:</strong> {{ $r['date_available'] ?? 'N/A' }}
            </td>
            <td class="documents">
                @if($r['resume_path'])
                    <a href="/storage/{{ $r['resume_path'] }}" target="_blank"> Resume</a>
                @endif
                @if($r['tor_path'])
                    <a href="/storage/{{ $r['tor_path'] }}" target="_blank"> TOR</a>
                @endif
                @if($r['cert_path'])
                    <a href="/storage/{{ $r['cert_path'] }}" target="_blank"> Certificate</a>
                @endif
            </td>
        </tr>
        @endforeach
    </table>
</body>
</html>