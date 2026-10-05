# -*- coding: utf-8 -*-
"""
Gera o relatorio de auditoria de seguranca em PDF.

Uso (a partir da raiz do projeto):
    docs/security-audit/.venv/Scripts/python.exe docs/security-audit/gerar_relatorio.py

O ambiente isolado e criado com:
    python -m venv docs/security-audit/.venv
    docs/security-audit/.venv/Scripts/python.exe -m pip install reportlab matplotlib

Os dados vivem em achados.py — este arquivo cuida apenas da apresentacao.
"""

import os
import sys
from collections import Counter, OrderedDict

import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_JUSTIFY
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import cm
from reportlab.platypus import (
    BaseDocTemplate, Frame, PageTemplate, Paragraph, Spacer, Table, TableStyle,
    Image, KeepTogether, PageBreak,
)

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import achados as A  # noqa: E402

AQUI = os.path.dirname(os.path.abspath(__file__))
SAIDA = os.path.join(AQUI, 'relatorio-auditoria-seguranca.pdf')
TEMP = os.path.join(AQUI, '.graficos')

# Paleta pedida no escopo da auditoria.
COR = {
    'critica': '#B91C1C',
    'alta': '#EA580C',
    'media': '#D97706',
    'baixa': '#2563EB',
    'info': '#6B7280',
    'forte': '#059669',
}
ROTULO = {
    'critica': 'CRÍTICA', 'alta': 'ALTA', 'media': 'MÉDIA',
    'baixa': 'BAIXA', 'info': 'INFORMATIVA',
}
ORDEM = ['critica', 'alta', 'media', 'baixa', 'info']

TINTA = colors.HexColor('#0F172A')
TINTA_SUAVE = colors.HexColor('#475569')
LINHA = colors.HexColor('#CBD5E1')
FUNDO_CODIGO = colors.HexColor('#F1F5F9')
MARCA = colors.HexColor('#540D6E')

# --------------------------------------------------------------------------- estilos
ss = getSampleStyleSheet()


def estilo(nome, **kw):
    base = dict(fontName='Helvetica', fontSize=9.5, leading=13.5, textColor=TINTA)
    base.update(kw)
    return ParagraphStyle(nome, **base)


E = {
    'titulo_capa': estilo('tc', fontName='Helvetica-Bold', fontSize=25, leading=30,
                          textColor=MARCA, alignment=TA_CENTER),
    'sub_capa': estilo('sc', fontSize=12.5, leading=18, textColor=TINTA_SUAVE, alignment=TA_CENTER),
    'h1': estilo('h1', fontName='Helvetica-Bold', fontSize=16, leading=20, textColor=MARCA,
                 spaceBefore=6, spaceAfter=10),
    'h2': estilo('h2', fontName='Helvetica-Bold', fontSize=12, leading=16, textColor=TINTA,
                 spaceBefore=12, spaceAfter=6),
    'h3': estilo('h3', fontName='Helvetica-Bold', fontSize=10, leading=14, textColor=TINTA),
    'p': estilo('p', alignment=TA_JUSTIFY, spaceAfter=5),
    'pequeno': estilo('pq', fontSize=8.5, leading=12, textColor=TINTA_SUAVE),
    'codigo': estilo('cod', fontName='Courier', fontSize=7.8, leading=10.5,
                     textColor=colors.HexColor('#1E293B')),
    'celula': estilo('cel', fontSize=8.5, leading=11.5),
    'celula_mono': estilo('celm', fontName='Courier', fontSize=7.6, leading=10.5),
    'chip': estilo('chip', fontName='Helvetica-Bold', fontSize=7.5, leading=9.5,
                   textColor=colors.white, alignment=TA_CENTER),
    'issue': estilo('iss', fontName='Courier', fontSize=7.4, leading=10),
}


