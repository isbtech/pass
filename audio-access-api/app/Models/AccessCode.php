<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccessCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'audio_file_id',
        'validity_type',
        'validity_value',
        'expires_at',
        'max_plays',
        'is_active',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function audioFile()
    {
        return $this->belongsTo(AudioFile::class);
    }

    public function accessLogs()
    {
        return $this->hasMany(AccessLog::class);
    }
}