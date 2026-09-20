<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = [
        'cnpj',
        'name',
        'asaas_key',
        'url',
        'email',
        'logo',
        'active',
        'subscription',
        'expires_at',
        'subscription_id',
        'subscription_active',
        'subscription_suspended',
    ];


    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Resolve a URL do logo, aceitando tanto um arquivo salvo em storage/
     * quanto um link externo direto.
     */
    public function getLogoUrlAttribute(): string
    {
        if (! $this->logo) {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $this->logo)) {
            return $this->logo;
        }

        return url("storage/{$this->logo}");
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
