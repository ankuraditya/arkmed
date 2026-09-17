<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemporaryPrescriptionUpload extends Model
{
    protected $fillable = ['token', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'checksum', 'expires_at', 'claimed_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'claimed_at' => 'datetime'];
    }
}
