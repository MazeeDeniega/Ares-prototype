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

    // Get all candidates for the authenticated user
    public function getAllCandidates()
    {
        $user = Auth::user();

        $applications = Application::with('job')
            ->whereHas('job', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->get()
            ->map(function ($app) {
                return [
                    'id'           => $app->id,
                    'Name'         => $app->first_name . ' ' . $app->last_name,
                    'Contact'      => $app->email,
                    'job_position' => $app->job->title ?? 'N/A',
                    'status'       => $app->status ?? 'Pending',
                    'details'      => $app->resume_path,
                ];
            });

        return response()->json($applications);
    }

    /**
     *  ULTIMATE PDF EXTRACTION 
     */
    private function extractTextUltimate(string $pdfPath): array
    {
        Log::info(" Extracting text from: " . basename($pdfPath));
        $attempts = [];

        // Method 1: Smalot Parser (regular PDFs)
        $result = $this->trySmalotParser($pdfPath);
        $attempts[] = $this->traceAttempt('smalot', $result);
        if (!empty($result['text'])) {
            Log::info(" Smalot success: " . $result['char_count'] . " chars");
            return $result + ['attempts' => $attempts];
        }

        // Method 2: Cloud OCR (Canva/image PDFs - NO imagick!)
        $result = $this->tryCloudOcrNoImagick($pdfPath);
        $attempts[] = $this->traceAttempt('cloud_ocr', $result);
        if (!empty($result['text'])) {
            Log::info(" Cloud OCR success: " . $result['char_count'] . " chars");
            return $result + ['attempts' => $attempts];
        }

        // Method 3: Heuristic metadata extraction
        $result = $this->tryHeuristic($pdfPath);
        $attempts[] = $this->traceAttempt('heuristic', $result);
        if (!empty($result['text'])) {
            Log::info(" Heuristic success: " . $result['char_count'] . " chars");
            return $result + ['attempts' => $attempts];
        }

        Log::warning(" All extraction methods failed: " . basename($pdfPath));
        return ['text' => '', 'method' => 'all_failed', 'page_count' => 1, 'char_count' => 0, 'attempts' => $attempts];
    }

    /**
     * NEW: turns whatever a try*() method returned into a small, UI/log
     * friendly trace entry. Each try*() method now sets '_failure_reason'
     * on failure instead of just silently returning ['text' => ''], so we
     * can finally see WHY a tier failed instead of only THAT it failed.
     */
    private function traceAttempt(string $method, array $result): array
    {
        return [
            'method'  => $method,
            'success' => !empty($result['text']),
            'reason'  => $result['_failure_reason'] ?? (!empty($result['text']) ? 'ok' : 'no text returned'),
        ];
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
            return [
                'text' => '',
                '_failure_reason' => 'parsed only ' . strlen($text) . ' chars (need >50) — likely an image-based/scanned PDF with no real text layer',
            ];
        } catch (\Exception $e) {
            Log::debug("Smalot failed: " . $e->getMessage());
            return ['text' => '', '_failure_reason' => 'exception: ' . $e->getMessage()];
        }
    }

    //   Reconstruct text from OCR.space overlay data, preserving line breaks, blank lines, and indentation.
    private function reconstructTextFromOverlay(array $overlayLines): string
    {
        if (empty($overlayLines)) {
            return '';
        }

        // Defensive: make sure lines are read top-to-bottom even if the API
        // ever returns them out of order.
        usort($overlayLines, fn($a, $b) => ($a['MinTop'] ?? 0) <=> ($b['MinTop'] ?? 0));

        // Typical line height, used as the unit for "how big a gap counts
        // as a blank line" and "how far right counts as indented".
        $heights   = array_column($overlayLines, 'MaxHeight');
        $avgHeight = count($heights) ? array_sum($heights) / count($heights) : 12;

        // The leftmost text position anywhere on the page = the document's
        // baseline left margin. Lines starting noticeably further right
        // than this are treated as indented/bulleted.
        $leftEdges = [];
        foreach ($overlayLines as $line) {
            $words = $line['Words'] ?? [];
            if (!empty($words)) {
                $leftEdges[] = min(array_column($words, 'Left'));
            }
        }
        $baselineLeft = !empty($leftEdges) ? min($leftEdges) : 0;

        $output  = [];
        $prevTop = null;

        foreach ($overlayLines as $line) {
            $lineText = trim($line['LineText'] ?? '');
            if ($lineText === '') {
                continue;
            }

            $top = $line['MinTop'] ?? 0;

            // Big vertical jump from the previous line -> insert a blank
            // line, approximating the paragraph/section spacing a real
            // PDF would have had.
            if ($prevTop !== null) {
                $gap = $top - $prevTop;
                if ($gap > $avgHeight * 1.8) {
                    $output[] = '';
                }
            }

            // Indented/bulleted line -> prepend a couple of spaces so the
            // existing presentation-scoring logic (which looks for leading
            // whitespace) can detect it, same as it would for a normal
            // text-layer PDF.
            $words    = $line['Words'] ?? [];
            $lineLeft = !empty($words) ? min(array_column($words, 'Left')) : $baselineLeft;
            $indent   = ($lineLeft - $baselineLeft) > ($avgHeight * 0.8) ? '  ' : '';

            $output[] = $indent . $lineText;
            $prevTop  = $top;
        }

        return implode("\n", $output);
    }

    //    Cloud OCR.space API call (no imagick, no local OCR)
    private function tryCloudOcrNoImagick(string $pdfPath): array
    {
        try {
            $pdfContent = file_get_contents($pdfPath);
            if (!$pdfContent) return ['text' => '', '_failure_reason' => 'could not read file from disk'];

            $fileSizeMb = round(strlen($pdfContent) / 1024 / 1024, 2);

            // NEW: surface when we're silently relying on the hardcoded
            // demo/personal key. That key's quota (25,000 req/month, 500/day
            // on OCR.space's free tier) is shared across EVERY environment
            // that doesn't set OCR_SPACE_API_KEY — dev, staging, and prod all
            // drawing from the same bucket will exhaust it fast, and nothing
            // before this would have told you that's what happened.
            $apiKey = env('OCR_SPACE_API_KEY');
            if (!$apiKey) {
                Log::warning('OCR_SPACE_API_KEY not set in .env — using shared hardcoded fallback key. Its quota is shared across every environment running this code.');
                $apiKey = 'K89222848088957';
            }

            if ($fileSizeMb > 1.0) {
                // OCR.space's free API tier caps out around 1MB/file. Canva
                // and other image-heavy exports — exactly the PDFs this
                // method exists for — routinely land above that. We still
                // attempt the call (the cap isn't razor-precise) but log it
                // loudly so this is the first thing you see, not something
                // you have to guess at.
                Log::warning("Cloud OCR: file is {$fileSizeMb}MB, over OCR.space's free-tier ~1MB cap — likely to be rejected.");
            }

            // FIXED: OCR.space validates the upload by its FILENAME
            // extension, not its actual bytes. $pdfPath is sometimes a raw
            // upload temp file (Laravel's UploadedFile::getRealPath() —
            // e.g. C:\Windows\Temp\php1A2B.tmp on Windows), which has no
            // .pdf extension at all, so basename($pdfPath) alone gets
            // rejected with "File does not have a valid extension" even
            // though we already know it's a PDF (validated via `mimes:pdf`
            // before we ever reach this method).
            $ocrFilename = basename($pdfPath);
            if (!preg_match('/\.(pdf|jpe?g|png|bmp|gif|tiff?|webp)$/i', $ocrFilename)) {
                $ocrFilename = 'upload.pdf';
            }

            // UNBLOCK: the Windows CA-bundle path (cURL error 60 -> error 77
            // trying to point curl.cainfo/Guzzle at a bundle file) burned
            // more time than it was worth to chase further here. Skip TLS
            // verification, but ONLY in the local environment — this must
            // never reach staging/production, since resumes carry real PII
            // (names, emails, phone numbers) and disabling verification
            // there would expose that traffic to interception. Revisit the
            // proper CA-bundle fix (likely a Windows file-permission issue
            // on whatever account Apache's service runs as, given the file
            // sits under C:\Users\...\Documents) once this isn't blocking
            // active testing.
            $verify = true;
            if (app()->environment('local')) {
                $verify = false;
            } elseif (file_exists(storage_path('certs/cacert.pem'))) {
                $verify = storage_path('certs/cacert.pem');
            }

            $client = new \GuzzleHttp\Client([
                'timeout' => 45,
                'verify'  => $verify,
            ]);
            $response = $client->post('https://api.ocr.space/parse/image', [
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => $pdfContent,
                        'filename' => $ocrFilename
                    ],
                    [
                        'name' => 'apikey',
                        'contents' => $apiKey
                    ],
                    [
                        'name' => 'language',
                        'contents' => 'eng'
                    ],
                    [
                        // CHANGED: was 'false'. Requesting overlay data gives us
                        // per-line position info so real structure can be rebuilt
                        // (see reconstructTextFromOverlay above).
                        'name' => 'isOverlayRequired',
                        'contents' => 'true'
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

            // NEW: OCR.space very often responds HTTP 200 with a JSON body
            // that signals failure (file too large, quota exceeded, engine
            // timeout, unsupported file) rather than throwing an HTTP-level
            // error. The old code never inspected this, so a real OCR
            // failure and "the API just didn't find text" were
            // indistinguishable. This is most likely where your failures
            // are actually coming from.
            if (($data['IsErroredOnProcessing'] ?? false) === true) {
                $rawErr = $data['ErrorMessage'] ?? 'unknown error';
                $errMsg = is_array($rawErr) ? implode('; ', $rawErr) : $rawErr;
                $exitCode = $data['OCRExitCode'] ?? '?';
                Log::warning("Cloud OCR reported an error (ExitCode {$exitCode}): {$errMsg} | file size {$fileSizeMb}MB");
                return ['text' => '', '_failure_reason' => "OCR.space error (ExitCode {$exitCode}): {$errMsg} [file: {$fileSizeMb}MB]"];
            }

            $result = $data['ParsedResults'][0] ?? [];

            // Prefer structure-reconstructed text. Fall back to the old flat
            // ParsedText if no overlay came back (e.g. the image type
            // didn't support it, or overlay data was empty).
            $overlayLines = $result['TextOverlay']['Lines'] ?? [];
            $text = !empty($overlayLines)
                ? trim($this->reconstructTextFromOverlay($overlayLines))
                : '';

            if ($text === '') {
                $text = trim($result['ParsedText'] ?? '');
            }

            if (strlen($text) > 50) {
                return [
                    'text' => $text,
                    'method' => 'cloud_ocr',
                    'page_count' => 1,
                    'char_count' => strlen($text),
                    'used_overlay' => !empty($overlayLines),
                ];
            }

            return [
                'text' => '',
                '_failure_reason' => empty($result)
                    ? "ParsedResults came back empty [file: {$fileSizeMb}MB] — request likely rejected without an explicit error flag"
                    : 'parsed only ' . strlen($text) . " chars (need >50) [file: {$fileSizeMb}MB]",
            ];
        } catch (\Exception $e) {
            Log::debug("Cloud OCR failed: " . $e->getMessage());
            return ['text' => '', '_failure_reason' => 'exception: ' . $e->getMessage()];
        }
    }

    private function tryHeuristic(string $pdfPath): array
    {
        try {
            $content = file_get_contents($pdfPath);
            if (!$content) return ['text' => '', '_failure_reason' => 'could not read file from disk'];

            // FIXED: this previously looked for metadata values wrapped in
            // literal "$" characters (\$([^)]+)\$), which essentially never
            // matches anything real — PDF string values are delimited by
            // parentheses, e.g. `/Title (My Resume)`, not dollar signs. That
            // typo meant this half of the heuristic almost never contributed
            // anything; it was quietly running on the name-pattern regex
            // below alone.
            preg_match_all('/\/(Title|Subject|Author|Creator|Producer|Keywords)\s*\(([^)]*)\)/', $content, $matches);
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
            return ['text' => '', '_failure_reason' => 'found only ' . strlen($text) . ' chars of metadata/name-pattern matches (need >20)'];
        } catch (\Exception $e) {
            return ['text' => '', '_failure_reason' => 'exception: ' . $e->getMessage()];
        }
    }

    // ----------------------------------------------------------------
    // Render the Interactive Laravel-side Testing Sandbox UI
    // ----------------------------------------------------------------
    public function showSandbox()
    {
        return view('screening.sandbox');
    }

    private function extractPdfPageCount(string $path): int
    {
        if (!file_exists($path)) {
            return 1;
        }
        
        $content = file_get_contents($path);
        preg_match_all('/\/Type\s*\/Page[^s]/', $content, $matches);
        $count = count($matches[0]);
        
        return $count > 0 ? $count : 1;
    }

    /**
     * Pure structural diagnostics on already-extracted text — independent of
     * whatever the Flask scorer reports. Exists specifically to answer:
     * "did the extractor preserve real line/paragraph/indentation structure,
     * or did it collapse the resume into one undifferentiated blob?"
     *
     * This matters because formatting_score / organization_score on the
     * Flask side lean on exactly these signals (blank-line ratio, bullet/
     * indent detection, line lengths) — if extraction flattens everything
     * into 1-2 giant lines, presentation scoring becomes meaningless no
     * matter how good the OCR text recognition itself was.
     */
    private function analyzeTextStructure(string $text): array
    {
        if ($text === '') {
            return [
                'line_count'             => 0,
                'blank_line_count'       => 0,
                'indented_line_count'    => 0,
                'avg_line_length'        => 0,
                'longest_line_length'    => 0,
                'looks_like_single_blob' => true,
            ];
        }

        $lines      = explode("\n", $text);
        $lineCount  = count($lines);
        $blankCount = 0;
        $indentCount = 0;
        $lengths    = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                $blankCount++;
                continue;
            }
            // Mirrors the kind of leading-whitespace check the presentation
            // scorer uses to spot bullets/indentation.
            if (preg_match('/^\s{2,}/', $line)) {
                $indentCount++;
            }
            $lengths[] = mb_strlen($line);
        }

        $avgLen  = count($lengths) ? array_sum($lengths) / count($lengths) : 0;
        $longest = count($lengths) ? max($lengths) : 0;

        // Heuristic: a resume genuinely has tens of lines. If extraction
        // produced ~1-2 lines holding a few hundred+ characters, line breaks
        // were lost somewhere in the pipeline (most likely ParsedText fallback
        // instead of overlay reconstruction, or overlay data came back empty).
        $singleBlob = $lineCount <= 2 && mb_strlen($text) > 300;

        return [
            'line_count'             => $lineCount,
            'blank_line_count'       => $blankCount,
            'indented_line_count'    => $indentCount,
            'avg_line_length'        => round($avgLen, 1),
            'longest_line_length'    => $longest,
            'looks_like_single_blob' => $singleBlob,
        ];
    }

    // ----------------------------------------------------------------
    // Execute Telemetry Analysis via Production Code Stack
    // ----------------------------------------------------------------
    public function analyzeSandbox(Request $request)
    {
        try {
            $request->validate([
                'pdf' => 'required|file|mimes:pdf',
                'job_description' => 'required|string',
            ]);

            $pdfFile = $request->file('pdf');
            $jobDescription = $request->input('job_description');

            // 1. Capture Form Parameters (Simulating Recruiter Matrix Profiles)
            $pref = (object) [
                'keyword_weight'      => (int) $request->input('keyword_weight', 40),
                'semantic_weight'     => (int) $request->input('semantic_weight', 60),
                'qual_weight'         => (int) $request->input('qual_weight', 100),
                'pres_weight'         => (int) $request->input('layout_weight', 0),
                'skills_weight'       => (int) $request->input('skills_weight', 35),
                'experience_weight'   => (int) $request->input('experience_weight', 20),
                'education_weight'    => (int) $request->input('education_weight', 25),
                'cert_weight'         => (int) $request->input('cert_weight', 10),
                'formatting_weight'   => (int) $request->input('formatting_weight', 25),
                'language_weight'     => (int) $request->input('language_weight', 25),
                'concise_weight'      => (int) $request->input('concise_weight', 25),
                'organization_weight' => (int) $request->input('organization_weight', 25),
            ];

            // 2. Execute Production File Storage/Retrieval Simulations
            $tempPath = $pdfFile->getRealPath();
            $feedback = [];
            $startTime = microtime(true);

            // 3. EXECUTE PRODUCTION EXTRACTION PIPELINE
            //    CHANGED: was calling Smalot\PdfParser directly, which meant
            //    the sandbox could never reach the Cloud OCR / overlay-
            //    reconstruction branch — exactly the code path you're trying
            //    to validate. Now it runs the real production pipeline
            //    (Smalot -> Cloud OCR w/ overlay -> heuristic), same as
            //    evaluateApplicants() uses, so uploading an image-based/Canva
            //    PDF here actually exercises isOverlayRequired.
            $extracted        = $this->extractTextUltimate($tempPath);
            $resumeText        = $extracted['text'];
            $extractionMethod = $extracted['method'];
            $pageCount        = $extracted['page_count'] ?: $this->extractPdfPageCount($tempPath);

            if (empty($resumeText)) {
                $feedback[] = 'All extraction methods failed — file may be image-based, corrupt, or unsupported';
            } elseif ($extractionMethod === 'heuristic') {
                // The heuristic tier only ever recovers PDF metadata (title/
                // author) and stray capitalized-word matches — never real
                // resume body content. If this fires, Smalot AND Cloud OCR
                // both failed first; check 'pipeline_trace' in this response
                // for why.
                $feedback[] = '⚠ Heuristic fallback used — only metadata/name-pattern matches were recovered, not real resume content. All scores below are unreliable.';
            } else {
                $feedback[] = "Extracted via: {$extractionMethod}";
            }

            // NEW: structural diagnostics — the direct answer to "one chunk
            // vs. real layout", computed straight off the extracted string,
            // independent of the Flask scorer.
            $structure = $this->analyzeTextStructure($resumeText);

            // 4. DISPATCH PAYLOAD VIA LARAVEL HTTP CLIENT
            $response = Http::timeout(60)->post('http://127.0.0.1:5000/analyze', [
                'resume'               => $resumeText,
                'job'                  => $jobDescription,
                'page_count'           => $pageCount,
                'keyword_weight'       => $pref->keyword_weight,
                'semantic_weight'      => $pref->semantic_weight,
                'presentation_weights' => [
                    'formatting_weight'   => $pref->formatting_weight,
                    'language_weight'     => $pref->language_weight,
                    'concise_weight'      => $pref->concise_weight,
                    'organization_weight' => $pref->organization_weight,
                ],
            ]);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            $data = $response->json();

            // 5. RUN PRODUCTION SCORE CALCULATION ENGINE
            $yearsExp  = $data['years_experience']    ?? 0;
            $eduScore  = $data['education_score']     ?? 0;
            $certScore = $data['certification_score'] ?? 0;
            $skills    = $data['matched_skills']      ?? [];

            $blendedSimilarity = $data['combined_similarity'] ?? 0;
            $qualTotal = ($pref->skills_weight + $pref->experience_weight + $pref->education_weight + $pref->cert_weight) ?: 100;
            $experienceNorm = min($yearsExp, 5) / 5;

            $qualificationsScore = round((
                ($blendedSimilarity * $pref->skills_weight     / $qualTotal) +
                ($experienceNorm    * $pref->experience_weight / $qualTotal) +
                ($eduScore          * $pref->education_weight  / $qualTotal) +
                ($certScore         * $pref->cert_weight       / $qualTotal)
            ) * 100, 2);

            $presentationRaw   = $data['presentation_score'] ?? 0;
            $presentationScore = round(($presentationRaw <= 1 ? $presentationRaw * 100 : $presentationRaw), 2);

            $finalScore = round(
                ($qualificationsScore * $pref->qual_weight / 100) +
                ($presentationScore   * $pref->pres_weight / 100), 2
            );

            if ($blendedSimilarity < 0.5) $feedback[] = 'Low job similarity';
            if ($yearsExp < 2)             $feedback[] = 'Limited experience';
            if ($certScore == 0)           $feedback[] = 'No certifications detected';
            if (empty($feedback))          $feedback[] = 'Good match';

            // 6. RETURN COMPLETE TELEMETRY
            return response()->json([
                'success' => true,
                'parser_used' => "extractTextUltimate() — Smalot \u{2192} Cloud OCR (overlay) \u{2192} Heuristic",
                'extraction_method' => $extractionMethod,
                'php_execution_latency_ms' => $executionTime,
                // NEW: full text, not just a preview — the sandbox view renders
                // this verbatim (monospace, white-space preserved) so you can
                // see exactly where line breaks/indentation did or didn't land.
                'extracted_text' => $resumeText,
                'extracted_text_preview' => mb_strimwidth($resumeText, 0, 800, '...'),
                'extracted_char_count' => strlen($resumeText),
                'page_count' => $pageCount,
                'text_structure' => $structure,
                // NEW: what each tier tried and why it succeeded/failed —
                // e.g. "cloud_ocr: OCR.space error (ExitCode 3): file too
                // large [file: 2.4MB]" tells you definitively that file size
                // is the problem, instead of guessing from "heuristic was
                // used" alone.
                'pipeline_trace' => $extracted['attempts'] ?? [],
                'calculated_scores' => [
                    'final_score' => $finalScore,
                    'qualifications_score' => $qualificationsScore,
                    'presentation_score' => $presentationScore,
                    'formatting_score' => round(($data['formatting_score'] ?? 0) * 100, 1),
                    'language_score' => round(($data['language_score'] ?? 0) * 100, 1),
                    'concise_score' => round(($data['concise_score'] ?? 0) * 100, 1),
                    'organization_score' => round(($data['organization_score'] ?? 0) * 100, 1),
                ],
                'extracted_candidate_meta' => [
                    'skills_detected' => $skills,
                    'years_experience' => $yearsExp,
                    'education_raw_score' => $eduScore,
                    'certification_raw_score' => $certScore,
                ],
                'generated_decision_feedback' => $feedback,
                'raw_flask_json_response' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
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
            $resumeText = '';
            $extracted = ['page_count' => 1];

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