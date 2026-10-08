---
name: ops-03-secrets-configuracao
description: Secrets e Configuração (grupo operations do Protocolo TI-SECEC). Ativar quando a mudanca tiver o risco correspondente, e na revisao final. Subordinada as normas corporativas oficiais: a skill nao e a fonte da regra.
version: "0.1-draft"
activation: risk
group: operations
status: rascunho
fonte: .ai/normas/protocolo-ti-secec/
---

# Secrets e Configuração

## Princípio de governança

Esta skill **não é a fonte oficial da regra**. Ela operacionaliza o Protocolo TI-SECEC, as normas corporativas, decisões arquiteturais aprovadas e regras documentadas do projeto. Em conflito, prevalece a fonte oficial de maior hierarquia.

## Objetivo

Conduzir a IA e o desenvolvedor na execução desta disciplina sem criar processo paralelo ao Protocolo TI-SECEC.

## Regras operacionais

Secrets nunca devem ser hardcoded, commitados, impressos em logs ou incorporados em imagens. Usar a solução corporativa de gestão de secrets definida nos documentos C/F, com separação por ambiente, menor privilégio e rotação. Enquanto a solução oficial estiver pendente, não inventar um padrão permanente: sinalizar a dependência.

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
