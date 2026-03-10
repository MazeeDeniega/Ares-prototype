<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Preference;
use App\Models\Job;
use App\Models\JobPreference;

class PreferenceController extends Controller
{
    // ----------------------------------------------------------------
    // Auto-distribute 100% equally among checked presentation categories.
    // If none checked, defaults to 25/25/25/25 (all equal).
    // ----------------------------------------------------------------
    private function distributePresentationWeights(Request $request): array
    {
        $keys = ['formatting', 'language', 'conciseness', 'organization'];
        $checked = array_filter($keys, fn($k) => $request->boolean('pref_' . $k));
        $count   = count($checked);

        if ($count === 0) {
            return [
                'formatting_weight'   => 25,
                'language_weight'     => 25,
                'concise_weight'      => 25,
                'organization_weight' => 25,
                'pref_formatting'     => false,
                'pref_language'       => false,
                'pref_conciseness'    => false,
                'pref_organization'   => false,
            ];
        }

        $base      = intdiv(100, $count);
        $remainder = 100 % $count;
        $checked   = array_values($checked);

        $weights = [];
        foreach ($keys as $i => $key) {
            $active = in_array($key, $checked);
            $w = 0;
            if ($active) {
                $w = $base + ($remainder > 0 ? 1 : 0);
                $remainder--;
            }
            $colKey = $key === 'conciseness' ? 'concise_weight' : $key . '_weight';
            $weights[$colKey]          = $w;
            $weights['pref_' . $key]   = $active;
        }

        return $weights;
    }

    public function edit()
    {
        $pref = Auth::user()->preference;

        if (!$pref) {
            $pref = Preference::create([
                'user_id'             => Auth::id(),
                'keyword_weight'      => 40,
                'semantic_weight'     => 60,
                'qual_weight'         => 100,
                'layout_weight'       => 0,
                'skills_weight'       => 35,
                'experience_weight'   => 20,
                'education_weight'    => 25,
                'cert_weight'         => 10,
                'formatting_weight'   => 25,
                'language_weight'     => 25,
                'concise_weight'      => 25,
                'organization_weight' => 25,
                'pref_formatting'     => false,
                'pref_language'       => false,
                'pref_conciseness'    => false,
                'pref_organization'   => false,
            ]);
        }

        return view('preferences.edit', compact('pref'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'qual_weight'         => 'required|integer|min:0|max:100',
            'keyword_weight'      => 'required|integer|min:0|max:100',
            'semantic_weight'     => 'required|integer|min:0|max:100',
            'skills_weight'       => 'required|integer|min:0|max:100',
            'experience_weight'   => 'required|integer|min:0|max:100',
            'education_weight'    => 'required|integer|min:0|max:100',
            'cert_weight'         => 'required|integer|min:0|max:100',
        ]);

        if (($request->keyword_weight + $request->semantic_weight) !== 100) {
            return back()->withErrors(['TF-IDF + Semantic weights must equal 100%.']);
        }

        $qualTotal = $request->skills_weight + $request->experience_weight
                   + $request->education_weight + $request->cert_weight;
        if ($qualTotal !== 100) {
            return back()->withErrors(['Skills + Experience + Education + Cert weights must equal 100%.']);
        }

        $qualWeight = (int) $request->qual_weight;
        $presWeight = 100 - $qualWeight;

        $presWeights = $this->distributePresentationWeights($request);

        $pref = Auth::user()->preference
            ?? Preference::create(['user_id' => Auth::id()]);

        $pref->update(array_merge(
            $request->only([
                'keyword_weight', 'semantic_weight',
                'skills_weight', 'experience_weight', 'education_weight', 'cert_weight',
            ]),
            ['qual_weight' => $qualWeight, 'layout_weight' => $presWeight],
            $presWeights
        ));

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
                'qual_weight'         => $userPref->qual_weight         ?? 100,
                'layout_weight'       => $userPref->layout_weight       ?? 0,
                'skills_weight'       => $userPref->skills_weight       ?? 35,
                'experience_weight'   => $userPref->experience_weight   ?? 20,
                'education_weight'    => $userPref->education_weight    ?? 25,
                'cert_weight'         => $userPref->cert_weight         ?? 10,
                'formatting_weight'   => $userPref->formatting_weight   ?? 25,
                'language_weight'     => $userPref->language_weight     ?? 25,
                'concise_weight'      => $userPref->concise_weight      ?? 25,
                'organization_weight' => $userPref->organization_weight ?? 25,
                'pref_formatting'     => $userPref->pref_formatting     ?? false,
                'pref_language'       => $userPref->pref_language       ?? false,
                'pref_conciseness'    => $userPref->pref_conciseness    ?? false,
                'pref_organization'   => $userPref->pref_organization   ?? false,
            ]);
        }

        return view('preferences.job', compact('pref', 'job'));
    }

    public function updateJobPreference(Request $request, $jobId)
    {
        $job = Job::findOrFail($jobId);
        if ($job->user_id != Auth::id()) abort(403);

        $request->validate([
            'qual_weight'         => 'required|integer|min:0|max:100',
            'keyword_weight'      => 'required|integer|min:0|max:100',
            'semantic_weight'     => 'required|integer|min:0|max:100',
            'skills_weight'       => 'required|integer|min:0|max:100',
            'experience_weight'   => 'required|integer|min:0|max:100',
            'education_weight'    => 'required|integer|min:0|max:100',
            'cert_weight'         => 'required|integer|min:0|max:100',
        ]);

        if (($request->keyword_weight + $request->semantic_weight) !== 100) {
            return back()->withErrors(['TF-IDF + Semantic weights must equal 100%.']);
        }

        $qualTotal = $request->skills_weight + $request->experience_weight
                   + $request->education_weight + $request->cert_weight;
        if ($qualTotal !== 100) {
            return back()->withErrors(['Skills + Experience + Education + Cert weights must equal 100%.']);
        }

        $qualWeight  = (int) $request->qual_weight;
        $presWeight  = 100 - $qualWeight;
        $presWeights = $this->distributePresentationWeights($request);

        JobPreference::updateOrCreate(
            ['job_id' => $jobId],
            array_merge(
                $request->only([
                    'keyword_weight', 'semantic_weight',
                    'skills_weight', 'experience_weight', 'education_weight', 'cert_weight',
                ]),
                ['qual_weight' => $qualWeight, 'layout_weight' => $presWeight],
                $presWeights
            )
        );
        
        //debugging
        //  dd([
        // 'saved_data' => $request->all(),
        // 'pref_after_save' => JobPreference::where('job_id', $jobId)->first(),  
        // ]);

        return redirect('/recruiter')->with('success', 'Job preferences saved!');
    }
}