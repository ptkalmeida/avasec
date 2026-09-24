import { describe, it, expect } from 'vitest';
import { gestoresSemListagem } from '../../src/context/LMSContext';

/*
 * Um ALUNO apareceu na aba "Gestão" da tela de login. Para um gestor, a rota
 * `/auth/users?role=instructor` devolve os alunos dele, e a resposta era gravada
 * como lista de gestores. Quem não é admin não lista gestores: usa esta regra.
 */
describe('gestoresSemListagem', () => {
  it('gestor logado vê só a si mesmo — nunca os próprios alunos', () => {
    expect(gestoresSemListagem({ id: 'g-1', name: 'Gestor de Conteúdos', role: 'instructor' }))
      .toEqual([{ id: 'g-1', name: 'Gestor de Conteúdos' }]);
  });

  it('aluno logado não recebe a si mesmo como gestor', () => {
    const lista = gestoresSemListagem({ id: 'a-1', name: 'João Silva', role: 'student' });

    expect(lista.map((p) => p.name)).not.toContain('João Silva');
    expect(lista.map((p) => p.id)).not.toContain('a-1');
  });

  it('sem sessão, a lista padrão', () => {
    expect(gestoresSemListagem(null).map((p) => p.name)).toEqual(['Gestor de Conteúdos']);
    expect(gestoresSemListagem(undefined).map((p) => p.name)).toEqual(['Gestor de Conteúdos']);
  });
});
