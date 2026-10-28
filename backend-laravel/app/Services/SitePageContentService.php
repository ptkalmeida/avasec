<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\ConteudoPaginaSite;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Conteúdo editável das páginas públicas do portal. Só o Admin Superior
 * escreve; a leitura é pública (visitante anônimo monta o site com isso).
 *
 * Todo campo é TEXTO PURO. Nada aqui vira HTML na renderização — o frontend
 * escapa o conteúdo normalmente. É uma decisão de segurança deliberada:
 * conteúdo autorado no admin e exibido em página pública com HTML livre
 * seria XSS armazenado.
 *
 * O schema de campos (SCHEMA) é servido junto com o conteúdo para que a tela
 * do admin monte os formulários rotulados sem hardcodar cada página.
 */
final class SitePageContentService
{
    /** Limite de caracteres dos campos curtos (títulos, datas, etiquetas). */
    private const SHORT = 191;

    /** Limite de caracteres dos campos de parágrafo. */
    private const LONG = 2000;

    /**
     * Definição de cada página: rótulos exibidos no admin, campos do
     * cabeçalho, campos de cada item da lista e teto de itens.
     *
     * @var array<string, array{
     *     label: string,
     *     itemsLabel: string,
     *     maxItems: int,
     *     header: list<array{key:string,label:string,tipo:string,maxLength:int}>,
     *     item: list<array{key:string,label:string,tipo:string,maxLength:int}>
     * }>
     */
    private const SCHEMA = [
        'o-ava' => [
            'label' => 'O que é o AVA',
            'itemsLabel' => 'Destaques do ambiente',
            'maxItems' => 9,
            'header' => [
                ['key' => 'eyebrow', 'label' => 'Linha de apoio (acima do título)', 'tipo' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'titulo', 'label' => 'Título da página', 'tipo' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'descricao', 'label' => 'Texto de abertura', 'tipo' => 'textarea', 'maxLength' => self::LONG],
            ],
            'item' => [
                ['key' => 'titulo', 'label' => 'Título do destaque', 'tipo' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'descricao', 'label' => 'Descrição', 'tipo' => 'textarea', 'maxLength' => self::LONG],
            ],
        ],
        'certificados' => [
            'label' => 'Certificados e Emissão',
            'itemsLabel' => 'Critérios de aprovação',
            'maxItems' => 9,
            'header' => [
                ['key' => 'eyebrow', 'label' => 'Linha de apoio (acima do título)', 'tipo' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'titulo', 'label' => 'Título da página', 'tipo' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'descricao', 'label' => 'Texto de abertura', 'tipo' => 'textarea', 'maxLength' => self::LONG],
                ['key' => 'criteriaTitle', 'label' => 'Título do bloco de critérios', 'tipo' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'criteriaIntro', 'label' => 'Texto introdutório dos critérios', 'tipo' => 'textarea', 'maxLength' => self::LONG],
                ['key' => 'noticeText', 'label' => 'Aviso destacado (validação por terceiros)', 'tipo' => 'textarea', 'maxLength' => self::LONG],
                ['key' => 'authenticatorTitle', 'label' => 'Título do autenticador', 'tipo' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'authenticatorDescription', 'label' => 'Instrução do autenticador', 'tipo' => 'textarea', 'maxLength' => self::LONG],
            ],
            'item' => [
                ['key' => 'titulo', 'label' => 'Critério', 'tipo' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'descricao', 'label' => 'Como é apurado', 'tipo' => 'textarea', 'maxLength' => self::LONG],
            ],
        ],
        'o-projeto' => [
            'label' => 'O Projeto Pedagógico',
            'itemsLabel' => 'Pilares do projeto',
            'maxItems' => 12,
            'header' => [
                ['key' => 'eyebrow', 'label' => 'Linha de apoio (acima do título)', 'tipo' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'titulo', 'label' => 'Título da página', 'tipo' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'descricao', 'label' => 'Texto de abertura', 'tipo' => 'textarea', 'maxLength' => self::LONG],
                ['key' => 'imageUrl', 'label' => 'Endereço da imagem', 'tipo' => 'text', 'maxLength' => 500],
            ],
            'item' => [
                ['key' => 'titulo', 'label' => 'Título do pilar', 'tipo' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'descricao', 'label' => 'Descrição', 'tipo' => 'textarea', 'maxLength' => self::LONG],
            ],
        ],
        'noticias' => [
            'label' => 'Notícias & Novidades',
            'itemsLabel' => 'Notícias publicadas',
            'maxItems' => 24,
            'header' => [
                ['key' => 'eyebrow', 'label' => 'Linha de apoio (acima do título)', 'tipo' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'titulo', 'label' => 'Título da página', 'tipo' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'descricao', 'label' => 'Texto de abertura', 'tipo' => 'textarea', 'maxLength' => self::LONG],
            ],
            'item' => [
                ['key' => 'titulo', 'label' => 'Título da notícia', 'tipo' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'descricao', 'label' => 'Resumo', 'tipo' => 'textarea', 'maxLength' => self::LONG],
                ['key' => 'data', 'label' => 'Data exibida', 'tipo' => 'text', 'maxLength' => 50],
                ['key' => 'tag', 'label' => 'Etiqueta', 'tipo' => 'text', 'maxLength' => 50],
                ['key' => 'imagem', 'label' => 'Endereço da imagem', 'tipo' => 'text', 'maxLength' => 500],
            ],
        ],
        'duvidas' => [
            'label' => 'Dúvidas Frequentes',
            'itemsLabel' => 'Perguntas e respostas',
            'maxItems' => 30,
            'header' => [
                ['key' => 'eyebrow', 'label' => 'Linha de apoio (acima do título)', 'tipo' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'titulo', 'label' => 'Título da página', 'tipo' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'descricao', 'label' => 'Texto de abertura', 'tipo' => 'textarea', 'maxLength' => self::LONG],
            ],
            'item' => [
                ['key' => 'question', 'label' => 'Pergunta', 'tipo' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'answer', 'label' => 'Resposta', 'tipo' => 'textarea', 'maxLength' => self::LONG],
            ],
        ],
        'calendario' => [
            'label' => 'Calendário de Aulas ao Vivo',
            'itemsLabel' => 'Encontros agendados',
            'maxItems' => 24,
            'header' => [
                ['key' => 'eyebrow', 'label' => 'Linha de apoio (acima do título)', 'tipo' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'titulo', 'label' => 'Título da página', 'tipo' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'descricao', 'label' => 'Texto de abertura', 'tipo' => 'textarea', 'maxLength' => self::LONG],
            ],
            'item' => [
                ['key' => 'titulo', 'label' => 'Título do encontro', 'tipo' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'tutor', 'label' => 'Quem ministra', 'tipo' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'day', 'label' => 'Dia', 'tipo' => 'text', 'maxLength' => 10],
                ['key' => 'month', 'label' => 'Mês (abreviado)', 'tipo' => 'text', 'maxLength' => 10],
                ['key' => 'horario', 'label' => 'Horário', 'tipo' => 'text', 'maxLength' => 20],
                ['key' => 'tipo', 'label' => 'Tipo de acesso', 'tipo' => 'text', 'maxLength' => 50],
            ],
        ],
        'orientacoes' => [
            'label' => 'Orientações Gerais',
            'itemsLabel' => 'Diretrizes',
            'maxItems' => 12,
            'header' => [
                ['key' => 'eyebrow', 'label' => 'Linha de apoio (acima do título)', 'tipo' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'titulo', 'label' => 'Título da página', 'tipo' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'descricao', 'label' => 'Texto de abertura', 'tipo' => 'textarea', 'maxLength' => self::LONG],
            ],
            'item' => [
                ['key' => 'titulo', 'label' => 'Título da diretriz', 'tipo' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'descricao', 'label' => 'Descrição', 'tipo' => 'textarea', 'maxLength' => self::LONG],
            ],
        ],
    ];

