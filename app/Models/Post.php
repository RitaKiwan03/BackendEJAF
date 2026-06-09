<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $fillable = [
        'title_en', 'title_ar',
        'excerpt_en', 'excerpt_ar',
        'content_en', 'content_ar',
        'slug', 'image', 'tags',
        'created_at_display',
    ];

    protected $casts = [
        'tags' => 'array',
    ];
}