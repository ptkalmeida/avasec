<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Catálogo público — réplica de `featuredCoursesData` (src/App.tsx)
|--------------------------------------------------------------------------
|
| ESTE NÃO É O BANCO. É a vitrine institucional do portal, e sempre foi: no
| React, a tela `/cursos` nunca leu a tabela `Course` — ela renderiza um array
| fixo declarado dentro do `App.tsx`. A busca do cabeçalho lê o MESMO array.
|
| Isso foi descoberto ao migrar a tela (19/10/2026) e merece ficar escrito,
| porque a suposição natural é a oposta. Duas consequências práticas:
|
| 1. Trocar a vitrine pela tabela `Course` NÃO seria migrar a tela: seria mudar
|    o que ela mostra, contra a invariante 1 do plano 16. E mostraria ao público
|    os registros de teste que existem no banco ("Curso de Teste", "Curso Teste
|    Gabriel") — regressão visível no portal, não melhoria.
| 2. Enquanto a vitrine for conteúdo fixo, ela não tem data de publicação. A
|    skill `redesenhar-ava-lms` pede "os quatro cursos mais recentes" e prevê
|    exatamente este caso: usar os quatro primeiros e **não afirmar** que a
|    ordem foi verificada. É o que a tela faz — e por isso o título é "Cursos em
|    destaque", não "mais recentes".
|
| Ligar a vitrine ao banco é decisão de produto, com checkpoint humano: exige
| uma data de publicação em `Course` (schema) e um critério de quais cursos
| aparecem ao público. Está registrado como pendência no plano 18.
|
| `CatalogoPublicoParityTest` falha quando este arquivo e o array do React saem
| de sincronia.
|
*/

return [

    [
        'title' => 'Inteligência Artificial e Cultura 2ª Oferta',
        'category' => 'Economia Criativa & IA',
        'instructor' => 'Gestor de Conteúdos',
        'image' => 'https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?w=500&auto=format&fit=crop&q=60',
        'description' => 'Aprenda a aplicar ferramentas de Inteligência Artificial generativa no fomento, roteirização e design de projetos de artes integradas.',
    ],
    [
        'title' => 'Produção Audiovisual 2ª Oferta',
        'category' => 'Áreas Técnicas',
        'instructor' => 'Gestor de Conteúdos',
        'image' => 'https://images.unsplash.com/photo-1492691527719-9d1e07e534b4?w=500&auto=format&fit=crop&q=60',
        'description' => 'Da captação de áudio e iluminação até as técnicas de edição e publicação. Um guia prático para criadores independentes.',
    ],
    [
        'title' => 'Submissão de Propostas Simplificadas 2ª Oferta',
        'category' => 'Políticas e Gestão Culturais',
        'instructor' => 'Gestor de Conteúdos',
        'image' => 'https://images.unsplash.com/photo-1517048676732-d65bc937f952?w=500&auto=format&fit=crop&q=60',
        'description' => 'Inscreva sua proposta cultural sem complicação. Compreenda leis de incentivo e preenchimento técnico de formulários oficiais.',
    ],
    [
        'title' => 'Prestação de Contas de Propostas Simplificadas 2ª Oferta',
        'category' => 'Políticas e Gestão Culturais',
        'instructor' => 'Gestor de Conteúdos',
        'image' => 'https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?w=500&auto=format&fit=crop&q=60',
        'description' => 'Como organizar recibos, despesas e relatórios de atividades para certificar que os fundos recebidos foram devidamente executados.',
    ],
    [
        'title' => 'UX/UI Design: Interfaces de Alta Performance',
        'category' => 'Economia Criativa & IA',
        'instructor' => 'Gestor de Conteúdos',
        'image' => 'https://images.unsplash.com/photo-1559028006-448665bd7c7f?w=500&auto=format&fit=crop&q=60',
        'description' => 'Aprenda do zero ao avançado como planejar, estruturar e prototipar sistemas complexos utilizando as melhores práticas do Figma, Design Systems e testes de usabilidade.',
    ],
    [
        'title' => 'Fotografia Cultural e Patrimônio Histórico',
        'category' => 'Áreas Técnicas',
        'instructor' => 'Gestor de Conteúdos',
        'image' => 'https://images.unsplash.com/photo-1495707902641-75cac588d2e9?w=500&auto=format&fit=crop&q=60',
        'description' => 'Técnicas de composição, luz natural e pós-produção para registrar acervos, monumentos e manifestações culturais com qualidade profissional.',
    ],
    [
        'title' => 'Gestão de Editais e Leis de Incentivo à Cultura',
        'category' => 'Políticas e Gestão Culturais',
        'instructor' => 'Gestor de Conteúdos',
        'image' => 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?w=500&auto=format&fit=crop&q=60',
        'description' => 'Panorama completo das principais leis de fomento cultural (Rouanet, ICMS Cultural, editais municipais) e como estruturar propostas competitivas.',
    ],
    [
        'title' => 'Empreendedorismo Criativo e Modelos de Negócio',
        'category' => 'Economia Criativa & IA',
        'instructor' => 'Gestor de Conteúdos',
        'image' => 'https://images.unsplash.com/photo-1519389950473-47ba0277781c?w=500&auto=format&fit=crop&q=60',
        'description' => 'Do plano de negócios à precificação: como transformar talento artístico e cultural em iniciativas sustentáveis e escaláveis.',
    ],
    [
        'title' => 'Produção de Podcast e Narrativas Sonoras',
        'category' => 'Áreas Técnicas',
        'instructor' => 'Gestor de Conteúdos',
        'image' => 'https://images.unsplash.com/photo-1478737270239-2f02b77fc618?w=500&auto=format&fit=crop&q=60',
        'description' => 'Roteiro, captação, edição e distribuição de podcasts — um guia prático para criadores que querem contar histórias em áudio.',
    ],
];
