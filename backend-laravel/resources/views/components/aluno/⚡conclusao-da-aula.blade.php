<?php

use App\Http\Middleware\JwtAuthenticate;
use App\Services\EnrollmentService;
use App\Support\ProgressoDoAluno;
use Livewire\Component;

/**
 * "Aula concluída" / "Marcar esta aula como concluída".
 *
 * ## Por que Livewire, e não um formulário
 *
 * Porque a pessoa está LENDO. Um POST com redirecionamento devolveria a página
 * pelo topo, e quem marcou a aula no fim do texto perderia o lugar — para
 * então rolar tudo de novo. É interação dentro de uma tela que continua aberta,
 * que é exatamente o caso que o Documento C atribui ao Livewire.
 *
 * ## Estado primeiro, ação depois
 *
 * Havia um botão só, e ele mostrava sempre a AÇÃO — nunca a situação da aula.
 * Ao chegar numa aula ainda não concluída, lia-se "Concluir esta aula", e isso
 * se lê como "a aula acabou de virar pendente". Nada era alterado, mas a tela
 * não dava como saber disso. Aqui o estado vem primeiro, e a ação ao lado.
 *
 * ## Quem decide o que é progresso
 *
 * O `EnrollmentService`, pelo mesmo caminho da API. Este componente não escreve
 * em `StudentProgress`: ele lê o registro, acrescenta ou tira ESTE id e manda
 * salvar. Escrever direto aqui duplicaria a regra que já emite certificado.
 */
new class extends Component
{
    public string $cursoId = '';

    public string $aulaId = '';

    public bool $concluida = false;

    public function mount(string $cursoId, string $aulaId, bool $concluida): void
    {
        $this->cursoId = $cursoId;
        $this->aulaId = $aulaId;
        $this->concluida = $concluida;
    }

    public function alternar(EnrollmentService $matriculas): void
    {
        $aluno = $this->aluno();

        if ($aluno === null) {
            // Sessão caiu enquanto a página estava aberta. Recarregar leva ao
            // login pelo middleware, e é melhor que um erro no meio da aula.
            $this->redirect('/entrar');

            return;
        }

        $registro = null;
        foreach ($matriculas->getProgress(null, $aluno) as $linha) {
            if (($linha['courseId'] ?? null) === $this->cursoId) {
                $registro = $linha;
                break;
            }
        }

        $concluidas = is_array($registro['completedLessons'] ?? null) ? $registro['completedLessons'] : [];
        $presencas = is_array($registro['attendedLiveSessions'] ?? null) ? $registro['attendedLiveSessions'] : [];

        $concluidas = $this->concluida
            ? array_values(array_filter($concluidas, fn (mixed $id): bool => $id !== $this->aulaId))
            : array_values(array_unique([...$concluidas, $this->aulaId]));

        $matriculas->upsertProgress([
            'courseId' => $this->cursoId,
            'completedLessons' => array_values(array_filter($concluidas, static fn (mixed $id): bool => is_string($id))),
            'attendedLiveSessions' => array_values(array_filter($presencas, static fn (mixed $id): bool => is_string($id))),
        ], $aluno);

        $this->concluida = ! $this->concluida;

        // A barra do menu lateral e a contagem do curso mudaram junto; quem as
        // desenha é a página, então ela precisa saber.
        $this->dispatch('progresso-mudou');
    }

    /** @return array{sub:string,name:string,role:string}|null */
    private function aluno(): ?array
    {
        try {
            return JwtAuthenticate::decode(JwtAuthenticate::extractToken(request()));
        } catch (\Throwable) {
            return null;
        }
    }
}; ?>

<div>
    @if ($concluida)
        <div class="flex flex-wrap items-center gap-2.5">
            <span class="flex items-center gap-1.5 rounded-xl border border-ava-lilas-borda bg-ava-lilas-fundo px-3 py-2 text-rotulo font-bold text-ava-lilas">
                <span aria-hidden="true">✓</span>
                Aula concluída
            </span>
            <button type="button" wire:click="alternar" wire:loading.attr="disabled"
                    title="Voltar esta aula para pendente"
                    class="cursor-pointer text-sobretitulo uppercase text-escult-ink-2 underline decoration-ava-borda underline-offset-2 hover:text-ava-tinta focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao">
                Desfazer
            </button>
        </div>
    @else
        <button type="button" wire:click="alternar" wire:loading.attr="disabled"
                class="flex cursor-pointer items-center gap-1.5 rounded-xl bg-ava-acao px-4 py-2.5 text-rotulo font-bold text-white transition-colors hover:bg-ava-acao-escuro focus:outline-none focus-visible:ring-2 focus-visible:ring-ava-acao focus-visible:ring-offset-2">
            <span aria-hidden="true">✓</span>
            <span>Marcar esta aula como concluída</span>
        </button>
    @endif
</div>
