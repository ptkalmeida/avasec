<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\ModeloDocumento;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Área de gerenciamento de templates de documentos (certificado, histórico
 * escolar) — só o Admin Superior lê/edita. Services de geração de PDF (ex.:
 * CertificatePdfService) consomem `get()` diretamente, sem passar por rota
 * HTTP, e devem funcionar com os defaults abaixo mesmo sem nenhuma linha
 * salva ainda no banco.
 */
final class DocumentTemplateService
{
    public const TYPES = ['certificado', 'historico'];

    /** Colunas da tabela do histórico (chave => nome de fábrica). */
    public const ROTULOS_DO_HISTORICO = [
        'curso' => 'Curso',
        'carga_horaria' => 'Carga horária',
        'frequencia' => 'Frequência',
        'conclusao' => 'Conclusão',
    ];

    private const CHAVES_EDITAVEIS = [
        'nome_instituicao', 'titulo', 'texto_corpo', 'rotulos_colunas', 'caminho_logo_instituicao',
        'signatarios', 'texto_rodape', 'html_personalizado',
    ];

    /**
     * Espelha o que já estava hardcoded em resources/views/certificates/pdf.blade.php
     * e src/components/CertificateTemplate.tsx antes desta área existir.
     *
     * @return array<string, array<string, mixed>>
     */
    private const DEFAULTS = [
        'certificado' => [
            'nome_instituicao' => 'República Federativa do Brasil • AVA LMS',
            'titulo' => 'Certificado de Conclusão',
            'texto_corpo' => "Certificamos para os devidos fins de direito que o(a) aluno(a) de excelência acadêmica\n\n{aluno}\n\nconcluiu e obteve aprovação no programa de capacitação do curso livre da plataforma digital AVA\n\n{curso}\n\ncom carga horária integralizada de {carga_horaria} e aproveitamento com frequência registrada de {frequencia} de presença total nas aulas teóricas e transmissões periódicas ao vivo.",
            'rotulos_colunas' => null,
            'caminho_logo_instituicao' => null,
            'signatarios' => [
                ['name' => 'Alessandro Pinto', 'role' => 'Diretor de Tecnologia & AVA'],
                ['name' => 'Mariana Santos', 'role' => 'Professora Responsável (Coordenação Acadêmica)'],
            ],
            'texto_rodape' => 'A emissão de certificados na plataforma AVA respeita a presença mínima e obrigatória nas atividades letivas e transmissões ao vivo.',
            'html_personalizado' => null,
        ],
        'historico' => [
            'nome_instituicao' => 'República Federativa do Brasil • AVA LMS',
            'titulo' => 'Histórico Escolar',
            'texto_corpo' => 'Registro dos cursos concluídos por {aluno} na plataforma, emitido em {data}.',
            'rotulos_colunas' => self::ROTULOS_DO_HISTORICO,
            'caminho_logo_instituicao' => null,
            'signatarios' => [
                ['name' => 'Secretaria Acadêmica', 'role' => 'AVA LMS'],
            ],
            'texto_rodape' => 'Documento de uso interno — não substitui o histórico escolar oficial da instituição de origem.',
            'html_personalizado' => null,
        ],
    ];

    /**
     * @return array<string, mixed>
     */
    public function get(string $type): array
    {
        $this->assertValidType($type);

        $row = ModeloDocumento::query()->find($type);
        $defaults = self::DEFAULTS[$type];

        if ($row === null) {
            return array_merge($defaults, ['tipo' => $type, 'atualizado_em' => null, 'atualizado_por_usuario_id' => null]);
        }

        return [
            'tipo' => $type,
            'nome_instituicao' => $row->nome_instituicao ?? $defaults['nome_instituicao'],
            'titulo' => $row->titulo ?? $defaults['titulo'],
            'texto_corpo' => $row->texto_corpo ?? $defaults['texto_corpo'],
            'rotulos_colunas' => array_merge(
                is_array($defaults['rotulos_colunas']) ? $defaults['rotulos_colunas'] : [],
                is_array($row->rotulos_colunas) ? $row->rotulos_colunas : [],
            ) ?: null,
            'caminho_logo_instituicao' => $row->caminho_logo_instituicao,
            'signatarios' => $row->signatarios ?? $defaults['signatarios'],
            'texto_rodape' => $row->texto_rodape ?? $defaults['texto_rodape'],
            'html_personalizado' => $row->html_personalizado,
            'atualizado_em' => $row->atualizado_em?->toIso8601String(),
            'atualizado_por_usuario_id' => $row->atualizado_por_usuario_id,
        ];
    }

