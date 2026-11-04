<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Celular (ou navegador) de um usuario do painel que ativou os avisos de pedido novo.
 */
class PushSubscription extends Model
{
    protected $fillable = ['user_id', 'endpoint_hash', 'endpoint', 'p256dh', 'auth', 'user_agent'];

    public static function hashDoEndpoint(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
