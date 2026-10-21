<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\ExerciseSubmission;
use App\Models\ForumMessage;
use App\Models\PracticalExercise;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\QuizSubmission;
use App\Support\BusinessRules;
use App\Support\CourseAccess;
use App\Support\DataTexto;
use App\Support\Fuso;
use App\Support\Identity;
use App\Support\InstructorScope;
use App\Support\Payload;
use App\Support\QuestaoDeProva;
use App\Support\Visibilidade;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Quizzes, fórum e exercícios práticos — espelha src/server/services/learningService.ts.
 * senderName/studentName sempre vêm da identidade autenticada, nunca do corpo.
 *
 * $requester = ['sub'=>id, 'name'=>..., 'role'=>...].
 */
final class LearningService
{
    // ---------- QUIZZES ----------

    /**
     * Quizzes dos cursos a que o requester pertence.
     *
     * O gabarito (correctOptionIndex) continua vindo junto de propósito, para o
     * feedback imediato do aluno — decisão registrada na rota, e a nota é sempre
     * recalculada no servidor em submitQuiz. O que estava errado era o alcance:
     * a listagem entregava as questões de TODAS as avaliações da escola a qualquer
     * autenticado, inclusive de cursos que a pessoa nunca fez.
     *
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<int, array<string, mixed>>
     */
    public function listQuizzes(array $requester): array
    {
        $query = Quiz::query()->with('questions');
        if ($requester['role'] !== 'admin') {
            $query->whereIn('courseId', CourseAccess::accessibleCourseIds($requester));
        }

        return $query->get()->map->toArray()->all();
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<string, mixed>
     */
    public function createQuiz(array $input, array $requester): array
    {
        $courseId = is_string($input['courseId'] ?? null) ? $input['courseId'] : null;
        $this->assertInstructorOwnsCourse($courseId, $requester);

        /*
         * O gabarito tem de apontar para uma alternativa que EXISTE. Até 21/10
         * esta conferência só morava no navegador (`problemaNaQuestao`), e a API
         * aceitava `correctOptionIndex` qualquer: duas alternativas com gabarito
         * 9 eram gravadas sem reclamação, e a prova ficava impossível de
         * acertar — bloqueando o certificado da turma inteira, já que a prova é
         * exigida para ele.
         */
        foreach (Payload::assocList($input['questions'] ?? []) as $i => $questao) {
            $opcoes = is_array($questao['options'] ?? null)
                ? array_values(array_map(static fn (mixed $o): string => is_scalar($o) ? (string) $o : '', $questao['options']))
                : [];
            $problema = QuestaoDeProva::problema(
                is_string($questao['questionText'] ?? null) ? $questao['questionText'] : '',
                $opcoes,
                is_numeric($questao['correctOptionIndex'] ?? null) ? (int) $questao['correctOptionIndex'] : 0,
            );
            if ($problema !== null) {
                throw ApiException::validation('Questão '.($i + 1).': '.$problema);
            }
        }

        $id = is_string($input['id'] ?? null) ? $input['id'] : ('quiz-'.$this->nowMs());
        $quiz = Quiz::query()->withTrashed()->find($id);
        // Um quiz existente não pode ser sequestrado para outro curso alheio.
        if ($quiz !== null && is_string($quiz->courseId)) {
            $this->assertInstructorOwnsCourse($quiz->courseId, $requester);
        }
        if ($quiz !== null) {
            $quiz->fill(['courseId' => $input['courseId'], 'title' => $input['title']])->save();
        } else {
            Quiz::query()->create(['id' => $id, 'courseId' => $input['courseId'], 'title' => $input['title']]);
        }

        $questions = Payload::assocList($input['questions'] ?? []);

        /*
         * O id da questão é DECIDIDO AQUI, e não aceito do cliente.
         *
         * O editor React mandava `rascunho-N`, um marcador provisório cujo
         * contador recomeça a cada carregamento da página. O servidor tratava
         * qualquer id existente como EDIÇÃO — inclusive o de OUTRA prova. Foi o
         * que aconteceu: ao criar o "Desafio 2", `rascunho-3` já existia no
         * "Desafio 1", e a questão foi MOVIDA para a prova nova com o texto
         * sobrescrito. A original se perdeu.
         *
         * É também um furo de autorização: `assertInstructorOwnsCourse` confere
         * a prova, não a questão, então quem soubesse (ou adivinhasse) o id de
         * uma questão de outro curso a sobrescrevia.
         *
         * Regra: id vindo do cliente só vale se a questão JÁ PERTENCE a esta
         * prova. Qualquer outro caso ganha id novo.
         */
        $idsDasQuestoes = [];
        foreach ($questions as $i => $q) {
            $idsDasQuestoes[$i] = $this->idDaQuestao(is_string($q['id'] ?? null) ? $q['id'] : null, $id, $i);
        }
        $keptIds = array_values($idsDasQuestoes);
        // Questão que saiu da edição é INATIVADA, não apagada (ADR 12): a
        // resposta que o aluno já deu continua apontando para um item existente.
        QuizQuestion::query()->where('quizId', $id)
            ->when(count($keptIds) > 0, fn ($q) => $q->whereNotIn('id', $keptIds))
            ->delete();

        foreach ($questions as $i => $q) {
            $qId = $idsDasQuestoes[$i];
            $data = [
                'quizId' => $id,
                'questionText' => $q['questionText'],
                'options' => $q['options'],
                'correctOptionIndex' => is_numeric($q['correctOptionIndex'] ?? null) ? (int) $q['correctOptionIndex'] : 0,
                'explanation' => $q['explanation'] ?? null,
                'reviewMessage' => $q['reviewMessage'] ?? null,
                'recommendedModule' => $q['recommendedModule'] ?? null,
                'allowRetry' => $q['allowRetry'] ?? null,
            ];
            // withTrashed: questão retirada e depois readicionada com o mesmo id
            // não seria encontrada por find(), e o create() estouraria a chave.
            $existing = QuizQuestion::query()->withTrashed()->find($qId);
            if ($existing !== null) {
                if ($existing->estaInativo()) {
                    $existing->reativar();
                }
                $existing->fill($data)->save();
            } else {
                $data['id'] = $qId;
                QuizQuestion::query()->create($data);
            }
        }

        return Quiz::query()->with('questions')->find($id)?->toArray() ?? [];
    }

    /**
     * O id que a questão terá. O pedido do cliente só é aceito quando a questão
     * já pertence a ESTA prova (ver o comentário em `createQuiz`).
     */
    private function idDaQuestao(?string $pedido, string $quizId, int $posicao): string
    {
        if ($pedido !== null) {
            $existente = QuizQuestion::query()->withTrashed()->find($pedido, ['id', 'quizId']);
            if ($existente !== null && $existente->quizId === $quizId) {
                return $pedido;
            }
        }

        return $quizId.'-q-'.$this->nowMs().'-'.$posicao.'-'.Str::lower(Str::random(5));
    }

    /**
     * Inativa a avaliação (ADR 12). As respostas dos alunos NÃO são tocadas.
     *
     * Isto apagava `QuizSubmission` do quiz junto — as notas que os alunos já
     * tinham tirado desapareciam do banco porque quem deu aula excluiu a
     * avaliação. Nota lançada é registro acadêmico e sobrevive à retirada do
     * instrumento que a gerou.
     *
     * @param  array{sub:string,name:string,role:string}  $requester
     */
    public function deleteQuiz(string $id, array $requester, ?string $motivo = null): void
    {
        $quiz = Quiz::query()->find($id);
        if ($quiz === null) {
            return;
        }
        $this->assertInstructorOwnsCourse(is_string($quiz->courseId) ? $quiz->courseId : null, $requester);
        $quiz->inativar($requester['sub'] ?? null, $motivo);
    }

    /**
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<int, array<string, mixed>>
     */
    public function listQuizSubmissions(array $requester): array
    {
        $q = QuizSubmission::query();
        if ($requester['role'] === 'student') {
            Identity::applyOwnRows($q, $requester);
        } elseif ($requester['role'] === 'instructor') {
            $q->whereIn('courseId', InstructorScope::courseIds($requester));
        }

        /*
         * Mais recente primeiro. Isto é contrato, não conveniência: a lista antes
         * tinha uma linha por aluno+quiz, e há consumidor que ainda lê a primeira
         * e a chama de nota do aluno — inclusive a declaração impressa. Com o
         * histórico no ar, "a primeira" tem de ser a vigente.
         *
         * `id` como desempate porque carrega o timestamp em milissegundos: duas
         * tentativas no mesmo segundo ainda saem na ordem em que aconteceram.
         * Linha sem `enviadoEm` (string antiga não reconhecível) cai para o fim,
         * que é onde a mais antiga pertence.
         */
        return $q->orderByDesc('enviadoEm')->orderByDesc('id')->get()->map->toArray()->all();
    }

    /**
     * A nota NUNCA vem do cliente: é recalculada no servidor comparando as respostas
     * enviadas ($input['answers']: questionId => índice escolhido) com o
     * correctOptionIndex de cada QuizQuestion. O courseId também é derivado do quiz,
     * não do corpo. Isso impede o aluno de auto-declarar scorePercent/passed.
     *
     * @param  array{quizId:string,answers:array<string,int>}  $input
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<string, mixed>
     */
    public function submitQuiz(array $input, array $requester): array
    {
        if ($requester['role'] !== 'student') {
            throw ApiException::forbidden('Somente alunos podem responder quizzes.');
        }

        $quiz = Quiz::query()->with('questions')->find($input['quizId']);
        if ($quiz === null) {
            throw ApiException::notFound('Quiz não encontrado.');
        }

        // Responder avaliação de curso alheio gerava submissão válida num curso ao qual
        // o aluno não pertence — poluindo o painel do instrutor daquela turma.
        if (! is_string($quiz->courseId) || ! CourseAccess::canAccess($requester, $quiz->courseId)) {
            throw ApiException::forbidden('Você não participa deste curso.');
        }

        $acertos = $this->acertosPorQuestao($quiz, $input['answers']);
        $total = count($acertos);
        $correct = count(array_filter($acertos));
        $scorePercent = $total === 0 ? 0 : (int) round(($correct / $total) * 100);
        $passed = $scorePercent >= BusinessRules::quizPassThreshold();

        /*
         * A tentativa nova é ACRESCENTADA. A anterior fica no ar, visível, como
         * a tentativa que foi.
         *
         * Antes, responder de novo inativava a anterior — o dado ficava no banco
         * (ADR 12) mas saía das listagens, e a tela só sabia dizer "última
         * tentativa". Faltava a coluna `enviadoEm`, sem a qual não havia como
         * afirmar QUAL é a vigente: `submittedAt` é texto de exibição e ordena
         * alfabeticamente. Com o eixo de ordenação, o histórico inteiro aparece.
         */
        /*
         * DOIS papéis, de propósito em duas variáveis. `enviadoEm` é o instante
         * gravado e fica em UTC, que é o relógio de armazenamento do sistema;
         * `submittedAt` é texto para uma pessoa ler e vai no fuso de exibição.
         * Uma variável só para os dois gravaria BRT na coluna e quebraria o
         * invariante — e o erro não apareceria até alguém comparar duas datas.
         */
        $agora = CarbonImmutable::now();
        $agoraLocal = Fuso::de($agora);

        return QuizSubmission::query()->create([
            'id' => 'sub-'.$this->nowMs().'-'.random_int(0, 999),
            'studentName' => $requester['name'],
            'userId' => $requester['sub'],
            'courseId' => $quiz->courseId,
            'quizId' => $quiz->id,
            'scorePercent' => $scorePercent,
            'passed' => $passed,
            'submittedAt' => $agoraLocal->format('d/m/Y').' às '.$agoraLocal->format('H:i'),
            'enviadoEm' => $agora,
        ])->toArray();
    }

    /**
     * Acerto de cada questão, pela ordem do quiz. É a ÚNICA comparação com o
     * gabarito: a nota de `submitQuiz` e a correção que a tela mostra saem
     * daqui, e por isso não podem discordar.
     *
     * @param  array<array-key, mixed>  $respostas  questionId => índice escolhido
     * @return array<string, bool>
     */
    private function acertosPorQuestao(Quiz $quiz, array $respostas): array
    {
        $acertos = [];
        foreach ($quiz->questions as $question) {
            $dada = $respostas[$question->id] ?? null;
            $acertos[(string) $question->id] = is_int($dada) && $dada === $question->correctOptionIndex;
        }

        return $acertos;
    }

    /**
     * As avaliações de um curso como o aluno as vê, com a situação de cada uma.
     *
     * Só as publicadas (escada de `Visibilidade`) — a mesma regra que o
     * certificado usa para decidir quais são exigidas.
     *
     * "Aprovada" segue o certificado: basta UMA tentativa aprovada. Refazer
     * depois de aprovado e ir pior não desfaz a aprovação, e a tela não pode
     * dizer outra coisa que o certificado diz. A nota mostrada é a da
     * tentativa mais recente.
     *
     * @param  array{sub:string,name:string,role:string}  $aluno
     * @return list<array{id:string,title:string,questoes:int,aprovada:bool,tentativas:int,ultima:array{scorePercent:int,passed:bool,submittedAt:string}|null}>
     */
    public function avaliacoesDoCursoParaAluno(string $courseId, array $aluno): array
    {
        $consulta = Quiz::query()->withCount('questions')->where('courseId', $courseId);
        Visibilidade::aplicar($consulta, 'student');
        $quizzes = $consulta->orderBy('id')->get();

        $tentativas = QuizSubmission::query()
            ->where('userId', $aluno['sub'])
            ->where('courseId', $courseId)
            ->orderByDesc('enviadoEm')->orderByDesc('id')
            ->get()
            ->groupBy('quizId');

        $lista = [];
        foreach ($quizzes as $quiz) {
            $doQuiz = $tentativas->get($quiz->id) ?? collect();
            $ultima = $doQuiz->first();
            $lista[] = [
                'id' => (string) $quiz->id,
                'title' => (string) $quiz->title,
                'questoes' => is_numeric($n = $quiz->getAttribute('questions_count')) ? (int) $n : 0,
                'aprovada' => $doQuiz->contains(static fn (QuizSubmission $t): bool => (bool) $t->passed),
                'tentativas' => $doQuiz->count(),
                'ultima' => $ultima === null ? null : [
                    'scorePercent' => (int) $ultima->scorePercent,
                    'passed' => (bool) $ultima->passed,
                    'submittedAt' => (string) $ultima->submittedAt,
                ],
            ];
        }

        return $lista;
    }

    /**
     * As avaliações de um curso como o INSTRUTOR as vê.
     *
     * Conta ALUNOS, e não tentativas. O painel React somava tentativas: com
     * refazer ilimitado, um aluno que tentou três vezes contava três vezes em
     * "respostas" e, se passou nas três, três vezes em "aprovados".
     *
     * "Aprovado" segue o certificado — uma tentativa aprovada basta, mesmo que
     * a última tenha sido pior —, para a tela não dizer outra coisa que a
     * emissão diz.
     *
     * Entram as avaliações que o PAPEL alcança na escada de `Visibilidade`: o
     * instrutor vê as restritas, que é onde ele as prepara, e o admin vê também
     * os rascunhos. O aluno só alcança as publicadas — e só elas contam para o
     * certificado (`avaliacoesNaoAprovadas`).
     *
     * @return list<array{id:string,title:string,questoes:int,alunos:int,aprovados:int,tentativas:int}>
     */
    public function avaliacoesDoCurso(string $courseId, string $papel): array
    {
        $consulta = Quiz::query()->withCount('questions')->where('courseId', $courseId);
        Visibilidade::aplicar($consulta, $papel);
        $quizzes = $consulta->orderBy('id')->get();

        $tentativas = QuizSubmission::query()
            ->where('courseId', $courseId)
            ->get(['quizId', 'userId', 'passed'])
            ->groupBy('quizId');

        $lista = [];
        foreach ($quizzes as $quiz) {
            $doQuiz = $tentativas->get($quiz->id) ?? collect();
            $porAluno = $doQuiz->groupBy('userId');

            $n = $quiz->getAttribute('questions_count');
            $lista[] = [
                'id' => (string) $quiz->id,
                'title' => (string) $quiz->title,
                'questoes' => is_numeric($n) ? (int) $n : 0,
                'alunos' => $porAluno->count(),
                'aprovados' => $porAluno->filter(
                    static fn ($linhas): bool => $linhas->contains(static fn (QuizSubmission $t): bool => (bool) $t->passed),
                )->count(),
                'tentativas' => $doQuiz->count(),
            ];
        }

        return $lista;
    }

    /**
     * Uma avaliação, com o gabarito, para o instrutor editar.
     *
     * Aqui o gabarito VAI — é para quem o escreve. A diferença para
     * `provaParaResponder`, que o esconde do aluno, é de quem pergunta, e é por
     * isso que as duas são funções separadas: uma só, com um parâmetro, seria
     * um `if` entre mostrar e esconder a resposta.
     *
     * Null quando a avaliação não é deste curso.
     *
     * @return array{id:string,title:string,questions:list<array{id:string,questionText:string,options:list<string>,correctOptionIndex:int,explanation:string|null,reviewMessage:string|null,recommendedModule:string|null,allowRetry:bool}>}|null
     */
    public function avaliacaoParaEditar(string $courseId, string $quizId): ?array
    {
        $quiz = Quiz::query()->with('questions')->whereKey($quizId)->where('courseId', $courseId)->first();
        if ($quiz === null) {
            return null;
        }

        $questoes = [];
        foreach ($quiz->questions as $q) {
            $opcoes = is_array($q->options) ? $q->options : [];
            $questoes[] = [
                'id' => (string) $q->id,
                'questionText' => (string) $q->questionText,
                'options' => array_values(array_map(static fn (mixed $o): string => is_scalar($o) ? (string) $o : '', $opcoes)),
                'correctOptionIndex' => (int) $q->correctOptionIndex,
                'explanation' => is_string($q->explanation) ? $q->explanation : null,
                'reviewMessage' => is_string($q->reviewMessage) ? $q->reviewMessage : null,
                'recommendedModule' => is_string($q->recommendedModule) ? $q->recommendedModule : null,
                // `null` no banco quer dizer "usar o padrão", que é permitir.
                'allowRetry' => $q->allowRetry !== false,
            ];
        }

        return ['id' => (string) $quiz->id, 'title' => (string) $quiz->title, 'questions' => $questoes];
    }

    /** Quantos alunos já responderam esta avaliação — o aviso de quem edita uma publicada. */
    public function quantosJaResponderam(string $quizId): int
    {
        return QuizSubmission::query()->where('quizId', $quizId)->distinct()->count('userId');
    }

    /**
     * A prova para o aluno responder — enunciados e alternativas, SEM gabarito.
     *
     * Não sai daqui `correctOptionIndex`, `explanation`, `reviewMessage` nem
     * `recommendedModule`. Na API eles vão junto (`listQuizzes`), e a tela do
     * React mostrava a alternativa certa a cada questão e deixava refazer a
     * questão até acertar: a nota "recalculada no servidor" era sobre
     * respostas dadas depois de ver o gabarito. Decisão de 20/10: a prova é
     * corrigida no fim, e o gabarito não chega ao navegador.
     *
     * Null quando o quiz não é deste curso ou não está publicado.
     *
     * @return array{id:string,title:string,questions:list<array{id:string,questionText:string,options:list<string>}>}|null
     */
    public function provaParaResponder(string $courseId, string $quizId): ?array
    {
        $consulta = Quiz::query()->with('questions')->whereKey($quizId)->where('courseId', $courseId);
        Visibilidade::aplicar($consulta, 'student');
        $quiz = $consulta->first();
        if ($quiz === null) {
            return null;
        }

        $questoes = [];
        foreach ($quiz->questions as $q) {
            $opcoes = is_array($q->options) ? $q->options : [];
            $questoes[] = [
                'id' => (string) $q->id,
                'questionText' => (string) $q->questionText,
                'options' => array_values(array_map(static fn (mixed $o): string => is_scalar($o) ? (string) $o : '', $opcoes)),
            ];
        }

        return ['id' => (string) $quiz->id, 'title' => (string) $quiz->title, 'questions' => $questoes];
    }

    /**
     * O que a tela de resultado mostra de cada questão, depois de enviada.
     *
     * Diz se acertou — mas NÃO qual era a alternativa certa: o aluno pode
     * refazer a prova, e com a letra na mão a segunda tentativa deixaria de
     * medir alguma coisa.
     *
     * Os dois textos da questão têm destinatários diferentes, e isso decide
     * quem vê qual. Nos cursos cadastrados, `explanation` é escrita para QUEM
     * ACERTOU ("Correto! As fotografias foram reunidas principalmente
     * para…") — explica a resposta certa, e por isso a entrega. `reviewMessage`
     * é a dica para quem errou ("Observe o objetivo das fotografias…"). Então:
     * acertou, vê a explicação; errou, vê a dica e o módulo a revisar.
     *
     * @param  array<array-key, mixed>  $respostas
     * @return list<array{numero:int,questionText:string,acertou:bool,explanation:string|null,reviewMessage:string|null,recommendedModule:string|null}>
     */
    public function correcaoParaAluno(string $quizId, array $respostas): array
    {
        $quiz = Quiz::query()->with('questions')->find($quizId);
        if ($quiz === null) {
            return [];
        }

        $acertos = $this->acertosPorQuestao($quiz, $respostas);
        $texto = static fn (mixed $v): ?string => is_string($v) && trim($v) !== '' ? $v : null;

        $correcao = [];
        foreach ($quiz->questions->values() as $i => $q) {
            $acertou = $acertos[(string) $q->id] ?? false;
            $correcao[] = [
                'numero' => $i + 1,
                'questionText' => (string) $q->questionText,
                'acertou' => $acertou,
                'explanation' => $acertou ? $texto($q->explanation) : null,
                'reviewMessage' => $acertou ? null : $texto($q->reviewMessage),
                'recommendedModule' => $acertou ? null : $texto($q->recommendedModule),
            ];
        }

        return $correcao;
    }

    // ---------- FÓRUM ----------

    /**
     * Fórum escopado por curso, como o chat de aula (MessagingService::listChatMessages).
     * Antes devolvia o fórum de TODOS os cursos para qualquer autenticado: a regra já
     * existia no projeto, faltava aqui.
     *
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<int, array<string, mixed>>
     */
    public function listForumMessages(array $requester): array
    {
        $query = ForumMessage::query();
        if ($requester['role'] !== 'admin') {
            $query->whereIn('courseId', CourseAccess::accessibleCourseIds($requester));
        }

        return $query->get()->map->toArray()->all();
    }

    /**
     * @param  array{courseId:string,text:string}  $input
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<string, mixed>
     */
    public function createForumMessage(array $input, array $requester): array
    {
        // courseId vem do corpo: sem esta checagem, qualquer autenticado publicava no
        // fórum de qualquer turma. createChatMessage já barrava o caso equivalente.
        if (! CourseAccess::canAccess($requester, $input['courseId'])) {
            throw ApiException::forbidden('Você não participa deste curso.');
        }

        return ForumMessage::query()->create([
            'id' => 'forum-msg-'.$this->nowMs(),
            'courseId' => $input['courseId'],
            'senderName' => $requester['name'],
            'senderUserId' => $requester['sub'],
            'senderRole' => $requester['role'],
            'text' => $input['text'],
            'timestamp' => Fuso::agora()->format('d/m/Y H:i'),
            // Mesmo instante em coluna nativa (Norma C.2.4). O texto acima é hora
            // de Brasília e continua sendo o que a tela mostra; a coluna guarda em
            // UTC, como todo instante aqui.
            'enviadaEm' => CarbonImmutable::now()->utc(),
            'likes' => 0,
            'likedBy' => [],
        ])->toArray();
    }

    /**
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<string, mixed>
     */
    public function toggleForumLike(string $messageId, array $requester): array
    {
        $msg = ForumMessage::query()->find($messageId);
        if ($msg === null) {
            throw ApiException::notFound('Mensagem não encontrada.');
        }
        // likedBy é um array de userIds desde a ADR 10 (backfill converteu nomes).
        $likedBy = is_array($msg->likedBy) ? $msg->likedBy : [];
        $hasLiked = in_array($requester['sub'], $likedBy, true);
        $next = $hasLiked
            ? array_values(array_filter($likedBy, fn ($u) => $u !== $requester['sub']))
            : [...$likedBy, $requester['sub']];
        $msg->likedBy = $next;
        $msg->likes = count($next);
        $msg->save();

        return $msg->toArray();
    }

    /** @param array{sub:string,name:string,role:string} $requester */
    public function deleteForumMessage(string $id, array $requester): void
    {
        $msg = ForumMessage::query()->find($id);
        if ($msg === null) {
            return;
        }
        $owns = Identity::ownsRow($msg->senderUserId, $requester);
        if ($requester['role'] !== 'admin' && ! $owns) {
            throw ApiException::forbidden('Você só pode remover as próprias mensagens.');
        }
        // Sai do fórum, fica no registro (ADR 12): moderação precisa poder
        // responder depois o que foi dito e por quem.
        $msg->inativar($requester['sub'] ?? null);
    }

    // ---------- EXERCÍCIOS PRÁTICOS ----------

    /**
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<int, array<string, mixed>>
     */
    public function listExercises(array $requester): array
    {
        $query = PracticalExercise::query();
        if ($requester['role'] !== 'admin') {
            $query->whereIn('courseId', CourseAccess::accessibleCourseIds($requester));
        }

        return $query->get()->map->toArray()->all();
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<string, mixed>
     */
    public function createExercise(array $input, array $requester): array
    {
        $this->assertInstructorOwnsCourse(is_string($input['courseId'] ?? null) ? $input['courseId'] : null, $requester);

        $id = $input['id'] ?? ('exercise-'.$this->nowMs());
        $data = $this->exerciseScalar($input);
        $data['id'] = $id;

        return PracticalExercise::query()->create($data)->toArray();
    }

    /**
     * @param  array<string, mixed>  $updates
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<string, mixed>
     */
    public function updateExercise(string $id, array $updates, array $requester): array
    {
        $exercise = PracticalExercise::query()->find($id);
        if ($exercise === null) {
            throw ApiException::notFound('Exercício não encontrado.');
        }
        // Posse do curso atual do exercício e, se o payload mudar o curso, também do novo.
        $this->assertInstructorOwnsCourse(is_string($exercise->courseId) ? $exercise->courseId : null, $requester);
        if (is_string($updates['courseId'] ?? null) && $updates['courseId'] !== $exercise->courseId) {
            $this->assertInstructorOwnsCourse($updates['courseId'], $requester);
        }
        $exercise->fill($this->exerciseScalar($updates))->save();

        return $exercise->toArray();
    }

    /** @param array{sub:string,name:string,role:string} $requester */
    public function deleteExercise(string $id, array $requester, ?string $motivo = null): void
    {
        $exercise = PracticalExercise::query()->find($id);
        if ($exercise === null) {
            return;
        }
        $this->assertInstructorOwnsCourse(is_string($exercise->courseId) ? $exercise->courseId : null, $requester);
        // As entregas dos alunos NÃO são tocadas: com a exclusão física, o
        // ON DELETE CASCADE de ExerciseSubmission.exerciseId levava embora todo
        // trabalho entregue e corrigido daquele exercício.
        $exercise->inativar($requester['sub'] ?? null, $motivo);
    }

    /**
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<int, array<string, mixed>>
     */
    public function listExerciseSubmissions(array $requester): array
    {
        $q = ExerciseSubmission::query();
        if ($requester['role'] === 'student') {
            Identity::applyOwnRows($q, $requester);
        } elseif ($requester['role'] === 'instructor') {
            // ExerciseSubmission não tem courseId; escopo via exercícios dos cursos do instrutor.
            $exerciseIds = PracticalExercise::query()
                ->whereIn('courseId', InstructorScope::courseIds($requester))
                ->pluck('id')->all();
            $q->whereIn('exerciseId', $exerciseIds);
        }

        return $q->get()->map->toArray()->all();
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<string, mixed>
     */
    public function submitExercise(array $input, array $requester): array
    {
        if ($requester['role'] !== 'student') {
            throw ApiException::forbidden('Somente alunos podem enviar entregas de exercícios.');
        }
        $data = [
            'exerciseId' => $input['exerciseId'],
            'studentName' => $requester['name'],
            'userId' => $requester['sub'],
            'submissionText' => $input['submissionText'],
            'fileUrl' => $input['fileUrl'] ?? null,
            'fileName' => $input['fileName'] ?? null,
            'submittedAt' => Fuso::agora()->format('d/m/Y H:i:s'),
            'status' => 'pending',
        ];
        $existing = ExerciseSubmission::query()
            ->where('exerciseId', $input['exerciseId'])->where('userId', $requester['sub'])->first();
        if ($existing !== null) {
            $existing->fill($data)->save();

            return $existing->toArray();
        }
        $data['id'] = 'sub-'.$this->nowMs();

        return ExerciseSubmission::query()->create($data)->toArray();
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<string, mixed>
     */
    public function gradeSubmission(string $submissionId, array $input, array $requester): array
    {
        if ($requester['role'] === 'student') {
            throw ApiException::forbidden('Alunos não podem corrigir entregas.');
        }
        $submission = ExerciseSubmission::query()->find($submissionId);
        if ($submission === null) {
            throw ApiException::notFound('Entrega não encontrada.');
        }
        // Instrutor só corrige entregas de exercícios dos seus cursos (antes: qualquer um).
        $exerciseCourseId = PracticalExercise::query()->whereKey($submission->exerciseId)->value('courseId');
        $this->assertInstructorOwnsCourse(is_string($exerciseCourseId) ? $exerciseCourseId : null, $requester);
        $submission->fill([
            'score' => is_numeric($input['score'] ?? null) ? (int) $input['score'] : 0,
            'feedback' => $input['feedback'],
            'status' => $input['status'],
            'gradedAt' => Fuso::agora()->format('d/m/Y H:i:s'),
            'gradedBy' => $requester['name'],
        ])->save();

        return $submission->toArray();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function exerciseScalar(array $input): array
    {
        $campos = array_intersect_key($input, array_flip(['courseId', 'title', 'description', 'instructions', 'maxPoints', 'dueDate']));

        /*
         * `prazoEm` acompanha `dueDate` (Norma C.2.4): o texto 'd/m/Y' segue sendo
         * o que a tela exibe e o que a API entrega, e a coluna DATE ao lado é o
         * prazo comparável. Só é tocada quando `dueDate` vem no pedido — assim uma
         * edição parcial não zera um prazo que ninguém pediu para mudar.
         *
         * Data que não converte vira NULL em vez de data inventada; a validação da
         * rota já exige `date_format:d/m/Y`, então isso é rede, não caminho normal.
         */
        if (array_key_exists('dueDate', $campos)) {
            $campos['prazoEm'] = DataTexto::dia(is_string($campos['dueDate']) ? $campos['dueDate'] : null);
        }

        return $campos;
    }

    /**
     * Posse de curso do instrutor (ADR 10): admin é irrestrito; instrutor só age em
     * curso que leciona; qualquer outro caso é 403. Evita que um instrutor gerencie
     * quizzes/exercícios/notas de cursos de terceiros.
     *
     * @param  array{sub:string,name:string,role:string}  $requester
     */
    private function assertInstructorOwnsCourse(?string $courseId, array $requester): void
    {
        if ($requester['role'] === 'admin') {
            return;
        }
        if ($requester['role'] === 'instructor'
            && $courseId !== null
            && in_array($courseId, InstructorScope::courseIds($requester), true)) {
            return;
        }
        throw ApiException::forbidden('Você só pode gerenciar conteúdo dos seus próprios cursos.');
    }

    private function nowMs(): int
    {
        return (int) round(microtime(true) * 1000);
    }
}
