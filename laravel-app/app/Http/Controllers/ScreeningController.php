<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\Job;
use App\Models\Application;
use App\Models\JobPreference;
use Smalot\PdfParser\Parser;

class ScreeningController extends Controller
{
    // NEW: reused across every OCR.space call within a single request.
    // evaluateApplicants() loops over every applicant in one request — a
    // fresh GuzzleHttp\Client per call meant a brand new TCP+TLS handshake
    // to api.ocr.space for every single resume that needed OCR, even back
    // to back. Reusing one client lets the underlying cURL handle keep that
    // connection alive across calls in the same request instead of
    // renegotiating TLS each time.
    private ?\GuzzleHttp\Client $ocrHttpClient = null;

    private function getOcrHttpClient(): \GuzzleHttp\Client
    {
        if ($this->ocrHttpClient === null) {
            // Same verify logic as before — local-only TLS bypass, real
            // CA bundle otherwise. Resolved once per request, not per call.
            $verify = true;
            if (app()->environment('local')) {
                $verify = false;
            } elseif (file_exists(storage_path('certs/cacert.pem'))) {
                $verify = storage_path('certs/cacert.pem');
            }

            $this->ocrHttpClient = new \GuzzleHttp\Client([
                'timeout' => 45,
                'verify'  => $verify,
            ]);
        }

        return $this->ocrHttpClient;
    }

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
            ->map(fn ($app) => $this->formatCandidate($app));

