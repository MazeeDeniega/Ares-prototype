<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\Job;
use App\Models\Application;

class SeedTestResumes extends Command
{
    protected $signature = 'ares:seed-resumes
                            {job_id : ID of the job to attach resumes to}
                            {--path= : Folder containing PDFs (defaults to storage/app/test-resumes/)}
                            {--prefix=Candidate : Label prefix for placeholder names}
                            {--clear : Delete existing test applications for this job first}';

    protected $description = 'Bulk-seed test Application rows from a folder of PDFs, using placeholder personal data';

    private function placeholderLabel(int $index): string
    {
        $label = '';
        do {
            $label = chr(65 + ($index % 26)) . $label;
            $index = intdiv($index, 26) - 1;
        } while ($index >= 0);
        return $label;
    }

    public function handle(): int
    {
        $jobId  = (int) $this->argument('job_id');
        $prefix = $this->option('prefix');
        $folder = $this->option('path') ?? storage_path('app/test-resumes');

        $job = Job::find($jobId);
        if (!$job) {
            $this->error("Job #{$jobId} not found.");
            return 1;
        }

        if (!is_dir($folder)) {
            $this->error("Folder not found: {$folder}");
            $this->line("Create it and drop PDFs inside, then re-run.");
            return 1;
        }

        // --clear: wipe existing test entries for this job first
        if ($this->option('clear')) {
            $existing = Application::where('job_id', $jobId)
                ->where('email', 'like', '%@test.local')
                ->get();
            foreach ($existing as $app) {
                if ($app->resume_path) {
                    Storage::disk('public')->delete($app->resume_path);
                }
                $app->delete();
            }
            $this->info("Cleared {$existing->count()} existing test application(s).");
        }

        $pdfs = glob($folder . DIRECTORY_SEPARATOR . '*.pdf');
        if (empty($pdfs)) {
            $this->warn("No PDFs found in: {$folder}");
            return 0;
        }

        $this->info("Found " . count($pdfs) . " PDF(s). Seeding into job #{$jobId} ({$job->title})...");

        // Offset so re-running without --clear doesn't produce duplicate labels
        $offset = Application::where('job_id', $jobId)
            ->where('email', 'like', '%@test.local')
            ->count();

        $bar = $this->output->createProgressBar(count($pdfs));
        $bar->start();

        $created = 0;
        $failed  = [];

        foreach ($pdfs as $i => $pdfPath) {
            try {
                $filename = basename($pdfPath);
                $label    = $prefix . ' ' . $this->placeholderLabel($offset + $i);

                // Copy into public storage so serveFile() and the extraction
                // pipeline find it at the same path as real applicant resumes.
                $dest = 'resumes/' . \Illuminate\Support\Str::random(40) . '.pdf';
                Storage::disk('public')->put($dest, file_get_contents($pdfPath));

                Application::create([
                    'job_id'            => $jobId,
                    'user_id'           => null,
                    'first_name'        => $prefix,
                    'last_name'         => $this->placeholderLabel($offset + $i),
                    'email'             => strtolower(str_replace(' ', '.', $label)) . '@test.local',
                    'phone'             => '+63 000 000 0000',
                    'city'              => 'Test City',
                    'province'          => 'Test Province',
                    'postal_code'       => '0000',
                    'country'           => 'Philippines',
                    'date_available'    => now()->toDateString(),
                    'desired_pay'       => '0',
                    'highest_education' => "Bachelor's Degree",
                    'college_university'=> 'Test University',
                    'engagement_type'   => 'Full-time',
                    'status'            => 'Pending',
                    'resume_path'       => $dest,
                    'tor_path'          => null,
                    'cert_path'         => null,
                    'referred_by'       => null,
                    'references'        => null,
                    'address'           => 'Test Address',
                ]);

                $created++;
            } catch (\Exception $e) {
                $failed[] = basename($pdfPath) . ': ' . $e->getMessage();
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Created {$created} application(s) for job #{$jobId} ({$job->title}).");

        if (!empty($failed)) {
            $this->warn(count($failed) . " file(s) failed:");
            foreach ($failed as $f) {
                $this->line("  - {$f}");
            }
        }

        $this->line("Evaluate at: " . url("/screening/{$jobId}/evaluate"));
        return 0;
    }
}