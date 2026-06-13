<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Job;
use App\Models\Application;
use App\Models\JobPreference;
use Smalot\PdfParser\Parser;

class ScreeningController extends Controller
{
    /**
     * Serve uploaded files (resume, tor, cert) — auth-gated
     */
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

    /**
     * Show applicants list for a job
     */
    public function showJobApplicants($jobId)
    {
        $job = Job::with('applications')->findOrFail($jobId);

        $user = Auth::user();
        if ($job->user_id != Auth::id() && !$user->isAdmin()) {
            abort(403, 'Unauthorized');
        }

        return view('screening.applicants', compact('job'));
    }

    /**
     *  ULTIMATE PDF EXTRACTION 
     */
    private function extractTextUltimate(string $pdfPath): array
    {
        Log::info(" Extracting text from: " . basename($pdfPath));
        
        // Method 1: Smalot Parser (regular PDFs)
        $result = $this->trySmalotParser($pdfPath);
        if (!empty($result['text'])) {
            Log::info(" Smalot success: " . $result['char_count'] . " chars");
            return $result;
        }
        
        // Method 2: Cloud OCR (Canva/image PDFs - NO imagick!)
        $result = $this->tryCloudOcrNoImagick($pdfPath);
        if (!empty($result['text'])) {
            Log::info(" Cloud OCR success: " . $result['char_count'] . " chars");
            return $result;
        }
        
        // Method 3: Heuristic metadata extraction
        $result = $this->tryHeuristic($pdfPath);
        if (!empty($result['text'])) {
            Log::info(" Heuristic success: " . $result['char_count'] . " chars");
            return $result;
        }
        
        Log::warning(" All extraction methods failed: " . basename($pdfPath));
        return ['text' => '', 'method' => 'all_failed', 'page_count' => 1, 'char_count' => 0];
    }

