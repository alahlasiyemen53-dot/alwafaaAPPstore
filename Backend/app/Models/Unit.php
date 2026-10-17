<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = ['code', 'name_ar', 'name_en', 'decimal_places', 'is_active'];

    protected $casts = ['decimal_places' => 'integer', 'is_active' => 'boolean'];
}
