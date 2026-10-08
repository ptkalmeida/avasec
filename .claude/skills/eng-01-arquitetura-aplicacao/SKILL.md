---
name: eng-01-arquitetura-aplicacao
description: Arquitetura de Aplicação (grupo engineering do Protocolo TI-SECEC). Ativar quando o trabalho estiver nesta fase do ciclo. Subordinada as normas corporativas oficiais: a skill nao e a fonte da regra.
version: "0.1-draft"
activation: phase
group: engineering
status: rascunho
fonte: .ai/normas/protocolo-ti-secec/
---

# Arquitetura de Aplicação

## Princípio de governança

Esta skill **não é a fonte oficial da regra**. Ela operacionaliza o Protocolo TI-SECEC, as normas corporativas, decisões arquiteturais aprovadas e regras documentadas do projeto. Em conflito, prevalece a fonte oficial de maior hierarquia.

## Objetivo

Conduzir a IA e o desenvolvedor na execução desta disciplina sem criar processo paralelo ao Protocolo TI-SECEC.

## Regras operacionais

Aplicar a arquitetura oficial do projeto e da SECEC. Para novos sistemas, considerar MPA como padrão conforme a norma vigente; exceções exigem decisão aprovada. Avaliar responsabilidades, integrações, cache, filas, storage, APIs e impactos. Registrar decisões arquiteturais relevantes em ADR. Não trocar stack ou padrão arquitetural por preferência da IA.

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
