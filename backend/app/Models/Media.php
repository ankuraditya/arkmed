<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = ['disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'alt_text', 'uploaded_by'];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        return asset('storage/'.$this->path);
    }
}
