<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ApiRequestHelpers;
use App\Rules\SafeUrlRule;
use App\Rules\VideoUrlRule;
use App\Services\AuditLogger;
use App\Services\CourseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CourseController extends Controller
{
    use ApiRequestHelpers;

    public function __construct(
        private readonly CourseService $courses,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Catálogo. Segue público (escopo declarado em 01-visao-geral.md), mas o material
     * de estudo sai só para quem tem acesso — a rota usa `jwt.optional`, então aqui o
     * requester pode ser nulo (visitante) e isso NÃO é erro.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->courses->listCoursesFor($this->optionalRequester($request)));
    }

    /**
     * Resolve um endereço de curso: slug de hoje, slug aposentado ou id antigo.
     *
     * Existe para o link salvo não morrer. O frontend usa o slug no endereço,
     * mas precisa do id para falar com a API — e quando o valor recebido não é o
     * canônico (rename, ou link de antes desta mudança), a resposta diz qual é,
     * para a barra de endereços ser corrigida em vez de propagar o antigo.
     *
     * `jwt.optional` como o catálogo: sem sessão resolve só curso publicado.
     */
    public function resolve(Request $request, string $valor): JsonResponse
    {
        $requester = $this->optionalRequester($request);

        return response()->json(
            $this->courses->resolverCurso($valor, $requester['papel'] ?? null)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, false);
        $course = $this->courses->createCourse($data, $this->requester($request));
        $title = $this->optionalString($course, 'titulo') ?? '';
        $this->audit->log($request, 'Criação de Curso', "Curso \"{$title}\" criado.");

        return response()->json($course, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $this->validated($request, true);
        $course = $this->courses->updateCourse($id, $data, $this->requester($request));
        $title = $this->optionalString($course, 'titulo') ?? '';
        $this->audit->log($request, 'Alteração de Curso', "Curso \"{$title}\" ({$id}) atualizado.");

        return response()->json($course);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $motivo = $request->input('motivo');
        $this->courses->deleteCourse(
            $id,
            $this->requester($request),
            is_string($motivo) && trim($motivo) !== '' ? trim($motivo) : null
        );
        $this->audit->log($request, 'Inativação de Curso', "Curso {$id} inativado (registro preservado).", 'WARNING');

        return response()->json(['success' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $partial): array
    {
        // Espelha createCourseSchema / updateCourseSchema (Zod). No update (partial),
        // os campos de topo tornam-se opcionais (sometimes).
        $req = $partial ? 'sometimes' : 'required';
        $rules = [
            // Só letras, números, `_` e `-`: o id entra em consultas `LIKE '%"id"%'` sobre os cursos extras
            // dos alunos, e um id como `%` ampliava o escopo de alunos do instrutor (achado da varredura
            // de segurança de 23/10/2026).
            'id' => ['sometimes', 'string', 'max:191', 'regex:/^[A-Za-z0-9_-]+$/'],
            'titulo' => [$req, 'string', 'min:3', 'max:200'],
            'descricao' => [$req, 'string', 'min:10', 'max:5000'],
            'categoria' => [$req, 'string', 'min:1', 'max:120'],
            'miniatura' => [$req, 'string', 'min:1', 'max:2000', new SafeUrlRule],
            // ADR 10: autoria por instrutor_id (admin); nome_instrutor é aceito
            // apenas por compat de payload e IGNORADO — display deriva do Usuario.
            'instrutor_id' => ['sometimes', 'nullable', 'string', 'max:191'],
            'nome_instrutor' => ['sometimes', 'nullable', 'string', 'max:150'],
            'imagem_capa' => ['sometimes', 'nullable', 'string', 'max:2000', new SafeUrlRule],
            'tipo_curso' => ['sometimes', 'in:fixo,ao_vivo'],
            'tem_chat' => ['sometimes', 'boolean'],
            'frequencia_minima' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'vencimento_contrato_texto' => ['sometimes', 'nullable', 'string', 'max:60'],
            'area_tematica' => ['sometimes', 'nullable', 'string', 'max:150'],
            'carga_horaria' => ['sometimes', 'integer', 'min:1', 'max:2000'],
            'modalidade' => ['sometimes', 'nullable', 'string', 'max:60'],
            'nivel' => ['sometimes', 'nullable', 'string', 'max:60'],
            'emite_certificado' => ['sometimes', 'boolean'],
            'status_curso' => ['sometimes', 'nullable', 'string', 'max:60'],
            // Aulas aninhadas
            'aulas' => ['sometimes', 'array'],
            'aulas.*.id' => ['sometimes', 'string', 'max:191'],
            'aulas.*.titulo' => ['required_with:lessons', 'string', 'min:1', 'max:200'],
            'aulas.*.duracao' => ['required_with:lessons', 'string', 'min:1', 'max:30'],
            'aulas.*.url_video' => ['sometimes', 'nullable', 'string', 'max:2000', new VideoUrlRule],
            'aulas.*.conteudo' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'aulas.*.ordem' => ['required_with:lessons', 'integer', 'min:0'],
            'aulas.*.documentos' => ['sometimes', 'array'],
            'aulas.*.documentos.*.titulo' => ['required_with:lessons.*.documents', 'string', 'min:1', 'max:200'],
            'aulas.*.documentos.*.tipo' => ['required_with:lessons.*.documents', 'in:pdf,doc,url,drive,outro'],
            'aulas.*.documentos.*.url' => ['required_with:lessons.*.documents', 'string', 'min:1', 'max:2000', new SafeUrlRule],
            'aulas.*.documentos.*.tamanho' => ['sometimes', 'nullable', 'string', 'max:30'],
            // Sessões ao vivo aninhadas
            'sessoes_ao_vivo' => ['sometimes', 'array'],
            'sessoes_ao_vivo.*.id' => ['sometimes', 'string', 'max:191'],
            'sessoes_ao_vivo.*.titulo' => ['required_with:liveSessions', 'string', 'min:1', 'max:200'],
            // Data real, no formato do <input type="datetime-local"> (ISO local, sem fuso).
            // Antes era texto livre e chegavam valores como "Hoje, as 19:30" ou "Proxima
            // Segunda, as 20:00" — impossivel de ordenar ou filtrar, o que impedia a
            // agenda dos proximos 30 dias na aba Calendario. Fuso literal e deliberado:
            // a escola opera num unico fuso (ver src/utils/liveSchedule.ts).
            'sessoes_ao_vivo.*.agendado_para_texto' => [
                'required_with:liveSessions', 'string', 'max:100',
                'date_format:Y-m-d\TH:i,Y-m-d\TH:i:s',
            ],
            'sessoes_ao_vivo.*.duracao_minutos' => ['required_with:liveSessions', 'integer', 'min:1', 'max:600'],
            'sessoes_ao_vivo.*.link_reuniao' => ['required_with:liveSessions', 'string', 'min:1', 'max:2000', new SafeUrlRule],
            'sessoes_ao_vivo.*.ao_vivo' => ['sometimes', 'boolean'],
        ];

        return $this->validateKeepingAll($request, $rules);
    }
}
