---
name: eng-05-sql-specialist
description: SQL Specialist (grupo engineering do Protocolo TI-SECEC). Ativar quando a tarefa envolver esta tecnologia. Subordinada as normas corporativas oficiais: a skill nao e a fonte da regra.
version: "0.1-draft"
activation: technology
group: engineering
status: rascunho
fonte: .ai/normas/protocolo-ti-secec/
---

# SQL Specialist

## Princípio de governança

Esta skill **não é a fonte oficial da regra**. Ela operacionaliza o Protocolo TI-SECEC, as normas corporativas, decisões arquiteturais aprovadas e regras documentadas do projeto. Em conflito, prevalece a fonte oficial de maior hierarquia.

## Objetivo

Conduzir a IA e o desenvolvedor na execução desta disciplina sem criar processo paralelo ao Protocolo TI-SECEC.

## Regras operacionais

Projetar e revisar SQL para MySQL/MariaDB conforme a stack oficial. Analisar joins, agregações, CTEs, índices e planos de execução. Usar EXPLAIN para consultas potencialmente pesadas. Evitar SELECT * quando os campos necessários forem conhecidos. Não criar índices indiscriminadamente; justificar índices relevantes pela consulta, seletividade e cardinalidade. Não substituir as regras corporativas de modelagem da skill Banco de Dados SECEC.

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
