<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $fillable = [
        'eyebrow_en',
        'eyebrow_ar',
        'title_en',
        'title_ar',
        'desc_en',
        'desc_ar',
        'map_url',
        'location_name',
        'lat',
        'lng',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
    ];
}
