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
                'user_id' => Auth::id(),
                'skills_weight' => 35,
                'experience_weight' => 20,
                'education_weight' => 25,
                'cert_weight' => 10,
                'keyword_weight' => 40,
                'semantic_weight' => 60,
                'layout_weight' => 0,
            ]);
        }
        
        return view('preferences.edit', compact('pref'));
    }

    public function update(Request $request)
    {
        $totalScoring = ($request->keyword_weight ?? 0) + ($request->semantic_weight ?? 0);

        $request->validate([
            'keyword_weight' => 'required|integer|min:0|max:100',
            'semantic_weight' => 'required|integer|min:0|max:100',
            'layout_weight' => 'required|integer|min:0|max:100',
        ]);

        if ($totalScoring != 100) {
            return back()->withErrors(['Keyword + Semantic must equal 100%']);
        }

        $pref = Auth::user()->preference;
        
        if (!$pref) {
            $pref = Preference::create(['user_id' => Auth::id()]);
        }
        
        $pref->update($request->all());
        return redirect('/dashboard')->with('success', 'Preferences saved!');
    }

    public function editJobPreference($jobId)
    {
        $job = Job::findOrFail($jobId);
        if ($job->user_id != Auth::id()) abort(403);

        $pref = JobPreference::where('job_id', $jobId)->first();
        
        if (!$pref) {
            $userPref = Auth::user()->preference;
            $pref = JobPreference::create([
                'job_id' => $jobId,
                'keyword_weight' => $userPref->keyword_weight ?? 40,
                'semantic_weight' => $userPref->semantic_weight ?? 60,
                'skills_weight' => $userPref->skills_weight ?? 35,
                'experience_weight' => $userPref->experience_weight ?? 20,
                'education_weight' => $userPref->education_weight ?? 25,
                'cert_weight' => $userPref->cert_weight ?? 10,
                'layout_weight' => $userPref->layout_weight ?? 0,
            ]);
        }
        
        return view('preferences.job', compact('pref', 'job'));
    }

    public function updateJobPreference(Request $request, $jobId)
    {
        $job = Job::findOrFail($jobId);
        if ($job->user_id != Auth::id()) abort(403);

        $totalScoring = ($request->keyword_weight ?? 0) + ($request->semantic_weight ?? 0);

        $request->validate([
            'keyword_weight' => 'required|integer|min:0|max:100',
            'semantic_weight' => 'required|integer|min:0|max:100',
            'layout_weight' => 'required|integer|min:0|max:100',
        ]);

        if ($totalScoring != 100) {
            return back()->withErrors(['Keyword + Semantic must equal 100%']);
        }

        JobPreference::updateOrCreate(
            ['job_id' => $jobId],
            $request->all()
        );
        
        return redirect('/dashboard')->with('success', 'Job preferences saved!');
    }
}