<?php

declare(strict_types=1);

// Réplica de src/config/constants.ts — FONTE ÚNICA das regras numéricas de negócio.
// Mantido em sincronia manual com o Node durante a migração.
return [
    // Percentual mínimo de frequência para certificado quando o curso não define o seu.
    'default_min_attendance' => 70,
    // Percentual mínimo de acertos para um quiz ser considerado aprovado (passed).
    'quiz_pass_threshold' => 70,
    // Dias após a matrícula em que o cancelamento passa a gerar restrição de rematrícula.
    'dropout_penalty_free_days' => 5,
    // Duração (em dias) da restrição de nova matrícula após cancelamento tardio.
    'dropout_penalty_days' => 30,
];
