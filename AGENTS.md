# AGENTS.md — instruções obrigatórias para qualquer agente de IA neste repositório

> Arquivo lido nativamente por Codex, Cursor, Gemini CLI, GitHub Copilot, Windsurf, Aider, Zed e outros.
> Claude Code lê `CLAUDE.md`, que importa este arquivo com `@AGENTS.md`.
> Este é o único lugar onde a regra é escrita. Não duplique conteúdo em outro arquivo de instrução — edite aqui.

## Regra inegociável

Nenhum código é gerado sem passar, nesta ordem, por:

```
1. PLANEJAR   → ler/preencher .ai/planejamento/ (mínimo: 01-visao-geral.md)
2. SKILLS     → carregar .ai/skills/obrigatorias/ (sempre) + .ai/skills/especificas/ (se aplicável)
3. GERAR      → implementar só o que está coberto pelo plano acima
4. TESTAR     → rodar/gerar testes; nunca remover ou enfraquecer teste para "passar"
5. VERIFICAR  → checklist de .ai/guidelines/03-checklist-conformidade.md
6. PRODUÇÃO   → merge só depois de checkpoint humano (ver abaixo)
```

Se o pedido do usuário não tiver planejamento prévio (nem um parágrafo em `01-visao-geral.md`), **pare e peça** para o humano preencher — mesmo que mínimo — antes de gerar qualquer linha de código. Isso vale mesmo para pedidos que pareçam pequenos ou urgentes.

## Antes de escrever qualquer código, declare

1. Seu entendimento da tarefa.
2. Quais arquivos pretende criar ou alterar.
3. Um plano de implementação.
4. Ambiguidades ou riscos identificados.
5. Testes necessários.

Não implemente até esse plano ser confirmado pelo humano. Isto é o `.ai/skills/obrigatorias/05-protocolo-operacao-ia.md` — leia-o antes de qualquer tarefa não trivial.

## Nunca mude sem sinalizar e obter validação humana explícita

- Escopo definido em `.ai/planejamento/01-visao-geral.md`
- Regra de negócio já validada
- Modelo de dados / schema do banco
- Fluxo de autenticação/autorização
- Decisão técnica já registrada (stack, arquitetura, ADR)
- Contrato de API já em uso por outro consumidor

Ver `.ai/skills/obrigatorias/06-controle-de-mudancas.md`.

## Skills — o que carregar e quando

- **Sempre:** todo o conteúdo de `.ai/skills/obrigatorias/`.
- **Conforme a tarefa:** as skills específicas relevantes em `.ai/skills/especificas/`.
- **Sob demanda:** o restante de `.ai/planejamento/`, só quando a tarefa exigir revisitar arquitetura ou fases.

Não carregue o catálogo inteiro em todo prompt — contexto em excesso dilui a atenção às regras que importam para a tarefa atual. Ver `.ai/skills/INDEX.md` para o índice completo e origem de cada skill.

## Fallback: tecnologia sem skill própria

Ver `.ai/guidelines/01-fallback-sem-skill.md`: consultar documentação oficial via MCP do Context7 (não confiar só na memória de treinamento) e aplicar a técnica "grill-me-with-docs" antes de implementar.

## Checkpoints humanos obrigatórios (nenhum agente avança sozinho)

1. Aprovação da arquitetura técnica antes de iniciar geração de código.
2. Aprovação do plano de fases antes de iniciar uma nova fase.
3. Revisão de código (PR) antes de merge — mesmo 100% gerado por IA.
4. Qualquer decisão envolvendo dados de produção, credenciais reais ou sistemas externos.
5. Qualquer mudança listada na seção acima.

**Este arquivo é a garantia do fluxo — não há bloqueio técnico automático.** Nenhum hook trava commit ou escrita de arquivo; a conformidade depende de o agente seguir este protocolo e de revisão humana no PR (`.ai/guidelines/03-checklist-conformidade.md`) antes do merge. Se um agente pular uma etapa, isso deve aparecer na revisão de código, não é pego automaticamente antes disso.

## Comandos do projeto

_(preencher: build, test, lint — ex. `npm test`, `npm run lint`, `composer test`)_

## Rastreabilidade

Todo PR referencia, na descrição, qual arquivo de planejamento e quais skills foram usados como base.
