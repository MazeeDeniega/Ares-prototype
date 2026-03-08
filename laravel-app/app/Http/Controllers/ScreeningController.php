<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\Job;
use App\Models\Application;
use App\Models\JobPreference;
use Smalot\PdfParser\Parser;

class ScreeningController extends Controller
{
    /**
     * Stream a private file (resume, TOR, certificate) to the browser.
     * Only the job owner or admin can access it.
     *
     * Route: GET /files/{applicationId}/{type}
     * type: resume | tor | cert
     */
    public function serveFile(int $applicationId, string $type)
    {
        $application = Application::findOrFail($applicationId);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $job  = Job::findOrFail($application->job_id);
        if ($job->user_id !== Auth::id() && !$user->isAdmin()) {
            abort(403);
        }

        $path = match($type) {
            'resume' => $application->resume_path,
            'tor'    => $application->tor_path,
            'cert'   => $application->cert_path,
            default  => null,
        };

        if (!$path) abort(404);

        if (Storage::exists($path)) {
            return Storage::response($path, basename($path), ['Content-Type' => 'application/pdf']);
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->response($path, basename($path), ['Content-Type' => 'application/pdf']);
        }

        abort(404, 'File not found');
    }

    public function showJobApplicants($jobId)
    {
        $job = Job::with('applications.user')->findOrFail($jobId);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        if ($job->user_id != Auth::id() && !$user->isAdmin()) {
            abort(403, 'Unauthorized');
        }

        return view('screening.applicants', compact('job'));
    }

    private function extractPdfPageCount(string $pdfPath): int
    {
        try {
            // Raw byte scan is more reliable than smalot getPages() on many PDFs
            $content = file_get_contents($pdfPath);
            preg_match_all('/\/Type\s*\/Page[^s]/', $content, $matches);
            $count = count($matches[0]);
            // Fall back to smalot if raw scan finds nothing
            if ($count === 0) {
                $pdf = (new Parser())->parseFile($pdfPath);
                $count = count($pdf->getPages());
            }
            return max($count, 1);
        } catch (\Exception $e) {
            return 1;
        }
    }

