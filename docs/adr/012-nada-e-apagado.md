# ADR 12 — Nada é apagado: inativação em vez de exclusão

- **Status:** aceita
- **Data:** 03/09/2026
- **Decidida por:** coordenação do AVASEC (requisito de auditoria)
- **Substitui:** nada. Complementa a [ADR 10](010-identidade-por-userid.md) (identidade por `userId`).

## Contexto

O AVASEC é o LMS de uma escola pública estadual. Nota, frequência, entrega
corrigida, matrícula e certificado são **registro acadêmico**: existe obrigação
de poder reconstruir, depois, o que aconteceu e quando. Até esta decisão, o
sistema apagava fisicamente.

O levantamento feito em 03/09/2026 encontrou **14 pontos de exclusão física** no
backend e, pior, **chaves estrangeiras em `ON DELETE CASCADE`**. O efeito medido:
apagar **um** usuário destruía em cascata

| Tabela | Regra | O que se perdia |
|---|---|---|
| `QuizSubmission` | CASCADE | todas as notas de avaliação |
| `ExerciseSubmission` | CASCADE | todos os trabalhos entregues e corrigidos |
| `StudentProgress` | CASCADE | progresso e presenças |
| `StudentEnrollment` | CASCADE | a matrícula |
| `AcademicRequest`, `AdmissionRequest` | CASCADE | requerimentos e a inscrição |
| `DirectMessage` | CASCADE | o histórico de atendimento |
| `Certificate.userId` | SET NULL | o certificado **sobrevivia apontando para ninguém** |

Ou seja: um clique em "excluir aluno" apagava o histórico escolar da pessoa e
deixava um certificado válido, verificável e sem titular. Havia ainda
`DELETE /api/audit`, que apagava a trilha de auditoria inteira.

## Decisão

**Nenhum registro de pessoa, disciplina, conteúdo ou avaliação é apagado do
banco. Nunca.** Conteúdo que sai do ar é inativado; o registro permanece
íntegro, com o momento em que saiu.

Dois eixos, deliberadamente separados — *existir* e *quem pode ver* são
perguntas diferentes, e um curso pode estar no ar visível só para a gestão:

```
inativadoEm   NULL = no ar  |  data/hora = fora do ar (registro intacto)
status        1 = aluno vê (e gestor e admin)
              2 = gestor e admin
              3 = só admin
```

`status` é uma **escada**: o número é o papel mínimo para ver, e quem vê o nível
1 vê tudo acima. O valor `0` para "excluído" foi descartado de propósito: com ele
o número passaria a significar duas coisas (audiência **e** existência), e
haveria duas fontes para o mesmo fato — questão de tempo até existir linha com
`inativadoEm` nulo e `status = 0`, sem nenhuma consulta sabendo qual obedecer.

O mecanismo é o `SoftDeletes` do Eloquent com a coluna renomeada:

```php
final class Course extends Model
{
    use SoftDeletes;

    const DELETED_AT = 'inativadoEm';
}
```

A escolha do `SoftDeletes` sobre uma coluna `ativo` conferida à mão é o ponto
central desta ADR: **o Eloquent exclui o inativo de toda consulta
automaticamente**. Numa coluna manual, a proteção depende de cada uma das
dezenas de consultas de listagem lembrar do filtro, e uma esquecida exibe no
site conteúdo que a escola tirou do ar. A palavra "delete" fica confinada ao
framework; o código de domínio usa `inativar()`, `reativar()` e `estaInativo()`.

## Consequências

**Obrigatórias, e valem para qualquer alteração futura:**

1. `->delete()`, `->forceDelete()`, `->truncate()` e `DELETE FROM` são
   **proibidos** sobre tabela de domínio. Revisão de PR deve recusar.
2. Rota `DELETE` existente **inativa**, não apaga. O nome do verbo HTTP fica,
   por compatibilidade com o frontend; o efeito é inativação.
3. Remoção de aula, questão ou encontro pela edição do curso (o "sync" que
   apagava o que o cliente não reenviou) **inativa** o que saiu.
4. Nova tentativa de avaliação **não substitui** a anterior: toda tentativa fica
   registrada, e a vigente é a mais recente.
5. `SecurityLog` não tem expurgo. A trilha de auditoria não se apaga a si mesma.
6. Certificado não é apagado: perder validade é **revogação registrada**, com
   quem revogou e por quê.
7. Toda listagem pública ou de aluno respeita `status` e `inativadoEm`.

**Aceitas como custo:**

- A base cresce monotonicamente. Para o volume de uma escola estadual, é
  irrelevante frente ao risco de perder histórico.
- Unicidade (e-mail, CPF) precisa considerar o inativo: reaproveitar o CPF de uma
  pessoa inativada exige decisão explícita, não colisão silenciosa.
- Purga por exigência legal (LGPD, direito ao esquecimento) passa a ser
  procedimento administrativo documentado, com autorização humana registrada —
  não uma consequência de clicar num botão da tela.

## Alternativas descartadas

- **Coluna `ativo` (0/1) conferida à mão.** Mais legível no banco, mas a
  proteção contra vazamento passa a depender de dezenas de consultas lembrarem
  do filtro. Descartada por isso.
- **`status = 0` para excluído, num campo só.** Mais simples de ler, mas mistura
  audiência com existência e cria duas fontes para o mesmo fato.
- **Apagar e confiar em backup.** Backup restaura a base, não responde "o que
  este aluno tinha em 12/08". Não serve a auditoria.
