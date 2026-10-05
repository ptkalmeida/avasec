# Auditoria de segurança — AVASEC

- `relatorio-auditoria-seguranca.pdf` — relatório completo (23 páginas), com resumo
  executivo, gráficos, pontos fortes, achados detalhados, recomendações priorizadas e
  o texto pronto das issues para o GitHub.
- `achados.py` — os dados da auditoria (achados, pontos fortes, recomendações).
  É aqui que se mexe para atualizar o relatório.
- `gerar_relatorio.py` — só a apresentação: monta o PDF a partir de `achados.py`.

## Regerar o PDF

```bash
python -m venv docs/security-audit/.venv
docs/security-audit/.venv/Scripts/python.exe -m pip install reportlab matplotlib
docs/security-audit/.venv/Scripts/python.exe docs/security-audit/gerar_relatorio.py
```

Em Linux/macOS, troque `.venv/Scripts/python.exe` por `.venv/bin/python`.

O ambiente fica dentro de `docs/security-audit/.venv` e é ignorado pelo git — nada é
instalado globalmente.