    /**
     * Espelha o conteúdo que estava hardcoded em src/components/pages/*.tsx
     * e src/data/news.ts antes desta área existir.
     *
     * @var array<string, array<string, mixed>>
     */
    private const DEFAULTS = [
        'o-ava' => [
            'eyebrow' => 'Ecossistema de Qualificação Digital',
            'titulo' => 'O que é o AVA?',
            'descricao' => 'O AVA (Ambiente Virtual de Aprendizagem) da Escola Estadual da Cultura RJ é um ecossistema digital inteligente voltado para a formação continuada, democrático e acessível a todos os fazedores de cultura.',
            'items' => [
                [
                    'id' => 'ava-1',
                    'titulo' => 'Aulas Assíncronas',
                    'descricao' => 'Assista às videoaulas gravadas quando e onde quiser, no seu próprio ritmo. Nosso player interativo permite que você retome os estudos exatamente de onde parou.',
                ],
                [
                    'id' => 'ava-2',
                    'titulo' => 'Interação Próxima',
                    'descricao' => 'Participe de mentorias coletivas ao vivo através do nosso Calendário e envie mensagens diretas aos professores e tutores especializados de cada trilha.',
                ],
                [
                    'id' => 'ava-3',
                    'titulo' => 'Diplomas Válidos',
                    'descricao' => 'Ao atingir os objetivos acadêmicos, emita um certificado oficial digital homologado pela Secretaria da Cultura do Estado com verificação em blockchain.',
                ],
            ],
        ],
        'certificados' => [
            'eyebrow' => 'Qualificação Oficial Homologada',
            'titulo' => 'Certificados e Emissão',
            'descricao' => 'Todos os cursos da Escola Estadual da Cultura RJ dão direito a certificados de conclusão oficiais. Entenda os critérios necessários para emissão e valide certificados existentes abaixo.',
            'criteriaTitle' => 'Orientações de Aprovação & Emissão',
            'criteriaIntro' => 'Para estar elegível à geração do seu certificado digital, você deve atender aos seguintes critérios letivos na plataforma:',
            'noticeText' => 'Validação por Terceiros: Qualquer instituição pública ou parceira pode validar os certificados emitidos utilizando o nosso autenticador ao lado com o código de registro ou nome completo.',
            'authenticatorTitle' => 'Autenticador de Certificados',
            'authenticatorDescription' => 'Insira o código de validação de 10 dígitos ou o nome completo do aluno para verificar sua autenticidade.',
            'items' => [
                [
                    'id' => 'criterio-1',
                    'titulo' => '70% de Frequência Mínima:',
                    'descricao' => 'Calculada automaticamente pelas videoaulas assistidas por completo e presenças nas mentorias síncronas do Calendário.',
                ],
                [
                    'id' => 'criterio-2',
                    'titulo' => 'Nota no Questionário Final:',
                    'descricao' => 'Atingir nota igual ou superior a 70% de acertos nos questionários avaliativos de cada módulo do curso.',
                ],
                [
                    'id' => 'criterio-3',
                    'titulo' => 'Emissão Sem Complicações:',
                    'descricao' => 'O botão de download do certificado em PDF ficará visível na aba "Certificados" do seu Painel de Estudos assim que as metas forem cumpridas.',
                ],
            ],
        ],
        'o-projeto' => [
            'eyebrow' => 'Iniciativa de fomento público',
            'titulo' => 'O Projeto Pedagógico',
            'descricao' => 'A Escola Estadual da Cultura RJ é um projeto estratégico estatal gerido pela Diretoria de Formação e Qualificação de Trabalhadores da Cultura. Nosso plano político-pedagógico tem como compromisso democratizar as ferramentas da Economia Criativa.',
            'imageUrl' => 'https://images.unsplash.com/photo-1513364776144-60967b0f800f?auto=format&fit=crop&q=80&w=600',
            'items' => [
                [
                    'id' => 'pilar-1',
                    'titulo' => 'Foco na Descentralização e Acesso Público',
                    'descricao' => 'Trilhamos caminhos para alcançar comunidades distantes dos grandes eixos culturais, proporcionando qualificação técnica para jovens e adultos.',
                ],
                [
                    'id' => 'pilar-2',
                    'titulo' => 'Fomento à Lei Paulo Gustavo e Editais Públicos',
                    'descricao' => 'Nossos conteúdos auxiliam o fazedor de cultura a elaborar propostas robustas, captar recursos em editais governamentais e prestar contas de forma simplificada.',
                ],
                [
                    'id' => 'pilar-3',
                    'titulo' => 'Pedagogia Decolonial e Inclusiva',
                    'descricao' => 'Partimos da pedagogia crítica e da vanguarda negra de Solano Trindade, integrando teoria crítica com prática imediata do fazer artístico.',
                ],
            ],
        ],
        'noticias' => [
            'eyebrow' => 'Destaques Letivos',
            'titulo' => 'Notícias & Novidades',
            'descricao' => 'Acompanhe os informativos, aberturas de turma e novidades do Portal AVASEC.',
            'items' => [
                [
                    'id' => 'noticia-1',
                    'data' => '20 mai. 2026',
                    'tag' => 'Video Mapping',
                    'titulo' => 'AVASEC abre inscrições para curso de Video Mapping focado no cenário cultural',
                    'imagem' => 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=600&auto=format&fit=crop&q=70',
                    'descricao' => 'Uma imersão completa na arte digital e projeção mapeada para fachadas históricas, palcos e intervenções urbanas de impacto.',
                ],
                [
                    'id' => 'noticia-2',
                    'data' => '16 mai. 2026',
                    'tag' => 'Prestação de Contas',
                    'titulo' => 'Nova turma do Curso de Prestação de Contas de Propostas Simplificadas',
                    'imagem' => 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?w=600&auto=format&fit=crop&q=70',
                    'descricao' => 'Domine sem mistérios as exigências contábeis e fiscais das leis de fomento, garantindo a aprovação tranquila do seu relatório.',
                ],
                [
                    'id' => 'noticia-3',
                    'data' => '14 mai. 2026',
                    'tag' => 'Propostas Simplificadas',
                    'titulo' => 'Abertas as inscrições para a nova turma do curso de Submissão de Propostas',
                    'imagem' => 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?w=600&auto=format&fit=crop&q=70',
                    'descricao' => 'Como transformar as suas ideias artísticas em formatos de propostas campeãs para concorrer a editais públicos e privados.',
                ],
            ],
        ],
        'duvidas' => [
            'eyebrow' => 'Suporte ao Aluno',
            'titulo' => 'Dúvidas Frequentes',
            'descricao' => 'Tem dúvidas sobre como utilizar o Portal AVA? Acesse nosso FAQ rápido:',
            'items' => [
                [
                    'id' => 'faq-1',
                    'question' => 'Como faço para emitir o meu certificado homologado?',
                    'answer' => 'O certificado é gerado automaticamente na plataforma assim que você atingir um mínimo de 70% de frequência de participação letiva (somadas as visualizações de aulas teóricas gravadas, tarefas e presenças síncronas de mentoria).',
                ],
                [
                    'id' => 'faq-2',
                    'question' => 'Sou aluno novo, como faço para ingressar no Ambient Virtual de Estudo?',
                    'answer' => "Você só precisa clicar no botão 'Entrar' no topo direito e selecionar o seu perfil correspondente (Student João Silva ou Ana Souza para simular o LMS) e clicar em Conectar.",
                ],
                [
                    'id' => 'faq-3',
                    'question' => 'O que é o fomento das trilhas de Economia Criativa?',
                    'answer' => 'As trilhas auxiliam profissionais a gerenciarem portfólios, captarem recursos em editais de fomento públicos (Lei Paulo Gustavo, Aldir Blanc) e gerarem declarações contábeis de forma simplificada.',
                ],
            ],
        ],
        'calendario' => [
            'eyebrow' => 'Encontros Síncronos Interativos',
            'titulo' => 'Calendário de Aulas ao Vivo',
            'descricao' => 'Nossos cursos livres oferecem encontros ao vivo periódicos para tirar dúvidas, realizar mentorias de projetos e debater temas contemporâneos da cultura. Acompanhe a nossa agenda síncrona:',
            'items' => [
                [
                    'id' => 'evento-1',
                    'day' => '10',
                    'month' => 'JUL',
                    'horario' => '19:00',
                    'titulo' => 'Mentoria de Elaboração de Editais (Lei Paulo Gustavo)',
                    'tutor' => 'Profª Helena Ribeiro',
                    'tipo' => 'Sessão Aberta',
                ],
                [
                    'id' => 'evento-2',
                    'day' => '15',
                    'month' => 'JUL',
                    'horario' => '18:30',
                    'titulo' => 'Aula Prática: Fotografia Digital Básica com Smartphones',
                    'tutor' => 'Prof. Marcos Souza',
                    'tipo' => 'Exclusivo de Trilha',
                ],
                [
                    'id' => 'evento-3',
                    'day' => '22',
                    'month' => 'JUL',
                    'horario' => '20:00',
                    'titulo' => 'Fórum Geral: Elaboração e Gestão de Projetos Culturais',
                    'tutor' => 'Prof. Daniel Costa',
                    'tipo' => 'Aberto a Todos',
                ],
            ],
        ],
        'orientacoes' => [
            'eyebrow' => 'Manual do Estudante',
            'titulo' => 'Orientações Gerais',
            'descricao' => 'Consulte as orientações e diretrizes de como interagir com o AVA da Escola Estadual da Cultura RJ e garanta uma experiência de aprendizado transformadora.',
            'items' => [
                [
                    'id' => 'diretriz-1',
                    'titulo' => 'Matrícula Simples',
                    'descricao' => 'Qualquer aluno cadastrado pode se matricular em um curso ativo por vez. A troca de cursos é permitida de forma simples pelo painel.',
                ],
                [
                    'id' => 'diretriz-2',
                    'titulo' => 'Roteiro Letivo',
                    'descricao' => 'Os módulos são sequenciais. É altamente recomendável assistir às videoaulas na ordem cronológica proposta para melhor absorção.',
                ],
                [
                    'id' => 'diretriz-3',
                    'titulo' => 'Apoio e Tutoria',
                    'descricao' => 'Caso tenha dúvidas nas aulas, envie mensagens diretas aos tutores através da aba de Suporte ou utilize as salas de chat comunitárias.',
                ],
                [
                    'id' => 'diretriz-4',
                    'titulo' => 'Acessibilidade Total',
                    'descricao' => 'A plataforma conta com leitor de tela nativo, reguladores de contraste e aumentador de fontes para garantir a inclusão de todos.',
                ],
            ],
        ],
    ];

