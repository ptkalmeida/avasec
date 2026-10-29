# ADR 027 — Página pública de certificados e autenticador por QR inativados

- **Data:** 29/10/2026
- **Situação:** aceita — decisão do responsável: a escola não vai usar a página pública de certificados nem o autenticador por QR
- **Afeta:** ADR 09 (QR de verificação), ADR 12 (nada é apagado)

## Decisão

A página pública `/certificados` ("Certificados e Emissão", com o autenticador) e a
verificação por QR saem do ar. Pela regra do ADR 12, **inativa-se; não se apaga**: tudo é
controlado pela feature flag `verificacaoPublicaCertificados`, que fica **desligada**
(`config/features.php`). Ligá-la devolve tudo.

| O que | Com a flag desligada |
|---|---|
| `GET /certificados` | 404 comum (a mesma página de erro das demais) |
| Item "Certificados" no cabeçalho (desktop e celular) e no rodapé | some |
| `GET /?verify=<código>` (o que um QR já impresso abre) | a home abre normalmente; **não** redireciona para um 404 |
| `GET /api/certificados/verificar` | 404 `FEATURE_DISABLED` |
| `{{qrImg}}` num layout personalizado de certificado | vira vazio: o QR não é gerado, e não sobra `<img>` quebrado |

## O que NÃO muda

- A área do aluno (`/aluno/certificados`), a emissão, a listagem e o download do PDF
  seguem pela flag `certificados`, que continua ligada.
- Nada foi apagado: o controlador, a view, o texto editável da página (`Páginas do site`),
  a coluna `hash_verificacao` e os testes continuam. Os testes que exercitam a página e a
  consulta ligam a flag com o recurso `ComVerificacaoPublicaLigada`, para o código inativo
  não apodrecer; o estado desligado (o padrão) é prendido em `VerificacaoPublicaDesligadaTest`.
- O modelo **padrão** do PDF (`certificates/pdf.blade.php`) já não imprime QR; só sai QR
  em layout personalizado que use `{{qrImg}}`. Nenhum modelo do banco de dev o usa.

## Consequências aceitas

- Um certificado **já impresso** com QR (layout personalizado) abre a home em vez de verificar
  a autenticidade. Escolhido de propósito: um redirecionamento para 404 só pioraria.
- Sem a página, **não existe verificação pública de autenticidade** de certificado. Se a
  escola vier a precisar (por exemplo, instituição parceira que queira conferir), basta
  ligar a flag.
- O texto da página continua editável em "Páginas do site", mas não aparece para ninguém
  enquanto a flag estiver desligada.

## Como religar

`config/features.php`: `'verificacaoPublicaCertificados' => true`. Voltam a página, o menu, o
rodapé, o redirecionamento do QR, a rota da API e o QR nos layouts personalizados.
