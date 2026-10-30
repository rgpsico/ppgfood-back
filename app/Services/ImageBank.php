<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Cliente do banco de imagens compartilhado (MediaHub).
 * Só lê: lista/busca as imagens e COPIA a escolhida para o storage do próprio sistema,
 * assim o cardápio não quebra se a imagem for apagada ou o link assinado expirar.
 */
class ImageBank
{
    public function enabled(): bool
    {
        return filled(config('services.mediahub.url')) && filled(config('services.mediahub.key'));
    }

    private function http()
    {
        return Http::withToken((string) config('services.mediahub.key'))
            ->acceptJson()
            ->timeout(15)
            ->baseUrl(rtrim((string) config('services.mediahub.url'), '/') . '/api/v1/integration');
    }

    public function collections(): array
    {
        return Cache::remember('imagebank:collections', 300, fn () => $this->http()->get('/collections')->throw()->json('data') ?? []);
    }

    /** @return array{data: array<int, array{id:string,name:string,thumb:string}>, last_page:int} */
    public function search(?string $term, ?string $collection, int $page = 1): array
    {
        $params = array_filter([
            'type' => 'image',
            'search' => $term,
            'collection' => $collection,
            'per_page' => 24,
            'page' => max(1, $page),
            'url_ttl' => 3600,
        ], fn ($v) => $v !== null && $v !== '');

        // Cache menor que o url_ttl para nunca devolver link assinado vencido.
        $json = Cache::remember('imagebank:' . md5(json_encode($params)), 600, fn () => $this->http()->get('/media', $params)->throw()->json());

        return [
            'data' => collect($json['data'] ?? [])->map(fn ($m) => [
                'id' => $m['id'],
                'name' => $m['name'],
                'thumb' => $m['urls']['thumbnail'] ?? null,
            ])->values()->all(),
            'last_page' => (int) ($json['meta']['last_page'] ?? 1),
        ];
    }

    /** Baixa a imagem escolhida (1200 px, WebP) e grava no disco padrão. Devolve o caminho salvo. */
    public function copyTo(string $mediaId, string $directory): string
    {
        if (! preg_match('/^[0-9A-Za-z]{20,40}$/', $mediaId)) {
            throw new RuntimeException('Imagem inválida.');
        }

        $res = $this->http()->get("/media/{$mediaId}/file/preview");
        if (! $res->successful() || ! str_starts_with((string) $res->header('Content-Type'), 'image/')) {
            throw new RuntimeException('Não foi possível copiar a imagem do banco.');
        }

        $ext = str_contains((string) $res->header('Content-Type'), 'webp') ? 'webp' : 'jpg';
        $path = trim($directory, '/') . '/' . (string) Str::uuid() . '.' . $ext;
        Storage::put($path, $res->body());

        return $path;
    }
}