def esc(txt):
    """Escapa o texto para os mini-tags do reportlab."""
    return (str(txt).replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;'))


def chip(sev):
    return Table(
        [[Paragraph(ROTULO[sev], E['chip'])]],
        colWidths=[2.05 * cm], rowHeights=[0.48 * cm],
        style=TableStyle([
            ('BACKGROUND', (0, 0), (-1, -1), colors.HexColor(COR[sev])),
            ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
            ('LEFTPADDING', (0, 0), (-1, -1), 1), ('RIGHTPADDING', (0, 0), (-1, -1), 1),
            ('TOPPADDING', (0, 0), (-1, -1), 2), ('BOTTOMPADDING', (0, 0), (-1, -1), 2),
            ('ROUNDEDCORNERS', [3, 3, 3, 3]),
        ]))


def bloco_codigo(texto, largura):
    def linha(l):
        # Paragraph colapsa espaco a esquerda: preserva a indentacao do trecho.
        recuo = len(l) - len(l.lstrip(' '))
        return Paragraph('&nbsp;' * recuo + (esc(l.strip()) or '&nbsp;'), E['codigo'])

    linhas = [linha(l) for l in texto.split('\n')]
    return Table([[l] for l in linhas], colWidths=[largura], style=TableStyle([
        ('BACKGROUND', (0, 0), (-1, -1), FUNDO_CODIGO),
        ('BOX', (0, 0), (-1, -1), 0.5, LINHA),
        ('LEFTPADDING', (0, 0), (-1, -1), 7), ('RIGHTPADDING', (0, 0), (-1, -1), 7),
        ('TOPPADDING', (0, 0), (-1, -1), 1.5), ('BOTTOMPADDING', (0, 0), (-1, -1), 1.5),
    ]))


# --------------------------------------------------------------------------- graficos
def graficos():
    os.makedirs(TEMP, exist_ok=True)
    por_sev = Counter(a['sev'] for a in A.ACHADOS)

    # Rosca por severidade
    sevs = [s for s in ORDEM if por_sev.get(s)]
    valores = [por_sev[s] for s in sevs]
    fig, ax = plt.subplots(figsize=(4.1, 3.1), dpi=200)
    cunhas, _, textos = ax.pie(
        valores, colors=[COR[s] for s in sevs], startangle=90, counterclock=False,
        wedgeprops=dict(width=0.42, edgecolor='white', linewidth=2),
        autopct=lambda p: f'{round(p * sum(valores) / 100)}',
        pctdistance=0.79, textprops=dict(color='white', fontsize=10, fontweight='bold'))
    ax.legend(cunhas, [f'{ROTULO[s].title()} ({por_sev[s]})' for s in sevs],
              loc='center left', bbox_to_anchor=(1.0, 0.5), frameon=False, fontsize=8.5)
    ax.set_title('Achados por severidade', fontsize=10.5, fontweight='bold', color='#0F172A', pad=10)
    ax.axis('equal')
    fig.tight_layout()
    p1 = os.path.join(TEMP, 'rosca.png')
    fig.savefig(p1, transparent=True, bbox_inches='tight')
    plt.close(fig)

    # Barras por categoria, empilhadas por severidade
    cats = list(OrderedDict.fromkeys(a['cat'] for a in A.ACHADOS))
    fig, ax = plt.subplots(figsize=(6.6, 3.0), dpi=200)
    base = [0] * len(cats)
    for s in ORDEM:
        vals = [sum(1 for a in A.ACHADOS if a['cat'] == c and a['sev'] == s) for c in cats]
        if not any(vals):
            continue
        ax.barh(range(len(cats)), vals, left=base, color=COR[s], label=ROTULO[s].title(), height=0.55)
        base = [b + v for b, v in zip(base, vals)]
    ax.set_yticks(range(len(cats)))
    ax.set_yticklabels([c if len(c) < 34 else c[:32] + '…' for c in cats], fontsize=8.5)
    ax.invert_yaxis()
    ax.set_xlabel('Achados', fontsize=8.5)
    ax.xaxis.set_major_locator(matplotlib.ticker.MaxNLocator(integer=True))
    for lado in ('top', 'right'):
        ax.spines[lado].set_visible(False)
    ax.spines['left'].set_color('#CBD5E1')
    ax.spines['bottom'].set_color('#CBD5E1')
    ax.tick_params(colors='#475569', labelsize=8)
    ax.legend(frameon=False, fontsize=8, ncol=4, loc='lower right', bbox_to_anchor=(1.0, 1.0))
    ax.set_title('Achados por categoria', fontsize=10.5, fontweight='bold',
                 color='#0F172A', loc='left', pad=18)
    fig.tight_layout()
    p2 = os.path.join(TEMP, 'barras.png')
    fig.savefig(p2, transparent=True, bbox_inches='tight')
    plt.close(fig)

    return p1, p2


# --------------------------------------------------------------------------- moldura
def moldura(canvas, doc):
    canvas.saveState()
    largura, altura = A4
    if doc.page > 1:
        canvas.setFont('Helvetica', 7.5)
        canvas.setFillColor(TINTA_SUAVE)
        canvas.drawString(2 * cm, altura - 1.25 * cm,
                          'Relatório de Auditoria de Segurança — AVASEC')
        canvas.setStrokeColor(LINHA)
        canvas.setLineWidth(0.4)
        canvas.line(2 * cm, altura - 1.4 * cm, largura - 2 * cm, altura - 1.4 * cm)
        canvas.line(2 * cm, 1.55 * cm, largura - 2 * cm, 1.55 * cm)
        canvas.drawString(2 * cm, 1.1 * cm, A.DATA)
        canvas.drawRightString(largura - 2 * cm, 1.1 * cm, f'Página {doc.page}')
    canvas.restoreState()


def construir():
    doc = BaseDocTemplate(
        SAIDA, pagesize=A4,
        leftMargin=2 * cm, rightMargin=2 * cm, topMargin=2 * cm, bottomMargin=2 * cm,
        title=f'Relatório de Auditoria de Segurança — {A.PROJETO}',
        author='Auditoria técnica', subject='Segurança de aplicação',
    )
    quadro = Frame(doc.leftMargin, doc.bottomMargin, doc.width, doc.height, id='corpo')
    doc.addPageTemplates([PageTemplate(id='padrao', frames=[quadro], onPage=moldura)])
    L = doc.width  # largura util

    rosca, barras = graficos()
    por_sev = Counter(a['sev'] for a in A.ACHADOS)
    hist = []

    # ------------------------------------------------------------------ capa
    hist += [
        Spacer(1, 3.4 * cm),
        Paragraph('Relatório de Auditoria de Segurança', E['titulo_capa']),
        Spacer(1, 0.3 * cm),
        Paragraph(esc(A.PROJETO), E['sub_capa']),
        Spacer(1, 1.1 * cm),
    ]
    resumo_capa = [
        [Paragraph('<b>Data</b>', E['celula']), Paragraph(esc(A.DATA), E['celula'])],
        [Paragraph('<b>Versão auditada</b>', E['celula']), Paragraph(esc(A.COMMIT), E['celula'])],
        [Paragraph('<b>Achados</b>', E['celula']),
         Paragraph(' · '.join(f'{por_sev[s]} {ROTULO[s].lower()}' for s in ORDEM if por_sev.get(s)),
                   E['celula'])],
        [Paragraph('<b>Pontos fortes</b>', E['celula']),
         Paragraph(f'{len(A.PONTOS_FORTES)} controles verificados e aprovados', E['celula'])],
    ]
    hist += [Table(resumo_capa, colWidths=[4 * cm, L - 4 * cm], style=TableStyle([
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('LINEBELOW', (0, 0), (-1, -2), 0.4, LINHA),
        ('TOPPADDING', (0, 0), (-1, -1), 6), ('BOTTOMPADDING', (0, 0), (-1, -1), 6),
    ])), Spacer(1, 0.9 * cm)]

    hist.append(Paragraph('Escopo auditado', E['h2']))
    linhas_stack = [[Paragraph(f'<b>{esc(k)}</b>', E['celula']), Paragraph(esc(v), E['celula'])]
                    for k, v in A.STACK]
    hist += [Table(linhas_stack, colWidths=[3.3 * cm, L - 3.3 * cm], style=TableStyle([
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('TOPPADDING', (0, 0), (-1, -1), 3), ('BOTTOMPADDING', (0, 0), (-1, -1), 3),
    ]))]

    hist.append(PageBreak())

    # ------------------------------------------------- nota metodologica
    hist.append(Paragraph('Nota metodológica', E['h1']))
    hist.append(Paragraph(
        'Cada categoria do roteiro foi traduzida para o equivalente desta stack antes da leitura do '
        'código. Todo achado abaixo foi confirmado no código real e, quando indicado, no comportamento '
        'da aplicação em execução — nada foi inferido por semelhança.', E['p']))
    for titulo, texto in A.METODOLOGIA:
        hist.append(Paragraph(f'<b>{esc(titulo)}</b>', E['h3']))
        hist.append(Paragraph(esc(texto), E['p']))

    hist.append(Paragraph('Categorias sem aplicação nesta stack', E['h2']))
    for titulo, texto in A.NAO_APLICAVEL:
        hist.append(Paragraph(f'<b>{esc(titulo)}:</b> {esc(texto)}', E['p']))

    hist.append(PageBreak())

    # ------------------------------------------------- resumo executivo
    hist.append(Paragraph('Resumo executivo', E['h1']))
    total = len(A.ACHADOS)
    criticos = por_sev.get('critica', 0) + por_sev.get('alta', 0)
    hist.append(Paragraph(
        f'A auditoria percorreu os 72 handlers registrados no backend, o frontend e os artefatos de '
        f'build, e registrou <b>{total} achados</b>, dos quais <b>{criticos}</b> são de severidade alta '
        f'ou crítica, além de <b>{len(A.PONTOS_FORTES)} controles verificados e aprovados</b>. '
        'O achado dominante não está em uma regra de negócio, e sim na configuração de build: uma '
        'linha no .env faz o pacote de produção ser gerado em modo de desenvolvimento, publicando na '
        'tela de login as senhas de Admin e Gestor.', E['p']))

    tabela_sev = [[Paragraph('<b>Severidade</b>', E['celula']),
                   Paragraph('<b>Qtd.</b>', E['celula']),
                   Paragraph('<b>Leitura</b>', E['celula'])]]
    leitura = {
        'critica': 'Exige correção imediata; explorável sem conta e sem pré-condição.',
        'alta': 'Correção prioritária: dado sensível ou credencial exposta.',
        'media': 'Falha de escopo entre perfis legítimos; corrigir no ciclo corrente.',
        'baixa': 'Risco contido ou dependente de ação da própria vítima.',
        'info': 'Sem risco direto; registrado para acompanhamento.',
    }
    for s in ORDEM:
        if not por_sev.get(s):
            continue
        tabela_sev.append([chip(s), Paragraph(str(por_sev[s]), E['celula']),
                           Paragraph(leitura[s], E['celula'])])
    hist.append(Table(tabela_sev, colWidths=[2.6 * cm, 1.6 * cm, L - 4.2 * cm], style=TableStyle([
        ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
        ('LINEBELOW', (0, 0), (-1, 0), 0.7, TINTA),
        ('LINEBELOW', (0, 1), (-1, -2), 0.3, LINHA),
        ('TOPPADDING', (0, 0), (-1, -1), 5), ('BOTTOMPADDING', (0, 0), (-1, -1), 5),
    ])))
    hist.append(Spacer(1, 0.5 * cm))
    hist.append(Image(rosca, width=L * 0.72, height=L * 0.72 * 0.62))
    hist.append(Spacer(1, 0.35 * cm))
    hist.append(Image(barras, width=L, height=L * 0.44))

    hist.append(PageBreak())

    # ------------------------------------------------- pontos fortes
    hist.append(Paragraph('Pontos fortes verificados', E['h1']))
    hist.append(Paragraph(
        'A lista abaixo não é genérica: cada item foi conferido no arquivo indicado e, em vários casos, '
        'existe teste automatizado cobrindo o comportamento. Serve também como prova da cobertura '
        'desta auditoria.', E['p']))
    linhas = []
    for titulo, evidencia in A.PONTOS_FORTES:
        linhas.append([
            Paragraph('<font color="#059669">&#10004;</font>', E['celula']),
            Paragraph(f'<b>{esc(titulo)}</b><br/>{esc(evidencia)}', E['celula']),
        ])
    hist.append(Table(linhas, colWidths=[0.7 * cm, L - 0.7 * cm], style=TableStyle([
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('LINEBELOW', (0, 0), (-1, -2), 0.3, LINHA),
        ('TOPPADDING', (0, 0), (-1, -1), 5), ('BOTTOMPADDING', (0, 0), (-1, -1), 5),
    ])))

    hist.append(Paragraph('Pontos fracos — os riscos centrais', E['h1']))
    fracos = [
        ('Conteudo sem tranca na porta da frente',
         'O isolamento por matricula existe e funciona no chat, nas mensagens diretas e nos '
         'certificados, mas nao no proprio material de estudo: GET /api/courses e publico e devolve as '
         'aulas inteiras. O controle certo esta implementado no lugar errado.'),
        ('Segredo protegido por condicional de build',
         'As senhas administrativas moram no codigo e sao "protegidas" por import.meta.env.DEV. Uma '
         'variavel de ambiente derrubou essa protecao inteira. Condicional de build nao e controle de '
         'acesso.'),
        ('Escopo do instrutor aplicado de forma desigual',
         'O mesmo projeto que limita o instrutor em DMs e certificados deixa passar forum, arquivos '
         'privados e solicitacoes academicas. A regra existe; falta uniformidade.'),
        ('URL tratada como texto',
         'Campos de URL sao validados apenas por tamanho e caem direto em href. O projeto ja tem o '
         'padrao correto em videoSource.ts — falta aplica-lo aos demais campos.'),
    ]
    for titulo, texto in fracos:
        hist.append(Paragraph(f'<b>{esc(titulo)}</b>', E['h3']))
        hist.append(Paragraph(esc(texto), E['p']))

    hist.append(PageBreak())

    # ------------------------------------------------- achados detalhados
    hist.append(Paragraph('Achados detalhados', E['h1']))
    cat_atual = None
    for a in A.ACHADOS:
        if a['cat'] != cat_atual:
            cat_atual = a['cat']
            hist.append(Paragraph(esc(cat_atual), E['h2']))

        cabecalho = Table(
            [[chip(a['sev']),
              Paragraph(f"<b>{esc(a['id'])} — {esc(a['titulo'])}</b>", E['celula'])]],
            colWidths=[2.25 * cm, L - 2.25 * cm],
            style=TableStyle([('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
                              ('LEFTPADDING', (0, 0), (0, 0), 0),
                              ('BOTTOMPADDING', (0, 0), (-1, -1), 4)]))

        corpo = [
            cabecalho,
            Paragraph(f"<b>Local:</b> <font face='Courier' size='8'>{esc(a['local'])}</font>",
                      E['celula']),
            Spacer(1, 3),
            bloco_codigo(a['codigo'], L),
            Spacer(1, 5),
            Paragraph(f"<b>Por que é explorável:</b> {esc(a['porque'])}", E['p']),
            Paragraph(f"<b>Impacto:</b> {esc(a['impacto'])}", E['p']),
            Paragraph(f"<b>Correção sugerida:</b> {esc(a['fix'])}", E['p']),
            Spacer(1, 10),
        ]
        hist.append(KeepTogether(corpo) if len(a['porque']) < 700 else corpo[0])
        if len(a['porque']) >= 700:
            hist += corpo[1:]

    hist.append(Paragraph('Condições de explorabilidade', E['h2']))
    for obs in A.OBSERVACOES_EXPLORABILIDADE:
        hist.append(Paragraph(f'• {esc(obs)}', E['p']))

    hist.append(PageBreak())

    # ------------------------------------------------- recomendacoes
    hist.append(Paragraph('Recomendações priorizadas', E['h1']))
    tab = [[Paragraph('<b>Prio.</b>', E['celula']), Paragraph('<b>Sev.</b>', E['celula']),
            Paragraph('<b>Ação</b>', E['celula'])]]
    for prio, acao, sev in A.RECOMENDACOES:
        tab.append([Paragraph(f'<b>{prio}</b>', E['celula']), chip(sev),
                    Paragraph(esc(acao), E['celula'])])
    hist.append(Table(tab, colWidths=[1.4 * cm, 2.4 * cm, L - 3.8 * cm], style=TableStyle([
        ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
        ('LINEBELOW', (0, 0), (-1, 0), 0.7, TINTA),
        ('LINEBELOW', (0, 1), (-1, -2), 0.3, LINHA),
        ('TOPPADDING', (0, 0), (-1, -1), 5), ('BOTTOMPADDING', (0, 0), (-1, -1), 5),
    ])))

    hist.append(PageBreak())

    # ------------------------------------------------- issues
    hist.append(Paragraph('Issues para o GitHub', E['h1']))
    hist.append(Paragraph(
        'Cada bloco abaixo é o texto completo de uma issue, pronto para copiar e colar. Achados do '
        'mesmo tema foram agrupados para não gerar ruído no quadro.', E['p']))

    for i, issue in enumerate(montar_issues(), start=1):
        hist.append(Spacer(1, 6))
        hist.append(Paragraph(f'--- ISSUE {i} ---', E['h3']))
        hist.append(bloco_codigo(issue, L))
        hist.append(Paragraph(f'--- FIM ISSUE {i} ---', E['h3']))

    doc.build(hist)
    return SAIDA


# --------------------------------------------------------------------------- issues
def montar_issues():
    """Texto Markdown de cada issue. Achados irmaos entram na mesma issue."""
    by_id = {a['id']: a for a in A.ACHADOS}

    def bloco(a):
        return (f"**Arquivo:** `{a['local']}`\n\n"
                f"```\n{a['codigo']}\n```\n\n"
                f"{a['porque']}\n")

    issues = []

    a = by_id['SEC-01']
    issues.append(
        "## [Seguranca] Build de producao sai em modo dev e publica as senhas de Admin e Gestor\n\n"
        "**Labels:** security, critica\n\n"
        "### Problema\n"
        f"{a['porque']}\n\n"
        "### Evidencia\n"
        f"{bloco(a)}\n"
        "Reproducao:\n"
        "```bash\n"
        "npm run build\n"
        "grep -c 'Dica para Avaliacao do Fluxo' dist/assets/index-*.js   # 1 = dica de PIN no bundle\n"
        "grep -o 'pin:\"[0-9]*\"' dist/assets/index-*.js                   # pin:\"1234\" pin:\"5678\" pin:\"9999\"\n"
        "grep -c jsxDEV dist/assets/index-*.js                            # 19 = React em modo dev\n"
        "```\n\n"
        "### Impacto\n"
        f"{a['impacto']}\n\n"
        "### Correcao sugerida\n"
        f"{a['fix']}\n\n"
        "### Criterios de aceite\n"
        "- [ ] `NODE_ENV` removido de `.env` (e documentado em `.env.example` que o Vite define o modo)\n"
        "- [ ] Apos `npm run build`, `grep -c 'Dica para Avaliacao' dist/assets/index-*.js` retorna 0\n"
        "- [ ] Apos `npm run build`, `grep -c jsxDEV dist/assets/index-*.js` retorna 0\n"
        "- [ ] Nenhum caminho absoluto do disco do desenvolvedor aparece no bundle\n"
        "- [ ] Teste ou script de CI que falhe se um marcador de dev voltar ao artefato\n")

    a = by_id['SEC-02']
    b = by_id['SEC-03']
    issues.append(
        "## [Seguranca] Credenciais administrativas e senha padrao embutidas no codigo\n\n"
        "**Labels:** security, alta\n\n"
        "### Problema\n"
        f"{a['porque']}\n\n"
        f"Relacionado (mesmo tema, severidade media): {b['titulo']}. {b['porque']}\n\n"
        "### Evidencia\n"
        f"{bloco(a)}\n{bloco(b)}\n"
        "### Impacto\n"
        f"{a['impacto']}\n\n"
        "### Correcao sugerida\n"
        f"{a['fix']}\n\n"
        f"Para a senha padrao: {b['fix']}\n\n"
        "### Criterios de aceite\n"
        "- [ ] Nenhum PIN literal em `src/` (`grep -rn \"pin: '\" src/` sem resultado)\n"
        "- [ ] Senhas de Admin Superior e Gestor de Conteudos trocadas por valores fortes\n"
        "- [ ] Cadastro administrativo de aluno sem senha gera valor aleatorio de uso unico\n"
        "- [ ] Nenhum texto de interface exibe senha padrao\n")

    a = by_id['ISO-01']
    issues.append(
        "## [Seguranca] GET /api/courses expoe o conteudo integral das aulas sem autenticacao\n\n"
        "**Labels:** security, alta\n\n"
        "### Problema\n"
        f"{a['porque']}\n\n"
        "### Evidencia\n"
        f"{bloco(a)}\n"
        "Reproducao:\n"
        "```bash\n"
        "curl -s http://localhost:8000/api/courses | head -c 800   # content, documents e meetingLink\n"
        "```\n\n"
        "### Impacto\n"
        f"{a['impacto']}\n\n"
        "### Correcao sugerida\n"
        f"{a['fix']}\n\n"
        "### Criterios de aceite\n"
        "- [ ] Requisicao anonima a `/api/courses` nao devolve `content`, `videoUrl`, `documents` nem "
        "`liveSessions[].meetingLink`\n"
        "- [ ] Aluno matriculado continua recebendo o curso completo\n"
        "- [ ] Aluno nao matriculado nao recebe o conteudo das aulas\n"
        "- [ ] Teste de feature cobrindo os tres casos acima\n")

    a = by_id['XSS-01']
    issues.append(
        "## [Seguranca] URLs de anexo, biblioteca e aula ao vivo aceitam esquema javascript:\n\n"
        "**Labels:** security, alta\n\n"
        "### Problema\n"
        f"{a['porque']}\n\n"
        "### Evidencia\n"
        f"{bloco(a)}\n"
        "### Impacto\n"
        f"{a['impacto']}\n\n"
        "### Correcao sugerida\n"
        f"{a['fix']}\n\n"
        "### Criterios de aceite\n"
        "- [ ] Backend rejeita com 400 uma URL que nao comece por `http://` ou `https://` em documento, "
        "biblioteca e meetingLink\n"
        "- [ ] Frontend nao renderiza href com esquema fora da whitelist (funcao unica, testada)\n"
        "- [ ] Teste com payload `javascript:alert(1)` cobrindo os dois lados\n")

    escopo = [by_id['ISO-02'], by_id['ISO-03'], by_id['IDOR-01'], by_id['IDOR-02'], by_id['ISO-04']]
    issues.append(
        "## [Seguranca] Escopo de curso/aluno ausente em forum, arquivos privados e solicitacoes\n\n"
        "**Labels:** security, media\n\n"
        "### Problema\n"
        "Cinco pontos aplicam o filtro de escopo de forma inconsistente com o resto do projeto, que ja "
        "possui os helpers corretos (`CourseAccess`, `InstructorScope`, `Identity`).\n\n"
        + "\n".join(f"- **{x['id']}** — {x['titulo']}" for x in escopo) + "\n\n"
        "### Evidencia\n" + "\n".join(bloco(x) for x in escopo) + "\n"
        "### Impacto\n"
        "Leitura e escrita entre turmas, download de documento privado de aluno fora do escopo do "
        "instrutor e decisao academica sem vinculo com o aluno.\n\n"
        "### Correcao sugerida\n"
        + "\n".join(f"- **{x['id']}**: {x['fix']}" for x in escopo) + "\n\n"
        "### Criterios de aceite\n"
        "- [ ] Aluno so le e escreve no forum de cursos a que tem acesso\n"
        "- [ ] Instrutor so baixa arquivo privado de aluno dentro do seu escopo\n"
        "- [ ] Instrutor so decide solicitacao academica de aluno dentro do seu escopo\n"
        "- [ ] Decisao sobre a leitura ampla de solicitacoes registrada em ADR\n"
        "- [ ] Teste de feature para cada regra acima\n")

    a = by_id['ISO-05']
    b = by_id['ISO-06']
    issues.append(
        "## [Seguranca] Listagem de quizzes entrega o gabarito a qualquer autenticado\n\n"
        "**Labels:** security, media\n\n"
        "### Problema\n"
        f"{a['porque']}\n\n"
        f"Relacionado: {b['titulo']} ({b['id']}, severidade baixa).\n\n"
        "### Evidencia\n"
        f"{bloco(a)}\n{bloco(b)}\n"
        "### Impacto\n"
        f"{a['impacto']}\n\n"
        "### Correcao sugerida\n"
        f"{a['fix']}\n\n"
        "### Criterios de aceite\n"
        "- [ ] `GET /api/quizzes` nao devolve `correctOptionIndex` para o papel student\n"
        "- [ ] A correcao continua chegando ao aluno na resposta de `POST /api/quiz-submissions`\n"
        "- [ ] Exercicios filtrados por curso acessivel\n"
        "- [ ] Teste de feature cobrindo os dois pontos\n")

    a = by_id['PRIV-01']
    b = by_id['PRIV-02']
    issues.append(
        "## [Seguranca] Emissao de certificado e configuracoes do sistema sem checagem adequada\n\n"
        "**Labels:** security, media\n\n"
        "### Problema\n"
        f"**{a['id']}** — {a['porque']}\n\n"
        f"**{b['id']}** — {b['porque']}\n\n"
        "### Evidencia\n"
        f"{bloco(a)}\n{bloco(b)}\n"
        "### Impacto\n"
        f"{a['impacto']} {b['impacto']}\n\n"
        "### Correcao sugerida\n"
        f"- **{a['id']}**: {a['fix']}\n"
        f"- **{b['id']}**: {b['fix']}\n\n"
        "### Criterios de aceite\n"
        "- [ ] `POST /api/certificates` exige papel e valida escopo do instrutor\n"
        "- [ ] `GET /api/system-settings` exige autenticacao ou devolve apenas chaves publicas\n"
        "- [ ] `PUT /api/system-settings` valida as chaves aceitas\n"
        "- [ ] Teste de feature para cada regra\n")

    a = by_id['XSS-02']
    issues.append(
        "## [Seguranca] Anotacoes do aluno manipulam HTML cru sem sanitizacao\n\n"
        "**Labels:** security, baixa\n\n"
        "### Problema\n"
        f"{a['porque']}\n\n"
        "### Evidencia\n"
        f"{bloco(a)}\n"
        "### Impacto\n"
        f"{a['impacto']}\n\n"
        "### Correcao sugerida\n"
        f"{a['fix']}\n\n"
        "### Criterios de aceite\n"
        "- [ ] Conteudo lido do localStorage passa por whitelist de tags antes do innerHTML\n"
        "- [ ] Arquivo exportado nao contem `<script>` nem manipuladores inline\n"
        "- [ ] Teste cobrindo payload de script salvo nas anotacoes\n")

    return issues


if __name__ == '__main__':
    caminho = construir()
    print(f'PDF gerado: {caminho}')
