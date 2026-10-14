import React, { useMemo, useState } from 'react';
import { Award, BookOpen, PlayCircle, Search } from 'lucide-react';
import type { Certificate, Course } from '../../types';

/** Um curso do aluno, com as contas já resolvidas por quem chama. */
export interface CursoDoAluno {
  curso: Course;
  /** Aulas concluídas que AINDA EXISTEM no curso (ver utils/courseProgress). */
  concluidas: number;
  /** Aulas que o curso tem hoje. */
  total: number;
  /**
   * Frequência atual e mínima exigida, em porcentagem.
   *
   * NÃO é o mesmo que progresso, e por isso os dois aparecem juntos: progresso
   * conta aulas; frequência conta aulas **e** encontros ao vivo, e é o número
   * que decide a emissão do certificado. O cartão anterior mostrava só este —
   * trocá-lo pelo progresso da referência teria tirado da tela a informação
   * acadêmica em favor da decorativa.
   */
  frequencia?: number;
  minimoFrequencia?: number;
}

interface MeusCursosProps {
  emAndamento: CursoDoAluno[];
  concluidos: CursoDoAluno[];
  /** Certificado ativo de cada curso concluído, quando houver. */
  certificadoPorCurso: Record<string, Certificate | undefined>;
  onAbrirCurso: (courseId: string) => void;
  onVerCertificados: () => void;
}

type Filtro = 'todos' | 'andamento' | 'concluidos';

const percentual = (concluidas: number, total: number): number =>
  total > 0 ? Math.min(100, Math.round((concluidas / total) * 100)) : 0;

/**
 * "Meus cursos" — o que o aluno já tem, separado por situação.
 *
 * POR QUE EXISTE
 *
 * O painel tinha os cursos do aluno espalhados em dois blocos distantes, com
 * desenhos e informações diferentes: o cartão largo do curso ativo no topo e,
 * depois do catálogo inteiro, uma tira de cartõezinhos "Cursos Concluídos
 * (Acesso Vitalício de Revisão)" com título, professor e nada mais — sem capa,
 * sem descrição, sem progresso e **sem nenhuma menção ao certificado**, que é
 * justamente o que se procura num curso terminado.
 *
 * Entre os dois ficava o catálogo de cursos DISPONÍVEIS, misturando o que o
 * aluno já tem com o que ele ainda pode escolher — o oposto do que a entrega
 * pede ("separar cursos ativos, concluídos e disponíveis").
 *
 * Aqui os dois viram uma seção só, com filtro e busca sobre o que é SEU. O
 * catálogo continua abaixo, intocado.
 *
 * O QUE NÃO MUDOU DE LUGAR, DE PROPÓSITO
 *
 * Curso com vigência encerrada continua no cartão de aviso anterior: ele carrega
 * o fluxo de cancelamento de inscrição e um texto que explica como liberar a
 * matrícula. É estado excepcional que pede ação, não item de lista.
 *
 * O QUE FICOU DE FORA, E POR QUÊ
 *
 * A referência traz, em cada cartão, "Última aula: ..." e "Última atividade:
 * <data>". `StudentProgress` tem `completedLessons`, e mais nada: **nenhum
 * instante e nenhuma referência à última aula**. Não dá para derivar — só
 * inventar. Decisão do responsável em 13/10/2026: ficam de fora até existir
 * coluna. Pelo mesmo motivo sai "Concluído em <data>": não há data de conclusão,
 * só a de emissão do certificado, que é outra coisa e seria rótulo errado.
 *
 * Os indicadores soltos da referência ("2 cursos em andamento" / "1 concluído")
 * também não viraram cartões próprios: as contagens já aparecem nos filtros, e
 * o cartão de saudação acima já carrega indicadores — repetir o mesmo número em
 * dois lugares da mesma tela é como eles passam a divergir.
 */
