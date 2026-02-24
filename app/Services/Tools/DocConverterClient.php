<?php

namespace App\Services\Tools;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP client for tools/doc-convertor microservice.
 * Operations: convert, extract, merge, split. Each has its own status/result URLs.
 * See TOOLS_MICROSERVICES_REFERENCE.md. Uses X-API-Key (if configured).
 */
class DocConverterClient
{
    public function convertDocument(string $filePath, string $targetFormat, array $options = []): array
    {
        $url = $this->baseUrl() . '/v1/convert';
        if (empty($this->baseUrl())) {
            Log::warning('[DocConverterClient] DOC_CONVERTER_URL not set');
            return ['success' => false, 'error' => 'Doc-converter service is not configured.'];
        }
        if (! is_readable($filePath)) {
            return ['success' => false, 'error' => 'File not found or not readable.'];
        }

        try {
            // Microservice expects options as a JSON object string (dict). Plugins use options.get("key") so we must send "{}" not "[]".
            $response = Http::withHeaders($this->headers())
                ->timeout($this->timeout())
                ->attach('file', file_get_contents($filePath), basename($filePath))
                ->post($url, [
                    'target_format' => $targetFormat,
                    'options' => json_encode((object) $options),
                ]);

            $body = $response->json() ?? [];
            if (! $response->successful()) {
                $raw = $body['message'] ?? $body['error'] ?? $response->body() ?: "HTTP {$response->status()}";
                $error = is_string($raw) ? $raw : json_encode($raw);
                return ['success' => false, 'error' => MicroserviceErrorHelper::userMessage('Doc-converter service', $error)];
            }

            $jobId = $body['job_id'] ?? null;
            return [
                'success' => true,
                'job_id' => $jobId,
                'status' => $body['status'] ?? 'queued',
                'message' => $body['message'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('[DocConverterClient] Convert failed', ['error' => $e->getMessage(), 'url' => $url]);
            return ['success' => false, 'error' => MicroserviceErrorHelper::userMessage('Doc-converter service', $e->getMessage(), $e)];
        }
    }

    /**
     * Merge multiple documents into one (e.g. PDF). Microservice: POST /v1/merge with multipart files.
     */
    public function mergeDocuments(array $filePaths, array $options = []): array
    {
        $url = $this->baseUrl() . '/v1/merge';
        if (empty($this->baseUrl())) {
            Log::warning('[DocConverterClient] DOC_CONVERTER_URL not set');
            return ['success' => false, 'error' => 'Doc-converter service is not configured.'];
        }
        if (count($filePaths) < 2) {
            return ['success' => false, 'error' => 'Merge requires at least two files.'];
        }
        foreach ($filePaths as $path) {
            if (! is_readable($path)) {
                return ['success' => false, 'error' => 'File not found or not readable: ' . basename($path)];
            }
        }

        try {
            $request = Http::withHeaders($this->headers())->timeout($this->timeout());
            foreach ($filePaths as $i => $path) {
                $request = $request->attach('files', file_get_contents($path), basename($path));
            }
            $response = $request->post($url, $options);
            return $this->parseJobResponse($response, $url, 'merge');
        } catch (\Throwable $e) {
            Log::error('[DocConverterClient] Merge failed', ['error' => $e->getMessage(), 'url' => $url]);
            return ['success' => false, 'error' => MicroserviceErrorHelper::userMessage('Doc-converter service', $e->getMessage(), $e)];
        }
    }

    /**
     * Extract text or metadata from a document. Microservice: POST /v1/extract.
     * Status/result: GET /v1/extraction/status?job_id=, GET /v1/extraction/result?job_id=
     */
    public function extractDocument(string $filePath, string $extractionType = 'text', array $options = []): array
    {
        $url = $this->baseUrl() . '/v1/extract';
        if (empty($this->baseUrl())) {
            Log::warning('[DocConverterClient] DOC_CONVERTER_URL not set');
            return ['success' => false, 'error' => 'Doc-converter service is not configured.'];
        }
        if (! is_readable($filePath)) {
            return ['success' => false, 'error' => 'File not found or not readable.'];
        }

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout($this->timeout())
                ->attach('file', file_get_contents($filePath), basename($filePath))
                ->post($url, array_merge(['extraction_type' => $extractionType], $options));

            $body = $response->json() ?? [];
            if (! $response->successful()) {
                $raw = $body['message'] ?? $body['error'] ?? $response->body() ?: "HTTP {$response->status()}";
                $error = is_string($raw) ? $raw : json_encode($raw);
                return ['success' => false, 'error' => MicroserviceErrorHelper::userMessage('Doc-converter service', $error)];
            }
            $jobId = $body['job_id'] ?? null;
            return [
                'success' => true,
                'job_id' => $jobId,
                'status' => $body['status'] ?? 'queued',
                'message' => $body['message'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('[DocConverterClient] Extract failed', ['error' => $e->getMessage(), 'url' => $url]);
            return ['success' => false, 'error' => MicroserviceErrorHelper::userMessage('Doc-converter service', $e->getMessage(), $e)];
        }
    }

    /**
     * Split a PDF by page numbers. Microservice: POST /v1/pdf/split with multipart file and split_points (required).
     * Status/result: GET /v1/pdf/split/status?job_id=, GET /v1/pdf/split/result?job_id=
     *
     * @param  array<string, mixed>  $options  Must include 'split_points' => '3,7,12' (comma-separated page numbers)
     */
    public function splitDocument(string $filePath, array $options = []): array
    {
        $url = $this->baseUrl() . '/v1/pdf/split';
        if (empty($this->baseUrl())) {
            Log::warning('[DocConverterClient] DOC_CONVERTER_URL not set');
            return ['success' => false, 'error' => 'Doc-converter service is not configured.'];
        }
        if (! is_readable($filePath)) {
            return ['success' => false, 'error' => 'File not found or not readable.'];
        }

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout($this->timeout())
                ->attach('file', file_get_contents($filePath), basename($filePath))
                ->post($url, $options);
            return $this->parseJobResponse($response, $url, 'split');
        } catch (\Throwable $e) {
            Log::error('[DocConverterClient] Split failed', ['error' => $e->getMessage(), 'url' => $url]);
            return ['success' => false, 'error' => MicroserviceErrorHelper::userMessage('Doc-converter service', $e->getMessage(), $e)];
        }
    }

    private function parseJobResponse($response, string $url, string $op): array
    {
        $body = $response->json() ?? [];
        if (! $response->successful()) {
            $raw = $body['message'] ?? $body['error'] ?? $response->body() ?: "HTTP {$response->status()}";
            $error = is_string($raw) ? $raw : json_encode($raw);
            return ['success' => false, 'error' => MicroserviceErrorHelper::userMessage('Doc-converter service', $error)];
        }
        $jobId = $body['job_id'] ?? null;
        return [
            'success' => true,
            'job_id' => $jobId,
            'status' => $body['status'] ?? 'queued',
            'message' => $body['message'] ?? null,
        ];
    }

    public function getConversionStatus(string $jobId): array
    {
        $url = $this->baseUrl() . '/v1/conversion/status?' . http_build_query(['job_id' => $jobId]);
        return $this->get($url);
    }

    public function getConversionResult(string $jobId): array
    {
        $url = $this->baseUrl() . '/v1/conversion/result?' . http_build_query(['job_id' => $jobId]);
        return $this->get($url);
    }

    public function getMergeStatus(string $jobId): array
    {
        $url = $this->baseUrl() . '/v1/pdf/merge/status?' . http_build_query(['job_id' => $jobId]);
        return $this->get($url);
    }

    public function getMergeResult(string $jobId): array
    {
        $url = $this->baseUrl() . '/v1/pdf/merge/result?' . http_build_query(['job_id' => $jobId]);
        return $this->get($url);
    }

    public function getSplitStatus(string $jobId): array
    {
        $url = $this->baseUrl() . '/v1/pdf/split/status?' . http_build_query(['job_id' => $jobId]);
        return $this->get($url);
    }

    public function getSplitResult(string $jobId): array
    {
        $url = $this->baseUrl() . '/v1/pdf/split/result?' . http_build_query(['job_id' => $jobId]);
        return $this->get($url);
    }

    public function getExtractionStatus(string $jobId): array
    {
        $url = $this->baseUrl() . '/v1/extraction/status?' . http_build_query(['job_id' => $jobId]);
        return $this->get($url);
    }

    public function getExtractionResult(string $jobId): array
    {
        $url = $this->baseUrl() . '/v1/extraction/result?' . http_build_query(['job_id' => $jobId]);
        return $this->get($url);
    }

    /**
     * Status for PDF operations: compress, watermark, page_numbers, annotate, protect, unlock, preview, edit_pdf, batch.
     * Microservice: GET /v1/pdf/{operation}/status?job_id=
     */
    public function getPdfOperationStatus(string $jobId, string $operation): array
    {
        $pathOp = $this->normalizePdfOperationForPath($operation);
        $url = $this->baseUrl() . '/v1/pdf/' . $pathOp . '/status?' . http_build_query(['job_id' => $jobId]);
        return $this->get($url);
    }

    /**
     * Result for PDF operations. Microservice: GET /v1/pdf/{operation}/result?job_id=
     */
    public function getPdfOperationResult(string $jobId, string $operation): array
    {
        $pathOp = $this->normalizePdfOperationForPath($operation);
        $url = $this->baseUrl() . '/v1/pdf/' . $pathOp . '/result?' . http_build_query(['job_id' => $jobId]);
        return $this->get($url);
    }

    /** Microservice expects page_numbers (underscore) in status/result path. */
    private function normalizePdfOperationForPath(string $operation): string
    {
        return $operation === 'page_numbers' ? 'page_numbers' : $operation;
    }

    /**
     * Single-file PDF operation: POST to microservice, return job response.
     * Used for: compress, watermark, page_numbers, annotate, protect, unlock, preview, edit_pdf.
     */
    public function postPdfOperation(string $operation, string $filePath, array $params = []): array
    {
        $pathMap = [
            'compress' => 'v1/pdf/compress',
            'watermark' => 'v1/pdf/watermark',
            'page_numbers' => 'v1/pdf/page-numbers',
            'annotate' => 'v1/pdf/annotate',
            'protect' => 'v1/pdf/protect',
            'unlock' => 'v1/pdf/unlock',
            'preview' => 'v1/pdf/preview',
            'edit_pdf' => 'v1/pdf/edit_pdf',
        ];
        $path = $pathMap[$operation] ?? null;
        if (! $path) {
            return ['success' => false, 'error' => 'Unsupported PDF operation: ' . $operation];
        }
        if (empty($this->baseUrl())) {
            Log::warning('[DocConverterClient] DOC_CONVERTER_URL not set');
            return ['success' => false, 'error' => 'Doc-converter service is not configured.'];
        }
        if (! is_readable($filePath)) {
            return ['success' => false, 'error' => 'File not found or not readable.'];
        }
        $url = $this->baseUrl() . '/' . $path;
        try {
            $request = Http::withHeaders($this->headers())->timeout($this->timeout())
                ->attach('file', file_get_contents($filePath), basename($filePath));
            foreach ($params as $key => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $request = $request->attach($key, is_array($value) || is_object($value) ? json_encode($value) : (string) $value);
            }
            $response = $request->post($url);
            return $this->parseJobResponse($response, $url, $operation);
        } catch (\Throwable $e) {
            Log::error('[DocConverterClient] PDF operation failed', ['operation' => $operation, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => MicroserviceErrorHelper::userMessage('Doc-converter service', $e->getMessage(), $e)];
        }
    }

    /**
     * Batch process: multiple PDFs + operations list. Microservice: POST /v1/pdf/batch-process.
     * Status/result use operation "batch".
     */
    public function batchProcess(array $filePaths, array $operations): array
    {
        $url = $this->baseUrl() . '/v1/pdf/batch-process';
        if (empty($this->baseUrl())) {
            Log::warning('[DocConverterClient] DOC_CONVERTER_URL not set');
            return ['success' => false, 'error' => 'Doc-converter service is not configured.'];
        }
        if (empty($filePaths)) {
            return ['success' => false, 'error' => 'At least one file is required for batch process.'];
        }
        foreach ($filePaths as $path) {
            if (! is_readable($path)) {
                return ['success' => false, 'error' => 'File not found or not readable: ' . basename($path)];
            }
        }
        try {
            $request = Http::withHeaders($this->headers())->timeout($this->timeout());
            foreach ($filePaths as $path) {
                $request = $request->attach('files', file_get_contents($path), basename($path));
            }
            $request = $request->attach('operations', json_encode($operations));
            $response = $request->post($url);
            return $this->parseJobResponse($response, $url, 'batch');
        } catch (\Throwable $e) {
            Log::error('[DocConverterClient] Batch process failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => MicroserviceErrorHelper::userMessage('Doc-converter service', $e->getMessage(), $e)];
        }
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.doc_converter.url', ''), '/');
    }

    private function apiKey(): ?string
    {
        return config('services.doc_converter.api_key');
    }

    private function timeout(): int
    {
        return (int) config('services.doc_converter.timeout', 120);
    }

    private function get(string $url): array
    {
        if (empty($this->baseUrl())) {
            return ['success' => false, 'error' => 'Doc-converter service is not configured.', 'data' => null];
        }

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(30)
                ->get($url);

            if (! $response->successful()) {
                $body = $response->json() ?? [];
                $raw = $body['message'] ?? $body['error'] ?? $response->body() ?: "HTTP {$response->status()}";
                $error = is_string($raw) ? $raw : json_encode($raw);
                return [
                    'success' => false,
                    'error' => MicroserviceErrorHelper::userMessage('Doc-converter service', $error),
                    'data' => null,
                ];
            }

            // Doc-converter returns FileResponse (binary) for single-file result; JSON for multi-file
            $contentType = $response->header('Content-Type');
            $bodyRaw = $response->body();
            if ($bodyRaw !== '' && ($this->isBinaryContentType($contentType) || ! $this->looksLikeJson($bodyRaw))) {
                $data = [
                    '_raw_body' => $bodyRaw,
                    '_content_type' => $contentType ?: 'application/octet-stream',
                    'status' => 'completed',
                ];
                return [
                    'success' => true,
                    'data' => $data,
                    'status' => 'completed',
                    'progress' => 100,
                ];
            }

            $body = $response->json() ?? [];
            // Merge top-level body with nested body['data'] so we don't lose URL keys from either place
            $nested = isset($body['data']) && is_array($body['data']) ? $body['data'] : [];
            $data = array_merge(is_array($body) ? $body : [], $nested);
            return [
                'success' => true,
                'data' => $data,
                'status' => is_array($data) ? ($data['status'] ?? null) : null,
                'progress' => is_array($data) ? ($data['progress'] ?? null) : null,
            ];
        } catch (\Throwable $e) {
            Log::error('[DocConverterClient] Get failed', ['error' => $e->getMessage(), 'url' => $url]);
            return ['success' => false, 'error' => MicroserviceErrorHelper::userMessage('Doc-converter service', $e->getMessage(), $e), 'data' => null];
        }
    }

    private function headers(): array
    {
        $headers = [];
        $key = $this->apiKey();
        if (! empty($key)) {
            $headers['X-API-Key'] = $key;
        }
        return $headers;
    }

    /** Doc-converter returns FileResponse (binary) for single-file conversion/PDF/Office result. */
    private function isBinaryContentType(?string $contentType): bool
    {
        if (empty($contentType)) {
            return false;
        }
        $primary = strtolower(trim(explode(';', $contentType)[0]));
        return $primary === 'application/octet-stream'
            || $primary === 'application/pdf'
            || str_starts_with($primary, 'image/')
            || str_contains($primary, 'vnd.openxmlformats-officedocument')
            || str_contains($primary, 'vnd.ms-');
    }

    private function looksLikeJson(string $body): bool
    {
        $trimmed = ltrim($body);
        return str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[');
    }
}
