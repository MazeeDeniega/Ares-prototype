<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ScreeningController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\ApplicantController;
use App\Http\Controllers\TestingController;
use Illuminate\Support\Facades\Auth;

    // Interactive Testing Sandbox UI
    Route::get('/screening/sandbox', [ScreeningController::class, 'showSandbox'])->name('screening.sandbox');
    // AJAX Execution Endpoint
    Route::post('/screening/sandbox/analyze', [ScreeningController::class, 'analyzeSandbox'])->name('screening.sandbox.analyze');

// Guest only routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister']);
    Route::post('/register', [AuthController::class, 'register']);
});

Route::post('/logout', [AuthController::class, 'logout']);

// Root redirect
Route::get('/', function () {
    if (!Auth::check()) {
        return redirect('/login');
    }

    $user = Auth::user();

    return match($user->role) {
        'admin'     => redirect('/admin'),
        'recruiter' => redirect('/recruiter'),
        'applicant' => redirect('/jobs'),
        default     => redirect('/login'),
    };
});

// Public Jobs
Route::get('/jobs', [JobController::class, 'index']);
Route::get('/jobs/{id}', [JobController::class, 'show']);
Route::get('/apply/{jobId}', [ApplicantController::class, 'showApplyForm']);
Route::post('/apply/{jobId}', [ApplicantController::class, 'apply']);

// Applicant Routes
Route::middleware(['auth', 'role:applicant'])->group(function () {
    Route::get('/my-applications', [ApplicantController::class, 'myApplications']);
});

// File serving — auth only, constrained to known types
Route::middleware('auth')
    ->get('/files/{applicationId}/{type}', [ScreeningController::class, 'serveFile'])
    ->where('type', 'resume|tor|cert');

// Recruiter
Route::middleware(['auth', 'role:recruiter'])->group(function () {
    Route::get('/recruiter', [DashboardController::class, 'index']);
    Route::post('/jobs', [JobController::class, 'store']);
    Route::delete('/jobs/{id}', [JobController::class, 'destroy']);
    Route::get('/preferences/edit', [PreferenceController::class, 'edit']);
    Route::post('/preferences', [PreferenceController::class, 'update']);
    Route::get('/screening/{jobId}', [ScreeningController::class, 'showJobApplicants']);
    Route::get('/candidates', [DashboardController::class, 'index']); 
    Route::get('/api/candidates', [ScreeningController::class, 'getAllCandidates']);
    Route::patch('/api/candidates/{id}', [ScreeningController::class, 'updateCandidateStatus']);

    Route::match(['get', 'post'], '/screening/{jobId}/evaluate', [ScreeningController::class, 'evaluateApplicants'])->name('screen.evaluate');
    Route::get('/jobs/{id}/preferences', [PreferenceController::class, 'editJobPreference']);
    Route::post('/jobs/{id}/preferences', [PreferenceController::class, 'updateJobPreference']);

});

// Admin
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin', [DashboardController::class, 'adminIndex']);

    Route::delete('/users/{id}', [DashboardController::class, 'deleteUser']);
    Route::post('/users/{id}/role', [DashboardController::class, 'updateUserRole']);
});

// ── Testing only — remove before production ──────────────────────────
Route::middleware(['auth'])->prefix('testing')->name('testing.')->group(function () {
    Route::get('/mass-upload',          [TestingController::class, 'showMassUpload'])->name('mass-upload.index');
    Route::post('/mass-upload',         [TestingController::class, 'massUpload'])->name('mass-upload.store');
    Route::delete('/mass-upload/clear/{jobId}', [TestingController::class, 'clearTestApplications'])->name('mass-upload.clear');
});