export const MeusCursos: React.FC<MeusCursosProps> = ({
  emAndamento,
  concluidos,
  certificadoPorCurso,
  onAbrirCurso,
  onVerCertificados,
}) => {
  const [filtro, setFiltro] = useState<Filtro>('todos');
  const [busca, setBusca] = useState('');

  const termo = busca.trim().toLowerCase();

  const filtrar = useMemo(
    () => (lista: CursoDoAluno[]) =>
      termo === ''
        ? lista
        : lista.filter(
            ({ curso }) =>
              curso.title.toLowerCase().includes(termo) ||
              (curso.category ?? '').toLowerCase().includes(termo),
          ),
    [termo],
  );

  const andamentoVisivel = filtro === 'concluidos' ? [] : filtrar(emAndamento);
  const concluidosVisivel = filtro === 'andamento' ? [] : filtrar(concluidos);
  const nadaParaMostrar = andamentoVisivel.length === 0 && concluidosVisivel.length === 0;

  // Sem curso nenhum, a seção inteira some: o catálogo abaixo já convida a
  // escolher um, e um bloco vazio com título só ocuparia a dobra.
  if (emAndamento.length === 0 && concluidos.length === 0) {
    return null;
  }

  const abas: { id: Filtro; rotulo: string; n: number }[] = [
    { id: 'todos', rotulo: 'Todos', n: emAndamento.length + concluidos.length },
    { id: 'andamento', rotulo: 'Em andamento', n: emAndamento.length },
    { id: 'concluidos', rotulo: 'Concluídos', n: concluidos.length },
  ];

  return (
    <section className="space-y-5" aria-labelledby="titulo-meus-cursos">
      <div>
        <span className="text-sobretitulo uppercase text-ava-ciano-texto">Minha aprendizagem</span>
        <h2 id="titulo-meus-cursos" className="text-2xl font-black text-slate-950 mt-0.5">
          {abas[0].n === 1 ? 'Meu curso' : 'Meus cursos'}
        </h2>
        <p className="text-rotulo text-escult-ink-2 mt-1">
          Acompanhe seus cursos, retome de onde parou e acesse o que já concluiu.
        </p>
      </div>

      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex flex-wrap gap-2" role="tablist" aria-label="Filtrar meus cursos">
          {abas.map((a) => (
            <button
              key={a.id}
              role="tab"
              aria-selected={filtro === a.id}
              onClick={() => setFiltro(a.id)}
              className={`rounded-xl px-4 py-2 text-rotulo font-semibold transition-colors cursor-pointer ${
                filtro === a.id
                  ? 'bg-ava-acao text-white shadow-xs'
                  : 'bg-ava-faixa text-escult-ink-2 border border-ava-borda hover:text-escult-ink'
              }`}
            >
              {a.rotulo} ({a.n})
            </button>
          ))}
        </div>

        <div className="relative sm:w-72">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-escult-ink-2" />
          <input
            type="search"
            value={busca}
            onChange={(e) => setBusca(e.target.value)}
            aria-label="Buscar entre os meus cursos"
            placeholder="Buscar por nome do curso..."
            className="w-full rounded-xl border border-ava-borda bg-white py-2 pl-9 pr-3 text-rotulo text-slate-800 focus:outline-hidden focus:ring-1 focus:ring-ava-acao"
          />
        </div>
      </div>

      {nadaParaMostrar && (
        <p className="rounded-2xl border border-ava-borda bg-ava-faixa p-5 text-rotulo text-escult-ink-2">
          Nenhum curso seu corresponde a esta busca.
        </p>
      )}

      {andamentoVisivel.length > 0 && (
        <div className="space-y-3">
          <div>
            <h3 className="text-lg font-black text-slate-950">Em andamento</h3>
            <p className="text-apoio text-escult-ink-2">
              Cursos que você já iniciou e pode continuar a qualquer momento.
            </p>
          </div>

          {andamentoVisivel.map(({ curso, concluidas, total, frequencia, minimoFrequencia }) => {
            const pct = percentual(concluidas, total);
            const temFrequencia = frequencia !== undefined && minimoFrequencia !== undefined;
            const frequenciaOk = temFrequencia && frequencia >= minimoFrequencia;

            return (
              <article
                key={curso.id}
                className="rounded-2xl border border-ava-borda bg-white p-4 shadow-3xs flex flex-col gap-4 lg:flex-row lg:items-center"
              >
                <Capa curso={curso} />

                <div className="min-w-0 flex-1 space-y-1.5">
                  <Etiqueta texto={curso.category} />
                  <h4 className="font-extrabold text-slate-950 leading-snug">{curso.title}</h4>
                  <p className="text-apoio text-escult-ink-2 line-clamp-2">{curso.description}</p>

                  <div className="flex flex-wrap items-center gap-x-4 gap-y-1 pt-0.5">
                    <span className="text-apoio text-escult-ink-2">
                      Prof. <strong className="font-bold text-slate-800">{curso.instructorName}</strong>
                    </span>
                    {temFrequencia && (
                      <span className="text-apoio text-escult-ink-2 flex items-center gap-1.5">
                        <span>Frequência atual:</span>
                        <strong
                          className={`font-black ${frequenciaOk ? 'text-emerald-600' : 'text-amber-600'}`}
                        >
                          {frequencia}%
                        </strong>
                        <span>/ mínimo {minimoFrequencia}%</span>
                      </span>
                    )}
                  </div>
                </div>

                <div className="shrink-0 space-y-2 lg:w-60 lg:border-l lg:border-ava-borda lg:pl-4">
                  <div className="flex items-center justify-between gap-2">
                    <span className="text-sobretitulo uppercase text-escult-ink-2">
                      Seu progresso
                    </span>
                    <strong className="text-rotulo font-black text-ava-acao">{pct}%</strong>
                  </div>
                  <div className="h-1.5 w-full overflow-hidden rounded-full border border-ava-borda bg-ava-faixa">
                    <div className="h-full bg-ava-acao" style={{ width: `${pct}%` }} />
                  </div>
                  {/* O número e o texto acompanham a barra: progresso que só
                      existe como cor não é legível por quem não a distingue
                      (Documento D §2.13). */}
                  <span className="block text-apoio text-escult-ink-2">
                    {concluidas} de {total} aulas concluídas
                  </span>

                  <button
                    onClick={() => onAbrirCurso(curso.id)}
                    className="mt-1 flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-xl bg-ava-acao px-4 py-2 text-xs font-bold text-white shadow-xs transition-colors hover:bg-ava-acao-escuro"
                  >
                    <PlayCircle className="h-4 w-4" />
                    <span>Continuar curso</span>
                  </button>
                </div>
              </article>
            );
          })}
        </div>
      )}

      {concluidosVisivel.length > 0 && (
        <div className="space-y-3">
          <div>
            <h3 className="text-lg font-black text-slate-950">Concluídos</h3>
            <p className="text-apoio text-escult-ink-2">
              Cursos que você já finalizou. O conteúdo continua acessível.
            </p>
          </div>

          {concluidosVisivel.map(({ curso }) => {
            const certificado = certificadoPorCurso[curso.id];

            return (
              <article
                key={curso.id}
                className="rounded-2xl border border-ava-borda bg-white p-4 shadow-3xs flex flex-col gap-4 lg:flex-row lg:items-center"
              >
                <Capa curso={curso} />

                <div className="min-w-0 flex-1 space-y-1.5">
                  <Etiqueta texto={curso.category} />
                  <h4 className="font-extrabold text-slate-950 leading-snug">{curso.title}</h4>
                  <p className="text-apoio text-escult-ink-2 line-clamp-2">{curso.description}</p>
                </div>

                <div className="shrink-0 space-y-2 lg:w-60 lg:border-l lg:border-ava-borda lg:pl-4">
                  <div className="flex items-start gap-2">
                    <Award className="h-4 w-4 shrink-0 text-ava-ciano-texto mt-0.5" />
                    <div className="min-w-0">
                      {certificado ? (
                        <>
                          <span className="block text-sobretitulo uppercase text-escult-ink-2">
                            Certificado emitido
                          </span>
                          {/*
                            `issueDate` chega do Laravel já como d/m/Y (cast
                            `date:d/m/Y` no model). NÃO passa por `new Date()`:
                            data pura não tem instante, e converter fuso faria
                            o certificado recuar um dia (ver Support/Fuso.php).
                          */}
                          <span className="block text-rotulo font-bold text-slate-900">
                            {certificado.issueDate}
                          </span>
                        </>
                      ) : (
                        <span className="block text-apoio text-escult-ink-2">
                          Sem certificado emitido para este curso.
                        </span>
                      )}
                    </div>
                  </div>

                  <button
                    onClick={certificado ? onVerCertificados : () => onAbrirCurso(curso.id)}
                    className="flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-xl border border-ava-borda bg-ava-faixa px-4 py-2 text-xs font-bold text-ava-ciano-texto transition-colors hover:bg-white"
                  >
                    {certificado ? (
                      <>
                        <Award className="h-4 w-4" />
                        <span>Acessar certificado</span>
                      </>
                    ) : (
                      <>
                        <BookOpen className="h-4 w-4" />
                        <span>Rever o conteúdo</span>
                      </>
                    )}
                  </button>
                </div>
              </article>
            );
          })}
        </div>
      )}
    </section>
  );
};

/** Miniatura do curso. Curso sem imagem não deixa buraco: o bloco some. */
const Capa: React.FC<{ curso: Course }> = ({ curso }) => {
  const src = curso.thumbnail || curso.coverImage;
  if (!src) return null;

  return (
    <img
      src={src}
      alt=""
      aria-hidden="true"
      loading="lazy"
      className="h-32 w-full shrink-0 rounded-xl border border-ava-borda object-cover lg:h-24 lg:w-40"
    />
  );
};

/** Etiqueta de categoria. Curso sem categoria simplesmente não mostra nada. */
const Etiqueta: React.FC<{ texto?: string }> = ({ texto }) => {
  if (!texto) return null;

  return (
    <span className="inline-block rounded bg-ava-icone-fundo px-2 py-0.5 text-sobretitulo uppercase text-ava-ciano-texto">
      {texto}
    </span>
  );
};
