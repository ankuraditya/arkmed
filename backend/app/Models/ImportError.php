<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportError extends Model
{
    protected $fillable = ['import_job_id', 'row_number', 'raw_json', 'error_message'];

    protected function casts(): array
    {
        return ['raw_json' => 'array'];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ImportJob::class, 'import_job_id');
    }
}
