<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionFile extends Model
{
    protected $fillable = ['prescription_id', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'checksum'];

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }
}
