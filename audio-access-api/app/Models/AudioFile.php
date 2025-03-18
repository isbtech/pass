<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AudioFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'filename',
        'file_path',
        'file_size',
        'duration',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function accessCodes()
    {
        return $this->hasMany(AccessCode::class);
    }
}