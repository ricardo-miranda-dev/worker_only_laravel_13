<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KommoToken extends Model
{
    protected $fillable = [
        'subdomain',
        'access_token',
        'refresh_token',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return now()->gte($this->expires_at->subMinutes(5));
    }

    public static function getCurrent(): ?self
    {
        return static::where('subdomain', config('services.kommo.subdomain'))
            ->latest()
            ->first();
    }
}