<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'image_path',
        'is_primary',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the full URL for the image.
     */
    protected function imagePath(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn ($value) => filter_var($value, FILTER_VALIDATE_URL) ? $value : asset('storage/' . $value),
        );
    }
}
