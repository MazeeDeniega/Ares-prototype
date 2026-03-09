<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use App\Models\Job;
use App\Models\Application;
use App\Models\JobPreference;
use Smalot\PdfParser\Parser;

class ScreeningController extends Controller
{
    // ----------------------------------------------------------------
    // Serve uploaded files (resume, tor, cert) — auth-gated
    // ----------------------------------------------------------------
    public function serveFile($applicationId, $type)
    {
        $application = Application::findOrFail($applicationId);

        $pathMap = [
            'resume' => $application->resume_path,
            'tor'    => $application->tor_path,
            'cert'   => $application->cert_path,
        ];

        $path = $pathMap[$type] ?? null;
        if (!$path) abort(404);

        $fullPath = Storage::path($path);
        if (!file_exists($fullPath)) {
            $fullPath = Storage::disk('public')->path($path);
        }
        if (!file_exists($fullPath)) abort(404);

        return response()->file($fullPath, ['Content-Type' => 'application/pdf']);
    }

    // ----------------------------------------------------------------
    // Show applicants list for a job
    // ----------------------------------------------------------------
    public function showJobApplicants($jobId)
    {
        $job = Job::with('applications.user')->findOrFail($jobId);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        if ($job->user_id != Auth::id() && !$user->isAdmin()) {
            abort(403, 'Unauthorized');
        }

        return view('screening.applicants', compact('job')); // ← was 'screening.index'
    }

    // ----------------------------------------------------------------
    // Build a zeroed-out result skeleton for one application
    // ----------------------------------------------------------------
    private function baseResult(Application $application): array
    {
        return [
            'application_id'       => $application->id,
            'first_name'           => $application->first_name,
            'last_name'            => $application->last_name,
            'email'                => $application->email,
            'phone'                => $application->phone        ?? null,
            'city'                 => $application->city,
            'province'             => $application->province,
            'engagement_type'      => $application->engagement_type,
            'highest_education'    => $application->highest_education,
            'date_available'       => $application->date_available,
            'final_score'          => 0,
            'qualifications_score' => 0,
            'presentation_score'   => 0,
            'formatting_score'     => 0,
            'language_score'       => 0,
            'concise_score'        => 0,
            'organization_score'   => 0,
            'skills'               => [],
            'experience'           => 0,
            'feedback'             => [],
            'layout_feedback'      => [],
            'resume_path'          => $application->resume_path,
            'tor_path'             => $application->tor_path  ?? null,
            'cert_path'            => $application->cert_path ?? null,
        ];
    }

    private function extractPdfPageCount(string $path): int
    {
        $content = file_get_contents($path);
        preg_match_all('/\/Type\s*\/Page[^s]/', $content, $matches);
        $count = count($matches[0]);
        return $count > 0 ? $count : 1;
    }

