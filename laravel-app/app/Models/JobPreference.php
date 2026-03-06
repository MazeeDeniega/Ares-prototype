<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobPreference extends Model
{
    protected $fillable = [
        'job_id',
        'keyword_weight',
        'semantic_weight',
        'skills_weight',
        'experience_weight',
        'education_weight',
        'cert_weight',
        'layout_weight',
        'pref_formatting',
        'pref_language',
        'pref_conciseness',
        'pref_organization',
    ];
    
    public function job(): BelongsTo 
    { 
        return $this->belongsTo(Job::class); 
    }
}