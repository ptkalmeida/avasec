---
name: sec-02-secure-code-guardian
description: Secure Code Guardian (grupo security do Protocolo TI-SECEC). Ativar quando a mudanca tiver o risco correspondente, e na revisao final. Subordinada as normas corporativas oficiais: a skill nao e a fonte da regra.
version: "0.1-draft"
activation: risk
group: security
status: rascunho
fonte: .ai/normas/protocolo-ti-secec/
---

# Secure Code Guardian

## Princípio de governança

Esta skill **não é a fonte oficial da regra**. Ela operacionaliza o Protocolo TI-SECEC, as normas corporativas, decisões arquiteturais aprovadas e regras documentadas do projeto. Em conflito, prevalece a fonte oficial de maior hierarquia.

## Objetivo

Conduzir a IA e o desenvolvedor na execução desta disciplina sem criar processo paralelo ao Protocolo TI-SECEC.

## Regras operacionais

Atuar durante a implementação para identificar e evitar vulnerabilidades: validação, autorização, injection, XSS, CSRF, SSRF quando aplicável, uploads, path traversal, mass assignment, secrets, sessão, criptografia e dependências. Verificar cada fronteira de confiança. A política oficial de segurança prevalece sobre esta skill.

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
