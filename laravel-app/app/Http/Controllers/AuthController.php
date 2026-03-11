<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Preference;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin() { return view('auth.login'); }
    public function showRegister() { return view('auth.register'); }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required',
            'password' => 'required'
        ]);

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            $request->session()->regenerate();
            
            if ($user->role === 'admin') {
                return redirect('/admin');
            } elseif ($user->role === 'recruiter') {
                return redirect('/recruiter');
            } else {
                return redirect('/jobs');
            }
            if ($request->expectsJson()) {
            return response()->json(['redirect' => '/dashboard']);
            }

            return redirect()->intended('/dashboard');
        }
        
        return back()->withErrors(['email' => 'Invalid credentials']);
    }

    public function register(Request $request)
{
    $request->validate([
        'name' => 'required',
        'email' => 'required|unique:users',
        'password' => 'required',
        // 'role' => 'required|in:recruiter,applicant'
    ]);
    
    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
        'role' => 'recruiter', //defaulted 
    ]);

    // Create default preferences for recruiters
    if ($request->role === 'recruiter') {
        Preference::create([
            'user_id' => $user->id,
            'skills_weight' => 45,
            'experience_weight' => 20,
            'education_weight' => 25,
            'cert_weight' => 10,
            'keyword_weight' => 40,
            'semantic_weight' => 60,
            'layout_weight' => 0,
        ]);
    }

    Auth::login($user);

    if ($request->expectsJson()) {
        return response()->json([
            'success' => true,
            'redirect' => '/recruiter'
        ]);
    }

    return redirect('/recruiter');
}

    public function logout()
    {
        Auth::logout();
        return redirect('/login');
    }
}