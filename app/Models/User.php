<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'email',
        'password',
        'role',   // 'guest' | 'admin'
    ];

    protected $hidden = ['password','remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // Favorites (many-to-many)
    public function favorites(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'favorites')->withTimestamps();
    }

    public function hasFavorited(int|Vehicle $vehicle): bool
    {
        $id = $vehicle instanceof Vehicle ? $vehicle->id : $vehicle;
        return $this->favorites()->where('vehicle_id', $id)->exists();
    }

    // Single check for admin
    public function isAdmin(): bool
    {
        return ($this->role === 'admin');
    }
}