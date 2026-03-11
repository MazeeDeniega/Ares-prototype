<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Application;

class ApplicantController extends Controller
{
    public function showApplyForm($jobId)
    {
        $job = \App\Models\Job::with('user')->findOrFail($jobId);
        return view('applicant.apply', compact('job'));
    }

    public function apply(Request $request, $jobId)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'city' => 'required|string|max:100',
            'province' => 'required|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'required|string|max:100',
            'desired_pay' => 'required|numeric|min:10000|max:200000',
            'engagement_type' => 'required|string|max:50',
            'date_available' => 'nullable|date',
            'highest_education' => 'nullable|string|max:100',
            'college_university' => 'nullable|string|max:255',
            'referred_by' => 'nullable|string|max:255',
            'references' => 'nullable|string',
            'tor_path' => 'nullable|file|mimes:pdf,doc,docx,jpg,png|max:4096',
            'cert_path' => 'nullable|file|mimes:pdf,doc,docx,jpg,png|max:4096',
        ]);

        $data = [
            'job_id' => $jobId,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'city' => $request->city,
            'province' => $request->province,
            'postal_code' => $request->postal_code ?? null,
            'country' => $request->country,
            'engagement_type' => $request->engagement_type,
            'date_available' => $request->date_available,
            'highest_education' => $request->highest_education,
            'college_university' => $request->college_university ?? null,
            'referred_by' => $request->referred_by ?? null,
            'references' => $request->references ?? null,
            'status' => 'pending',
        ];

        // Upload TOR
        if ($request->hasFile('tor_path')) {
            $data['tor_path'] = $request->file('tor_path')->store('tors', 'public');
        }

        // Upload Certificate
        if ($request->hasFile('cert_path')) {
            $data['cert_path'] = $request->file('cert_path')->store('certs', 'public');
        }

        $application = Application::create($data);

        // ✅ Return JSON for React
        return response()->json([
            'success' => true,
            'application_id' => $application->id,
        ]);
    }

    // public function myApplications()
    // {
    //     $applications = Auth::user()->applications()->with('job')->get();
    //     return view('applications.index', compact('applications'));
    // }
}