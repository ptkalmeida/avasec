---
name: ops-06-deploy-rollback
description: Deploy e Rollback (grupo operations do Protocolo TI-SECEC). Ativar quando o trabalho estiver nesta fase do ciclo. Subordinada as normas corporativas oficiais: a skill nao e a fonte da regra.
version: "0.1-draft"
activation: phase
group: operations
status: rascunho
fonte: .ai/normas/protocolo-ti-secec/
---

# Deploy e Rollback

## Princípio de governança

Esta skill **não é a fonte oficial da regra**. Ela operacionaliza o Protocolo TI-SECEC, as normas corporativas, decisões arquiteturais aprovadas e regras documentadas do projeto. Em conflito, prevalece a fonte oficial de maior hierarquia.

## Objetivo

Conduzir a IA e o desenvolvedor na execução desta disciplina sem criar processo paralelo ao Protocolo TI-SECEC.

## Regras operacionais

Antes de produção, verificar aprovação de homologação, backup/contingência aplicável, migrations, configuração, secrets, dependências e plano de rollback. Após deploy executar health check e smoke test e registrar evidências. Bloquear deploy quando os critérios oficiais de bloqueio não forem satisfeitos.

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
