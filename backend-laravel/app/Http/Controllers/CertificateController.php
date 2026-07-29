<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Controllers\Concerns\ApiRequestHelpers;
use App\Services\AuditLogger;
use App\Services\CertificatePdfService;
use App\Services\CertificateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class CertificateController extends Controller
{
    use ApiRequestHelpers;

    public function __construct(
        private readonly CertificateService $certificates,
        private readonly CertificatePdfService $pdf,
        private readonly AuditLogger $audit,
    ) {}

    public function verify(Request $request): JsonResponse
    {
        $q = (string) $request->query('q', '');
        if (trim($q) === '') {
            throw ApiException::validation('Informe um termo de busca.');
        }

        return response()->json($this->certificates->verifyCertificatePublic($q));
    }

    public function index(Request $request): JsonResponse
    {
        [$page, $pageSize, $skip, $take] = $this->pageParams($request);
        $result = $this->certificates->listCertificates($this->requester($request), $skip, $take);

        return response()->json([
            'items' => $result['items'],
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => $result['total'],
                'totalPages' => max(1, (int) ceil($result['total'] / $pageSize)),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateInput($request, [
            'studentName' => ['required', 'string', 'min:2', 'max:150'],
            'courseId' => ['required', 'string', 'max:191'],
        ]);

        $studentName = $this->stringField($data, 'studentName');
        $courseId = $this->stringField($data, 'courseId');
        $cert = $this->certificates->issueCertificate([
            'studentName' => $studentName,
            'courseId' => $courseId,
        ], $this->requester($request));
        $this->audit->log($request, 'Emissão de Certificado', "Certificado emitido para \"{$studentName}\" no curso {$courseId}.");

        return response()->json($cert, 201);
    }

    public function pdf(Request $request, string $id): Response
    {
        $result = $this->pdf->renderPdf($id, $this->requester($request));
        $this->audit->log($request, 'Download de Certificado', "PDF do certificado {$id} baixado.");

        return response($result['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$result['filename']}\"",
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->certificates->deleteCertificate($id);
        $this->audit->log($request, 'Exclusão de Certificado', "Certificado {$id} removido.", 'WARNING');

        return response()->json(['success' => true]);
    }
}
