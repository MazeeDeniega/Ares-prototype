<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable {
    protected $fillable = ['name', 'email', 'password', 'role'];
    protected $hidden = ['password', 'remember_token'];

    public function isAdmin() 
    { 
        return $this->role === 'admin'; 
    }

    public function isRecruiter() 
    { 
        return $this->role === 'recruiter'; 
    }

    public function isApplicant() 
    { 
        return $this->role === 'applicant'; 
    }

    public function preference(): HasOne 
    { 
        return $this->hasOne(Preference::class); 
    }

    public function jobs(): HasMany 
    { 
        return $this->hasMany(Job::class); 
    }

    public function applications(): HasMany 
    { 
        return $this->hasMany(Application::class);
    }
}