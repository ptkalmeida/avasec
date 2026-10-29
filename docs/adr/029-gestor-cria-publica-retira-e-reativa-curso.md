# ADR 029 — O gestor cria, publica, retira e reativa o próprio curso, e troca a capa

- **Data:** 29/10/2026
- **Situação:** aceita — decisão do responsável: o gestor faz isso "na ausência do admin"
- **Afeta:** ADR 12 (nada é apagado), ADR 025 (vários gestores por curso); **muda uma regra de autorização já validada** (quem reativa curso retirado)

## Contexto

O botão "Cadastrar Novo Curso" do painel React ficou de fora quando `/inst` virou Blade (M4, 20/10), com a
nota "entra na fatia seguinte". A fatia nunca o entregou: o gestor ficou sem como criar disciplina. Ao
devolvê-lo, o responsável pediu mais: que o curso nasça **restrito**, que o gestor o **ative ou desative**
para os alunos, que **retire do ar** e **troque a capa** (hoje só o admin fazia), e que veja os cursos
retirados, **à parte**, com o botão de **reativar**.

## Decisões

| O quê | Como ficou |
|---|---|
| Criar curso | `/inst/cursos/novo` e `POST /inst/cursos`, com os campos da criação do admin, sem o de professor. O curso é sempre **em nome de quem criou** (regra do `CourseService`; `instrutor_id` enviado é ignorado). |
| Visibilidade ao nascer | **Restrito** (`Visibilidade::RESTRITO`): só gestor e admin veem. Curso criado pelo admin ou pela API segue **publicado** (padrão do banco). |
| Ativar / desativar para o aluno | `POST /inst/curso/{ref}/visibilidade`: ativar = publicado, desativar = restrito. **Rascunho** continua só do admin e não passa por aqui. A visibilidade do curso, antes, não era gravada em lugar nenhum do código. |
| Retirar do ar | `POST /inst/curso/{ref}/retirar`, em duas etapas e com motivo opcional. **Inativa** (ADR 12): quem, quando e por quê ficam gravados; aulas, avaliações e matrículas ficam. |
| Reativar | `POST /inst/cursos/{id}/reativar`. O curso volta **como estava** (restrito ou publicado); não se publica por baixo dos panos. |
| Capa | `POST /inst/curso/{ref}/capa`: a mesma regra do admin (PNG, JPEG ou WebP, até 4 MB, bytes conferidos pelo `UploadService`), com opção de remover. |
| "Meus cursos" | Dois grupos: **Cursos ativos** e **Cursos inativos**. O inativo mostra o selo, quando saiu, o motivo e "Reativar curso". |

## A regra que mudou

`CourseService::reactivateCourse` dizia "só o administrador devolve um curso ao ar; gestor não vê nem os
retirados". Agora reativa **o admin ou quem gerencia o curso** (responsável em `curso.instrutor_id` ou
gestor em `curso_gestor`), por `GestoresDoCurso::gerenciaIncluindoRetirados`. O teste que protegia a regra
antiga (`test_curso_inativado_nao_aparece`) foi **substituído de propósito**, e não enfraquecido.

## O que NÃO mudou

- O escopo de dados continua sem os retirados: listas, alunos, mensagens e a API usam `cursoIdsDe` e
  `gerencia`, que seguem excluindo curso inativado. Só "Meus cursos" usa a versão que os inclui
  (`PainelDoInstrutor::cursos(..., incluirRetirados: true)`), e **só os do próprio gestor**: retirado de
  outro gestor continua invisível.
- A rota de admin `/admin/cursos/{id}/reativar` segue só do admin. O admin também continua vendo tudo na
  seção "Retirados".
- Nenhuma mudança de schema.

## Pontos a saber

- Ao ativar um curso sem aulas, a tela avisa; não bloqueia.
- O curso retirado **não tem tela própria** enquanto está inativo (404 em `/inst/curso/{ref}`): o caminho é
  reativar pela lista.
- Reativar devolve o curso a "todos os gestores do curso e aos alunos", como no admin (ADR 025).
- Auditoria: criação, ativação/desativação, retirada, reativação e capa gravam `log_seguranca`.

## Testes

`CriarCursoDoInstrutorTest`, `GestorGerenciaOCursoTest` (18 casos: restrito ao nascer, alternar, retirar
sem apagar, reativar como estava, capa, e a posse — gestor de outro curso não ativa, retira, reativa nem
troca capa) e `EscolhaDeCursoDoInstrutorTest` (os dois grupos).
