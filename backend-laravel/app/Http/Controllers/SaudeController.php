<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\UploadService;
use App\Support\Fuso;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Verificação de saúde no padrão da Norma Técnica TI-SECEC (C.8).
 *
 * - `/health/live`: o processo responde. Não consulta dependência nenhuma — é o
 *   que o Nginx usa para saber se a instância está de pé, e uma queda do banco
 *   não pode fazer todas as instâncias parecerem mortas ao mesmo tempo.
 * - `/health/ready`: a instância pode receber requisições (banco e armazenamento
 *   de arquivos respondem). 503 quando não.
 * - `/sistema/status`: a mesma verificação, para pessoas.
 *
 * Só entram as dependências que o AVASEC USA hoje. Redis, fila e agendador não
 * aparecem porque não existem nesta aplicação ainda; listá-los como
 * "operacional" seria afirmar algo que ninguém verificou.
 *
 * Nada aqui revela endereço, porta, versão, caminho ou mensagem de erro (C.8.1):
 * a exceção é engolida e vira só "indisponível".
 */
final class SaudeController extends Controller
{
    /** Arquivo gravado e removido a cada verificação do armazenamento. */
    private const SONDA = '.verificacao-de-saude';

    public function live(): JsonResponse
    {
        return $this->semCache(response()->json(['status' => 'ok']));
    }

    public function ready(): JsonResponse
    {
        $verificacoes = $this->verificar();
        $pronto = ! in_array(false, $verificacoes, true);

        return $this->semCache(response()->json([
            'status' => $pronto ? 'ok' : 'indisponivel',
            'verificacoes' => array_map(static fn (bool $ok): string => $ok ? 'ok' : 'indisponivel', $verificacoes),
        ], $pronto ? 200 : 503));
    }

    public function status(): Response
    {
        $verificacoes = $this->verificar();

        /** @var View $pagina */
        $pagina = view('sistema.status', [
            'componentes' => [
                'Aplicação' => true,
                'Banco de dados' => $verificacoes['banco'],
                'Armazenamento de arquivos' => $verificacoes['armazenamento'],
            ],
            'verificadoEm' => Fuso::agora()->format('d/m/Y H:i:s'),
        ]);

        return $this->semCache(response($pagina->render()));
    }

    /**
     * @return array{banco: bool, armazenamento: bool}
     */
    private function verificar(): array
    {
        return [
            'banco' => $this->bancoResponde(),
            'armazenamento' => $this->armazenamentoGravavel(),
        ];
    }

    private function bancoResponde(): bool
    {
        try {
            DB::connection()->select('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Os discos de upload aceitam escrita: grava e remove um arquivo-sonda em
     * cada um. Pelo disco, e não pela pasta, para a verificação continuar valendo
     * quando o driver virar S3/MinIO. O disco cria a pasta que faltar, então uma
     * instalação nova não nasce "indisponível" por ainda não ter a pasta privada.
     */
    private function armazenamentoGravavel(): bool
    {
        try {
            foreach (['public', 'private'] as $visibilidade) {
                $disco = Storage::disk(UploadService::nomeDoDisco($visibilidade));
                $disco->put(self::SONDA, 'ok');
                $disco->delete(self::SONDA);
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @template T of \Symfony\Component\HttpFoundation\Response
     *
     * @param  T  $resposta
     * @return T
     */
    private function semCache($resposta)
    {
        // Resposta de saúde em cache mentiria sobre o estado atual.
        $resposta->headers->set('Cache-Control', 'no-store');

        return $resposta;
    }
}
