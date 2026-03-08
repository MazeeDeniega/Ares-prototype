<!DOCTYPE html>
<html>
<head>
    <title>Results — {{ $job->title }}</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        h2 { margin-bottom: 4px; }
        .pref-bar { font-size: 0.83em; color: #555; background: #f8f8f8; border: 1px solid #e5e7eb;
                    padding: 8px 12px; border-radius: 6px; margin-bottom: 18px; }
        .pref-bar a { color: #2563eb; }

        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; vertical-align: top; }
        th { background: #f2f2f2; white-space: nowrap; }

        /* Score columns */
        .score-cell { text-align: center; min-width: 140px; }
        .score-num  { font-size: 1.6em; font-weight: bold; line-height: 1; }
        .score-denom { font-size: 0.9em; color: #888; }
        .score-label { font-size: 0.75em; color: #555; margin-top: 3px; display: block; }
        .score-divider { border-top: 1px solid #eee; margin: 8px 0; }

        /* Progress bars for sub-scores */
        .breakdown { font-size: 0.78em; text-align: left; }
        .bar-row { display: flex; align-items: center; gap: 5px; margin: 3px 0; }
        .bar-label { width: 88px; color: #444; white-space: nowrap; }
        .bar-bg  { flex: 1; background: #e5e7eb; border-radius: 3px; height: 7px; }
        .bar-fill { height: 7px; border-radius: 3px; background: #2563eb; }
        .bar-val { width: 32px; text-align: right; color: #555; }

        /* Feedback */
        ul.fb { margin: 6px 0 0; padding-left: 14px; font-size: 0.8em; }
        ul.fb li { margin: 2px 0; }
        .fb-qual { color: #92400e; }
        .fb-pres { color: #1e40af; }

        .docs a { display: block; margin: 3px 0; font-size: 0.85em; color: #2563eb; }
        .rank-badge { display: inline-flex; align-items: center; justify-content: center;
                      background: #2563eb; color: white; border-radius: 50%;
                      width: 30px; height: 30px; font-weight: bold; font-size: 0.95em; }

        /* Hover tooltip */
        .hoverable { position: relative; cursor: default; display: inline-block; }
        .hoverable .score-num { text-decoration: underline dotted #aaa; text-underline-offset: 3px; }
        .hover-detail {
            display: none;
            position: absolute;
            z-index: 100;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
            min-width: 230px;
            left: 50%;
            transform: translateX(-50%);
            top: calc(100% + 6px);
            text-align: left;
            white-space: normal;
        }
        .hoverable:hover .hover-detail { display: block; }
        /* Caret arrow */
        .hover-detail::before {
            content: '';
            position: absolute;
            top: -6px; left: 50%;
            transform: translateX(-50%);
            border-width: 0 6px 6px;
            border-style: solid;
            border-color: transparent transparent #e5e7eb;
        }
        .hover-detail::after {
            content: '';
            position: absolute;
            top: -5px; left: 50%;
            transform: translateX(-50%);
            border-width: 0 5px 5px;
            border-style: solid;
            border-color: transparent transparent white;
        }
    </style>
</head>
<body>
    <p><a href="/screening/{{ $job->id }}">&larr; Back to Applicants</a></p>
    <h2>Ranking Results — {{ $job->title }}</h2>

    <div class="pref-bar">
        <strong>Active weights &mdash; Qualifications:</strong>
        Match {{ $pref->skills_weight ?? 35 }}% &middot;
        Experience {{ $pref->experience_weight ?? 20 }}% &middot;
        Education {{ $pref->education_weight ?? 25 }}% &middot;
        Cert {{ $pref->cert_weight ?? 10 }}%
        &ensp;|&ensp;
        <strong>Presentation:</strong>
        Formatting {{ $pref->formatting_weight ?? 25 }}% &middot;
        Language {{ $pref->language_weight ?? 25 }}% &middot;
        Conciseness {{ $pref->concise_weight ?? 25 }}% &middot;
        Organization {{ $pref->organization_weight ?? 25 }}%
        &ensp;|&ensp;
        <a href="/jobs/{{ $job->id }}/preferences">Edit preferences</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>Rank</th>
                <th>Candidate</th>
                <th>Contact</th>
                <th>Qualifications<br><small style="font-weight:normal">out of 100</small></th>
                <th>Presentation<br><small style="font-weight:normal">out of 100</small></th>
                <th>Details</th>
                <th>Documents</th>
            </tr>
        </thead>
        <tbody>
        @foreach($results as $index => $r)
        <tr>
            <td style="text-align:center">
                <span class="rank-badge">{{ $index + 1 }}</span>
            </td>

            <td>
                <strong>{{ $r['first_name'] ?? '' }} {{ $r['last_name'] ?? '' }}</strong>
                <br><small style="color:#666">{{ $r['engagement_type'] ?? '' }}</small>
            </td>

            <td style="font-size:0.87em">
                {{ $r['email'] }}<br>
                {{ $r['phone'] ?? '' }}<br>
                {{ $r['city'] ?? '' }}{{ ($r['city'] && $r['province']) ? ', ' : '' }}{{ $r['province'] ?? '' }}
            </td>

            {{-- QUALIFICATIONS SCORE --}}
            <td class="score-cell">
                <div class="hoverable">
                    <span class="score-num">{{ $r['qualifications_score'] }}</span><span class="score-denom">/100</span>

                    <div class="hover-detail">
                        @if(!empty($r['feedback']))
                            <ul class="fb" style="margin-top:0">
                                @foreach($r['feedback'] as $f)
                                    <li class="fb-qual">{{ $f }}</li>
                                @endforeach
                            </ul>
                        @else
                            <span style="font-size:0.82em;color:#16a34a">✓ No qualification issues</span>
                        @endif
                    </div>
                </div>
            </td>

            {{-- PRESENTATION SCORE --}}
            <td class="score-cell">
                <div class="hoverable">
                    <span class="score-num">{{ $r['presentation_score'] }}</span><span class="score-denom">/100</span>

                    <div class="hover-detail">
                        <div class="breakdown">
                            @foreach([
                                'Formatting'   => $r['formatting_score'],
                                'Language'     => $r['language_score'],
                                'Conciseness'  => $r['concise_score'],
                                'Organization' => $r['organization_score'],
                            ] as $label => $val)
                            <div class="bar-row">
                                <span class="bar-label">{{ $label }}</span>
                                <div class="bar-bg"><div class="bar-fill" style="width:{{ $val }}%"></div></div>
                                <span class="bar-val">{{ $val }}</span>
                            </div>
                            @endforeach
                        </div>

                        @if(!empty($r['layout_feedback']))
                            <div class="score-divider"></div>
                            <ul class="fb" style="margin-top:0">
                                @foreach($r['layout_feedback'] as $tips)
                                    @foreach($tips as $tip)
                                        <li class="fb-pres">{{ $tip }}</li>
                                    @endforeach
                                @endforeach
                            </ul>
                        @else
                            <div class="score-divider"></div>
                            <span style="font-size:0.82em;color:#16a34a">✓ No presentation issues</span>
                        @endif
                    </div>
                </div>
            </td>

            {{-- DETAILS --}}
            <td style="font-size:0.87em">
                <strong>Skills:</strong> {{ implode(', ', $r['skills']) ?: '—' }}<br>
                <strong>Experience:</strong> {{ $r['experience'] }} yr(s)<br>
                <strong>Education:</strong> {{ $r['highest_education'] ?? 'N/A' }}<br>
                <strong>Available:</strong> {{ $r['date_available'] ?? 'N/A' }}
            </td>

            {{-- DOCUMENTS --}}
            <td class="docs">
                @if($r['resume_path'])
                    <a href="/files/{{ $r['application_id'] }}/resume" target="_blank">📄 Resume</a>
                @endif
                @if($r['tor_path'])
                    <a href="/files/{{ $r['application_id'] }}/tor" target="_blank">📋 TOR</a>
                @endif
                @if($r['cert_path'])
                    <a href="/files/{{ $r['application_id'] }}/cert" target="_blank">🏅 Certificate</a>
                @endif
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>