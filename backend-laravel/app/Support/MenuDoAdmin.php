<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A navegação lateral do painel administrativo.
 *
 * Tradução de `src/config/menuAdmin.ts` (e dos endereços de
 * `src/router/adminRoutes.ts`). Os grupos, a ordem, os rótulos e as flags são
 * os mesmos do painel React; o que se acrescenta é o ícone (FontAwesome, D §1.9)
 * e o endereço de cada item.
 *
 * Cada item responde pela SUA flag, como na API: seção desligada some do menu
 * e o servidor responde que ela está desligada — não basta esconder o link.
 */
final class MenuDoAdmin
{
    /**
     * @var list<array{titulo:string,itens:list<array{id:string,rotulo:string,subtitulo:string,href:string,icone:string,flag:?string}>}>
     */
    private const GRUPOS = [
        [
            'titulo' => '',
            'itens' => [
                ['id' => 'analytics', 'rotulo' => 'Dashboard & Relatórios', 'subtitulo' => 'Indicadores da rede e relatórios de acompanhamento.', 'href' => '/admin', 'icone' => 'fa-chart-line', 'flag' => null],
            ],
        ],
        [
            'titulo' => 'Pessoas',
            'itens' => [
                ['id' => 'professors', 'rotulo' => 'Equipe Pedagógica', 'subtitulo' => 'Cadastro, vínculo e permissões de professores e gestores.', 'href' => '/admin/equipe', 'icone' => 'fa-chalkboard-user', 'flag' => null],
                ['id' => 'students', 'rotulo' => 'Alunos', 'subtitulo' => 'Cadastro, matrícula e situação acadêmica dos alunos.', 'href' => '/admin/alunos', 'icone' => 'fa-user-graduate', 'flag' => null],
            ],
        ],
        [
            'titulo' => 'Ensino',
            'itens' => [
                ['id' => 'courses', 'rotulo' => 'Cursos & Trilhas', 'subtitulo' => 'Cursos, aulas e material didático da grade.', 'href' => '/admin/cursos', 'icone' => 'fa-book-open', 'flag' => 'catalogoCursos'],
                ['id' => 'exercicios', 'rotulo' => 'Exercícios Práticos', 'subtitulo' => 'Exercícios propostos e acompanhamento das correções.', 'href' => '/admin/exercicios', 'icone' => 'fa-pen-to-square', 'flag' => 'atividadesPraticasAvancadas'],
                ['id' => 'requests', 'rotulo' => 'Solicitações Acadêmicas', 'subtitulo' => 'Requerimentos enviados pelos alunos à secretaria.', 'href' => '/admin/documentos', 'icone' => 'fa-inbox', 'flag' => 'solicitacoesAcademicas'],
            ],
        ],
        [
            'titulo' => 'Sistema',
            'itens' => [
                ['id' => 'export_bi', 'rotulo' => 'Dados Gerenciais', 'subtitulo' => 'Exportação de bases para uso gerencial e BI.', 'href' => '/admin/dados-gerenciais', 'icone' => 'fa-database', 'flag' => 'dadosGerenciais'],
                ['id' => 'templates', 'rotulo' => 'Modelos de Certificado e Histórico', 'subtitulo' => 'Cabeçalho, assinaturas e rodapé dos documentos emitidos.', 'href' => '/admin/templates', 'icone' => 'fa-certificate', 'flag' => null],
                ['id' => 'site_content', 'rotulo' => 'Páginas do Site', 'subtitulo' => 'Textos e imagens das páginas públicas do portal.', 'href' => '/admin/paginas-do-site', 'icone' => 'fa-globe', 'flag' => 'gestaoConteudoSite'],
                ['id' => 'settings', 'rotulo' => 'Configurações', 'subtitulo' => 'Parâmetros letivos, incluindo a exigência de presença para o certificado.', 'href' => '/admin/configuracoes', 'icone' => 'fa-gear', 'flag' => 'perfilBasico'],
            ],
        ],
    ];

    /**
     * Os grupos que o administrador enxerga: itens de flag desligada saem, e
     * grupo que ficou sem item sai com eles (cabeçalho sem nada embaixo é ruído).
     *
     * @return list<array{titulo:string,itens:list<array{id:string,rotulo:string,subtitulo:string,href:string,icone:string,flag:?string}>}>
     */
    public static function grupos(): array
    {
        $visiveis = [];

        foreach (self::GRUPOS as $grupo) {
            $itens = array_values(array_filter(
                $grupo['itens'],
                static fn (array $item): bool => $item['flag'] === null || config("features.{$item['flag']}") === true,
            ));

            if ($itens !== []) {
                $visiveis[] = ['titulo' => $grupo['titulo'], 'itens' => $itens];
            }
        }

        return $visiveis;
    }

    /**
     * Um item pelo id, com ou sem flag — a tela "seção desligada" precisa
     * dizer de qual seção se trata.
     *
     * @return array{id:string,rotulo:string,subtitulo:string,href:string,icone:string,flag:?string}|null
     */
    public static function item(string $id): ?array
    {
        foreach (self::GRUPOS as $grupo) {
            foreach ($grupo['itens'] as $item) {
                if ($item['id'] === $id) {
                    return $item;
                }
            }
        }

        return null;
    }
}
