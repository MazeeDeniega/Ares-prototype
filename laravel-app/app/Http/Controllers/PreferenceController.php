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
                'pref_formatting' => false,
                'pref_language' => false,
                'pref_conciseness' => false,
                'pref_organization' => false,
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
            'pref_formatting' => 'nullable|boolean',
            'pref_language' => 'nullable|boolean',
            'pref_conciseness' => 'nullable|boolean',
            'pref_organization' => 'nullable|boolean',
        ]);

        if ($totalScoring != 100) {
            return back()->withErrors(['Keyword + Semantic must equal 100%']);
        }

        // Convert checkbox values to boolean
        $request->merge([
            'pref_formatting' => (bool) $request->pref_formatting,
            'pref_language' => (bool) $request->pref_language,
            'pref_conciseness' => (bool) $request->pref_conciseness,
            'pref_organization' => (bool) $request->pref_organization,
        ]);

        // Validate max 2 layout categories
        $selected = collect([
            $request->pref_formatting,
            $request->pref_language,
            $request->pref_conciseness,
            $request->pref_organization,
        ])->filter()->count();

        if ($selected > 2) {
            return back()->withErrors(['You can select a maximum of 2 layout categories.']);
        }

        $pref = Auth::user()->preference;
        
        if (!$pref) {
            $pref = Preference::create(['user_id' => Auth::id()]);
        }
        
        $pref->update($request->all());
        return redirect('/recruiter')->with('success', 'Preferences saved!');
    }

    // Job-specific preferences
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
                'layout_weight' => $userPref->layout_weight ?? 10,
                'pref_formatting' => false,
                'pref_language' => false,
                'pref_conciseness' => false,
                'pref_organization' => false,
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
            'pref_formatting' => 'nullable|boolean',
            'pref_language' => 'nullable|boolean',
            'pref_conciseness' => 'nullable|boolean',
            'pref_organization' => 'nullable|boolean',
        ]);

        if ($totalScoring != 100) {
            return back()->withErrors(['Keyword + Semantic must equal 100%']);
        }

        // Convert checkbox values to boolean
        $request->merge([
            'pref_formatting' => (bool) $request->pref_formatting,
            'pref_language' => (bool) $request->pref_language,
            'pref_conciseness' => (bool) $request->pref_conciseness,
            'pref_organization' => (bool) $request->pref_organization,
        ]);

        // Validate max 2 layout categories
        $selected = collect([
            $request->pref_formatting,
            $request->pref_language,
            $request->pref_conciseness,
            $request->pref_organization,
        ])->filter()->count();

        if ($selected > 2) {
            return back()->withErrors(['You can select a maximum of 2 layout categories.']);
        }

        JobPreference::updateOrCreate(
            ['job_id' => $jobId],
            $request->all()
        );
        
        //debugging
        //  dd([
        // 'saved_data' => $request->all(),
        // 'pref_after_save' => JobPreference::where('job_id', $jobId)->first(),  
        // ]);

        return redirect('/recruiter')->with('success', 'Job preferences saved!');
    }
}