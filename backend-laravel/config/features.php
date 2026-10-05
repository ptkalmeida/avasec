<?php

declare(strict_types=1);

// Réplica em PHP das feature flags do frontend/Node (src/config/features.ts).
//
// IMPORTANTE (dívida de migração): enquanto Node e Laravel coexistem, esta lista
// precisa ser mantida em sincronia manual com src/config/features.ts. O gate no
// backend não é cosmético: rota com flag desligada devolve 404 FEATURE_DISABLED,
// não apenas some do menu. Qualquer divergência aqui muda o comportamento visível
// da API — por isso os testes cobrem explicitamente o caso "flag desligada => 404".
return [
    // Desativadas por padrão no MVP
    'forum' => false,
    // Desligada em 14/09/2026 por decisão de produto: a coordenação não vai usar
    // exercícios práticos nesta fase. Nada foi removido — modelo, rotas, tela de
    // entrega do aluno e área de correção do professor continuam no código e nos
    // testes (ADR 12). Voltar ao ar é trocar este false por true, junto com o
    // espelho em src/config/features.ts que o FeatureFlagParityTest confere.
    'atividadesPraticasAvancadas' => false,
    'uploadArquivos' => true,
    'graficosAvancados' => false,
    'acompanhamentoParticipacao' => false,
    'historicoAvancado' => false,
    'internacionalizacao' => false,
    'gamificacao' => false,
    'trilhasAvancadas' => false,
    'solicitacoesAcademicas' => false,
    'bibliotecaDigital' => false,
    'eventosWebinars' => false,
    'liveClassroom' => false,
    'dossieAcademico' => false,
    'penalidadesCancelamento' => false,
    'mensagensDiretas' => true,
    'matriculasMultiplas' => true,
    // Aprovação automática de matrícula (coordenação, 14/09/2026): a solicitação
    // já nasce aprovada e matricula na mesma transação. As travas do caminho
    // normal continuam valendo — automática é "sem espera humana", não "sem
    // regra". Espelho em src/config/features.ts.
    'aprovacaoAutomaticaMatricula' => true,

    // Ativadas por padrão no MVP
    'catalogoCursos' => true,
    'detalhesCurso' => true,
    'matricula' => true,
    'modulosAulas' => true,
    'materiaisComplementares' => true,
    'quizSimples' => true,
    'progresso' => true,
    'certificados' => true,
    'dadosGerenciais' => true,
    'perfilBasico' => true,
    'gestaoConteudoSite' => true,
];
