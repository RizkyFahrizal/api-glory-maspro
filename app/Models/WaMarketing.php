<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaMarketing extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'position',
        'queue_order',
        'last_assigned_at',
        'is_next_in_queue',
        'coverage_area',
        'phone_number',
        'is_active',
    ];

    protected $casts = [
        'last_assigned_at' => 'datetime',
        'is_active' => 'boolean',
        'is_next_in_queue' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
