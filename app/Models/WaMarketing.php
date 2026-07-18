<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaMarketing extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'coverage_area',
        'phone_number',
        'is_active',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
