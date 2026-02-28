<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\Job;
use App\Models\Preference;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $jobs = Auth::user()->jobs()->with('applications')->get();
        return view('dashboard.index', compact('jobs'));
    }

    public function adminIndex()
    {
        $users = \App\Models\User::all();
        return view('dashboard.admin', compact('users'));
    }
    
    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        
        // Prevent deleting admin
        if ($user->role === 'admin') {
            return back()->with('error', 'Cannot delete admin');
        }
        
        $user->delete();
        return back()->with('success', 'User deleted');
    }

    public function updateUserRole(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $user->update(['role' => $request->role]);
        
        // Create preference for recruiter
        if ($request->role === 'recruiter' && !$user->preference) {
            Preference::create([
                'user_id' => $user->id,
                'skills_weight' => 35,
                'experience_weight' => 20,
                'education_weight' => 25,
                'cert_weight' => 10,
                'keyword_weight' => 40,
                'semantic_weight' => 60,
                'layout_weight' => 0,
            ]);
        }
        
        return back()->with('success', 'Role updated');
    }
}