    /**
     * Chaves de página aceitas.
     *
     * @return list<string>
     */
    public static function pageKeys(): array
    {
        return array_keys(self::SCHEMA);
    }

    /**
     * Schema de campos de todas as páginas — consumido pela tela do admin para
     * montar os formulários com rótulos em português.
     *
     * @return array<string, array{label: string, itemsLabel: string, maxItems: int, header: list<array{key:string,label:string,tipo:string,maxLength:int}>, item: list<array{key:string,label:string,tipo:string,maxLength:int}>}>
     */
    public function schema(): array
    {
        return self::SCHEMA;
    }

    /**
     * Conteúdo de uma página, caindo nos defaults quando não há linha salva.
     *
     * @return array<string, mixed>
     */
    public function get(string $chave_pagina): array
    {
        $this->assertValidPageKey($chave_pagina);

        $row = ConteudoPaginaSite::query()->find($chave_pagina);
        $defaults = self::DEFAULTS[$chave_pagina];

        if ($row === null) {
            return array_merge($defaults, [
                'chave_pagina' => $chave_pagina,
                'atualizado_em' => null,
                'atualizado_por_usuario_id' => null,
            ]);
        }

        $stored = is_array($row->conteudo) ? $row->conteudo : [];

        return array_merge($defaults, $stored, [
            'chave_pagina' => $chave_pagina,
            'atualizado_em' => $row->atualizado_em?->toIso8601String(),
            'atualizado_por_usuario_id' => $row->atualizado_por_usuario_id,
        ]);
    }

