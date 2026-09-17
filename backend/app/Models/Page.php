<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = ['title', 'slug', 'body', 'seo_title', 'seo_description', 'is_active', 'published_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'published_at' => 'datetime'];
    }
}
