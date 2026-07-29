{{--
    Certificado de conclusão (PDF via dompdf) — espelha o visual de
    src/components/CertificateTemplate.tsx (paleta amber, serif, assinaturas).
    Restrições do dompdf: CSS 2.1 (sem flex/grid), DejaVu Serif no lugar de
    Georgia, QR como <img> com data URI (SVG inline não é confiável).
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Certificado de Conclusão</title>
    <style>
        @page { margin: 24px; }
        body {
            font-family: 'DejaVu Serif', serif;
            margin: 0;
            color: #334155;
        }
        .frame {
            border: 8px double #78350f;
            background-color: #fffcf9;
            padding: 36px 48px;
            text-align: center;
            position: relative;
            height: 96%;
        }
        .corner { position: absolute; width: 56px; height: 56px; }
        .corner-tl { top: 8px; left: 8px; border-top: 4px solid #b4886b; border-left: 4px solid #b4886b; }
        .corner-tr { top: 8px; right: 8px; border-top: 4px solid #b4886b; border-right: 4px solid #b4886b; }
        .corner-bl { bottom: 8px; left: 8px; border-bottom: 4px solid #b4886b; border-left: 4px solid #b4886b; }
        .corner-br { bottom: 8px; right: 8px; border-bottom: 4px solid #b4886b; border-right: 4px solid #b4886b; }
        .institution {
            font-size: 10px;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #92400e;
            font-weight: bold;
            margin-bottom: 14px;
        }
        h1 { font-size: 34px; color: #451a03; margin: 0 0 18px 0; }
        .lead { font-size: 13px; color: #334155; margin: 0 auto 6px auto; max-width: 620px; }
        .student {
            font-size: 28px;
            font-weight: bold;
            color: #78350f;
            margin: 14px 0;
            border-bottom: 2px solid #d9b38c;
            display: inline-block;
            padding: 0 24px 4px 24px;
        }
        .course { font-size: 18px; font-weight: bold; color: #0f172a; margin: 10px 0; }
        .detail { font-size: 11.5px; color: #475569; margin: 2px auto; max-width: 620px; }
        .highlight { color: #047857; font-weight: bold; }
        .signatures { width: 100%; margin-top: 34px; border-top: 1px solid #e8ddce; padding-top: 22px; }
        .signatures td { width: 50%; text-align: center; vertical-align: top; }
        .sig-script { font-style: italic; color: #94a3b8; font-size: 13px; }
        .sig-line { width: 160px; border-top: 1px solid #cbd5e1; margin: 4px auto; }
        .sig-name { font-size: 10.5px; font-weight: bold; color: #334155; }
        .sig-role { font-size: 9px; color: #64748b; }
        .verify { margin-top: 26px; }
        .verify td { vertical-align: middle; }
        .verify-text { text-align: left; font-size: 9px; color: #64748b; font-family: 'DejaVu Sans Mono', monospace; }
        .verify-text strong { color: #334155; }
        .footnote { margin-top: 14px; font-size: 8.5px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="frame">
        <div class="corner corner-tl"></div>
        <div class="corner corner-tr"></div>
        <div class="corner corner-bl"></div>
        <div class="corner corner-br"></div>

        <div class="institution">República Federativa do Brasil &bull; AVA LMS</div>

        <h1>Certificado de Conclusão</h1>

        <p class="lead">Certificamos para os devidos fins de direito que o(a) aluno(a) de excelência acadêmica</p>

        <div class="student">{{ $studentName }}</div>

        <p class="lead">concluiu e obteve aprovação no programa de capacitação do curso livre da plataforma digital AVA</p>

        <div class="course">{{ $courseTitle }}</div>

        <p class="detail">
            @if ($cargaHoraria !== null)
                com carga horária integralizada de <strong>{{ $cargaHoraria }} horas</strong> e
            @else
                com carga horária integralizada e
            @endif
            aproveitamento com frequência registrada de
            <span class="highlight">{{ $attendancePercent }}%</span> de presença total
            nas aulas teóricas e transmissões periódicas ao vivo.
        </p>

        <table class="signatures">
            <tr>
                <td>
                    <div class="sig-script">Alessandro Pinto</div>
                    <div class="sig-line"></div>
                    <div class="sig-name">Alessandro Pinto</div>
                    <div class="sig-role">Diretor de Tecnologia &amp; AVA</div>
                </td>
                <td>
                    <div class="sig-script">Mariana Santos</div>
                    <div class="sig-line"></div>
                    <div class="sig-name">Coordenação Acadêmica</div>
                    <div class="sig-role">Professora Responsável</div>
                </td>
            </tr>
        </table>

        <table class="verify" align="center">
            <tr>
                <td style="padding-right: 12px;">
                    <img src="{{ $qrDataUri }}" width="76" height="76" alt="QR de verificação">
                </td>
                <td class="verify-text">
                    Código de Verificação: <strong>{{ $verificationHash }}</strong><br>
                    Emitido digitalmente em {{ $issueDate }}<br>
                    Valide em: {{ $verificationUrl }}
                </td>
            </tr>
        </table>

        <div class="footnote">
            * A emissão de certificados na plataforma AVA respeita a presença mínima e obrigatória nas atividades letivas e transmissões ao vivo.
        </div>
    </div>
</body>
</html>
