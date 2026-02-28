<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use App\Models\Job;
use App\Models\Application;
use Smalot\PdfParser\Parser;
use app\Models\JobPreference;

class ScreeningController extends Controller
{
    public function showJobApplicants($jobId)
    {
        $job = Job::with('applications.user')->findOrFail($jobId);
        
        if ($job->user_id != Auth::id() && !Auth::user()->isAdmin()) {
            abort(403, 'Unauthorized');
        }

        return view('screening.applicants', compact('job'));
    }

    public function evaluateApplicants(Request $request, $jobId)
    {
        $job = Job::findOrFail($jobId);
        
        if ($job->user_id != Auth::id() && !Auth::user()->isAdmin()) {
            abort(403, 'Unauthorized');
        }

        $results = [];
        $applications = Application::where('job_id', $jobId)->get();

        foreach ($applications as $application) {

            // dd($application->toArray());

            $feedback = [];
            $resumeText = '';

            // Get resume text
            if ($application->resume_path) {
                try {
                    $parser = new Parser();
                    $fullPath = storage_path('app/' . $application->resume_path);
                    
                    if (file_exists($fullPath)) {
                        $pdf = $parser->parseFile($fullPath);
                        $resumeText = $pdf->getText();
                    } else {
                        $feedback[] = 'File not found: ' . $application->resume_path;
                    }
                } catch (\Exception $e) {
                    $feedback[] = 'PDF Error: ' . $e->getMessage();
                }
            } else {
                $feedback[] = 'No resume uploaded';
            }

            // Skip if no resume text
            if (empty($resumeText)) {
                $results[] = [
                    'application_id' => $application->id,
                    'candidate_name' => $application->user->name,
                    'email' => $application->user->email,
                    'score' => 0,
                    'skills' => [],
                    'experience' => 0,
                    'feedback' => $feedback,
                    'resume_path' => $application->resume_path,
                ];
                continue;
            }

            // Call NLP API
            try {
                $response = Http::timeout(30)->post('http://127.0.0.1:5000/analyze', [
                    'resume' => $resumeText,
                    'job' => $job->description
                ]);

                // dd($response->json());

                if (!$response->ok()) {
                    $results[] = [
                        'application_id' => $application->id,
                        'candidate_name' => $application->user->name,
                        'email' => $application->user->email,
                        'score' => 0,
                        'skills' => [],
                        'experience' => 0,
                        'feedback' => ['NLP API error'],
                        'resume_path' => $application->resume_path,
                    ];
                    continue;
                }

                $data = $response->json();

                $skillScore = $data['combined_similarity'] ?? 0; 
                $yearsExp = $data['years_experience'] ?? 0;
                $eduScore = $data['education_score'] ?? 0;
                $certScore = $data['certification_score'] ?? 0;
                $skills = $data['matched_skills'] ?? [];

                // Get user preferences
                $jobPref = JobPreference::where('job_id', $jobId)->first();
                $pref = Auth::user()->preference;
                
                // Calculate score with preferences (scale to 0-100)
                $finalScore = 
                    ($skillScore * 100 * ($pref->skills_weight ?? 35) / 100) + 
                    (min($yearsExp, 5) / 5 * 100 * ($pref->experience_weight ?? 20) / 100) + 
                    ($eduScore * 100 * ($pref->education_weight ?? 25) / 100) + 
                    ($certScore * 100 * ($pref->cert_weight ?? 10) / 100);

                // Add feedback
                if ($skillScore < 0.5) $feedback[] = "Low job similarity";
                if ($yearsExp < 2) $feedback[] = "Limited experience";
                if ($certScore == 0) $feedback[] = "No certifications";
                if (empty($feedback)) $feedback[] = "Good match";

                // Add FULL details to results
                $results[] = [
                    'application_id' => $application->id,
                    'first_name' => $application->first_name,
                    'last_name' => $application->last_name,
                    'email' => $application->email,
                    'phone' => $application->phone,
                    'city' => $application->city,
                    'province' => $application->province,
                    'engagement_type' => $application->engagement_type,
                    'highest_education' => $application->highest_education,
                    'date_available' => $application->date_available,
                    'score' => round($finalScore, 2),
                    'skills' => $skills,
                    'experience' => $yearsExp,
                    'feedback' => $feedback,
                    'resume_path' => $application->resume_path,
                    'tor_path' => $application->tor_path,
                    'cert_path' => $application->cert_path,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'application_id' => $application->id,
                    'first_name' => $application->first_name,
                    'last_name' => $application->last_name,
                    'email' => $application->email,
                    'phone' => $application->phone,
                    'city' => $application->city,
                    'province' => $application->province,
                    'engagement_type' => $application->engagement_type,
                    'highest_education' => $application->highest_education,
                    'date_available' => $application->date_available,
                    'score' => 0,
                    'skills' => [],
                    'experience' => 0,
                    'feedback' => ['Error: ' . $e->getMessage()],
                    'resume_path' => $application->resume_path,
                    'tor_path' => $application->tor_path ?? null,
                    'cert_path' => $application->cert_path ?? null,
                ];

            }
        }

        // Sort by score descending
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return view('screening.results', compact('results', 'job'));
    }
}