    // ----------------------------------------------------------------
    // Main evaluation
    // ----------------------------------------------------------------
    public function evaluateApplicants(Request $request, $jobId)
    {
        $job = Job::findOrFail($jobId);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        if ($job->user_id != Auth::id() && !$user->isAdmin()) {
            abort(403, 'Unauthorized');
        }

        $jobPref  = JobPreference::where('job_id', $jobId)->first();
        $userPref = $user->preference;

        $pref = (object) [
            // Similarity blend (inside skills)
            'keyword_weight'      => $jobPref->keyword_weight      ?? $userPref->keyword_weight      ?? 40,
            'semantic_weight'     => $jobPref->semantic_weight     ?? $userPref->semantic_weight     ?? 60,
            // Final score split
            'qual_weight'         => $jobPref->qual_weight         ?? $userPref->qual_weight         ?? 100,
            'pres_weight'         => $jobPref->layout_weight       ?? $userPref->layout_weight       ?? 0,
            // Qualifications sub-weights
            'skills_weight'       => $jobPref->skills_weight       ?? $userPref->skills_weight       ?? 35,
            'experience_weight'   => $jobPref->experience_weight   ?? $userPref->experience_weight   ?? 20,
            'education_weight'    => $jobPref->education_weight    ?? $userPref->education_weight    ?? 25,
            'cert_weight'         => $jobPref->cert_weight         ?? $userPref->cert_weight         ?? 10,
            // Presentation sub-weights (0 = that category excluded)
            'formatting_weight'   => $jobPref->formatting_weight   ?? $userPref->formatting_weight   ?? 25,
            'language_weight'     => $jobPref->language_weight     ?? $userPref->language_weight     ?? 25,
            'concise_weight'      => $jobPref->concise_weight      ?? $userPref->concise_weight      ?? 25,
            'organization_weight' => $jobPref->organization_weight ?? $userPref->organization_weight ?? 25,
        ];

        $results      = [];
        $applications = Application::where('job_id', $jobId)->get();

        foreach ($applications as $application) {

            $result    = $this->baseResult($application);
            $feedback  = [];
            $resumeText = '';
            $pageCount  = 1;

            if ($application->resume_path) {
                $fullPath = Storage::path($application->resume_path);
                if (!file_exists($fullPath)) {
                    $fullPath = Storage::disk('public')->path($application->resume_path);
                }

                if (file_exists($fullPath)) {
                    try {
                        $parser    = new Parser();
                        $pdf       = $parser->parseFile($fullPath);
                        $pages     = $pdf->getPages();
                        $resumeText = trim(implode("\n\n", array_map(
                            fn($p) => $p->getText(), $pages
                        )));
                        $pageCount  = $this->extractPdfPageCount($fullPath);
                        if (empty($resumeText)) {
                            $feedback[] = 'PDF parsed but no text extracted — file may be image-based or corrupt';
                        }
                    } catch (\Exception $e) {
                        $feedback[] = 'PDF Error: ' . $e->getMessage();
                    }
                } else {
                    $feedback[] = 'Resume file not found on disk';
                }
            }

            try {
                $response = Http::timeout(60)->post('http://127.0.0.1:5000/analyze', [
                    'resume'     => $resumeText,
                    'job'        => $job->description,
                    'page_count' => $pageCount,
                    'keyword_weight'  => $pref->keyword_weight,
                    'semantic_weight' => $pref->semantic_weight,
                    'presentation_weights' => [
                        'formatting_weight'   => $pref->formatting_weight,
                        'language_weight'     => $pref->language_weight,
                        'concise_weight'      => $pref->concise_weight,
                        'organization_weight' => $pref->organization_weight,
                    ],
                ]);

                $data      = $response->json();
                $yearsExp  = $data['years_experience']    ?? 0;
                $eduScore  = $data['education_score']     ?? 0;
                $certScore = $data['certification_score'] ?? 0;
                $skills    = $data['matched_skills']      ?? [];

                // --------------------------------------------------------
                // QUALIFICATIONS SCORE (0–100)
                // --------------------------------------------------------
                $blendedSimilarity = $data['combined_similarity'] ?? 0;

                $qualTotal = (
                    $pref->skills_weight + $pref->experience_weight +
                    $pref->education_weight + $pref->cert_weight
                ) ?: 100;

                $experienceNorm = min($yearsExp, 5) / 5;

                $qualificationsScore = round((
                    ($blendedSimilarity * $pref->skills_weight     / $qualTotal) +
                    ($experienceNorm    * $pref->experience_weight / $qualTotal) +
                    ($eduScore          * $pref->education_weight  / $qualTotal) +
                    ($certScore         * $pref->cert_weight       / $qualTotal)
                ) * 100, 2);

                // --------------------------------------------------------
                // PRESENTATION SCORE (0–100)
                // NLP computes this using the weights we sent — use directly.
                // --------------------------------------------------------
                $presentationRaw   = $data['presentation_score'] ?? 0;
                $presentationScore = round(
                    ($presentationRaw <= 1 ? $presentationRaw * 100 : $presentationRaw),
                    2
                );

                // --------------------------------------------------------
                // FINAL SCORE (0–100)
                // qual_weight + pres_weight = 100
                // --------------------------------------------------------
                $qualW = $pref->qual_weight;
                $presW = $pref->pres_weight;
                $finalScore = round(
                    ($qualificationsScore * $qualW / 100) +
                    ($presentationScore   * $presW / 100),
                    2
                );

                // --------------------------------------------------------
                // FEEDBACK
                // --------------------------------------------------------
                if ($blendedSimilarity < 0.5) $feedback[] = 'Low job similarity';
                if ($yearsExp < 2)             $feedback[] = 'Limited experience';
                if ($certScore == 0)           $feedback[] = 'No certifications detected';
                if (empty($feedback))          $feedback[] = 'Good match';

                $result['final_score']          = $finalScore;
                $result['qualifications_score'] = $qualificationsScore;
                $result['presentation_score']   = $presentationScore;
                $result['formatting_score']     = round(($data['formatting_score']   ?? 0) * 100, 1);
                $result['language_score']       = round(($data['language_score']     ?? 0) * 100, 1);
                $result['concise_score']        = round(($data['concise_score']      ?? 0) * 100, 1);
                $result['organization_score']   = round(($data['organization_score'] ?? 0) * 100, 1);
                $result['skills']               = $skills;
                $result['experience']           = $yearsExp;
                $result['feedback']             = $feedback;
                $result['layout_feedback']      = $data['layout_feedback'] ?? [];

                $results[] = $result;

            } catch (\Exception $e) {
                $result['feedback'] = ['NLP Error: ' . $e->getMessage() . ' — is the Flask service running on port 5000?'];
                $results[] = $result;
            }
        }

        // Sort by final score descending
        usort($results, fn($a, $b) => $b['final_score'] <=> $a['final_score']);

        return view('screening.results', compact('results', 'job', 'pref'));
    }
}