    private function trySmalotParser(string $pdfPath): array
    {
        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($pdfPath);
            $text = trim($pdf->getText());
            if (strlen($text) > 50) { // Minimum meaningful text
                return [
                    'text' => $text,
                    'method' => 'smalot',
                    'page_count' => count($pdf->getPages()),
                    'char_count' => strlen($text)
                ];
            }
        } catch (\Exception $e) {
            Log::debug("Smalot failed: " . $e->getMessage());
        }
        return ['text' => ''];
    }

    /**
     * Cloud OCR
     */
    private function tryCloudOcrNoImagick(string $pdfPath): array
    {
        try {
            $pdfContent = file_get_contents($pdfPath);
            if (!$pdfContent) return ['text' => ''];
            
            $client = new \GuzzleHttp\Client(['timeout' => 45]);
            $response = $client->post('https://api.ocr.space/parse/image', [
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => $pdfContent,
                        'filename' => basename($pdfPath)
                    ],
                    [
                        'name' => 'apikey',
                        'contents' => env('OCR_SPACE_API_KEY', 'K89222848088957')
                    ],
                    [
                        'name' => 'language',
                        'contents' => 'eng'
                    ],
                    [
                        'name' => 'isOverlayRequired',
                        'contents' => 'false'
                    ],
                    [
                        'name' => 'OCREngine',
                        'contents' => '2'
                    ],
                    [
                        'name' => 'scale',
                        'contents' => 'true'
                    ]
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            $text = trim($data['ParsedResults'][0]['ParsedText'] ?? '');
            
            if (strlen($text) > 50) {
                return [
                    'text' => $text,
                    'method' => 'cloud_ocr',
                    'page_count' => 1,
                    'char_count' => strlen($text)
                ];
            }
        } catch (\Exception $e) {
            Log::debug("Cloud OCR failed: " . $e->getMessage());
        }
        return ['text' => ''];
    }

    private function tryHeuristic(string $pdfPath): array
    {
        try {
            $content = file_get_contents($pdfPath);
            if (!$content) return ['text' => ''];
            
            // Extract PDF metadata (Author, Title, etc.)
            preg_match_all('/\/(Title|Subject|Author|Creator|Producer|Keywords)\s*\$([^)]+)\$/', $content, $matches);
            $metadata = trim(implode(' ', $matches[2] ?? []));
            
            // Extract name-like patterns
            preg_match_all('/[A-Z][a-z]+\s+[A-Z][a-z]+/', $content, $nameMatches);
            $names = implode(' ', $nameMatches[0] ?? []);
            
            $text = trim($metadata . ' ' . $names);
            if (strlen($text) > 20) {
                return [
                    'text' => $text,
                    'method' => 'heuristic',
                    'page_count' => 1,
                    'char_count' => strlen($text)
                ];
            }
        } catch (\Exception $e) {
            // Silent fail
        }
        return ['text' => ''];
    }

    /**
     * Build result skeleton
     */
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
            'extraction_method'    => null,
            'char_count'           => 0,
            'resume_path'          => $application->resume_path,
            'tor_path'             => $application->tor_path ?? null,
            'cert_path'            => $application->cert_path ?? null,
        ];
    }

    /**
     * Main evaluation endpoint
     */
    public function evaluateApplicants(Request $request, $jobId)
    {
        $job = Job::findOrFail($jobId);
        $user = Auth::user();
        if ($job->user_id != Auth::id() && !$user->isAdmin()) {
            abort(403, 'Unauthorized');
        }

        $jobPref = JobPreference::where('job_id', $jobId)->first();
        $userPref = $user->preference;

        $pref = (object) [
            'keyword_weight'      => $jobPref->keyword_weight ?? $userPref->keyword_weight ?? 40,
            'semantic_weight'     => $jobPref->semantic_weight ?? $userPref->semantic_weight ?? 60,
            'qual_weight'         => $jobPref->qual_weight ?? $userPref->qual_weight ?? 100,
            'pres_weight'         => $jobPref->layout_weight ?? $userPref->layout_weight ?? 0,
            'skills_weight'       => $jobPref->skills_weight ?? $userPref->skills_weight ?? 35,
            'experience_weight'   => $jobPref->experience_weight ?? $userPref->experience_weight ?? 20,
            'education_weight'    => $jobPref->education_weight ?? $userPref->education_weight ?? 25,
            'cert_weight'         => $jobPref->cert_weight ?? $userPref->cert_weight ?? 10,
            'formatting_weight'   => $jobPref->formatting_weight ?? $userPref->formatting_weight ?? 25,
            'language_weight'     => $jobPref->language_weight ?? $userPref->language_weight ?? 25,
            'concise_weight'      => $jobPref->concise_weight ?? $userPref->concise_weight ?? 25,
            'organization_weight' => $jobPref->organization_weight ?? $userPref->organization_weight ?? 25,
        ];

        $results = [];
        $applications = Application::where('job_id', $jobId)->get();

        foreach ($applications as $application) {
            $result = $this->baseResult($application);
            $feedback = [];

            //  NEW EXTRACTION (handles ALL PDFs!)
            if ($application->resume_path) {
                $fullPath = Storage::path($application->resume_path);
                if (!file_exists($fullPath)) {
                    $fullPath = Storage::disk('public')->path($application->resume_path);
                }

                if (file_exists($fullPath)) {
                    $extracted = $this->extractTextUltimate($fullPath);
                    $resumeText = $extracted['text'];
                    
                    $result['extraction_method'] = $extracted['method'];
                    $result['char_count'] = $extracted['char_count'];

                    if (!empty($resumeText)) {
                        $feedback[] = " " . $extracted['method'] . " ({$extracted['char_count']} chars)";
                    } else {
                        $feedback[] = " No text extracted";
                    }
                } else {
                    $feedback[] = " File not found";
                }
            } else {
                $feedback[] = " No resume";
            }

            // NLP API Call (unchanged)
            try {
                $response = Http::timeout(60)->post('http://127.0.0.1:5000/analyze', [
                    'resume' => $resumeText,
                    'job' => $job->description,
                    'page_count' => $extracted['page_count'] ?? 1,
                    'keyword_weight' => $pref->keyword_weight,
                    'semantic_weight' => $pref->semantic_weight,
                    'presentation_weights' => [
                        'formatting_weight' => $pref->formatting_weight,
                        'language_weight' => $pref->language_weight,
                        'concise_weight' => $pref->concise_weight,
                        'organization_weight' => $pref->organization_weight,
                    ],
                ]);

                $data = $response->json();
                $yearsExp = $data['years_experience'] ?? 0;
                $eduScore = $data['education_score'] ?? 0;
                $certScore = $data['certification_score'] ?? 0;
                $skills = $data['matched_skills'] ?? [];

                // Score calculations (unchanged)
                $blendedSimilarity = $data['combined_similarity'] ?? 0;
                $qualTotal = ($pref->skills_weight + $pref->experience_weight + $pref->education_weight + $pref->cert_weight) ?: 100;
                $experienceNorm = min($yearsExp, 5) / 5;

                $qualificationsScore = round((
                    ($blendedSimilarity * $pref->skills_weight / $qualTotal) +
                    ($experienceNorm * $pref->experience_weight / $qualTotal) +
                    ($eduScore * $pref->education_weight / $qualTotal) +
                    ($certScore * $pref->cert_weight / $qualTotal)
                ) * 100, 2);

                $presentationScore = round(($data['presentation_score'] ?? 0) * 100, 2);
                $finalScore = round(
                    ($qualificationsScore * $pref->qual_weight / 100) +
                    ($presentationScore * $pref->pres_weight / 100), 2
                );

                // Smart feedback
                if ($blendedSimilarity < 0.5) $feedback[] = 'Low skills match';
                if ($yearsExp < 2) $feedback[] = 'Limited experience';
                if ($finalScore > 80 && !empty($skills)) $feedback[] = '🎯 Top candidate';

                $result['final_score'] = $finalScore;
                $result['qualifications_score'] = $qualificationsScore;
                $result['presentation_score'] = $presentationScore;
                $result['formatting_score'] = round(($data['formatting_score'] ?? 0) * 100, 1);
                $result['language_score'] = round(($data['language_score'] ?? 0) * 100, 1);
                $result['concise_score'] = round(($data['concise_score'] ?? 0) * 100, 1);
                $result['organization_score'] = round(($data['organization_score'] ?? 0) * 100, 1);
                $result['skills'] = $skills;
                $result['experience'] = $yearsExp;
                $result['feedback'] = $feedback;
                $result['layout_feedback'] = $data['layout_feedback'] ?? [];

            } catch (\Exception $e) {
                $result['feedback'][] = 'NLP Error: ' . $e->getMessage();
                Log::error("NLP failed: " . $e->getMessage());
            }

            $results[] = $result;
        }

        // Sort by score
        usort($results, fn($a, $b) => $b['final_score'] <=> $a['final_score']);

        return view('screening.results', compact('results', 'job', 'pref'));
    }
}