<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Menu do portal público — réplica de src/config/menuPublico.ts
|--------------------------------------------------------------------------
|
| Duas entradas diretas e dois grupos, na ordem em que aparecem. A regra de
| "qual entrada acende" é a mesma dos dois lados: numa página de dentro de um
| grupo, quem acende é o GRUPO — em Notícias, acende "A Escola".
|
| Isto é uma cópia MANUAL enquanto o cabeçalho existir nas duas tecnologias
| (fase M2 do plano 16). Uma divergência não quebra nada em teste de unidade —
| ela aparece como dois menus diferentes na mesma sessão da mesma pessoa, que é
| o tipo de defeito que ninguém nota até comparar duas telas lado a lado.
| `MenuPublicoParityTest` falha quando os dois saem de sincronia.
|
| Quando a fase M7 apagar o React, este arquivo deixa de ser réplica e passa a
| ser a fonte — e o teste de paridade sai junto com o arquivo TS, não antes.
|
*/

return [

    ['tipo' => 'link', 'rotulo' => 'Cursos', 'rota' => '/cursos'],

    ['tipo' => 'link', 'rotulo' => 'Certificados', 'rota' => '/certificados'],

    [
        'tipo' => 'grupo',
        'rotulo' => 'A Escola',
        'id' => 'escola',
        'itens' => [
            ['rotulo' => 'O que é o AVA', 'descricao' => 'Como funciona o ambiente virtual', 'rota' => '/o-ava'],
            ['rotulo' => 'O Projeto', 'descricao' => 'A concepção pedagógica da escola', 'rota' => '/o-projeto'],
            ['rotulo' => 'Notícias', 'descricao' => 'Avisos e novidades da rede', 'rota' => '/noticias'],
        ],
    ],

    [
        'tipo' => 'grupo',
        'rotulo' => 'Ajuda',
        'id' => 'ajuda',
        'itens' => [
            ['rotulo' => 'Dúvidas frequentes', 'descricao' => 'Respostas às perguntas mais comuns', 'rota' => '/duvidas'],
            ['rotulo' => 'Orientações ao estudante', 'descricao' => 'Regras de frequência, prazos e certificados', 'rota' => '/orientacoes'],
            ['rotulo' => 'Calendário', 'descricao' => 'Datas de matrícula e das turmas', 'rota' => '/calendario'],
        ],
    ],

];
