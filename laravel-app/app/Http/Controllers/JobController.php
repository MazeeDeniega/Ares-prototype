<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Job;

class JobController extends Controller {
    // For Recruiter: create job
    public function store(Request $request) 
    {
        $request->validate(['title' => 'required', 'description' => 'required']);
        Auth::user()->jobs()->create($request->all());
        return redirect('/recruiter')->with('success', 'Job created!');
    }
    
    public function destroy($id) 
    {
        $job = Job::where('id', $id)->first();
        if($job && ($job->user_id == Auth::id() || Auth::user()->isAdmin())) {
            $job->delete();
        }
        return back();
    }

    // For Applicants: view all jobs
    public function index() 
    {
        $jobs = Job::with('user')->latest()->get();

        // Debug to check if jobs are being retrieved correctly
        // dd($jobs->toArray());

        return view('jobs.index', compact('jobs'));
    }

    // Show single job
    public function show($id) 
    {
        $job = Job::findOrFail($id);
        return view('jobs.show', compact('job'));
    }
}