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
        .score-cell { text-align: center; min-width: 150px; }
        .score-num   { font-size: 1.8em; font-weight: bold; line-height: 1; }
        .score-denom { font-size: 0.9em; color: #888; }
        .score-divider { border-top: 1px solid #eee; margin: 8px 0; }
        .breakdown { font-size: 0.78em; text-align: left; }
        .bar-row { display: flex; align-items: center; gap: 5px; margin: 3px 0; }
        .bar-label { width: 96px; color: #444; white-space: nowrap; }
        .bar-bg  { flex: 1; background: #e5e7eb; border-radius: 3px; height: 7px; }
        .bar-fill-blue   { height: 7px; border-radius: 3px; background: #2563eb; }
        .bar-fill-purple { height: 7px; border-radius: 3px; background: #7c3aed; }
        .bar-val { width: 36px; text-align: right; color: #555; }
        .sub-header { font-size: 0.72em; font-weight: bold; color: #6b7280;
                      text-transform: uppercase; letter-spacing: 0.05em; margin: 8px 0 4px; }
        ul.fb { margin: 4px 0 0; padding-left: 14px; font-size: 0.8em; }
        ul.fb li { margin: 2px 0; }
        .fb-qual { color: #92400e; }
        .fb-pres { color: #5b21b6; }
        .docs a { display: block; margin: 3px 0; font-size: 0.85em; color: #2563eb; }
        .rank-badge { display: inline-flex; align-items: center; justify-content: center;
                      background: #2563eb; color: white; border-radius: 50%;
                      width: 30px; height: 30px; font-weight: bold; font-size: 0.95em; }
        .hoverable { position: relative; cursor: default; display: inline-block; }
        .hoverable .score-num { text-decoration: underline dotted #aaa; text-underline-offset: 3px; }
        .hover-detail {
            display: none; position: absolute; z-index: 100;
            background: white; border: 1px solid #e5e7eb; border-radius: 8px;
            padding: 12px 14px; box-shadow: 0 4px 16px rgba(0,0,0,0.13);
            min-width: 260px; left: 50%; transform: translateX(-50%);
            top: calc(100% + 6px); text-align: left; white-space: normal;
        }
        .hoverable:hover .hover-detail { display: block; }
        .hover-detail::before {
            content: ''; position: absolute; top: -6px; left: 50%;
            transform: translateX(-50%); border-width: 0 6px 6px;
            border-style: solid; border-color: transparent transparent #e5e7eb;
        }
        .hover-detail::after {
            content: ''; position: absolute; top: -5px; left: 50%;
            transform: translateX(-50%); border-width: 0 5px 5px;
            border-style: solid; border-color: transparent transparent white;
        }
    </style>
</head>
<body>
    <p><a href="/screening/{{ $job->id }}">&larr; Back to Applicants</a></p>
    <h2>Ranking Results — {{ $job->title }}</h2>

    <div class="pref-bar">
        <strong>Final Score:</strong>
        Qualifications {{ $pref->qual_weight ?? 100 }}% &middot;
        Presentation {{ $pref->pres_weight ?? 0 }}%
        &ensp;|&ensp;
        <strong>Qualifications:</strong>
        Skills {{ $pref->skills_weight ?? 35 }}% &middot;
        Experience {{ $pref->experience_weight ?? 20 }}% &middot;
        Education {{ $pref->education_weight ?? 25 }}% &middot;
        Cert {{ $pref->cert_weight ?? 10 }}%
        &ensp;|&ensp;
        <strong>Presentation:</strong>
        @php
            $presLabels = [];
            if (($pref->formatting_weight   ?? 0) > 0) $presLabels[] = 'Formatting '   . $pref->formatting_weight   . '%';
            if (($pref->language_weight     ?? 0) > 0) $presLabels[] = 'Language '     . $pref->language_weight     . '%';
            if (($pref->concise_weight      ?? 0) > 0) $presLabels[] = 'Conciseness '  . $pref->concise_weight      . '%';
            if (($pref->organization_weight ?? 0) > 0) $presLabels[] = 'Organization ' . $pref->organization_weight . '%';
        @endphp
        {{ implode(' · ', $presLabels) ?: 'all equal (25% each)' }}
        &ensp;|&ensp;
        <a href="/jobs/{{ $job->id }}/preferences">Edit preferences</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>Rank</th>
                <th>Candidate</th>
                <th>Contact</th>
                <th>Final Score<br><small style="font-weight:normal">out of 100</small></th>
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

            {{-- FINAL SCORE — hover reveals full breakdown --}}
            <td class="score-cell">
                <div class="hoverable">
                    <span class="score-num">{{ $r['final_score'] }}</span><span class="score-denom">/100</span>

                    <div class="hover-detail">

                        {{-- Qualifications --}}
                        <div class="sub-header">
                            Qualifications — {{ $r['qualifications_score'] }}/100
                            <span style="font-weight:normal;color:#9ca3af">(×{{ $pref->qual_weight ?? 100 }}%)</span>
                        </div>
                        <div class="breakdown">
                            @foreach([
                                'Skills'      => $r['qualifications_score'],
                                'Experience'  => min(($r['experience'] / 5) * 100, 100),
                            ] as $label => $val)
                            <div class="bar-row">
                                <span class="bar-label">{{ $label }}</span>
                                <div class="bar-bg"><div class="bar-fill-blue" style="width:{{ $val }}%"></div></div>
                                <span class="bar-val">{{ round($val) }}</span>
                            </div>
                            @endforeach
                        </div>
                        @if(!empty($r['feedback']))
                            <ul class="fb">
                                @foreach($r['feedback'] as $f)
                                    <li class="fb-qual">{{ $f }}</li>
                                @endforeach
                            </ul>
                        @endif

                        <div class="score-divider"></div>

                        {{-- Presentation --}}
                        <div class="sub-header">
                            Presentation — {{ $r['presentation_score'] }}/100
                            <span style="font-weight:normal;color:#9ca3af">(×{{ $pref->pres_weight ?? 0 }}%)</span>
                        </div>
                        <div class="breakdown">
                            @foreach([
                                'Formatting'   => ['val' => $r['formatting_score'],   'w' => $pref->formatting_weight   ?? 25],
                                'Language'     => ['val' => $r['language_score'],     'w' => $pref->language_weight     ?? 25],
                                'Conciseness'  => ['val' => $r['concise_score'],      'w' => $pref->concise_weight      ?? 25],
                                'Organization' => ['val' => $r['organization_score'], 'w' => $pref->organization_weight ?? 25],
                            ] as $label => $item)
                            @if($item['w'] > 0)
                            <div class="bar-row">
                                <span class="bar-label">{{ $label }}</span>
                                <div class="bar-bg"><div class="bar-fill-purple" style="width:{{ $item['val'] }}%"></div></div>
                                <span class="bar-val">{{ $item['val'] }}</span>
                            </div>
                            @endif
                            @endforeach
                        </div>
                        @if(!empty($r['layout_feedback']))
                            <ul class="fb" style="margin-top:4px">
                                @foreach($r['layout_feedback'] as $tips)
                                    @foreach($tips as $tip)
                                        <li class="fb-pres">{{ $tip }}</li>
                                    @endforeach
                                @endforeach
                            </ul>
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