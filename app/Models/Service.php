<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = [
        'title_en', 'title_ar',
        'description_en', 'description_ar',
        'icon', 'gif', 'order',
    ];
}