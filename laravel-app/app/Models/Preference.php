<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Preference extends Model
{
    protected $fillable = [
        'user_id',
        'keyword_weight',
        'semantic_weight',
        'qual_weight',
        'skills_weight',
        'experience_weight',
        'education_weight',
        'cert_weight',
        'layout_weight',
        'formatting_weight',
        'language_weight',
        'concise_weight',
        'organization_weight',
        'pref_formatting',
        'pref_language',
        'pref_conciseness',
        'pref_organization',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}