    private function baseResult(Application $application): array
    {
        return [
            'application_id'       => $application->id,
            'first_name'           => $application->first_name,
            'last_name'            => $application->last_name,
            'email'                => $application->email,
            'phone'                => $application->phone ?? null,
            'city'                 => $application->city,
            'province'             => $application->province,
            'engagement_type'      => $application->engagement_type,
            'highest_education'    => $application->highest_education,
            'date_available'       => $application->date_available,
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

    public function evaluateApplicants(Request $request, $jobId)
    {
        $job = Job::findOrFail($jobId);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        if ($job->user_id != Auth::id() && !$user->isAdmin()) {
            abort(403, 'Unauthorized');
        }

        // Load preferences once: job-level first, then user defaults, then hardcoded
        $jobPref  = JobPreference::where('job_id', $jobId)->first();
        $userPref = $user->preference;

        $pref = (object) [
            // Similarity blend
            'keyword_weight'      => $jobPref->keyword_weight      ?? $userPref->keyword_weight      ?? 40,
            'semantic_weight'     => $jobPref->semantic_weight     ?? $userPref->semantic_weight     ?? 60,
            // Qualifications sub-weights
            'skills_weight'       => $jobPref->skills_weight       ?? $userPref->skills_weight       ?? 35,
            'experience_weight'   => $jobPref->experience_weight   ?? $userPref->experience_weight   ?? 20,
            'education_weight'    => $jobPref->education_weight    ?? $userPref->education_weight    ?? 25,
            'cert_weight'         => $jobPref->cert_weight         ?? $userPref->cert_weight         ?? 10,
            // Presentation overall weight (scales presentation's contribution to final score)
            'layout_weight'       => $jobPref->layout_weight       ?? $userPref->layout_weight       ?? 0,
            // Presentation sub-weights (how each category is weighted within presentation)
            'formatting_weight'   => $jobPref->formatting_weight   ?? $userPref->formatting_weight   ?? 25,
            'language_weight'     => $jobPref->language_weight     ?? $userPref->language_weight     ?? 25,
            'concise_weight'      => $jobPref->concise_weight      ?? $userPref->concise_weight      ?? 25,
            'organization_weight' => $jobPref->organization_weight ?? $userPref->organization_weight ?? 25,
            // Presentation category toggles (which sub-scores are active at all)
            'pref_formatting'     => $jobPref->pref_formatting     ?? $userPref->pref_formatting     ?? false,
            'pref_language'       => $jobPref->pref_language       ?? $userPref->pref_language       ?? false,
            'pref_conciseness'    => $jobPref->pref_conciseness    ?? $userPref->pref_conciseness    ?? false,
            'pref_organization'   => $jobPref->pref_organization   ?? $userPref->pref_organization   ?? false,
        ];

        $results      = [];
        $applications = Application::where('job_id', $jobId)->get();

        foreach ($applications as $application) {

            $result     = $this->baseResult($application);
            $feedback   = [];
            $resumeText = '';
            $pageCount  = 1;

            // ----------------------------------------------------------------
            // 1. Resolve file path using Storage facade
            // ----------------------------------------------------------------
            if ($application->resume_path) {
                $fullPath = Storage::path($application->resume_path);

                if (!file_exists($fullPath)) {
                    $fullPath = Storage::disk('public')->path($application->resume_path);
                }

                if (file_exists($fullPath)) {
                    try {
                        $parser    = new Parser();
                        $pdf       = $parser->parseFile($fullPath);
                        // getText() collapses all pages into one line, which breaks
                        // the NLP layout scoring (blank ratio, heading detection, etc).
                        // Join page texts with double newlines to preserve structure.
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
                    $feedback[] = 'File not found: ' . $application->resume_path;
                }
            } else {
                $feedback[] = 'No resume uploaded';
            }

            if (empty($resumeText)) {
                $result['feedback'] = $feedback;
                $results[] = $result;
                continue;
            }

            // ----------------------------------------------------------------
            // 2. Call NLP API
            // ----------------------------------------------------------------
            try {
                $response = Http::timeout(30)->post('http://127.0.0.1:5000/analyze', [
                    'resume'               => $resumeText,
                    'job'                  => $job->description,
                    'page_count'           => $pageCount,
                    'keyword_weight'       => (int) $pref->keyword_weight,
                    'semantic_weight'      => (int) $pref->semantic_weight,
                    'presentation_weights' => [
                        'formatting_weight'   => (int) $pref->formatting_weight,
                        'language_weight'     => (int) $pref->language_weight,
                        'concise_weight'      => (int) $pref->concise_weight,
                        'organization_weight' => (int) $pref->organization_weight,
                    ],
                ]);

                if (!$response->ok()) {
                    $result['feedback'] = ['NLP API error: HTTP ' . $response->status() . ' — ' . $response->body()];
                    $results[] = $result;
                    continue;
                }

                $data = $response->json();

                // Uncomment to debug NLP response in browser:
                // dd($data);

                $yearsExp  = $data['years_experience']    ?? 0;
                $eduScore  = $data['education_score']     ?? 0;
                $certScore = $data['certification_score'] ?? 0;
                $skills    = $data['matched_skills']      ?? [];

                // --------------------------------------------------------
                // 3. QUALIFICATIONS SCORE (0–100)
                //    NLP pre-blended tfidf+semantic → combined_similarity.
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
                // 4. PRESENTATION SCORE (0–100)
                //    If pref_* toggles are set, re-compute using only the
                //    enabled categories. Otherwise use the NLP pre-weighted
                //    score directly.
                // --------------------------------------------------------
                $formattingScore   = $data['formatting_score']   ?? 0;
                $languageScore     = $data['language_score']     ?? 0;
                $conciseScore      = $data['concise_score']      ?? 0;
                $organizationScore = $data['organization_score'] ?? 0;

                // Use the NLP pre-weighted presentation_score by default.
                // Only re-compute if the recruiter has explicitly toggled specific
                // categories on — toggling none means "use all" (the NLP default).
                $anyToggleSet = $pref->pref_formatting || $pref->pref_language
                             || $pref->pref_conciseness || $pref->pref_organization;

                if ($anyToggleSet) {
                    $activeScore  = 0;
                    $activeWeight = 0;
                    if ($pref->pref_formatting)  { $activeScore += $formattingScore   * $pref->formatting_weight;  $activeWeight += $pref->formatting_weight; }
                    if ($pref->pref_language)    { $activeScore += $languageScore     * $pref->language_weight;    $activeWeight += $pref->language_weight; }
                    if ($pref->pref_conciseness) { $activeScore += $conciseScore      * $pref->concise_weight;     $activeWeight += $pref->concise_weight; }
                    if ($pref->pref_organization){ $activeScore += $organizationScore * $pref->organization_weight; $activeWeight += $pref->organization_weight; }
                    $presentationRaw = $activeWeight > 0 ? $activeScore / $activeWeight : 0;
                } else {
                    // No toggles set = all categories active, use NLP's pre-weighted score
                    $presentationRaw = $data['presentation_score'] ?? 0;
                }

                // NLP returns 0–1, convert to 0–100
                // Guard: if NLP returned already-scaled value (>1), don't double-multiply
                $presentationScore = round(
                    ($presentationRaw <= 1 ? $presentationRaw * 100 : $presentationRaw),
                    2
                );

                // --------------------------------------------------------
                // 5. FEEDBACK
                // --------------------------------------------------------
                if ($blendedSimilarity < 0.5) $feedback[] = 'Low job similarity';
                if ($yearsExp < 2)             $feedback[] = 'Limited experience';
                if ($certScore == 0)           $feedback[] = 'No certifications detected';
                if (empty($feedback))          $feedback[] = 'Good match';

                $result['qualifications_score'] = $qualificationsScore;
                $result['presentation_score']   = $presentationScore;
                $result['formatting_score']     = round($formattingScore   * 100, 1);
                $result['language_score']       = round($languageScore     * 100, 1);
                $result['concise_score']        = round($conciseScore      * 100, 1);
                $result['organization_score']   = round($organizationScore * 100, 1);
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

        usort($results, fn($a, $b) => $b['qualifications_score'] <=> $a['qualifications_score']);

        // Debug: uncomment to inspect preference resolution and results
        // dd([
        //     'job_pref'  => $jobPref,
        //     'user_pref' => $userPref,
        //     'pref_used' => $pref,
        //     'results'   => array_slice($results, 0, 2),
        // ]);

        return view('screening.results', compact('results', 'job', 'pref'));
    }
}