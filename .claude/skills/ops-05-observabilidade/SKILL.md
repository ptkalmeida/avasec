---
name: ops-05-observabilidade
description: Observabilidade (grupo operations do Protocolo TI-SECEC). Ativar quando o trabalho estiver nesta fase do ciclo. Subordinada as normas corporativas oficiais: a skill nao e a fonte da regra.
version: "0.1-draft"
activation: phase
group: operations
status: rascunho
fonte: .ai/normas/protocolo-ti-secec/
---

# Observabilidade

## Princípio de governança

Esta skill **não é a fonte oficial da regra**. Ela operacionaliza o Protocolo TI-SECEC, as normas corporativas, decisões arquiteturais aprovadas e regras documentadas do projeto. Em conflito, prevalece a fonte oficial de maior hierarquia.

## Objetivo

Conduzir a IA e o desenvolvedor na execução desta disciplina sem criar processo paralelo ao Protocolo TI-SECEC.

## Regras operacionais

Implementar logs estruturados sem dados sensíveis, métricas e health checks conforme a norma. O endpoint público /sistema/status deve seguir o contrato corporativo quando aplicável. Prever correlação de requisições, alarmes e indicadores definidos oficialmente. Não expor detalhes internos sensíveis em health checks.

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
