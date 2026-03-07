<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Job;
use App\Models\Application;
use App\Models\Preference;
use App\Models\JobPreference;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ----------------------------------------------------------------
        // Truncate all tables — FK-safe regardless of order
        // ----------------------------------------------------------------
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            foreach (['applications', 'job_preferences', 'preferences', 'jobs', 'users'] as $table) {
                DB::table($table)->truncate();
            }
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        } else {
            // SQLite / PostgreSQL — truncate in FK-safe order
            DB::table('applications')->delete();
            DB::table('job_preferences')->delete();
            DB::table('preferences')->delete();
            DB::table('jobs')->delete();
            DB::table('users')->delete();
        }

        // ----------------------------------------------------------------
        // Copy sample resume PDFs into private storage so serveFile works.
        // Place your sample PDFs in: database/seeders/sample_resumes/
        // ----------------------------------------------------------------
        $sampleDir = database_path('seeders/sample_resumes');
        Storage::makeDirectory('resumes');
        Storage::makeDirectory('documents');

        foreach (['sample_resume_1.pdf', 'sample_resume_2.pdf', 'sample_resume_3.pdf'] as $file) {
            $src = $sampleDir . '/' . $file;
            if (file_exists($src)) {
                Storage::put('resumes/' . $file, file_get_contents($src));
            }
        }

        // ----------------------------------------------------------------
        // Admin
        // ----------------------------------------------------------------
        User::create([
            'name'     => 'Admin',
            'email'    => 'admin',
            'password' => Hash::make('admin123'),
            'role'     => 'admin',
        ]);

        // ----------------------------------------------------------------
        // Recruiter + user-level default preferences
        // ----------------------------------------------------------------
        $recruiter = User::create([
            'name'     => 'Recruiter User',
            'email'    => 'recruiter@test.com',
            'password' => Hash::make('password'),
            'role'     => 'recruiter',
        ]);

        Preference::create([
            'user_id'             => $recruiter->id,
            'keyword_weight'      => 40,
            'semantic_weight'     => 60,
            'skills_weight'       => 35,
            'experience_weight'   => 20,
            'education_weight'    => 25,
            'cert_weight'         => 10,
            'layout_weight'       => 0,
            'formatting_weight'   => 25,
            'language_weight'     => 25,
            'concise_weight'      => 25,
            'organization_weight' => 25,
        ]);

        // ----------------------------------------------------------------
        // Jobs
        // ----------------------------------------------------------------
        $job1 = Job::create([
            'user_id'     => $recruiter->id,
            'title'       => 'Backend Developer',
            'description' => 'Looking for a Backend Developer with Laravel, PHP, MySQL experience. Must have at least 2 years of experience.',
        ]);

        $job2 = Job::create([
            'user_id'     => $recruiter->id,
            'title'       => 'Frontend Developer',
            'description' => 'Seeking Frontend Developer with HTML, CSS, JavaScript, Vue.js or React skills.',
        ]);

        $job3 = Job::create([
            'user_id'     => $recruiter->id,
            'title'       => 'Full Stack Developer',
            'description' => 'Need a Full Stack Developer with both frontend and backend experience.',
        ]);

        // ----------------------------------------------------------------
        // Job-level preference override for job1 (to test override behaviour)
        // Experience weighted heavier for a senior backend role.
        // ----------------------------------------------------------------
        JobPreference::create([
            'job_id'              => $job1->id,
            'keyword_weight'      => 40,
            'semantic_weight'     => 60,
            'skills_weight'       => 30,
            'experience_weight'   => 35,
            'education_weight'    => 25,
            'cert_weight'         => 10,
            'layout_weight'       => 0,
            'formatting_weight'   => 25,
            'language_weight'     => 25,
            'concise_weight'      => 25,
            'organization_weight' => 25,
        ]);

        // ----------------------------------------------------------------
        // Applicants
        // ----------------------------------------------------------------
        $applicant1 = User::create([
            'name'     => 'John Applicant',
            'email'    => 'applicant@test.com',
            'password' => Hash::make('password'),
            'role'     => 'applicant',
        ]);

        $applicant2 = User::create([
            'name'     => 'Jane Smith',
            'email'    => 'jane@test.com',
            'password' => Hash::make('password'),
            'role'     => 'applicant',
        ]);

        $applicant3 = User::create([
            'name'     => 'Mike Johnson',
            'email'    => 'mike@test.com',
            'password' => Hash::make('password'),
            'role'     => 'applicant',
        ]);

        // ----------------------------------------------------------------
        // Applications
        // ----------------------------------------------------------------
        Application::create([
            'job_id'             => $job1->id,
            'user_id'            => $applicant1->id,
            'first_name'         => 'John',
            'last_name'          => 'Applicant',
            'email'              => 'applicant@test.com',
            'phone'              => '123-456-7890',
            'address'            => '123 Main St',
            'city'               => 'Manila',
            'province'           => 'Metro Manila',
            'postal_code'        => '1000',
            'country'            => 'Philippines',
            'resume_path'        => 'resumes/sample_resume_1.pdf',
            'tor_path'           => 'documents/tor_1.pdf',
            'cert_path'          => 'documents/cert_1.pdf',
            'date_available'     => '2026-03-15',
            'desired_pay'        => '50000',
            'highest_education'  => 'bachelor',
            'college_university' => 'University of PHP',
            'referred_by'        => 'LinkedIn',
            'references'         => 'Mr. Smith - smith@email.com',
            'engagement_type'    => 'full_time',
            'status'             => 'pending',
        ]);

        Application::create([
            'job_id'             => $job1->id,
            'user_id'            => $applicant2->id,
            'first_name'         => 'Jane',
            'last_name'          => 'Smith',
            'email'              => 'jane@test.com',
            'phone'              => '987-654-3210',
            'address'            => '456 Oak Ave',
            'city'               => 'Cebu',
            'province'           => 'Cebu',
            'postal_code'        => '6000',
            'country'            => 'Philippines',
            'resume_path'        => 'resumes/sample_resume_2.pdf',
            'tor_path'           => null,
            'cert_path'          => 'documents/cert_2.pdf',
            'date_available'     => '2026-04-01',
            'desired_pay'        => '60000',
            'highest_education'  => 'master',
            'college_university' => 'University of JavaScript',
            'referred_by'        => 'Friend',
            'references'         => '',
            'engagement_type'    => 'part_time',
            'status'             => 'pending',
        ]);

        Application::create([
            'job_id'             => $job2->id,
            'user_id'            => $applicant3->id,
            'first_name'         => 'Mike',
            'last_name'          => 'Johnson',
            'email'              => 'mike@test.com',
            'phone'              => '555-123-4567',
            'address'            => '789 Pine Rd',
            'city'               => 'Davao',
            'province'           => 'Davao del Sur',
            'postal_code'        => '8000',
            'country'            => 'Philippines',
            'resume_path'        => 'resumes/sample_resume_3.pdf',
            'tor_path'           => 'documents/tor_3.pdf',
            'cert_path'          => null,
            'date_available'     => '2026-03-01',
            'desired_pay'        => '45000',
            'highest_education'  => 'associate',
            'college_university' => 'Tech College',
            'referred_by'        => 'Website',
            'references'         => 'Ms. Doe - doe@email.com',
            'engagement_type'    => 'full_time',
            'status'             => 'pending',
        ]);

        echo "Seeding complete!\n";
        echo "Admin:      admin / admin123\n";
        echo "Recruiter:  recruiter@test.com / password\n";
        echo "Applicants: applicant@test.com, jane@test.com, mike@test.com / password\n";
        echo "Note: job1 (Backend Developer) has a job-level preference override seeded.\n";
    }
}