    /**
     * Conteúdo de todas as páginas de uma vez (o site público hidrata com isto
     * numa única chamada).
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $result = [];
        foreach (self::pageKeys() as $chave_pagina) {
            $result[$chave_pagina] = $this->get($chave_pagina);
        }

        return $result;
    }

    /**
     * Substitui o conteúdo de uma página. O payload é normalizado contra o
     * SCHEMA: campo desconhecido é descartado, valor não-string é ignorado e
     * texto longo é truncado no limite do campo. Nada de HTML entra.
     *
     * @param  array<string, mixed>  $input
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array<string, mixed>
     */
    public function upsert(string $chave_pagina, array $input, array $requester): array
    {
        $this->assertValidPageKey($chave_pagina);

        // Conteúdo de página pública é visível a qualquer visitante — só o
        // Admin Superior edita, nunca instrutor.
        if ($requester['papel'] !== 'admin') {
            throw ApiException::forbidden('Apenas o Admin Superior pode editar o conteúdo do site.');
        }

        $content = $this->normalize($chave_pagina, $input);

        // Versão anterior e gravação na mesma transação: sem versão órfã se a gravação falhar.
        DB::transaction(function () use ($chave_pagina, $content, $requester): void {
            $row = ConteudoPaginaSite::query()->find($chave_pagina);
            $this->guardarVersaoAnterior($chave_pagina, $content, $requester['sub']);
            $attributes = [
                'conteudo' => $content,
                'atualizado_por_usuario_id' => $requester['sub'],
                'atualizado_em' => CarbonImmutable::now(),
            ];

            if ($row !== null) {
                $row->fill($attributes)->save();
            } else {
                ConteudoPaginaSite::query()->create(array_merge($attributes, ['chave_pagina' => $chave_pagina]));
            }
        });

        return $this->get($chave_pagina);
    }