    /**
     * @param  array{nome_instituicao?:string|null,caminho_logo_instituicao?:string|null,signatarios?:list<array{name: string, role: string}>,texto_rodape?:string|null,html_personalizado?:string|null}  $input
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array<string, mixed>
     */
    public function upsert(string $type, array $input, array $requester): array
    {
        $this->assertValidType($type);

        // Templates moldam documentos oficiais entregues a QUALQUER aluno —
        // só o Admin Superior concede/edita, nunca instrutor.
        if ($requester['papel'] !== 'admin') {
            throw ApiException::forbidden('Apenas o Admin Superior pode editar templates de documentos.');
        }

        DB::transaction(function () use ($type, $input, $requester): void {
            $antes = $this->conteudoDoModelo($type);

            $row = ModeloDocumento::query()->find($type);
            $merged = array_merge(
                $row?->only(self::CHAVES_EDITAVEIS) ?? [],
                $input,
            );
            $merged['atualizado_por_usuario_id'] = $requester['sub'];
            $merged['atualizado_em'] = CarbonImmutable::now();

            if ($row !== null) {
                $row->fill($merged)->save();
            } else {
                ModeloDocumento::query()->create(array_merge($merged, ['tipo' => $type]));
            }

            // O que estava no ar vai para o histórico (ADR 021) — só se a gravação mudou algo.
            if ($antes != $this->conteudoDoModelo($type)) {
                app(VersoesDeConteudo::class)->guardar(VersoesDeConteudo::MODELO_DE_DOCUMENTO, $type, $antes, $requester['sub']);
            }
        });

        return $this->get($type);
    }

    /**
     * Só o conteúdo editável do modelo, sem os metadados de quem salvou e quando.
     *
     * @return array<string, mixed>
     */
    public function conteudoDoModelo(string $type): array
    {
        $tudo = $this->get($type);
        unset($tudo['tipo'], $tudo['atualizado_em'], $tudo['atualizado_por_usuario_id']);

        return $tudo;
    }

    /**
     * Devolve ao ar uma versão anterior. Passa pelo `upsert`: mesma regra de quem
     * pode, e o que estava no ar vira uma nova versão.
     *
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array<string, mixed>
     */
    public function restaurarVersao(string $type, string $versaoId, array $requester): array
    {
        $this->assertValidType($type);

        if ($requester['papel'] !== 'admin') {
            throw ApiException::forbidden('Apenas o Admin Superior pode editar templates de documentos.');
        }

        $versao = app(VersoesDeConteudo::class)->obter(VersoesDeConteudo::MODELO_DE_DOCUMENTO, $type, $versaoId);
        if ($versao === null) {
            throw ApiException::notFound('Versão não encontrada.');
        }

        /** @var array{nome_instituicao?:string|null,caminho_logo_instituicao?:string|null,signatarios?:list<array{name: string, role: string}>,texto_rodape?:string|null,html_personalizado?:string|null} $versao */
        return $this->upsert($type, $versao, $requester);
    }

    private function assertValidType(string $type): void
    {
        if (! in_array($type, self::TYPES, true)) {
            throw ApiException::notFound('Tipo de documento desconhecido.');
        }
    }
}
