<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = [
        'title_en', 'title_ar',
        'description_en', 'description_ar',
        'image', 'technologies',
    ];

    protected $casts = [
        'technologies' => 'array',
    ];
}