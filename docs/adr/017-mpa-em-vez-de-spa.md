# ADR 017 — O AVASEC passa a ser MPA (Blade), e não SPA (React)

- **Data:** 08/10/2026
- **Situação:** aceita — decisão do responsável pelo projeto
- **Substitui:** nada. **Afeta:** ADR 015 (escada tipográfica e papéis de cor),
  planos 12, 13 e 14 (redesenho), plano 11 (item F10)

## Contexto

A Norma Técnica do Protocolo TI-SECEC (documento C, §C.1.1 e §C.1.3) define como
stack padrão **PHP/Laravel com arquitetura MPA**, renderização em **Blade**,
componentes dinâmicos em **Livewire** e comportamento de navegador em **Alpine.js**.
O texto é explícito: *"a utilização de SPA ou arquitetura frontend diferente será
considerada exceção arquitetural e deverá possuir justificativa técnica
registrada"*.

O AVASEC foi construído como **SPA React + Vite** consumindo uma API Laravel —
33.423 linhas em 78 arquivos, com 716 testes de frontend. Ou seja: o sistema está
fora do padrão institucional, e até hoje **sem o registro que tornaria isso
regular**, já que não havia ADR de exceção.

Havia dois caminhos, e o §C.18 admite os dois:

1. **Pedir exceção arquitetural.** Registrar a necessidade, a justificativa, os
   impactos e obter aprovação da Coordenação de Desenvolvimento e da chefia da TI.
   Custo imediato baixo; mantém o sistema divergente do padrão institucional.
2. **Migrar para MPA.** Custo alto — reescreve a camada de apresentação inteira —
   mas elimina a divergência.

## Decisão

**Migrar para MPA.** O responsável pelo projeto recusou o pedido de exceção em
08/10/2026, com a determinação de que o sistema seja MPA de verdade.

A migração é por **substituição gradual**: o Laravel passa a servir uma tela em
Blade, e o React continua servindo as demais, até não restar nenhuma. O plano
operacional está em `.ai/planejamento/16-migracao-mpa-blade.md`.

## Consequências

### Aceitas

- **A camada React é descartada ao final**, e com ela os 716 testes de Vitest, que
  precisam ser substituídos por testes de rota (PHPUnit) e de navegador (Dusk)
  **antes** de serem removidos, nunca depois.
- **As fases 12, 13 e 14 serão refeitas.** O redesenho do portal e da área logada na
  paleta azul foi trabalho recente e extenso. Ele **sobrevive como especificação
  visual** das telas Blade — não se perde o desenho, perde-se o código que o
  implementa.
- **A autenticação muda de mecanismo.** Hoje é JWT em cookie HttpOnly, escolha que
  só existia porque um SPA não tem sessão de servidor. No MPA passa a ser sessão do
  Laravel — e com isso **§C.4.2 passa a se aplicar**: sessão em Redis em HML e PRD,
  que o plano 11 havia corretamente dispensado enquanto o sistema era SPA.
- **O prazo é longo.** São sete fases sobre a maior parte do produto.

### Ganhas

- **Não é mais necessária exceção arquitetural** para o frontend (§C.1.3) nem para
  o **CSRF** (§C.13): o MPA usa o token do Laravel, que é o mecanismo nativo que a
  norma pede. Dois desvios desaparecem em vez de precisarem de aprovação.
- **A migração dos 131 ícones Lucide → FontAwesome deixa de existir.** Não se migra
  ícone: as telas Blade nascem com FontAwesome, como manda o documento D §1.9. Era
  o item mais caro e mais arriscado do plano 15.
- **O painel do admin nasce conforme a Seção 1 do documento D**, que é vinculante,
  em vez de ser repintado em React para depois ser descartado.

### Não afetadas

O **backend permanece como está**: models, services, migrations, regras de negócio
e os 335 testes de PHPUnit. A decisão é sobre a camada de apresentação. Nenhuma
regra de negócio é reescrita durante a migração — ela já vive no lado do servidor.

## Decisões de escopo tomadas junto (08/10/2026)

- **Painel do instrutor segue a Seção 2** do documento D, igual à área do aluno:
  *"o gestor precisa ver como o aluno vê a plataforma"*. Só o painel do admin é
  back-office para efeito da Seção 1.
- **A paleta azul do aluno e do instrutor está conforme** e é mantida: o documento D
  §2.2 classifica o AVA como "área pública autenticada" e §2.7 permite paleta
  própria, desde que haja contraste e cores semânticas.

## Verificação

Cada tela migrada é provada por captura antes/depois e por conferência de que a
informação exibida é a mesma, com o mesmo harness usado na varredura de 08/10. As
URLs das telas **não mudam** — os endereços definidos no plano 13 são contrato com
quem tem link salvo. O número total de testes não deve cair ao fim de nenhuma fase.