    /**
     * O texto que está no ar (o salvo, ou o padrão de fábrica se ninguém salvou
     * ainda) vai para o histórico antes de ser substituído — ADR 021. Se a nova
     * gravação não muda nada, não vira versão.
     *
     * @param  array<string, mixed>  $novo
     */
    private function guardarVersaoAnterior(string $chave_pagina, array $novo, string $porUserId): void
    {
        $atual = $this->conteudoDaPagina($chave_pagina);
        if ($atual == $novo) {
            return;
        }

        app(VersoesDeConteudo::class)->guardar(VersoesDeConteudo::PAGINA_DO_SITE, $chave_pagina, $atual, $porUserId);
    }

    /**
     * Só o conteúdo editável de uma página (cabeçalho e itens), sem os metadados
     * que `get()` acrescenta.
     *
     * @return array<string, mixed>
     */
    public function conteudoDaPagina(string $chave_pagina): array
    {
        $tudo = $this->get($chave_pagina);
        unset($tudo['chave_pagina'], $tudo['atualizado_em'], $tudo['atualizado_por_usuario_id']);

        return $tudo;
    }

    /**
     * Devolve ao ar uma versão anterior. É uma gravação como outra qualquer: passa
     * pelo `upsert` (mesma normalização, mesma regra de quem pode) e, por isso,
     * guarda o que estava no ar antes de trocar.
     *
     * @param  array{sub:string,nome:string,papel:string}  $requester
     * @return array<string, mixed>
     */
    public function restaurarVersao(string $chave_pagina, string $versaoId, array $requester): array
    {
        $this->assertValidPageKey($chave_pagina);

        if ($requester['papel'] !== 'admin') {
            throw ApiException::forbidden('Apenas o Admin Superior pode editar o conteúdo do site.');
        }

        $versao = app(VersoesDeConteudo::class)->obter(VersoesDeConteudo::PAGINA_DO_SITE, $chave_pagina, $versaoId);
        if ($versao === null) {
            throw ApiException::notFound('Versão não encontrada.');
        }

        return $this->upsert($chave_pagina, $versao, $requester);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalize(string $chave_pagina, array $input): array
    {
        $schema = self::SCHEMA[$chave_pagina];
        $defaults = self::DEFAULTS[$chave_pagina];
        $content = [];

        foreach ($schema['header'] as $field) {
            $key = $field['key'];
            $content[$key] = array_key_exists($key, $input)
                ? $this->cleanText($input[$key], $field['maxLength'])
                : (is_string($defaults[$key] ?? null) ? $defaults[$key] : '');
        }

        $rawItems = $input['items'] ?? null;
        if (! is_array($rawItems)) {
            $content['items'] = $defaults['items'];

            return $content;
        }

        $items = [];
        foreach (array_values($rawItems) as $position => $rawItem) {
            if (count($items) >= $schema['maxItems']) {
                break;
            }
            if (! is_array($rawItem)) {
                continue;
            }

            // Uma só passada: limpa cada campo do item e já registra se sobrou
            // algum conteúdo — item totalmente vazio não vira publicação.
            $values = [];
            $hasContent = false;
            foreach ($schema['item'] as $field) {
                $clean = $this->cleanText($rawItem[$field['key']] ?? null, $field['maxLength']);
                $values[$field['key']] = $clean;
                if ($clean !== '') {
                    $hasContent = true;
                }
            }

            if (! $hasContent) {
                continue;
            }

            $items[] = array_merge(
                ['id' => $this->cleanId($rawItem['id'] ?? null, $chave_pagina, $position)],
                $values,
            );
        }

        $content['items'] = $items;

        return $content;
    }

    private function cleanText(mixed $value, int $maxLength): string
    {
        if (! is_string($value)) {
            return '';
        }

        return mb_substr(trim($value), 0, $maxLength);
    }

    private function cleanId(mixed $value, string $chave_pagina, int $position): string
    {
        if (is_string($value) && preg_match('/^[A-Za-z0-9_-]{1,64}$/', $value) === 1) {
            return $value;
        }

        return $chave_pagina.'-'.($position + 1);
    }

    private function assertValidPageKey(string $chave_pagina): void
    {
        if (! array_key_exists($chave_pagina, self::SCHEMA)) {
            throw ApiException::notFound('Página de conteúdo desconhecida.');
        }
    }
}
