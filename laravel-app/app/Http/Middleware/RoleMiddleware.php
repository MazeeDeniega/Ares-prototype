<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, $role) {
        if (!Auth::check()) {
            return redirect('/login');
        }

        $user = Auth::user();
        
        // Allow admin
        if ($role === 'admin' && $user->role !== 'admin') {
            abort(403, 'Admin access required');
        }
        
        // Allow recruiter
        if ($role === 'recruiter' && $user->role !== 'recruiter') {
            abort(403, 'Recruiter access required');
        }

        // Allow applicant
        if ($role === 'applicant' && $user->role !== 'applicant') {
            abort(403, 'Applicant access required');
        }

        return $next($request);
    }
}