<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Services\CaixaDeMensagens;
use App\Services\MessagingService;
use App\Services\PainelDoInstrutor;
use App\Support\CanalDeMensagens;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Área do instrutor em Blade — fase M4 do plano 16.
 *
 * Como na área do aluno, nenhuma regra de negócio mora aqui: posse de curso,
 * escopo de alunos e o que cada papel alcança continuam nos services que a API
 * usa. Migrar a tela não pode mudar quem alcança o quê.
 */
final class AreaDoInstrutorController extends Controller
{
    public function __construct(
        private readonly PainelDoInstrutor $painel,
        private readonly CaixaDeMensagens $caixa,
        private readonly MessagingService $mensagens,
    ) {}

    /**
     * `/inst` — a escolha de curso, e a fila do que há para fazer.
     *
     * Esta tela existe porque grade, avaliações e alunos são sempre de UM
     * curso. O painel React nasceu sem ela: `selectedCourseId` começava no
     * primeiro curso da lista, então quem entrava já estava dentro de um curso
     * que não escolheu, e as abas operavam sobre ele.
     */
    public function escolha(Request $request): View
    {
        /** @var array{sub:string,name:string,role:string} $instrutor */
        $instrutor = $request->attributes->get('auth_user');

        return view('instrutor.escolha', [
            'cursos' => $this->painel->cursos($instrutor),
            'aFazer' => $this->painel->aFazer($instrutor),
        ]);
    }

    /**
     * `/inst/mensagens` — as dúvidas dos alunos e a resposta do gestor.
     *
     * Uma página para a caixa inteira, por decisão do responsável em 21/10. A
     * conversa aberta vem de `?aluno=`; sem ele, a tela pede a escolha.
     *
     * Quem decide o que este gestor alcança é o `MessagingService`, o mesmo da
     * API: pedir o aluno de outro instrutor devolve conversa vazia.
     */
    public function mensagens(Request $request): View
    {
        abort_unless(CanalDeMensagens::abertoParaInstrutor(), 404);

        /** @var array{sub:string,name:string,role:string} $instrutor */
        $instrutor = $request->attributes->get('auth_user');

        $conversas = $this->caixa->conversas($instrutor);

        $pedido = $request->query('aluno');
        $pedido = is_string($pedido) && $pedido !== '' ? $pedido : null;

        $aberta = null;
        foreach ($conversas as $conversa) {
            if ($conversa['alunoId'] === $pedido) {
                $aberta = $conversa;
                break;
            }
        }

        return view('instrutor.mensagens', [
            'conversas' => $conversas,
            'aberta' => $aberta,
            'mensagens' => $aberta === null ? [] : $this->caixa->conversa($instrutor, $aberta['alunoId']),
            'aguardando' => count(array_filter($conversas, static fn (array $c): bool => $c['aguardando'])),
        ]);
    }

    /**
     * Responder um aluno.
     *
     * O escopo é do serviço: desde 21/10 o instrutor só escreve para alunos
     * dos cursos dele — antes a leitura era escopada e a escrita não.
     */
    public function responder(Request $request): RedirectResponse
    {
        abort_unless(CanalDeMensagens::abertoParaInstrutor(), 404);

        /** @var array{sub:string,name:string,role:string} $instrutor */
        $instrutor = $request->attributes->get('auth_user');

        $dados = $request->validate([
            'aluno' => ['required', 'string', 'max:191'],
            'texto' => ['required', 'string', 'min:1', 'max:2000'],
        ]);

        $destino = '/inst/mensagens?aluno='.urlencode((string) $dados['aluno']);

        try {
            $this->mensagens->createDirectMessage([
                'studentUserId' => (string) $dados['aluno'],
                'text' => trim((string) $dados['texto']),
            ], $instrutor);
        } catch (ApiException $e) {
            return redirect($destino)->withInput()->withErrors(['texto' => $e->getMessage()]);
        }

        return redirect($destino)->with('respostaEnviada', true);
    }
}
