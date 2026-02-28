<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            'phone' => 'required|string|max:50',
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:255',
            'province' => 'required|string|max:255',
            'postal_code' => 'required|string|max:20',
            'country' => 'required|string|max:255',
            'resume' => 'required|file|mimes:pdf|max:2048',
            'tor' => 'nullable|file|mimes:pdf|max:2048',
            'cert' => 'nullable|file|mimes:pdf|max:2048',
            'date_available' => 'nullable|date',
            'desired_pay' => 'nullable|string|max:100',
            'highest_education' => 'nullable|string|max:255',
            'college_university' => 'nullable|string|max:255',
            'referred_by' => 'nullable|string|max:255',
            'references' => 'nullable|string|max:1000',
            'engagement_type' => 'nullable|in:full_time,part_time',
        ]);

        $data = [
            'job_id' => $jobId,
            'user_id' => Auth::id(),
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => Auth::user()->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'city' => $request->city,
            'province' => $request->province,
            'postal_code' => $request->postal_code,
            'country' => $request->country,
            'date_available' => $request->date_available,
            'desired_pay' => $request->desired_pay,
            'highest_education' => $request->highest_education,
            'college_university' => $request->college_university,
            'referred_by' => $request->referred_by,
            'references' => $request->references,
            'engagement_type' => $request->engagement_type,
            'status' => 'pending',
        ];

        // Upload Resume
        if ($request->hasFile('resume')) {
            $data['resume_path'] = $request->file('resume')->store('resumes');
        }

        // Upload TOR
        if ($request->hasFile('tor')) {
            $data['tor_path'] = $request->file('tor')->store('documents');
        }

        // Upload Certificate
        if ($request->hasFile('cert')) {
            $data['cert_path'] = $request->file('cert')->store('documents');
        }

        Application::create($data);

        return back()->with('success', 'Application submitted successfully!');
    }

    public function myApplications()
    {
        $applications = Auth::user()->applications()->with('job')->get();
        return view('applications.index', compact('applications'));
    }
}