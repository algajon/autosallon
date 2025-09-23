<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Vehicle extends Model
{
    protected $fillable = [
        'prodhuesi','modeli','varianti','viti',
        'cmimi_eur','kilometrazhi_km','karburanti','ngjyra',
        'transmisioni','uleset','vin','engine_cc',
        'images','listing_url','opsionet','raporti_url',
    ];
    protected $guarded = [];

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites')
            ->withTimestamps();
    }

    public function isFavoritedBy(?User $user): bool
    {
        if (!$user) return false;
        return $this->favoritedBy()->where('user_id', $user->id)->exists();
    }
    protected $casts = [
        'images'          => 'array',   // stored as JSON
        'viti'            => 'integer',
        'cmimi_eur'       => 'integer',
        'kilometrazhi_km' => 'integer',
        'uleset'          => 'integer',
        'engine_cc'       => 'integer',
    ];

    protected $appends = [
        'main_image_url', 'display_title', 'price_formatted', 'mileage_formatted',
    ];

    /* -------------------------------
     |  Mutators / Normalizers
     |-------------------------------*/
    public function setImagesAttribute($value): void
    {
        if (is_array($value)) {
            $this->attributes['images'] = json_encode(array_values(array_filter($value)));
            return;
        }
        if (is_string($value)) {
            $trim = trim($value);
            if ($trim !== '' && $trim[0] === '[' && substr($trim, -1) === ']') {
                $this->attributes['images'] = $trim;
                return;
            }
            $parts = preg_split('/[\r\n;,]+/u', $trim) ?: [];
            $parts = array_values(array_filter(array_map('trim', $parts)));
            $this->attributes['images'] = json_encode($parts);
            return;
        }
        $this->attributes['images'] = json_encode([]);
    }

    /* -------------------------------
     |  Accessors for views
     |-------------------------------*/
    public function getMainImageUrlAttribute(): string
    {
        $imgs = $this->images ?? [];
        if (is_string($imgs)) {
            $decoded = json_decode($imgs, true);
            $imgs = is_array($decoded)
                ? $decoded
                : array_values(array_filter(array_map('trim', explode(';', $imgs))));
        }
        return $imgs[0] ?? 'https://via.placeholder.com/640x360?text=No+Image';
    }

    public function getDisplayTitleAttribute(): string
    {
        $parts = array_filter([
            $this->viti,
            $this->prodhuesi ?? $this->manufacturer ?? null,
            $this->modeli    ?? $this->model        ?? null,
        ]);
        return trim(implode(' ', $parts));
    }

    public function getPriceFormattedAttribute(): string
    {
        return $this->cmimi_eur
            ? '€' . number_format($this->cmimi_eur, 0, ',', '.')
            : '—';
    }

    public function getMileageFormattedAttribute(): string
    {
        return $this->kilometrazhi_km
            ? number_format($this->kilometrazhi_km, 0, ',', '.') . ' km'
            : '—';
    }

    public function getImagesStringAttribute(): string
    {
        $imgs = $this->images ?? [];
        if (is_string($imgs)) {
            $decoded = json_decode($imgs, true);
            $imgs = is_array($decoded)
                ? $decoded
                : array_values(array_filter(array_map('trim', explode(';', $imgs))));
        }
        return implode(';', $imgs);
    }
}
