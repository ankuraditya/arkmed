<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enquiry extends Model
{
    protected $fillable = ['type', 'name', 'mobile', 'email', 'message', 'payload_json', 'status'];

    protected function casts(): array
    {
        return ['payload_json' => 'array'];
    }
}
