<?php

namespace App\Services\Push;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushSender implements PushSenderInterface
{
    private $webPush;

    // Criado so no primeiro envio: sem chaves VAPID no .env o erro cai no try/catch, nunca no pedido
    private function webPush(): WebPush
    {
        return $this->webPush ?: $this->webPush = new WebPush(
            ['VAPID' => [
                'subject' => config('services.vapid.subject'),
                'publicKey' => config('services.vapid.public'),
                'privateKey' => config('services.vapid.private'),
            ]],
            // TTL de 1h: pedido velho no celular so atrapalha. Urgency alta acorda o aparelho.
            ['TTL' => 3600, 'urgency' => 'high'],
            5 // timeout curto: nunca segurar o servidor esperando a Apple/Google
        );
    }

    public function enviar(PushSubscription $inscricao, array $payload): bool
    {
        try {
            $resultado = $this->webPush()->sendOneNotification(
                Subscription::create([
                    'endpoint' => $inscricao->endpoint,
                    'publicKey' => $inscricao->p256dh,
                    'authToken' => $inscricao->auth,
                ]),
                json_encode($payload, JSON_UNESCAPED_UNICODE)
            );
        } catch (\Throwable $e) {
            Log::warning('Push: falha ao enviar', ['erro' => $e->getMessage()]);
            \Illuminate\Support\Facades\Cache::put('push:ultima_falha', ['em' => now()->toIso8601String(), 'motivo' => mb_substr($e->getMessage(), 0, 80)], 86400);

            return true; // erro de rede nao e motivo para apagar o celular
        }

        if ($resultado->isSubscriptionExpired()) {
            return false;
        }

        if (!$resultado->isSuccess()) {
            Log::warning('Push: servico recusou', ['motivo' => $resultado->getReason()]);
            \Illuminate\Support\Facades\Cache::put('push:ultima_falha', ['em' => now()->toIso8601String(), 'motivo' => mb_substr((string) $resultado->getReason(), 0, 80)], 86400);
        }

        return true;
    }
}
