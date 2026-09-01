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
    'eventosWebinars' => true,
    'liveClassroom' => false,
    'dossieAcademico' => false,
    'penalidadesCancelamento' => false,
    'mensagensDiretas' => false,
    'matriculasMultiplas' => true,

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
