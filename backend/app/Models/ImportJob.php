<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportJob extends Model
{
    protected $fillable = ['public_id', 'file_name', 'stored_path', 'status', 'total_rows', 'success_rows', 'failed_rows', 'created_by'];

    public function errors(): HasMany
    {
        return $this->hasMany(ImportError::class);
    }
}
