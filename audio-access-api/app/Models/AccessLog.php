<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccessLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'access_code_id',
        'ip_address',
        'user_agent',
        'play_duration',
        'is_complete',
    ];

    protected $casts = [
        'is_complete' => 'boolean',
    ];

    public function accessCode()
    {
        return $this->belongsTo(AccessCode::class);
    }
}