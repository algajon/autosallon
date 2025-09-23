<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InStock extends Model
{
    protected $table = 'instocks';

    protected $fillable = [
        'name',
        'description',
        'price',
        'images',
    ];

    protected $casts = [
        'images' => 'array',
    ];

    public function getMainImageUrlAttribute(): string
    {
        $imgs = $this->images ?? [];
        if (is_string($imgs)) {
            $decoded = json_decode($imgs, true);
            $imgs = is_array($decoded)
                ? $decoded
                : array_values(array_filter(array_map('trim', explode(';', $imgs))));
        }
        return $imgs[0] ?? 'https://via.placeholder.com/640x360?text=N%C3%AB+Stok';
    }
}
