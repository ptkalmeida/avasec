---
name: eng-06-redis-cache-filas
description: Redis, Cache e Filas (grupo engineering do Protocolo TI-SECEC). Ativar quando a tarefa envolver esta tecnologia. Subordinada as normas corporativas oficiais: a skill nao e a fonte da regra.
version: "0.1-draft"
activation: technology
group: engineering
status: rascunho
fonte: .ai/normas/protocolo-ti-secec/
---

# Redis, Cache e Filas

## Princípio de governança

Esta skill **não é a fonte oficial da regra**. Ela operacionaliza o Protocolo TI-SECEC, as normas corporativas, decisões arquiteturais aprovadas e regras documentadas do projeto. Em conflito, prevalece a fonte oficial de maior hierarquia.

## Objetivo

Conduzir a IA e o desenvolvedor na execução desta disciplina sem criar processo paralelo ao Protocolo TI-SECEC.

## Regras operacionais

Aplicar Redis conforme a arquitetura oficial para cache, filas, locks e usos aprovados. Definir TTL e estratégia de invalidação; evitar cache sem política de expiração/invalidação. Em filas, prever retry, timeout, idempotência quando necessária, failed jobs e observabilidade. Não usar Redis como banco principal sem decisão arquitetural explícita.

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
