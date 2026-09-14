import { describe, it, expect } from 'vitest';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';

/*
 * A flag `atividadesPraticasAvancadas` guarda o ALUNO pela flag, não por
 * "a lista está vazia".
 *
 * Por que este teste existe, e por que ele lê o código-fonte:
 *
 * `practicalExercises` e `exerciseSubmissions` NÃO ficam vazios quando a flag
 * está desligada. Eles nascem do `localStorage` ou de uma lista embutida em
 * `LMSContext` (`exercise-1`, "Análise de Heurísticas de Usabilidade"). O
 * `fetch` de `/api/exercises` é pulado, mas o estado inicial permanece.
 *
 * Enquanto o admin e o professor escondiam a área pela flag, o aluno era
 * escondido só por `exerciciosDoCursoAberto.length > 0` — que continuava maior
 * que zero por causa desses defaults. O resultado media-se na tela: aluno
 * vendo exercício para entregar num endpoint que responde 404, sem ninguém do
 * outro lado para corrigir.
 *
 * O teste é de FONTE porque o defeito é a ausência de uma condição no
 * componente. Um teste de render passaria com a flag no valor de hoje e não
 * diria nada sobre o dia em que ela for religada — e é exatamente aí que a
 * armadilha volta.
 */

const PAINEL = join(__dirname, '..', '..', 'src', 'components', 'StudentDashboard.tsx');

describe('o aluno é guardado pela flag, não pela lista vazia', () => {
  const fonte = readFileSync(PAINEL, 'utf-8');

  it('a lista de exercícios do curso consulta a flag', () => {
    const trecho = fonte.slice(
      fonte.indexOf('const exerciciosDoCursoAberto'),
      fonte.indexOf('const exerciciosDoCursoAberto') + 400
    );

    expect(trecho).toContain('features.atividadesPraticasAvancadas');
  });

  it('a rota /aluno/curso/<slug>/exercicios também consulta a flag', () => {
    /*
     * O botão não é a única porta: o endereço é digitável e sobrevive em
     * favorito. Sem esta condição, quem tivesse o link salvo abriria a página
     * inteira de entrega com o recurso desligado.
     */
    const trecho = fonte.slice(
      fonte.indexOf('const showExercicios'),
      fonte.indexOf('const showExercicios') + 300
    );

    expect(trecho).toContain('features.atividadesPraticasAvancadas');
  });
});

describe('as duas pontas da flag dizem a mesma coisa', () => {
  it('o front e o back declaram o mesmo valor', () => {
    /*
     * O `FeatureFlagParityTest` do backend já confere a paridade, mas ele roda
     * na outra suíte: quem mexe só no front não o executa. Aqui a divergência
     * aparece no `npm test`.
     */
    const front = readFileSync(join(__dirname, '..', '..', 'src', 'config', 'features.ts'), 'utf-8');
    const back = readFileSync(
      join(__dirname, '..', '..', 'backend-laravel', 'config', 'features.php'),
      'utf-8'
    );

    const noFront = /atividadesPraticasAvancadas:\s*(true|false)/.exec(front)?.[1];
    const noBack = /'atividadesPraticasAvancadas'\s*=>\s*(true|false)/.exec(back)?.[1];

    expect(noFront).toBeDefined();
    expect(noBack).toBeDefined();
    expect(noFront).toBe(noBack);
  });
});
