---
name: eng-07-apis-integracoes
description: APIs e Integrações (grupo engineering do Protocolo TI-SECEC). Ativar quando a tarefa envolver esta tecnologia. Subordinada as normas corporativas oficiais: a skill nao e a fonte da regra.
version: "0.1-draft"
activation: technology
group: engineering
status: rascunho
fonte: .ai/normas/protocolo-ti-secec/
---

# APIs e Integrações

## Princípio de governança

Esta skill **não é a fonte oficial da regra**. Ela operacionaliza o Protocolo TI-SECEC, as normas corporativas, decisões arquiteturais aprovadas e regras documentadas do projeto. Em conflito, prevalece a fonte oficial de maior hierarquia.

## Objetivo

Conduzir a IA e o desenvolvedor na execução desta disciplina sem criar processo paralelo ao Protocolo TI-SECEC.

## Regras operacionais

Projetar integrações com contrato explícito, autenticação/autorização, validação, versionamento quando necessário, timeout, retry controlado, idempotência para operações críticas, logs sem dados sensíveis e tratamento de indisponibilidade. Datas externas devem seguir o contrato, preferencialmente ISO 8601 com timezone/offset; converter na fronteira para o padrão interno.

## Antes de executar

1. Identificar a fase atual do ciclo e o objetivo da atividade.
2. Consultar as normas/documentos oficiais aplicáveis e o contexto do projeto.
3. Identificar impactos, ambiguidades, riscos e outras skills necessárias.
4. Respeitar checkpoints e aprovações exigidos pelo protocolo.

## Saída esperada

Entregar implementação, análise, evidência ou orientação compatível com a fase atual, com decisões e pendências rastreáveis.

## Bloqueios

- Não inventar regra corporativa ausente.
- Não sobrescrever decisão aprovada por preferência técnica da IA.
- Não avançar silenciosamente diante de conflito, ambiguidade relevante ou gate não atendido.