        return response()->json($applications);
    }

    public function updateCandidateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:' . implode(',', Application::VALID_STATUSES),
        ]);

        $user = Auth::user();
        $application = Application::with('job')->findOrFail($id);

        if ($application->job->user_id != $user->id && !$user->isAdmin()) {
            abort(403, 'Unauthorized');
        }

        $application->status = $request->status;
        $application->save();

        return response()->json($this->formatCandidate($application));
    }

    private function formatCandidate(Application $app): array
    {
        return [
            'id'           => $app->id,
            'Name'         => $app->first_name . ' ' . $app->last_name,
            'Contact'      => $app->email,
            'job_position' => $app->job->title ?? 'N/A',
            'status'       => $this->formatStatusLabel($app->status),
            'details'      => $app->resume_path ? "/files/{$app->id}/resume" : null,
        ];
    }

    private function formatStatusLabel(?string $status): string
    {
        return match (strtolower($status ?? Application::STATUS_PENDING)) {
            Application::STATUS_APPROVED, 'accepted' => 'Approved',
            Application::STATUS_REJECTED => 'Rejected',
            Application::STATUS_INTERVIEW => 'Interview',
            default => 'Pending',
        };
    }

    /**
     * Shared by both the single-file path (extractTextUltimate, used by the
     * sandbox) and the new batched path (used by evaluateApplicants) so
     * caching behaves identically regardless of which one populated it.
     */
    private function extractionCacheKey(string $pdfPath): string
    {
        return 'extract:' . md5($pdfPath . '|' . @filemtime($pdfPath) . '|' . @filesize($pdfPath));
    }

    /**
     * FIXED (while adding the batch path below): the old Cache::remember()
     * cached EVERY outcome — including 'all_failed' — for the same 7 days
     * as a real success. A transient OCR.space hiccup or network blip would
     * lock a file into "no text" for a week with no way to self-heal short
     * of manually clearing cache. Real text is trusted for a while; nothing
     * found is only trusted briefly, so the next run naturally retries.
     */
    private function cacheExtractionResult(string $cacheKey, array $result): void
    {
        // FIXED: heuristic "success" is metadata scraps and stray
        // capitalized words — never a faithful parse. Treating it as
        // trustworthy for 7 days meant a single transient OCR.space hiccup
        // got baked in as this file's permanent answer for a week, even
        // though OCR would've succeeded on the very next attempt. Only
        // real extractions (smalot/cloud_ocr) earn the long TTL; heuristic
        // gets the same short one as an outright failure, so the next run
        // gives OCR another real shot instead of trusting a consolation prize.
        $trustworthy = !empty($result['text']) && ($result['method'] ?? null) !== 'heuristic';
        $ttl = $trustworthy ? now()->addDays(7) : now()->addMinutes(5);
        Cache::put($cacheKey, $result, $ttl);
    }

    /**
     *  ULTIMATE PDF EXTRACTION 
     */
    private function extractTextUltimate(string $pdfPath): array
    {
        // Cache the full extraction result per file. The OCR.space call is
        // the slow part of this pipeline (network round-trip, variable
        // free-tier latency) — without caching, re-running against the same
        // resumes while testing re-pays that cost every single time even
        // though the file hasn't changed.
        $cacheKey = $this->extractionCacheKey($pdfPath);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $result = $this->extractTextUltimateUncached($pdfPath);
        $this->cacheExtractionResult($cacheKey, $result);
        return $result;
    }

    private function extractTextUltimateUncached(string $pdfPath): array
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
    /**
     * Shared by the single-file path (tryCloudOcrNoImagick, used by the
     * sandbox) and the batched path (batchCloudOcr, used by
     * evaluateApplicants) so they don't drift out of sync with each other.
     *
     * Surfaces when we're silently relying on the hardcoded demo/personal
     * key. That key's quota (25,000 req/month, 500/day on OCR.space's free
     * tier) is shared across EVERY environment that doesn't set
     * OCR_SPACE_API_KEY — dev, staging, and prod all drawing from the same
     * bucket will exhaust it fast.
     */
    private function resolveOcrApiKey(): string
    {
        $apiKey = env('OCR_SPACE_API_KEY');
        if (!$apiKey) {
            Log::warning('OCR_SPACE_API_KEY not set in .env — using shared hardcoded fallback key. Its quota is shared across every environment running this code.');
            $apiKey = 'K89222848088957';
        }
        return $apiKey;
    }

    /**
     * Forces a recognized extension onto the OCR.space upload filename.
     * Shared for the same reason as resolveOcrApiKey() above — see the
     * comment at the original call site for why this is needed at all.
     */
    private function ocrSafeFilename(string $pdfPath): string
    {
        $filename = basename($pdfPath);
        if (!preg_match('/\.(pdf|jpe?g|png|bmp|gif|tiff?|webp)$/i', $filename)) {
            $filename = 'upload.pdf';
        }
        return $filename;
    }

    /**
     * Parses one OCR.space response body into our standard extraction
     * result shape. Pulled out of tryCloudOcrNoImagick() so the batched
     * path below can reuse the exact same error-handling and overlay-
     * reconstruction logic instead of a second, drifting copy of it.
     *
     * FIXED: this had regressed to only reading ParsedResults[0] — i.e.
     * only page 1 of a multi-page OCR.space response — silently truncating
     * every scanned resume with more than one page down to its first page
     * before any scoring ever ran. Restored the loop over ALL pages in
     * $data['ParsedResults'], same as the original "Page 1 limitation" fix.
     */
    private function parseOcrResponseBody(string $rawBody, float $fileSizeMb): array
    {
        $data = json_decode($rawBody, true);

        // OCR.space very often responds HTTP 200 with a JSON body that
        // signals failure (file too large, quota exceeded, engine timeout,
        // unsupported file) rather than throwing an HTTP-level error.
        if (($data['IsErroredOnProcessing'] ?? false) === true) {
            $rawErr = $data['ErrorMessage'] ?? 'unknown error';
            $errMsg = is_array($rawErr) ? implode('; ', $rawErr) : $rawErr;
            $exitCode = $data['OCRExitCode'] ?? '?';
            Log::warning("Cloud OCR reported an error (ExitCode {$exitCode}): {$errMsg} | file size {$fileSizeMb}MB");
            return ['text' => '', '_failure_reason' => "OCR.space error (ExitCode {$exitCode}): {$errMsg} [file: {$fileSizeMb}MB]"];
        }

        // EXTRACT ALL PAGES (not just ParsedResults[0])
        $parsedResults = $data['ParsedResults'] ?? [];
        $fullText = '';
        $usedOverlay = false;

        foreach ($parsedResults as $result) {
            $overlayLines = $result['TextOverlay']['Lines'] ?? [];

            $pageText = !empty($overlayLines)
                ? trim($this->reconstructTextFromOverlay($overlayLines))
                : '';

            if ($pageText === '') {
                $pageText = trim($result['ParsedText'] ?? '');
            }

            if (!empty($overlayLines)) {
                $usedOverlay = true;
            }

            if ($pageText !== '') {
                $fullText .= $pageText . "\n\n";
            }
        }

        $fullText = trim($fullText);

        if (strlen($fullText) > 50) {
            return [
                'text' => $fullText,
                'method' => 'cloud_ocr',
                'page_count' => count($parsedResults) > 0 ? count($parsedResults) : 1,
                'char_count' => strlen($fullText),
                'used_overlay' => $usedOverlay,
            ];
        }

        return [
            'text' => '',
            '_failure_reason' => empty($parsedResults)
                ? "ParsedResults came back empty [file: {$fileSizeMb}MB] — request likely rejected without an explicit error flag"
                : 'parsed only ' . strlen($fullText) . " chars (need >50) [file: {$fileSizeMb}MB]",
        ];
    }

    //    Cloud OCR.space API call (no imagick, no local OCR) — single file
    private function tryCloudOcrNoImagick(string $pdfPath): array
    {
        try {
            $pdfContent = file_get_contents($pdfPath);
            if (!$pdfContent) return ['text' => '', '_failure_reason' => 'could not read file from disk'];

            $fileSizeMb = round(strlen($pdfContent) / 1024 / 1024, 2);
            $apiKey = $this->resolveOcrApiKey();

            if ($fileSizeMb > 1.0) {
                // OCR.space's free API tier caps out around 1MB/file. Canva
                // and other image-heavy exports — exactly the PDFs this
                // method exists for — routinely land above that. We still
                // attempt the call (the cap isn't razor-precise) but log it
                // loudly so this is the first thing you see.
                Log::warning("Cloud OCR: file is {$fileSizeMb}MB, over OCR.space's free-tier ~1MB cap — likely to be rejected.");
            }

            // UNBLOCK (TLS verify) + connection reuse now both live in
            // getOcrHttpClient() — see the property at the top of the
            // class for why.
            $client = $this->getOcrHttpClient();
            $response = $client->post('https://api.ocr.space/parse/image', [
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => $pdfContent,
                        'filename' => $this->ocrSafeFilename($pdfPath)
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
                        // Requesting overlay data gives us per-line position
                        // info so real structure can be rebuilt (see
                        // reconstructTextFromOverlay above). Confirmed via
                        // OCR.space's own docs this costs ~nothing extra on
                        // Engine 2 (the 2-3x overlay slowdown they mention
                        // only applies to Engine 3).
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

            return $this->parseOcrResponseBody((string) $response->getBody(), $fileSizeMb);
        } catch (\Exception $e) {
            Log::debug("Cloud OCR failed: " . $e->getMessage());
            return ['text' => '', '_failure_reason' => 'exception: ' . $e->getMessage()];
        }
    }

    /**
     * Fires OCR.space requests for MULTIPLE files CONCURRENTLY instead of
     * one at a time. $pathsByKey is [anyKey => pdfPath] (evaluateApplicants
     * uses application IDs as the key); returns [anyKey => extraction-
     * result-array] in the same shape tryCloudOcrNoImagick() returns.
     *
     * This is the actual fix for wall-clock time on a batch of brand-new
     * resumes that caching can't help with: total time becomes roughly the
     * slowest single OCR call instead of the sum of every call, sequentially.
     *
     * Concurrency capped at 5 deliberately — OCR.space's free tier is a
     * shared resource (500 req/day per IP); bursting harder than this risks
     * the API throttling the whole batch rather than actually going faster.
     */
    private function batchCloudOcr(array $pathsByKey): array
    {
        if (empty($pathsByKey)) {
            return [];
        }

        $apiKey = $this->resolveOcrApiKey();
        $client = $this->getOcrHttpClient();
        $fileSizes = [];

        $requests = function () use ($pathsByKey, $apiKey, &$fileSizes) {
            foreach ($pathsByKey as $key => $pdfPath) {
                $pdfContent = @file_get_contents($pdfPath);
                if (!$pdfContent) {
                    // Nothing to send for this key — it's filled in with an
                    // explicit failure after the pool finishes, below.
                    continue;
                }

                $fileSizes[$key] = round(strlen($pdfContent) / 1024 / 1024, 2);
                if ($fileSizes[$key] > 1.0) {
                    Log::warning("Batch Cloud OCR: file for key {$key} is {$fileSizes[$key]}MB, over OCR.space's free-tier ~1MB cap — likely to be rejected.");
                }

                $multipart = new \GuzzleHttp\Psr7\MultipartStream([
                    ['name' => 'file', 'contents' => $pdfContent, 'filename' => $this->ocrSafeFilename($pdfPath)],
                    ['name' => 'apikey', 'contents' => $apiKey],
                    ['name' => 'language', 'contents' => 'eng'],
                    ['name' => 'isOverlayRequired', 'contents' => 'true'],
                    ['name' => 'OCREngine', 'contents' => '2'],
                    ['name' => 'scale', 'contents' => 'true'],
                ]);

                yield $key => new \GuzzleHttp\Psr7\Request(
                    'POST',
                    'https://api.ocr.space/parse/image',
                    ['Content-Type' => 'multipart/form-data; boundary=' . $multipart->getBoundary()],
                    $multipart
                );
            }
        };

        $results = [];

        $pool = new \GuzzleHttp\Pool($client, $requests(), [
            'concurrency' => 5,
            'fulfilled' => function ($response, $key) use (&$results, &$fileSizes) {
                $fileSizeMb = $fileSizes[$key] ?? 0;
                $results[$key] = $this->parseOcrResponseBody((string) $response->getBody(), $fileSizeMb);
            },
            'rejected' => function ($reason, $key) use (&$results) {
                $message = $reason instanceof \Throwable ? $reason->getMessage() : (string) $reason;
                Log::debug("Batch Cloud OCR failed for key {$key}: " . $message);
                $results[$key] = ['text' => '', '_failure_reason' => 'exception: ' . $message];
            },
        ]);

        $pool->promise()->wait();

        // Any key whose file couldn't be read never got a request queued
        // at all, so no fulfilled/rejected callback ever fires for it —
        // fill those in explicitly so every input key gets an output.
        foreach ($pathsByKey as $key => $pdfPath) {
            if (!array_key_exists($key, $results)) {
                $results[$key] = ['text' => '', '_failure_reason' => 'could not read file from disk'];
            }
        }

        return $results;
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
        // FIX: "Maximum execution time of 30 seconds exceeded" is PHP's own
        // max_execution_time (web SAPI default, ~30s) killing the whole
        // script — separate from Guzzle's 45s per-request timeout below.
        // A single slow OCR.space call can take 20-30s on its own; PHP cuts
        // the request before Guzzle's own timeout ever gets a chance to.
        // Scoped to this method only, not php.ini, so it doesn't loosen
        // limits for the rest of the app.
        if (function_exists('set_time_limit')) {
            set_time_limit(120);
        }

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
            'status'               => $application->status ?? Application::STATUS_PENDING,
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
            'pipeline_trace'       => [],
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
        // FIX: same root cause as analyzeSandbox — PHP's own
        // max_execution_time, not Guzzle's. This one needs more headroom
        // than the sandbox: it runs extraction + scoring for EVERY
        // applicant sequentially in one request, so the OCR/Flask time
        // compounds per applicant rather than happening once. As the
        // applicant count grows, even this won't be enough forever — the
        // real long-term fix is moving extraction+scoring to a queued
        // background job per applicant so this request just dispatches
        // and returns. Worth doing once this isn't actively on fire.
        if (function_exists('set_time_limit')) {
            set_time_limit(300);
        }

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

        // ===================================================================
        // PASS 1 of 3 — fast/local only. Check cache, then try Smalot (it's
        // local and near-instant, no reason to batch it). Anything that
        // doesn't resolve here gets queued for the concurrent OCR batch
        // below instead of calling OCR.space one applicant at a time.
        // ===================================================================
        $ctx = [];
        $pendingOcr = [];

        foreach ($applications as $application) {
            $result = $this->baseResult($application);
            $feedback = [];
            $extracted = null;
            $smalotAttempt = null;
            $cacheKey = null;
            $fullPath = null;

            if ($application->resume_path) {
                $fullPath = Storage::path($application->resume_path);
                if (!file_exists($fullPath)) {
                    $fullPath = Storage::disk('public')->path($application->resume_path);
                }

                if (file_exists($fullPath)) {
                    $cacheKey = $this->extractionCacheKey($fullPath);
                    $cached = Cache::get($cacheKey);

                    if ($cached !== null) {
                        $extracted = $cached;
                    } else {
                        $smalot = $this->trySmalotParser($fullPath);
                        $smalotAttempt = $this->traceAttempt('smalot', $smalot);

                        if (!empty($smalot['text'])) {
                            $extracted = $smalot + ['attempts' => [$smalotAttempt]];
                            $this->cacheExtractionResult($cacheKey, $extracted);
                        } else {
                            // Needs OCR — queue it for the pooled batch call
                            // below instead of blocking here one applicant
                            // at a time.
                            $pendingOcr[$application->id] = $fullPath;
                        }
                    }
                } else {
                    $feedback[] = " File not found";
                }
            } else {
                $feedback[] = " No resume";
            }

            $ctx[$application->id] = [
                'result'        => $result,
                'feedback'      => $feedback,
                'extracted'     => $extracted,
                'smalotAttempt' => $smalotAttempt,
                'cacheKey'      => $cacheKey,
                'fullPath'      => $fullPath,
            ];
        }

        // ===================================================================
        // PASS 2 of 3 — fire every still-needed OCR.space request
        // CONCURRENTLY instead of one at a time. This is the actual fix for
        // wall-clock time on a batch of brand-new resumes: total time
        // becomes roughly the slowest single OCR call instead of the sum of
        // every call, sequentially.
        // ===================================================================
        if (!empty($pendingOcr)) {
            $ocrResults = $this->batchCloudOcr($pendingOcr);

            foreach ($ocrResults as $appId => $ocrResult) {
                $attempts = [$ctx[$appId]['smalotAttempt'], $this->traceAttempt('cloud_ocr', $ocrResult)];

                if (!empty($ocrResult['text'])) {
                    $extracted = $ocrResult + ['attempts' => $attempts];
                } else {
                    // OCR also failed — last resort, same as the
                    // non-batched path. Heuristic is local/fast, no need
                    // to pool it.
                    $heuristic = $this->tryHeuristic($ctx[$appId]['fullPath']);
                    $attempts[] = $this->traceAttempt('heuristic', $heuristic);

                    $extracted = !empty($heuristic['text'])
                        ? $heuristic + ['attempts' => $attempts]
                        : ['text' => '', 'method' => 'all_failed', 'page_count' => 1, 'char_count' => 0, 'attempts' => $attempts];
                }

                $this->cacheExtractionResult($ctx[$appId]['cacheKey'], $extracted);
                $ctx[$appId]['extracted'] = $extracted;
            }
        }

        // ===================================================================
        // PASS 3 of 3 — scoring. Identical logic to before; just reading
        // from $ctx (already fully resolved above) instead of extracting
        // inline per applicant.
        // ===================================================================
        foreach ($applications as $application) {
            $c = $ctx[$application->id];
            $result = $c['result'];
            $feedback = $c['feedback'];
            $extracted = $c['extracted'];
            $resumeText = $extracted['text'] ?? '';

            if ($extracted !== null) {
                $result['extraction_method'] = $extracted['method'] ?? null;
                $result['char_count'] = $extracted['char_count'] ?? 0;
                // Rides along in `$results` straight to the Blade view,
                // where a small <script> block can console.log/
                // console.table it — no log-tailing needed.
                $result['pipeline_trace'] = $extracted['attempts'] ?? [];

                if (!empty($resumeText)) {
                    $feedback[] = " " . $extracted['method'] . " ({$extracted['char_count']} chars)";
                } else {
                    $feedback[] = " No text extracted";
                }
            }

            // Scoring an empty string isn't "a low score" — it's analyzing
            // nothing. baseResult() already zeroes every score field, so
            // just attach feedback and skip the NLP call entirely.
            if (trim($resumeText) === '') {
                $feedback[] = 'Not scored — no usable resume text';
                $result['feedback'] = array_values(array_filter($feedback));
                $results[] = $result;
                continue;
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