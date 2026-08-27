<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\SitePageContent;
use Carbon\CarbonImmutable;

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
     *     header: list<array{key:string,label:string,type:string,maxLength:int}>,
     *     item: list<array{key:string,label:string,type:string,maxLength:int}>
     * }>
     */
    private const SCHEMA = [
        'o-ava' => [
            'label' => 'O que é o AVA',
            'itemsLabel' => 'Destaques do ambiente',
            'maxItems' => 9,
            'header' => [
                ['key' => 'eyebrow', 'label' => 'Linha de apoio (acima do título)', 'type' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'title', 'label' => 'Título da página', 'type' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'description', 'label' => 'Texto de abertura', 'type' => 'textarea', 'maxLength' => self::LONG],
            ],
            'item' => [
                ['key' => 'title', 'label' => 'Título do destaque', 'type' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'description', 'label' => 'Descrição', 'type' => 'textarea', 'maxLength' => self::LONG],
            ],
        ],
        'certificados' => [
            'label' => 'Certificados e Emissão',
            'itemsLabel' => 'Critérios de aprovação',
            'maxItems' => 9,
            'header' => [
                ['key' => 'eyebrow', 'label' => 'Linha de apoio (acima do título)', 'type' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'title', 'label' => 'Título da página', 'type' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'description', 'label' => 'Texto de abertura', 'type' => 'textarea', 'maxLength' => self::LONG],
                ['key' => 'criteriaTitle', 'label' => 'Título do bloco de critérios', 'type' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'criteriaIntro', 'label' => 'Texto introdutório dos critérios', 'type' => 'textarea', 'maxLength' => self::LONG],
                ['key' => 'noticeText', 'label' => 'Aviso destacado (validação por terceiros)', 'type' => 'textarea', 'maxLength' => self::LONG],
                ['key' => 'authenticatorTitle', 'label' => 'Título do autenticador', 'type' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'authenticatorDescription', 'label' => 'Instrução do autenticador', 'type' => 'textarea', 'maxLength' => self::LONG],
            ],
            'item' => [
                ['key' => 'title', 'label' => 'Critério', 'type' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'description', 'label' => 'Como é apurado', 'type' => 'textarea', 'maxLength' => self::LONG],
            ],
        ],
        'o-projeto' => [
            'label' => 'O Projeto Pedagógico',
            'itemsLabel' => 'Pilares do projeto',
            'maxItems' => 12,
            'header' => [
                ['key' => 'eyebrow', 'label' => 'Linha de apoio (acima do título)', 'type' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'title', 'label' => 'Título da página', 'type' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'description', 'label' => 'Texto de abertura', 'type' => 'textarea', 'maxLength' => self::LONG],
                ['key' => 'imageUrl', 'label' => 'Endereço da imagem', 'type' => 'text', 'maxLength' => 500],
            ],
            'item' => [
                ['key' => 'title', 'label' => 'Título do pilar', 'type' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'description', 'label' => 'Descrição', 'type' => 'textarea', 'maxLength' => self::LONG],
            ],
        ],
        'noticias' => [
            'label' => 'Notícias & Novidades',
            'itemsLabel' => 'Notícias publicadas',
            'maxItems' => 24,
            'header' => [
                ['key' => 'eyebrow', 'label' => 'Linha de apoio (acima do título)', 'type' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'title', 'label' => 'Título da página', 'type' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'description', 'label' => 'Texto de abertura', 'type' => 'textarea', 'maxLength' => self::LONG],
            ],
            'item' => [
                ['key' => 'title', 'label' => 'Título da notícia', 'type' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'description', 'label' => 'Resumo', 'type' => 'textarea', 'maxLength' => self::LONG],
                ['key' => 'date', 'label' => 'Data exibida', 'type' => 'text', 'maxLength' => 50],
                ['key' => 'tag', 'label' => 'Etiqueta', 'type' => 'text', 'maxLength' => 50],
                ['key' => 'image', 'label' => 'Endereço da imagem', 'type' => 'text', 'maxLength' => 500],
            ],
        ],
        'duvidas' => [
            'label' => 'Dúvidas Frequentes',
            'itemsLabel' => 'Perguntas e respostas',
            'maxItems' => 30,
            'header' => [
                ['key' => 'eyebrow', 'label' => 'Linha de apoio (acima do título)', 'type' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'title', 'label' => 'Título da página', 'type' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'description', 'label' => 'Texto de abertura', 'type' => 'textarea', 'maxLength' => self::LONG],
            ],
            'item' => [
                ['key' => 'question', 'label' => 'Pergunta', 'type' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'answer', 'label' => 'Resposta', 'type' => 'textarea', 'maxLength' => self::LONG],
            ],
        ],
        'calendario' => [
            'label' => 'Calendário de Aulas ao Vivo',
            'itemsLabel' => 'Encontros agendados',
            'maxItems' => 24,
            'header' => [
                ['key' => 'eyebrow', 'label' => 'Linha de apoio (acima do título)', 'type' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'title', 'label' => 'Título da página', 'type' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'description', 'label' => 'Texto de abertura', 'type' => 'textarea', 'maxLength' => self::LONG],
            ],
            'item' => [
                ['key' => 'title', 'label' => 'Título do encontro', 'type' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'tutor', 'label' => 'Quem ministra', 'type' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'day', 'label' => 'Dia', 'type' => 'text', 'maxLength' => 10],
                ['key' => 'month', 'label' => 'Mês (abreviado)', 'type' => 'text', 'maxLength' => 10],
                ['key' => 'time', 'label' => 'Horário', 'type' => 'text', 'maxLength' => 20],
                ['key' => 'type', 'label' => 'Tipo de acesso', 'type' => 'text', 'maxLength' => 50],
            ],
        ],
        'orientacoes' => [
            'label' => 'Orientações Gerais',
            'itemsLabel' => 'Diretrizes',
            'maxItems' => 12,
            'header' => [
                ['key' => 'eyebrow', 'label' => 'Linha de apoio (acima do título)', 'type' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'title', 'label' => 'Título da página', 'type' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'description', 'label' => 'Texto de abertura', 'type' => 'textarea', 'maxLength' => self::LONG],
            ],
            'item' => [
                ['key' => 'title', 'label' => 'Título da diretriz', 'type' => 'text', 'maxLength' => self::SHORT],
                ['key' => 'description', 'label' => 'Descrição', 'type' => 'textarea', 'maxLength' => self::LONG],
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
            'title' => 'O que é o AVA?',
            'description' => 'O AVA (Ambiente Virtual de Aprendizagem) da Escola Estadual da Cultura é um ecossistema digital inteligente voltado para a formação continuada, democrático e acessível a todos os fazedores de cultura.',
            'items' => [
                [
                    'id' => 'ava-1',
                    'title' => 'Aulas Assíncronas',
                    'description' => 'Assista às videoaulas gravadas quando e onde quiser, no seu próprio ritmo. Nosso player interativo permite que você retome os estudos exatamente de onde parou.',
                ],
                [
                    'id' => 'ava-2',
                    'title' => 'Interação Próxima',
                    'description' => 'Participe de mentorias coletivas ao vivo através do nosso Calendário e envie mensagens diretas aos professores e tutores especializados de cada trilha.',
                ],
                [
                    'id' => 'ava-3',
                    'title' => 'Diplomas Válidos',
                    'description' => 'Ao atingir os objetivos acadêmicos, emita um certificado oficial digital homologado pela Secretaria da Cultura do Estado com verificação em blockchain.',
                ],
            ],
        ],
        'certificados' => [
            'eyebrow' => 'Qualificação Oficial Homologada',
            'title' => 'Certificados e Emissão',
            'description' => 'Todos os cursos da Escola Estadual da Cultura dão direito a certificados de conclusão oficiais. Entenda os critérios necessários para emissão e valide certificados existentes abaixo.',
            'criteriaTitle' => 'Orientações de Aprovação & Emissão',
            'criteriaIntro' => 'Para estar elegível à geração do seu certificado digital, você deve atender aos seguintes critérios letivos na plataforma:',
            'noticeText' => 'Validação por Terceiros: Qualquer instituição pública ou parceira pode validar os certificados emitidos utilizando o nosso autenticador ao lado com o código de registro ou nome completo.',
            'authenticatorTitle' => 'Autenticador de Certificados',
            'authenticatorDescription' => 'Insira o código de validação de 10 dígitos ou o nome completo do aluno para verificar sua autenticidade.',
            'items' => [
                [
                    'id' => 'criterio-1',
                    'title' => '70% de Frequência Mínima:',
                    'description' => 'Calculada automaticamente pelas videoaulas assistidas por completo e presenças nas mentorias síncronas do Calendário.',
                ],
                [
                    'id' => 'criterio-2',
                    'title' => 'Nota no Questionário Final:',
                    'description' => 'Atingir nota igual ou superior a 70% de acertos nos questionários avaliativos de cada módulo do curso.',
                ],
                [
                    'id' => 'criterio-3',
                    'title' => 'Emissão Sem Complicações:',
                    'description' => 'O botão de download do certificado em PDF ficará visível na aba "Certificados" do seu Painel de Estudos assim que as metas forem cumpridas.',
                ],
            ],
        ],
        'o-projeto' => [
            'eyebrow' => 'Iniciativa de fomento público',
            'title' => 'O Projeto Pedagógico',
            'description' => 'A Escola Estadual da Cultura é um projeto estratégico estatal gerido pela Diretoria de Formação e Qualificação de Trabalhadores da Cultura. Nosso plano político-pedagógico tem como compromisso democratizar as ferramentas da Economia Criativa.',
            'imageUrl' => 'https://images.unsplash.com/photo-1513364776144-60967b0f800f?auto=format&fit=crop&q=80&w=600',
            'items' => [
                [
                    'id' => 'pilar-1',
                    'title' => 'Foco na Descentralização e Acesso Público',
                    'description' => 'Trilhamos caminhos para alcançar comunidades distantes dos grandes eixos culturais, proporcionando qualificação técnica para jovens e adultos.',
                ],
                [
                    'id' => 'pilar-2',
                    'title' => 'Fomento à Lei Paulo Gustavo e Editais Públicos',
                    'description' => 'Nossos conteúdos auxiliam o fazedor de cultura a elaborar propostas robustas, captar recursos em editais governamentais e prestar contas de forma simplificada.',
                ],
                [
                    'id' => 'pilar-3',
                    'title' => 'Pedagogia Decolonial e Inclusiva',
                    'description' => 'Celebramos e nos inspiramos no grande educador patrono Paulo Freire e na vanguarda negra de Solano Trindade, integrando teoria crítica com prática imediata do fazer artístico.',
                ],
            ],
        ],
        'noticias' => [
            'eyebrow' => 'Destaques Letivos',
            'title' => 'Notícias & Novidades',
            'description' => 'Acompanhe os informativos, aberturas de turma e novidades do Portal AVASEC.',
            'items' => [
                [
                    'id' => 'noticia-1',
                    'date' => '20 mai. 2026',
                    'tag' => 'Video Mapping',
                    'title' => 'AVASEC abre inscrições para curso de Video Mapping focado no cenário cultural',
                    'image' => 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=600&auto=format&fit=crop&q=70',
                    'description' => 'Uma imersão completa na arte digital e projeção mapeada para fachadas históricas, palcos e intervenções urbanas de impacto.',
                ],
                [
                    'id' => 'noticia-2',
                    'date' => '16 mai. 2026',
                    'tag' => 'Prestação de Contas',
                    'title' => 'Nova turma do Curso de Prestação de Contas de Propostas Simplificadas',
                    'image' => 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?w=600&auto=format&fit=crop&q=70',
                    'description' => 'Domine sem mistérios as exigências contábeis e fiscais das leis de fomento, garantindo a aprovação tranquila do seu relatório.',
                ],
                [
                    'id' => 'noticia-3',
                    'date' => '14 mai. 2026',
                    'tag' => 'Propostas Simplificadas',
                    'title' => 'Abertas as inscrições para a nova turma do curso de Submissão de Propostas',
                    'image' => 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?w=600&auto=format&fit=crop&q=70',
                    'description' => 'Como transformar as suas ideias artísticas em formatos de propostas campeãs para concorrer a editais públicos e privados.',
                ],
            ],
        ],
        'duvidas' => [
            'eyebrow' => 'Suporte ao Aluno',
            'title' => 'Dúvidas Frequentes',
            'description' => 'Tem dúvidas sobre como utilizar o Portal AVA? Acesse nosso FAQ rápido:',
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
            'title' => 'Calendário de Aulas ao Vivo',
            'description' => 'Nossos cursos livres oferecem encontros ao vivo periódicos para tirar dúvidas, realizar mentorias de projetos e debater temas contemporâneos da cultura. Acompanhe a nossa agenda síncrona:',
            'items' => [
                [
                    'id' => 'evento-1',
                    'day' => '10',
                    'month' => 'JUL',
                    'time' => '19:00',
                    'title' => 'Mentoria de Elaboração de Editais (Lei Paulo Gustavo)',
                    'tutor' => 'Profª Helena Ribeiro',
                    'type' => 'Sessão Aberta',
                ],
                [
                    'id' => 'evento-2',
                    'day' => '15',
                    'month' => 'JUL',
                    'time' => '18:30',
                    'title' => 'Aula Prática: Fotografia Digital Básica com Smartphones',
                    'tutor' => 'Prof. Marcos Souza',
                    'type' => 'Exclusivo de Trilha',
                ],
                [
                    'id' => 'evento-3',
                    'day' => '22',
                    'month' => 'JUL',
                    'time' => '20:00',
                    'title' => 'Fórum Geral: Elaboração e Gestão de Projetos Culturais',
                    'tutor' => 'Prof. Daniel Costa',
                    'type' => 'Aberto a Todos',
                ],
            ],
        ],
        'orientacoes' => [
            'eyebrow' => 'Manual do Estudante',
            'title' => 'Orientações Gerais',
            'description' => 'Consulte as orientações e diretrizes de como interagir com o AVA da Escola Estadual da Cultura e garanta uma experiência de aprendizado transformadora.',
            'items' => [
                [
                    'id' => 'diretriz-1',
                    'title' => 'Matrícula Simples',
                    'description' => 'Qualquer aluno cadastrado pode se matricular em um curso ativo por vez. A troca de cursos é permitida de forma simples pelo painel.',
                ],
                [
                    'id' => 'diretriz-2',
                    'title' => 'Roteiro Letivo',
                    'description' => 'Os módulos são sequenciais. É altamente recomendável assistir às videoaulas na ordem cronológica proposta para melhor absorção.',
                ],
                [
                    'id' => 'diretriz-3',
                    'title' => 'Apoio e Tutoria',
                    'description' => 'Caso tenha dúvidas nas aulas, envie mensagens diretas aos tutores através da aba de Suporte ou utilize as salas de chat comunitárias.',
                ],
                [
                    'id' => 'diretriz-4',
                    'title' => 'Acessibilidade Total',
                    'description' => 'A plataforma conta com leitor de tela nativo, reguladores de contraste e aumentador de fontes para garantir a inclusão de todos.',
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
     * @return array<string, array<string, mixed>>
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
    public function get(string $pageKey): array
    {
        $this->assertValidPageKey($pageKey);

        $row = SitePageContent::query()->find($pageKey);
        $defaults = self::DEFAULTS[$pageKey];

        if ($row === null) {
            return array_merge($defaults, [
                'pageKey' => $pageKey,
                'updatedAt' => null,
                'updatedByUserId' => null,
            ]);
        }

        $stored = is_array($row->content) ? $row->content : [];

        return array_merge($defaults, $stored, [
            'pageKey' => $pageKey,
            'updatedAt' => $row->updatedAt?->toIso8601String(),
            'updatedByUserId' => $row->updatedByUserId,
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
        foreach (self::pageKeys() as $pageKey) {
            $result[$pageKey] = $this->get($pageKey);
        }

        return $result;
    }

    /**
     * Substitui o conteúdo de uma página. O payload é normalizado contra o
     * SCHEMA: campo desconhecido é descartado, valor não-string é ignorado e
     * texto longo é truncado no limite do campo. Nada de HTML entra.
     *
     * @param  array<string, mixed>  $input
     * @param  array{sub:string,name:string,role:string}  $requester
     * @return array<string, mixed>
     */
    public function upsert(string $pageKey, array $input, array $requester): array
    {
        $this->assertValidPageKey($pageKey);

        // Conteúdo de página pública é visível a qualquer visitante — só o
        // Admin Superior edita, nunca instrutor.
        if ($requester['role'] !== 'admin') {
            throw ApiException::forbidden('Apenas o Admin Superior pode editar o conteúdo do site.');
        }

        $content = $this->normalize($pageKey, $input);

        $row = SitePageContent::query()->find($pageKey);
        $attributes = [
            'content' => $content,
            'updatedByUserId' => $requester['sub'],
            'updatedAt' => CarbonImmutable::now(),
        ];

        if ($row !== null) {
            $row->fill($attributes)->save();
        } else {
            SitePageContent::query()->create(array_merge($attributes, ['pageKey' => $pageKey]));
        }

        return $this->get($pageKey);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalize(string $pageKey, array $input): array
    {
        $schema = self::SCHEMA[$pageKey];
        $defaults = self::DEFAULTS[$pageKey];
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
                ['id' => $this->cleanId($rawItem['id'] ?? null, $pageKey, $position)],
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

    private function cleanId(mixed $value, string $pageKey, int $position): string
    {
        if (is_string($value) && preg_match('/^[A-Za-z0-9_-]{1,64}$/', $value) === 1) {
            return $value;
        }

        return $pageKey.'-'.($position + 1);
    }

    private function assertValidPageKey(string $pageKey): void
    {
        if (! array_key_exists($pageKey, self::SCHEMA)) {
            throw ApiException::notFound('Página de conteúdo desconhecida.');
        }
    }
}
