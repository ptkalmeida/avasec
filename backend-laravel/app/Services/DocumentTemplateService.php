<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\DocumentTemplate;
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

    /**
     * Espelha o que já estava hardcoded em resources/views/certificates/pdf.blade.php
     * e src/components/CertificateTemplate.tsx antes desta área existir.
     *
     * @return array<string, array<string, mixed>>
     */
    private const DEFAULTS = [
        'certificado' => [
            'institutionName' => 'República Federativa do Brasil • AVA LMS',
            'institutionLogoPath' => null,
            'signatories' => [
                ['name' => 'Alessandro Pinto', 'role' => 'Diretor de Tecnologia & AVA'],
                ['name' => 'Mariana Santos', 'role' => 'Professora Responsável (Coordenação Acadêmica)'],
            ],
            'footerText' => 'A emissão de certificados na plataforma AVA respeita a presença mínima e obrigatória nas atividades letivas e transmissões ao vivo.',
            'customHtml' => null,
        ],
        'historico' => [
            'institutionName' => 'República Federativa do Brasil • AVA LMS',
            'institutionLogoPath' => null,
            'signatories' => [
                ['name' => 'Secretaria Acadêmica', 'role' => 'AVA LMS'],
            ],
            'footerText' => 'Documento de uso interno — não substitui o histórico escolar oficial da instituição de origem.',
            'customHtml' => null,
        ],
    ];

    /**
     * @return array<string, mixed>
     */
    public function get(string $type): array
    {
        $this->assertValidType($type);

        $row = DocumentTemplate::query()->find($type);
        $defaults = self::DEFAULTS[$type];

        if ($row === null) {
            return array_merge($defaults, ['type' => $type, 'updatedAt' => null, 'updatedByUserId' => null]);
        }

        return [
            'type' => $type,
            'institutionName' => $row->institutionName ?? $defaults['institutionName'],
            'institutionLogoPath' => $row->institutionLogoPath,
            'signatories' => $row->signatories ?? $defaults['signatories'],
            'footerText' => $row->footerText ?? $defaults['footerText'],
            'customHtml' => $row->customHtml,
            'updatedAt' => $row->updatedAt?->toIso8601String(),
            'updatedByUserId' => $row->updatedByUserId,
        ];
    }

    /**
     * @param  array{institutionName?:string|null,institutionLogoPath?:string|null,signatories?:list<array{name:string,role:string}>,footerText?:string|null,customHtml?:string|null}  $input
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<string, mixed>
     */
    public function upsert(string $type, array $input, array $requester): array
    {
        $this->assertValidType($type);

        // Templates moldam documentos oficiais entregues a QUALQUER aluno —
        // só o Admin Superior concede/edita, nunca instrutor.
        if ($requester['role'] !== 'admin') {
            throw ApiException::forbidden('Apenas o Admin Superior pode editar templates de documentos.');
        }

        DB::transaction(function () use ($type, $input, $requester): void {
            $antes = $this->conteudoDoModelo($type);

            $row = DocumentTemplate::query()->find($type);
            $merged = array_merge(
                $row?->only(['institutionName', 'institutionLogoPath', 'signatories', 'footerText', 'customHtml']) ?? [],
                $input,
            );
            $merged['updatedByUserId'] = $requester['sub'];
            $merged['updatedAt'] = CarbonImmutable::now();

            if ($row !== null) {
                $row->fill($merged)->save();
            } else {
                DocumentTemplate::query()->create(array_merge($merged, ['type' => $type]));
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
        unset($tudo['type'], $tudo['updatedAt'], $tudo['updatedByUserId']);

        return $tudo;
    }

    /**
     * Devolve ao ar uma versão anterior. Passa pelo `upsert`: mesma regra de quem
     * pode, e o que estava no ar vira uma nova versão.
     *
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<string, mixed>
     */
    public function restaurarVersao(string $type, string $versaoId, array $requester): array
    {
        $this->assertValidType($type);

        if ($requester['role'] !== 'admin') {
            throw ApiException::forbidden('Apenas o Admin Superior pode editar templates de documentos.');
        }

        $versao = app(VersoesDeConteudo::class)->obter(VersoesDeConteudo::MODELO_DE_DOCUMENTO, $type, $versaoId);
        if ($versao === null) {
            throw ApiException::notFound('Versão não encontrada.');
        }

        /** @var array{institutionName?:string|null,institutionLogoPath?:string|null,signatories?:list<array{name:string,role:string}>,footerText?:string|null,customHtml?:string|null} $versao */
        return $this->upsert($type, $versao, $requester);
    }

    private function assertValidType(string $type): void
    {
        if (! in_array($type, self::TYPES, true)) {
            throw ApiException::notFound('Tipo de documento desconhecido.');
        }
    }
}
