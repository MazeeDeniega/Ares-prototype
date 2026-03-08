<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Preference;
use App\Models\Job;
use App\Models\JobPreference;

class PreferenceController extends Controller
{
    public function edit()
    {
        $pref = Auth::user()->preference;

        if (!$pref) {
            $pref = Preference::create([
                'user_id'             => Auth::id(),
                'keyword_weight'      => 40,
                'semantic_weight'     => 60,
                'skills_weight'       => 35,
                'experience_weight'   => 20,
                'education_weight'    => 25,
                'cert_weight'         => 10,
                'layout_weight'       => 0,
                'formatting_weight'   => 25,
                'language_weight'     => 25,
                'concise_weight'      => 25,
                'organization_weight' => 25,
            ]);
        }

        return view('preferences.edit', compact('pref'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'keyword_weight'      => 'required|integer|min:0|max:100',
            'semantic_weight'     => 'required|integer|min:0|max:100',
            'skills_weight'       => 'required|integer|min:0|max:100',
            'experience_weight'   => 'required|integer|min:0|max:100',
            'education_weight'    => 'required|integer|min:0|max:100',
            'cert_weight'         => 'required|integer|min:0|max:100',
            'layout_weight'       => 'nullable|integer|min:0|max:100',
            'formatting_weight'   => 'required|integer|min:0|max:100',
            'language_weight'     => 'required|integer|min:0|max:100',
            'concise_weight'      => 'required|integer|min:0|max:100',
            'organization_weight' => 'required|integer|min:0|max:100',
        ]);

        if (($request->keyword_weight + $request->semantic_weight) !== 100) {
            return back()->withErrors(['Keyword + Semantic weights must equal 100%.']);
        }

        $qualTotal = $request->skills_weight + $request->experience_weight
                   + $request->education_weight + $request->cert_weight;
        if ($qualTotal !== 100) {
            return back()->withErrors(['Skills + Experience + Education + Certification weights must equal 100%.']);
        }

        $presTotal = $request->formatting_weight + $request->language_weight
                   + $request->concise_weight + $request->organization_weight;
        if ($presTotal !== 100) {
            return back()->withErrors(['Formatting + Language + Conciseness + Organization weights must equal 100%.']);
        }

        $pref = Auth::user()->preference
            ?? Preference::create(['user_id' => Auth::id()]);

        $pref->update($request->only([
            'keyword_weight', 'semantic_weight',
            'skills_weight', 'experience_weight', 'education_weight', 'cert_weight',
            'layout_weight',
            'formatting_weight', 'language_weight', 'concise_weight', 'organization_weight',
        ]));

        return redirect('/recruiter')->with('success', 'Default preferences saved!');
    }

    public function editJobPreference($jobId)
    {
        $job = Job::findOrFail($jobId);
        if ($job->user_id != Auth::id()) abort(403);

        $pref = JobPreference::where('job_id', $jobId)->first();

        if (!$pref) {
            $userPref = Auth::user()->preference;
            $pref = JobPreference::create([
                'job_id'              => $jobId,
                'keyword_weight'      => $userPref->keyword_weight      ?? 40,
                'semantic_weight'     => $userPref->semantic_weight     ?? 60,
                'skills_weight'       => $userPref->skills_weight       ?? 35,
                'experience_weight'   => $userPref->experience_weight   ?? 20,
                'education_weight'    => $userPref->education_weight    ?? 25,
                'cert_weight'         => $userPref->cert_weight         ?? 10,
                'layout_weight'       => $userPref->layout_weight       ?? 0,
                'formatting_weight'   => $userPref->formatting_weight   ?? 25,
                'language_weight'     => $userPref->language_weight     ?? 25,
                'concise_weight'      => $userPref->concise_weight      ?? 25,
                'organization_weight' => $userPref->organization_weight ?? 25,
            ]);
        }

        return view('preferences.job', compact('pref', 'job'));
    }

    public function updateJobPreference(Request $request, $jobId)
    {
        $job = Job::findOrFail($jobId);
        if ($job->user_id != Auth::id()) abort(403);

        $request->validate([
            'keyword_weight'      => 'required|integer|min:0|max:100',
            'semantic_weight'     => 'required|integer|min:0|max:100',
            'skills_weight'       => 'required|integer|min:0|max:100',
            'experience_weight'   => 'required|integer|min:0|max:100',
            'education_weight'    => 'required|integer|min:0|max:100',
            'cert_weight'         => 'required|integer|min:0|max:100',
            'layout_weight'       => 'nullable|integer|min:0|max:100',
            'formatting_weight'   => 'required|integer|min:0|max:100',
            'language_weight'     => 'required|integer|min:0|max:100',
            'concise_weight'      => 'required|integer|min:0|max:100',
            'organization_weight' => 'required|integer|min:0|max:100',
        ]);

        if (($request->keyword_weight + $request->semantic_weight) !== 100) {
            return back()->withErrors(['Keyword + Semantic weights must equal 100%.']);
        }

        $qualTotal = $request->skills_weight + $request->experience_weight
                   + $request->education_weight + $request->cert_weight;
        if ($qualTotal !== 100) {
            return back()->withErrors(['Skills + Experience + Education + Certification weights must equal 100%.']);
        }

        $presTotal = $request->formatting_weight + $request->language_weight
                   + $request->concise_weight + $request->organization_weight;
        if ($presTotal !== 100) {
            return back()->withErrors(['Formatting + Language + Conciseness + Organization weights must equal 100%.']);
        }

        JobPreference::updateOrCreate(
            ['job_id' => $jobId],
            $request->only([
                'keyword_weight', 'semantic_weight',
                'skills_weight', 'experience_weight', 'education_weight', 'cert_weight',
                'layout_weight',
                'formatting_weight', 'language_weight', 'concise_weight', 'organization_weight',
            ])
        );

        return redirect('/recruiter')->with('success', 'Job preferences saved!');
    }
}