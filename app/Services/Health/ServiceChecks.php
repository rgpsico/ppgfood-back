<?php

namespace App\Services\Health;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

/**
 * Verificacoes de saude do PPGFood para a tela de super admin. Cada uma devolve
 * ['nome', 'status' (ok|aviso|erro), 'detalhe', 'ms'] e NUNCA lanca excecao: uma peca fora do ar vira "erro" na tela.
 */
class ServiceChecks
{
    public function todas(): array
    {
        return [
            $this->banco(),
            $this->redis(),
            $this->socket(),
            $this->agendador(),
            $this->push(),
            $this->disco(),
            $this->armazenamento(),
        ];
    }

    private function medir(string $nome, callable $teste): array
    {
        $inicio = microtime(true);

        try {
            [$status, $detalhe] = $teste();
        } catch (\Throwable $e) {
            $status = 'erro';
            $detalhe = mb_substr($e->getMessage(), 0, 160);
        }

        return ['nome' => $nome, 'status' => $status, 'detalhe' => $detalhe, 'ms' => (int) round((microtime(true) - $inicio) * 1000)];
    }

    public function banco(): array
    {
        return $this->medir('Banco de dados (MySQL)', function () {
            DB::select('select 1');

            return ['ok', 'Respondendo'];
        });
    }

    public function redis(): array
    {
        return $this->medir('Redis (avisos em tempo real)', function () {
            Redis::connection()->ping();

            return ['ok', 'Respondendo'];
        });
    }

    /** Servidor de socket (laravel-echo-server): responde e aceita o handshake do socket.io. */
    public function socket(): array
    {
        return $this->medir('Socket (tempo real)', function () {
            $base = rtrim(config('services.health.echo_url', 'http://ppgfood_echo:6001'), '/');

            $raiz = Http::timeout(3)->get($base . '/');
            if (!$raiz->successful()) {
                return ['erro', 'Servidor de socket nao responde (HTTP ' . $raiz->status() . ')'];
            }

            $handshake = Http::timeout(3)->get($base . '/socket.io/', ['EIO' => 3, 'transport' => 'polling']);
            if (!$handshake->successful()) {
                return ['erro', 'Socket no ar, mas o socket.io recusou a conexao (HTTP ' . $handshake->status() . ')'];
            }

            return ['ok', 'Aceitando conexoes'];
        });
    }

    /** O agendador grava um sinal de vida a cada minuto (Kernel). Sem sinal = cron parado = lembretes nao saem. */
    public function agendador(): array
    {
        return $this->medir('Agendador (lembretes de pedido)', function () {
            $ultimo = Cache::get('scheduler:heartbeat');
            if (!$ultimo) {
                return ['erro', 'Nunca rodou (cron do schedule:run nao esta ligado?)'];
            }

            $segundos = Carbon::parse($ultimo)->diffInSeconds(now());

            if ($segundos > 180) {
                return ['erro', 'Parado ha ' . $this->duracao($segundos)];
            }

            return $segundos > 90
                ? ['aviso', 'Ultima execucao ha ' . $this->duracao($segundos)]
                : ['ok', 'Ultima execucao ha ' . $this->duracao($segundos)];
        });
    }

    public function push(): array
    {
        return $this->medir('Avisos no celular (push)', function () {
            if (!config('services.vapid.public') || !config('services.vapid.private')) {
                return ['erro', 'Chaves VAPID nao configuradas'];
            }

            $celulares = DB::table('push_subscriptions')->count();
            $falha = Cache::get('push:ultima_falha');

            if ($falha && Carbon::parse($falha['em'])->gt(now()->subHours(24))) {
                return ['aviso', "{$celulares} celular(es). Ultima falha de envio: " . Carbon::parse($falha['em'])->diffForHumans() . ' (' . $falha['motivo'] . ')'];
            }

            return ['ok', "{$celulares} celular(es) com avisos ativos"];
        });
    }

    public function disco(): array
    {
        return $this->medir('Disco do servidor', function () {
            $livre = disk_free_space('/');
            $total = disk_total_space('/');
            $pctLivre = $total ? round($livre / $total * 100) : 0;
            $detalhe = round($livre / 1073741824, 1) . ' GB livres de ' . round($total / 1073741824, 1) . " GB ({$pctLivre}% livre)";

            if ($pctLivre < 5) {
                return ['erro', $detalhe];
            }

            return [$pctLivre < 15 ? 'aviso' : 'ok', $detalhe];
        });
    }

    public function armazenamento(): array
    {
        return $this->medir('Gravacao de arquivos (fotos/logs)', function () {
            return is_writable(storage_path('app/public')) && is_writable(storage_path('logs'))
                ? ['ok', 'Pastas gravaveis']
                : ['erro', 'storage sem permissao de escrita (fotos e logs nao gravam)'];
        });
    }

    /** Ultimos erros do log da aplicacao: total nas ultimas 24 h e as 10 linhas mais recentes (so o inicio da mensagem). */
    public function erros(): array
    {
        $arquivo = storage_path('logs/laravel.log');
        $limite = now()->subHours(24);
        $total = 0;
        $recentes = [];

        if (is_readable($arquivo)) {
            // so o final do arquivo: o log pode ter dezenas de MB
            $tamanho = filesize($arquivo);
            $fh = fopen($arquivo, 'r');
            fseek($fh, max(0, $tamanho - 1500000));
            $trecho = stream_get_contents($fh);
            fclose($fh);

            preg_match_all('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(ERROR|CRITICAL|ALERT|EMERGENCY): (.*)$/m', $trecho, $m, PREG_SET_ORDER);

            foreach ($m as $linha) {
                if (Carbon::parse($linha[1])->lt($limite)) {
                    continue;
                }
                $total++;
                $recentes[] = ['em' => Carbon::parse($linha[1])->toIso8601String(), 'mensagem' => mb_substr($linha[3], 0, 220)];
            }
        }

        return ['ultimas_24h' => $total, 'recentes' => array_slice(array_reverse($recentes), 0, 10)];
    }

    /** Foto do servidor gravada a cada minuto pelo coletor do host (containers, sites, backups, memoria). */
    public function host(): array
    {
        $arquivo = storage_path('app/host-status.json');

        if (!is_readable($arquivo)) {
            return ['disponivel' => false, 'motivo' => 'O coletor do servidor ainda nao gravou nada.'];
        }

        $dados = json_decode((string) file_get_contents($arquivo), true);
        if (!is_array($dados) || empty($dados['gerado_em'])) {
            return ['disponivel' => false, 'motivo' => 'Arquivo do coletor ilegivel.'];
        }

        $idade = Carbon::parse($dados['gerado_em'])->diffInSeconds(now());
        $dados['disponivel'] = true;
        $dados['idade_segundos'] = $idade;
        $dados['desatualizado'] = $idade > 240;

        return $dados;
    }

    private function duracao(int $segundos): string
    {
        if ($segundos < 90) {
            return $segundos . ' s';
        }

        return $segundos < 5400 ? round($segundos / 60) . ' min' : round($segundos / 3600, 1) . ' h';
    }
}
