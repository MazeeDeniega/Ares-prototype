<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Application extends Model
{
    protected $fillable = [
        'job_id', 'user_id',
        // Personal Details
        'first_name', 'last_name', 'email',
        'phone', 'address', 'city', 'province', 'postal_code', 'country',
        // Documents
        'resume_path', 'tor_path', 'cert_path',
        // Employment
        'date_available', 'desired_pay', 'highest_education',
        'college_university', 'referred_by', 'references', 'engagement_type',
        // Status
        'status'
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_REJECTED = 'rejected';

    const ENGAGEMENT_FULL_TIME = 'full_time';
    const ENGAGEMENT_PART_TIME = 'part_time';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }
}