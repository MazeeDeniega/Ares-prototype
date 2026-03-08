<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use App\Models\Job;
use App\Models\Application;
use App\Models\JobPreference;
use Smalot\PdfParser\Parser;

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

                // dd([
                //     'nlp_response' => $data,
                //     'layout_from_api' => $data['layout'] ?? 'NO LAYOUT DATA',
                // ]);
                
                $tfidfScore = $data['tfidf_similarity'] ?? 0; 
                $semanticScore = $data['semantic_similarity'] ?? 0;
                $skillScore = $data['combined_similarity'] ?? 0; 
                $yearsExp = $data['years_experience'] ?? 0;
                $eduScore = $data['education_score'] ?? 0;
                $certScore = $data['certification_score'] ?? 0;
                $skills = $data['matched_skills'] ?? [];

                // Get layout scores from NLP API
                $layout = $data['layout'] ?? [];
                $formattingScore = $layout['formatting_score'] ?? 0;
                $languageScore = $layout['language_score'] ?? 0;
                $concisenessScore = $layout['conciseness_score'] ?? 0;
                $organizationScore = $layout['organization_score'] ?? 0;

                // Get job-specific preferences or fallback to user defaults
                $jobPref = JobPreference::where('job_id', $jobId)->first();
                $pref = $jobPref ?? Auth::user()->preference;

                // Calculate layout score based on selected categories (max 2)
                $layoutScore = 0;
                $selectedCount = 0;
                
                if ($pref->pref_formatting) {
                    $layoutScore += $formattingScore;
                    $selectedCount++;
                }
                if ($pref->pref_language) {
                    $layoutScore += $languageScore;
                    $selectedCount++;
                }
                if ($pref->pref_conciseness) {
                    $layoutScore += $concisenessScore;
                    $selectedCount++;
                }
                if ($pref->pref_organization) {
                    $layoutScore += $organizationScore;
                    $selectedCount++;
                }

                // Average if multiple categories selected
                if ($selectedCount > 0) {
                    $layoutScore = $layoutScore / $selectedCount;
                }

                // Get weights with fallback to defaults
                $keywordWeight = $pref->keyword_weight ?? 40;
                $semanticWeight = $pref->semantic_weight ?? 60;
                $skillWeight = $pref->skills_weight ?? 40;
                $expWeight = $pref->experience_weight ?? 20;
                $eduWeight = $pref->education_weight ?? 25;
                $certWeight = $pref->cert_weight ?? 10;
                $layoutWeight = $pref->layout_weight ?? 0;

                // Calculate final score with layout
                $finalScore = 
                    ($skillScore * 100 * $skillWeight / 100) + 
                    (min($yearsExp, 5) / 5 * 100 * $expWeight / 100) + 
                    ($eduScore * 100 * $eduWeight / 100) + 
                    ($certScore * 100 * $certWeight / 100) +
                    ($layoutScore * 100 * $layoutWeight / 100);

                // Add feedback
                if ($skillScore < 0.5) $feedback[] = "Low skill similarity";
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
                    // Layout scores
                    'tfidf_similarity' => round($tfidfScore * 100, 1),
                    'semantic_similarity' => round($semanticScore * 100, 1),
                    'layout_score' => round($layoutScore * 100, 1),
                    'layout_formatting' => round($formattingScore * 100, 1),
                    'layout_language' => round($languageScore * 100, 1),
                    'layout_conciseness' => round($concisenessScore * 100, 1),
                    'layout_organization' => round($organizationScore * 100, 1),
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
                    'layout_score' => 0,
                    'layout_formatting' => 0,
                    'layout_language' => 0,
                    'layout_conciseness' => 0,
                    'layout_organization' => 0,
                ];
            }
        }

        // Sort by score descending
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        // DEBUG: Show what's happening
        // dd([
        //     'job_id' => $jobId,
        //     'job_pref' => $jobPref ? [
        //         'keyword_weight' => $jobPref->keyword_weight,
        //         'semantic_weight' => $jobPref->semantic_weight,
        //         'skills_weight' => $jobPref->skills_weight,
        //         'experience_weight' => $jobPref->experience_weight,
        //         'education_weight' => $jobPref->education_weight,
        //         'cert_weight' => $jobPref->cert_weight,
        //         'layout_weight' => $jobPref->layout_weight,
        //         'pref_formatting' => $jobPref->pref_formatting,
        //         'pref_language' => $jobPref->pref_language,
        //         'pref_conciseness' => $jobPref->pref_conciseness,
        //         'pref_organization' => $jobPref->pref_organization,
        //     ] : 'NO JOB PREFERENCE FOUND',
        //     'user_pref' => Auth::user()->preference ? [
        //         'keyword_weight' => Auth::user()->preference->keyword_weight,
        //         'semantic_weight' => Auth::user()->preference->semantic_weight,
        //         'skills_weight' => Auth::user()->preference->skills_weight,
        //         'experience_weight' => Auth::user()->preference->experience_weight,
        //         'education_weight' => Auth::user()->preference->education_weight,
        //         'cert_weight' => Auth::user()->preference->cert_weight,
        //         'layout_weight' => Auth::user()->preference->layout_weight,
        //         'pref_formatting' => Auth::user()->preference->pref_formatting,
        //         'pref_language' => Auth::user()->preference->pref_language,
        //         'pref_conciseness' => Auth::user()->preference->pref_conciseness,
        //         'pref_organization' => Auth::user()->preference->pref_organization,
        //     ] : 'NO USER PREFERENCE FOUND',
        //     'pref_used' => $pref ? [
        //         'keyword_weight' => $pref->keyword_weight,
        //         'semantic_weight' => $pref->semantic_weight,
        //         'skills_weight' => $pref->skills_weight,
        //         'experience_weight' => $pref->experience_weight,
        //         'education_weight' => $pref->education_weight,
        //         'cert_weight' => $pref->cert_weight,
        //         'layout_weight' => $pref->layout_weight,
        //         'pref_formatting' => $pref->pref_formatting,
        //         'pref_language' => $pref->pref_language,
        //         'pref_conciseness' => $pref->pref_conciseness,
        //         'pref_organization' => $pref->pref_organization,
        //     ] : 'NO PREFERENCE FOUND',
        //     'results_sample' => array_slice($results, 0, 2), // Show first 2 results
        // ]);

        return view('screening.results', compact('results', 'job'));
    }
}