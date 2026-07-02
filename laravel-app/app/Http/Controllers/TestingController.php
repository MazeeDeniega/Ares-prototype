<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\Job;
use App\Models\Application;

class TestingController extends Controller
{
    /**
     * Generate a sequential placeholder label — A, B, C … Z, AA, AB …
     * so test candidates are easy to tell apart in the ranking table.
     */
    private function placeholderLabel(int $index): string
    {
        $label = '';
        do {
            $label = chr(65 + ($index % 26)) . $label;
            $index = intdiv($index, 26) - 1;
        } while ($index >= 0);
        return $label;
    }

    public function showMassUpload()
    {
        $jobs = Job::orderBy('created_at', 'desc')->get(['id', 'title']);
        return view('testing.mass-upload', compact('jobs'));
    }

    /**
     * Accept multiple PDFs, store them, and create Application rows with
     * placeholder personal data so the scoring pipeline has something to
     * work with. Personal info columns are filled with obvious test values
     * so real candidate data is never at risk of being confused with these.
     */
    public function massUpload(Request $request)
    {
        $request->validate([
            'job_id'  => 'required|exists:jobs,id',
            'resumes' => 'required|array|min:1',
            'resumes.*' => 'file|mimes:pdf|max:10240',
            'prefix'  => 'nullable|string|max:40',
        ]);

        $job    = Job::findOrFail($request->job_id);
        $prefix = trim($request->input('prefix', 'Candidate')) ?: 'Candidate';

        // Start label offset after however many test entries already exist
        // for this job, so re-running doesn't produce duplicate labels.
        $existing = Application::where('job_id', $job->id)
            ->where('email', 'like', '%@test.local')
            ->count();

        $created = [];
        $failed  = [];

        foreach ($request->file('resumes') as $i => $file) {
            try {
                $label    = $prefix . ' ' . $this->placeholderLabel($existing + $i);
                $filename = $file->getClientOriginalName();

                // Store under resumes/ same as the real application form does,
                // so serveFile() and the extraction pipeline find it normally.
                $path = $file->store('resumes', 'public');

                $app = Application::create([
                    'job_id'           => $job->id,
                    'user_id'          => null,
                    'first_name'       => $prefix,
                    'last_name'        => $this->placeholderLabel($existing + $i),
                    // email as a unique, obviously-fake identifier so
                    // clearTestApplications() can target only these rows.
                    'email'            => strtolower(str_replace(' ', '.', $label)) . '@test.local',
                    'phone'            => '+63 000 000 0000',
                    'city'             => 'Test City',
                    'province'         => 'Test Province',
                    'postal_code'      => '0000',
                    'country'          => 'Philippines',
                    'date_available'   => now()->toDateString(),
                    'desired_pay'      => '0',
                    'highest_education'=> "Bachelor's Degree",
                    'college_university' => 'Test University',
                    'engagement_type'  => 'Full-time',
                    'status'           => 'Pending',
                    'resume_path'      => $path,
                    'tor_path'         => null,
                    'cert_path'        => null,
                    'referred_by'      => null,
                    'references'       => null,
                    'address'          => 'Test Address',
                ]);

                $created[] = [
                    'id'       => $app->id,
                    'label'    => $label,
                    'filename' => $filename,
                    'path'     => $path,
                ];
            } catch (\Exception $e) {
                $failed[] = [
                    'filename' => $file->getClientOriginalName(),
                    'error'    => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success'     => true,
            'job_id'      => $job->id,
            'job_title'   => $job->title,
            'created'     => $created,
            'failed'      => $failed,
            'total'       => count($created),
            'evaluate_url'=> url("/screening/{$job->id}/evaluate"),
        ]);
    }

    /**
     * Delete all test applications for a job (identified by their
     * @test.local email suffix) and remove their stored files.
     * Lets you wipe and re-seed without touching real applicants.
     */
    public function clearTestApplications(Request $request, $jobId)
    {
        $job = Job::findOrFail($jobId);

        $testApps = Application::where('job_id', $jobId)
            ->where('email', 'like', '%@test.local')
            ->get();

        $count = 0;
        foreach ($testApps as $app) {
            if ($app->resume_path) {
                Storage::disk('public')->delete($app->resume_path);
            }
            $app->delete();
            $count++;
        }

        return response()->json([
            'success' => true,
            'deleted' => $count,
            'job_id'  => $jobId,
        ]);
    }
}