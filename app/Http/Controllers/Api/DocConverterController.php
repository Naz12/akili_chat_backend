<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GeneratedFile;
use App\Models\GuestSession;
use App\Models\Plan;
use App\Models\User;
use App\Services\GuestUserService;
use App\Services\Tools\DocConverterClient;
use App\Services\UsageMeterService;
use App\Services\UsageValidatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocConverterController extends Controller
{
    private const CACHE_PREFIX = 'doc_convert_job:';

    private const CACHE_TTL_SECONDS = 3600;

    private const DOWNLOAD_CACHE_PREFIX = 'doc_converter_download:';

    private const STORAGE_SUBDIR = 'doc-converter';

    public function __construct(
        protected DocConverterClient $docConverterClient,
        protected UsageValidatorService $usageValidator,
        protected GuestUserService $guestUserService,
        protected UsageMeterService $usageMeterService
    ) {}

    private function checkQuota(Request $request): ?\Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $guestSession = null;
        $plan = null;
        if (! $user) {
            $guestSession = $this->guestUserService->getGuestSessionFromRequest($request);
            $region = $request->segment(3) ?? 'local';
            $plan = Plan::where('is_default', true)->where('region', $region)->first() ?? Plan::where('is_default', true)->first();
        }
        $result = $this->usageValidator->checkQuota($user, false, $guestSession, $plan);
        if (! empty($result['error'])) {
            return response()->json(['error' => $result['message'] ?? 'Quota exceeded.'], 403);
        }
        return null;
    }

    /**
     * POST /api/v1/{region}/doc-converter/convert
     * Expects multipart: file, target_format (e.g. text, md).
     */
    public function convert(Request $request)
    {
        if ($response = $this->checkQuota($request)) {
            return $response;
        }
        $request->validate([
            'file' => 'required|file|max:51200',
            'target_format' => 'required|string|max:20',
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();
        $targetFormat = $request->input('target_format');

        $result = $this->docConverterClient->convertDocument($path, $targetFormat);

        if (! $result['success']) {
            return response()->json(['success' => false, 'error' => $result['error'] ?? 'Doc-converter service is temporarily unavailable. Please try again later.'], 502);
        }
        $jobId = $result['job_id'] ?? null;
        if ($jobId === null || $jobId === '') {
            return response()->json(['success' => false, 'error' => 'Doc-converter service did not return a job. Please try again.'], 502);
        }
        if ($jobId) {
            $this->cacheJobPayload($request, $jobId, 'convert', ['target_format' => $targetFormat]);
        }

        return response()->json([
            'success' => true,
            'job_id' => $jobId,
            'message' => $result['message'] ?? 'Conversion started.',
        ]);
    }

    /**
     * POST /api/v1/{region}/doc-converter/extract
     * Expects multipart: file; optional extraction_type (text, metadata, both). Default text.
     */
    public function extract(Request $request)
    {
        if ($response = $this->checkQuota($request)) {
            return $response;
        }
        $request->validate([
            'file' => 'required|file|max:51200',
            'extraction_type' => 'nullable|string|in:text,metadata,both',
        ]);

        $path = $request->file('file')->getRealPath();
        $extractionType = $request->input('extraction_type', 'text');
        $result = $this->docConverterClient->extractDocument($path, $extractionType);

        if (! $result['success']) {
            return response()->json(['success' => false, 'error' => $result['error'] ?? 'Doc-converter service is temporarily unavailable.'], 502);
        }
        $jobId = $result['job_id'] ?? null;
        if (empty($jobId)) {
            return response()->json(['success' => false, 'error' => 'Doc-converter service did not return a job.'], 502);
        }
        $this->cacheJobPayload($request, $jobId, 'extract');
        return response()->json([
            'success' => true,
            'job_id' => $jobId,
            'message' => $result['message'] ?? 'Extraction started.',
        ]);
    }

    /**
     * POST /api/v1/{region}/doc-converter/merge
     * Expects multipart: files[] (multiple files). Merges into one document (e.g. PDF).
     */
    public function merge(Request $request)
    {
        if ($response = $this->checkQuota($request)) {
            return $response;
        }
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'required|file|max:51200',
        ]);

        $paths = [];
        foreach ($request->file('files') as $file) {
            $paths[] = $file->getRealPath();
        }
        $result = $this->docConverterClient->mergeDocuments($paths);

        if (! $result['success']) {
            return response()->json(['success' => false, 'error' => $result['error'] ?? 'Doc-converter service is temporarily unavailable.'], 502);
        }
        $jobId = $result['job_id'] ?? null;
        if (empty($jobId)) {
            return response()->json(['success' => false, 'error' => 'Doc-converter service did not return a job.'], 502);
        }
        $this->cacheJobPayload($request, $jobId, 'merge');
        return response()->json([
            'success' => true,
            'job_id' => $jobId,
            'message' => $result['message'] ?? 'Merge started.',
        ]);
    }

    /**
     * POST /api/v1/{region}/doc-converter/split
     * Expects multipart: file (single document to split, e.g. PDF by pages).
     */
    public function split(Request $request)
    {
        if ($response = $this->checkQuota($request)) {
            return $response;
        }
        $request->validate([
            'file' => 'required|file|max:51200',
        ]);

        $path = $request->file('file')->getRealPath();
        $splitPoints = $request->input('split_points', '');
        $result = $this->docConverterClient->splitDocument($path, ['split_points' => $splitPoints]);

        if (! $result['success']) {
            return response()->json(['success' => false, 'error' => $result['error'] ?? 'Doc-converter service is temporarily unavailable.'], 502);
        }
        $jobId = $result['job_id'] ?? null;
        if (empty($jobId)) {
            return response()->json(['success' => false, 'error' => 'Doc-converter service did not return a job.'], 502);
        }
        $this->cacheJobPayload($request, $jobId, 'split');
        return response()->json([
            'success' => true,
            'job_id' => $jobId,
            'message' => $result['message'] ?? 'Split started.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra  Extra keys to store (e.g. target_format for convert)
     */
    private function cacheJobPayload(Request $request, string $jobId, string $operation = 'convert', array $extra = []): void
    {
        $user = $request->user();
        $guestSession = ! $user ? $this->guestUserService->getGuestSessionFromRequest($request) : null;
        $sessionId = $request->input('session_id');
        Cache::put(self::CACHE_PREFIX . $jobId, array_merge([
            'user_id' => $user?->id,
            'guest_session_id' => $guestSession?->id,
            'chat_session_id' => $sessionId,
            'operation' => $operation,
        ], $extra), self::CACHE_TTL_SECONDS);
    }

    /** PDF operations that use /v1/pdf/{operation}/status and result. */
    private const PDF_OPERATIONS = [
        'compress', 'watermark', 'page_numbers', 'annotate', 'protect', 'unlock', 'preview', 'edit_pdf', 'batch',
    ];

    private function getStatusByOperation(string $jobId, string $operation): array
    {
        return match ($operation) {
            'merge' => $this->docConverterClient->getMergeStatus($jobId),
            'split' => $this->docConverterClient->getSplitStatus($jobId),
            'extract' => $this->docConverterClient->getExtractionStatus($jobId),
            default => in_array($operation, self::PDF_OPERATIONS, true)
                ? $this->docConverterClient->getPdfOperationStatus($jobId, $operation)
                : $this->docConverterClient->getConversionStatus($jobId),
        };
    }

    private function getResultByOperation(string $jobId, string $operation): array
    {
        return match ($operation) {
            'merge' => $this->docConverterClient->getMergeResult($jobId),
            'split' => $this->docConverterClient->getSplitResult($jobId),
            'extract' => $this->docConverterClient->getExtractionResult($jobId),
            default => in_array($operation, self::PDF_OPERATIONS, true)
                ? $this->docConverterClient->getPdfOperationResult($jobId, $operation)
                : $this->docConverterClient->getConversionResult($jobId),
        };
    }

    /**
     * POST /api/v1/{region}/doc-converter/pdf/{operation}
     * Single entry for PDF operations: compress, watermark, page_numbers, annotate, protect, unlock, preview, edit_pdf, batch_process.
     * Validation per operation (see tools/doc-convertor ENDPOINTS.md).
     */
    public function pdfOperation(Request $request, string $operation)
    {
        $allowed = [
            'compress', 'watermark', 'page_numbers', 'annotate', 'protect', 'unlock', 'preview', 'edit_pdf', 'batch_process',
        ];
        if (! in_array($operation, $allowed, true)) {
            return response()->json(['success' => false, 'error' => 'Unsupported operation. Allowed: ' . implode(', ', $allowed)], 422);
        }

        if ($response = $this->checkQuota($request)) {
            return $response;
        }

        $validationError = $this->validatePdfOperationRequest($request, $operation);
        if ($validationError !== null) {
            return response()->json(['success' => false, 'error' => $validationError], 422);
        }

        if ($operation !== 'batch_process') {
            $request->validate(['file' => 'required|file|max:51200']);
        } else {
            $request->validate(['files' => 'required|array', 'files.*' => 'required|file|max:51200']);
        }

        if ($operation === 'batch_process') {
            $operations = $request->input('operations', []);
            if (! is_array($operations) || empty($operations)) {
                return response()->json(['success' => false, 'error' => 'operations (array) is required for batch_process.'], 422);
            }
            $paths = $this->resolveFilePathsFromRequest($request, true);
            if (count($paths) < 1) {
                return response()->json(['success' => false, 'error' => 'files (multipart, at least one file) is required for batch_process.'], 422);
            }
            $result = $this->docConverterClient->batchProcess($paths, $operations);
        } else {
            $path = $this->resolveSingleFilePath($request);
            if ($path === null) {
                return response()->json(['success' => false, 'error' => 'file is required.'], 422);
            }
            $params = $this->buildPdfOperationParams($request, $operation);
            $result = $this->docConverterClient->postPdfOperation($operation, $path, $params);
        }

        if (! $result['success'] || empty($result['job_id'])) {
            return response()->json(['success' => false, 'error' => $result['error'] ?? 'Doc-converter service is temporarily unavailable.'], 502);
        }

        $jobId = $result['job_id'];
        $cacheOp = $operation === 'batch_process' ? 'batch' : $operation;
        $this->cacheJobPayload($request, $jobId, $cacheOp);

        return response()->json([
            'success' => true,
            'job_id' => $jobId,
            'message' => $result['message'] ?? ucfirst(str_replace('_', ' ', $operation)) . ' job started.',
        ]);
    }

    private function validatePdfOperationRequest(Request $request, string $operation): ?string
    {
        if ($operation === 'batch_process') {
            $files = $request->file('files');
            if (! is_array($files) || count($files) < 1) {
                return 'files (multipart, at least one file) is required for batch_process.';
            }
            return null;
        }

        if ($operation === 'protect') {
            if (empty($request->input('password'))) {
                return 'password is required for protect.';
            }
            return null;
        }
        if ($operation === 'unlock') {
            if (empty($request->input('password'))) {
                return 'password is required for unlock.';
            }
            return null;
        }
        if ($operation === 'watermark') {
            if (empty($request->input('watermark_type')) || ! in_array($request->input('watermark_type'), ['text', 'image'], true)) {
                return 'watermark_type (text or image) is required for watermark.';
            }
            if (empty($request->input('watermark_content'))) {
                return 'watermark_content is required for watermark.';
            }
            return null;
        }
        if ($operation === 'edit_pdf') {
            if (empty($request->input('page_order'))) {
                return 'page_order is required for edit_pdf (e.g. reverse, as_is, or comma-separated page numbers).';
            }
            return null;
        }
        if ($operation === 'annotate') {
            $ann = $request->input('annotations');
            if ($ann === null || $ann === '') {
                return 'annotations (JSON array) is required for annotate.';
            }
            if (is_string($ann)) {
                $decoded = json_decode($ann, true);
                if (! is_array($decoded)) {
                    return 'annotations must be a valid JSON array.';
                }
            }
            return null;
        }

        return null;
    }

    private function buildPdfOperationParams(Request $request, string $operation): array
    {
        $params = [];
        $allowedKeys = match ($operation) {
            'compress' => ['compression_level', 'quality'],
            'watermark' => ['watermark_type', 'watermark_content', 'position_x', 'position_y', 'rotation', 'opacity', 'color', 'font_family', 'font_size', 'apply_to_all', 'selected_pages'],
            'page_numbers' => ['position', 'format_type', 'font_size', 'page_ranges'],
            'annotate' => ['annotations'],
            'protect' => ['password', 'allow_print', 'allow_modify', 'allow_copy', 'allow_form_fill'],
            'unlock' => ['password'],
            'preview' => ['page_numbers', 'thumbnail_width', 'thumbnail_height', 'zoom'],
            'edit_pdf' => ['page_order', 'remove_blank_pages', 'remove_pages'],
            default => [],
        };
        foreach ($allowedKeys as $key) {
            $value = $request->input($key);
            if ($value === null || $value === '') {
                continue;
            }
            if (is_bool($value)) {
                $params[$key] = $value ? 'true' : 'false';
            } elseif (is_array($value) || is_object($value)) {
                $params[$key] = json_encode($value);
            } else {
                $params[$key] = $value;
            }
        }
        return $params;
    }

    private function resolveSingleFilePath(Request $request): ?string
    {
        $file = $request->file('file');
        if (! $file || ! $file->isValid()) {
            return null;
        }
        return $file->getRealPath();
    }

    private function resolveFilePathsFromRequest(Request $request, bool $forMergeOrBatch): array
    {
        if ($forMergeOrBatch) {
            $files = $request->file('files');
            if (! is_array($files)) {
                return [];
            }
            $paths = [];
            foreach ($files as $f) {
                if ($f && $f->isValid()) {
                    $paths[] = $f->getRealPath();
                }
            }
            return $paths;
        }
        $single = $this->resolveSingleFilePath($request);
        return $single ? [$single] : [];
    }

    /**
     * GET /api/v1/{region}/doc-converter/status?job_id=
     */
    public function status(Request $request)
    {
        $jobId = $request->query('job_id');
        if (empty($jobId)) {
            return response()->json(['success' => false, 'error' => 'job_id required.'], 400);
        }

        $payload = Cache::get(self::CACHE_PREFIX . $jobId);
        $operation = is_array($payload) ? ($payload['operation'] ?? 'convert') : 'convert';
        $result = $this->getStatusByOperation($jobId, $operation);

        if (! $result['success']) {
            return response()->json(['success' => false, 'error' => $result['error'] ?? 'Failed to get status.'], 502);
        }

        $data = $result['data'] ?? [];
        $raw = $data['status'] ?? $result['status'] ?? null;
        if ($raw === null && is_array($data)) {
            $raw = $data['status'] ?? 'unknown';
        }
        $status = ($raw === 'complete' || $raw === 'done') ? 'completed' : $raw;
        $payload = [
            'success' => true,
            'status' => $status ?? 'unknown',
            'progress' => $data['progress'] ?? $result['progress'] ?? 0,
            'data' => $data,
        ];
        // Forward microservice error when failed (same as zooys) so UI can show reason
        if ($status === 'failed') {
            $payload['error'] = $data['error'] ?? $data['message'] ?? $result['error'] ?? null;
        }
        return response()->json($payload);
    }

    /**
     * GET /api/v1/{region}/doc-converter/result?job_id=
     * Returns result (content or download URL); records usage when result is successful.
     */
    public function result(Request $request)
    {
        $jobId = $request->query('job_id');
        if (empty($jobId)) {
            return response()->json(['success' => false, 'error' => 'job_id required.'], 400);
        }

        $payload = Cache::get(self::CACHE_PREFIX . $jobId);
        $operation = is_array($payload) ? ($payload['operation'] ?? 'convert') : 'convert';
        $result = $this->getResultByOperation($jobId, $operation);

        if (! $result['success']) {
            return response()->json(['success' => false, 'error' => $result['error'] ?? 'Failed to get result.'], 502);
        }

        $data = $result['data'] ?? [];
        $status = is_array($data) ? ($data['status'] ?? null) : null;
        if ($status === 'completed') {
            if (is_array($payload)) {
                $cost = (int) config('tools.usage.doc_converter.cost', 200);
                $user = isset($payload['user_id']) ? User::find($payload['user_id']) : null;
                $guestSession = isset($payload['guest_session_id']) ? GuestSession::find($payload['guest_session_id']) : null;
                $this->usageMeterService->record($cost, 'doc_converter', $user, $guestSession, $payload['chat_session_id'] ?? null);
                Cache::forget(self::CACHE_PREFIX . $jobId);
            }
        }

        $normalized = $this->normalizeDocConverterResultData($data);
        $responseData = is_array($data) ? array_merge($data, $normalized) : $normalized;

        if ($status === 'completed') {
            $responseData = $this->proxyDownloadUrlsToStorage($request, $jobId, $responseData, $payload, $data);
        }

        if ($status === 'failed' && $operation === 'extract') {
            $responseData['content'] = $responseData['content'] ?? '';
            $responseData['note'] = $data['message'] ?? 'Extraction did not produce text (e.g. empty or image-only PDF).';
        }

        return response()->json([
            'success' => true,
            'data' => $responseData,
        ]);
    }

    /**
     * GET /api/v1/{region}/doc-converter/download?job_id=
     * Serves a doc-converter output file (legacy; prefer files/{fileId}/download for chat history).
     */
    public function download(Request $request)
    {
        $jobId = $request->query('job_id');
        if (empty($jobId)) {
            return response()->json(['error' => 'job_id required.'], 400);
        }
        $cached = Cache::get(self::DOWNLOAD_CACHE_PREFIX . $jobId);
        if (! is_array($cached)) {
            return response()->json(['error' => 'File not found or expired.'], 404);
        }
        $fileId = $cached['file_id'] ?? null;
        if ($fileId) {
            $file = GeneratedFile::where('id', $fileId)->where('type', 'doc_converter')->first();
            if ($file && Storage::disk('local')->exists($file->path)) {
                return Storage::disk('local')->download($file->path, $file->filename ?? 'converted');
            }
        }
        $path = $cached['path'] ?? null;
        $filename = $cached['filename'] ?? 'download';
        if (empty($path) || ! Storage::disk('local')->exists($path)) {
            Cache::forget(self::DOWNLOAD_CACHE_PREFIX . $jobId);
            return response()->json(['error' => 'File not found.'], 404);
        }
        return Storage::disk('local')->download($path, $filename);
    }

    /**
     * GET /api/v1/{region}/doc-converter/files/{fileId}/download
     * Serves a doc-converter file from backend storage (persisted for chat history, like diagram/PPT).
     */
    public function downloadFile(Request $request, string $fileId)
    {
        $file = GeneratedFile::where('id', $fileId)->where('type', 'doc_converter')->first();
        if (! $file) {
            Log::warning('[DocConverterController] Download: no record', ['file_id' => $fileId]);
            return response()->json(['error' => 'File not found.'], 404);
        }
        if (! Storage::disk('local')->exists($file->path)) {
            Log::warning('[DocConverterController] Download: record exists but file missing', ['file_id' => $fileId, 'path' => $file->path]);
            return response()->json(['error' => 'File not found.'], 404);
        }
        return Storage::disk('local')->download($file->path, $file->filename ?? $fileId);
    }

    /**
     * If result has external download URL(s), fetch file(s) from microservice, save to storage,
     * create GeneratedFile for chat history, and set download_url/file_id (like diagram/PPT).
     *
     * @param  array<string, mixed>  $rawData  Raw result data from microservice (for base64 fallback)
     */
    private function proxyDownloadUrlsToStorage(Request $request, string $jobId, array $responseData, mixed $payload, array $rawData = []): array
    {
        $baseUrl = rtrim((string) config('services.doc_converter.url', ''), '/');
        $apiKey = config('services.doc_converter.api_key');
        $headers = $apiKey ? ['X-API-Key' => $apiKey] : [];
        $region = $request->segment(3) ?? 'local';
        $appUrl = rtrim(config('app.url'), '/');
        $ourDownloadUrlByJob = $appUrl . '/api/v1/' . $region . '/doc-converter/download?job_id=' . urlencode($jobId);
        $ourDownloadUrlByFileId = static fn (string $fileId) => $appUrl . '/api/v1/' . $region . '/doc-converter/files/' . $fileId . '/download';

        $targetFormat = is_array($payload) ? ($payload['target_format'] ?? null) : null;
        $operation = is_array($payload) ? ($payload['operation'] ?? 'convert') : 'convert';
        $ext = $this->extensionFromFormatOrOperation($targetFormat, $operation);

        // Doc-converter returns FileResponse (binary) for single-file result; body is in _raw_body
        $rawBody = $rawData['_raw_body'] ?? $responseData['_raw_body'] ?? null;
        if (empty($responseData['download_url']) && ! empty($rawBody) && is_string($rawBody)) {
            // Use Content-Type for extension only when we don't have user-requested target_format (so we don't save as .pdf when user asked for JPG)
            $contentType = $rawData['_content_type'] ?? null;
            $extFromType = $this->extensionFromContentType($contentType);
            if ($extFromType && (empty($targetFormat) || $operation !== 'convert')) {
                $ext = $extFromType;
            }
            $fileId = $this->saveDocConverterOutput($request, $jobId, $rawBody, $ext);
            if ($fileId) {
                $responseData['file_id'] = $fileId;
                $responseData['download_url'] = $ourDownloadUrlByFileId($fileId);
            }
            unset($responseData['_raw_body'], $responseData['_content_type']);
        }

        $urlToFetch = $responseData['download_url'] ?? null;
        if (empty($urlToFetch)) {
            $urls = $responseData['download_urls'] ?? [];
            $urlToFetch = is_array($urls) && isset($urls[0]) ? $urls[0] : null;
        }
        $fetched = $this->fetchOneUrlToStorage($request, $jobId, $urlToFetch, $baseUrl, $headers, $ext, $responseData, $ourDownloadUrlByFileId);
        if ($fetched) {
            $responseData = $fetched;
        }

        // Fallback: result had no usable URL; get download_urls from status endpoint (like zooys)
        if (empty($responseData['file_id']) && $baseUrl !== '') {
            $statusResult = $this->docConverterClient->getConversionStatus($jobId);
            if ($statusResult['success'] && is_array($statusResult['data'] ?? null)) {
                $statusUrls = $statusResult['data']['download_urls'] ?? [];
                $firstStatusUrl = is_array($statusUrls) && isset($statusUrls[0]) ? $statusUrls[0] : null;
                if ($firstStatusUrl && is_string($firstStatusUrl)) {
                    $fetched = $this->fetchOneUrlToStorage($request, $jobId, $firstStatusUrl, $baseUrl, $headers, $ext, $responseData, $ourDownloadUrlByFileId);
                    if ($fetched) {
                        $responseData = $fetched;
                    }
                }
            }
        }

        $inner = $rawData['data'] ?? $rawData;
        $base64Content = $rawData['file_content'] ?? $rawData['content_base64'] ?? $rawData['output_base64']
            ?? (is_array($inner) ? ($inner['file_content'] ?? $inner['content_base64'] ?? $inner['output_base64'] ?? null) : null);
        if (empty($responseData['download_url']) && ! empty($base64Content) && is_string($base64Content)) {
            $decoded = base64_decode($base64Content, true);
            if ($decoded !== false && strlen($decoded) > 0) {
                $fileId = $this->saveDocConverterOutput($request, $jobId, $decoded, $ext);
                if ($fileId) {
                    $responseData['file_id'] = $fileId;
                    $responseData['download_url'] = $ourDownloadUrlByFileId($fileId);
                }
            }
        }

        unset($responseData['_raw_body'], $responseData['_content_type']);

        return $responseData;
    }

    /**
     * Fetch one URL from the microservice (absolute or relative), save to storage, return responseData with file_id and download_url set.
     * Returns updated responseData on success, null otherwise.
     *
     * @param  array<string, mixed>  $responseData
     */
    private function fetchOneUrlToStorage(Request $request, string $jobId, mixed $urlToFetch, string $baseUrl, array $headers, string $ext, array $responseData, callable $ourDownloadUrlByFileId): ?array
    {
        if (empty($urlToFetch) || ! is_string($urlToFetch)) {
            return null;
        }
        // Ignore plain filenames (e.g. "page_1.jpg") or paths that are not URLs
        if (! str_starts_with($urlToFetch, '/') && ! filter_var($urlToFetch, FILTER_VALIDATE_URL)) {
            return null;
        }
        if (str_starts_with($urlToFetch, '/') && $baseUrl !== '') {
            $urlToFetch = rtrim($baseUrl, '/') . $urlToFetch;
        }
        $isOurDownload = str_contains($urlToFetch, '/doc-converter/');
        if (! filter_var($urlToFetch, FILTER_VALIDATE_URL) || $isOurDownload) {
            return null;
        }
        try {
            $response = Http::withHeaders($headers)->timeout(60)->get($urlToFetch);
            if (! $response->successful()) {
                Log::warning('[DocConverterController] Failed to fetch result file from microservice', ['job_id' => $jobId, 'status' => $response->status(), 'url' => $urlToFetch]);
                return null;
            }
            $body = $response->body();
            $contentType = $response->header('Content-Type');
            $extUsed = $this->extensionFromContentType($contentType) ?: $ext;
            $fileId = $this->saveDocConverterOutput($request, $jobId, $body, $extUsed);
            if (! $fileId) {
                return null;
            }
            $responseData['file_id'] = $fileId;
            $responseData['download_url'] = $ourDownloadUrlByFileId($fileId);
            if (! empty($responseData['download_urls'])) {
                $responseData['download_urls'] = [$ourDownloadUrlByFileId($fileId)];
            }
            return $responseData;
        } catch (\Throwable $e) {
            Log::warning('[DocConverterController] Exception fetching result file', ['job_id' => $jobId, 'error' => $e->getMessage(), 'url' => $urlToFetch]);
            return null;
        }
    }

    /**
     * Save doc-converter output to storage, create GeneratedFile (for chat history like diagram/PPT), cache job_id for legacy download.
     * Returns file_id (UUID) or null on failure.
     */
    private function saveDocConverterOutput(Request $request, string $jobId, string $contents, string $ext): ?string
    {
        $dir = self::STORAGE_SUBDIR;
        if (! Storage::disk('local')->exists($dir)) {
            Storage::disk('local')->makeDirectory($dir, 0775);
        }
        $fileId = (string) Str::uuid();
        $path = $dir . '/' . $fileId . '.' . $ext;
        $filename = 'converted.' . $ext;
        try {
            $written = Storage::disk('local')->put($path, $contents);
            if (! $written) {
                return null;
            }
            $meta = $this->generatedFileMetaForChat($request);
            GeneratedFile::create(array_merge([
                'id' => $fileId,
                'path' => $path,
                'filename' => $filename,
                'type' => 'doc_converter',
            ], $meta));
            Cache::put(self::DOWNLOAD_CACHE_PREFIX . $jobId, [
                'file_id' => $fileId,
                'path' => $path,
                'filename' => $filename,
            ], 3600);
            Log::info('[DocConverterController] Doc-converter file saved for chat history', ['job_id' => $jobId, 'file_id' => $fileId]);
            return $fileId;
        } catch (\Throwable $e) {
            Log::error('[DocConverterController] Failed to save doc-converter output', ['job_id' => $jobId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function generatedFileMetaForChat(Request $request): array
    {
        $user = $request->user();
        $guestSession = $user ? null : $this->guestUserService->getGuestSessionFromRequest($request);
        $sessionId = $request->query('session_id');
        $messageId = $request->query('message_id');

        return array_filter([
            'chat_session_id' => is_string($sessionId) && $sessionId !== '' ? $sessionId : null,
            'chat_message_id' => is_numeric($messageId) ? (int) $messageId : null,
            'user_id' => $user?->id,
            'guest_session_id' => $guestSession?->id,
        ], fn ($v) => $v !== null);
    }

    private function extensionFromFormatOrOperation(?string $targetFormat, string $operation): string
    {
        if (! empty($targetFormat)) {
            $f = strtolower($targetFormat);
            if (in_array($f, ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'md', 'html'], true)) {
                return $f === 'jpeg' ? 'jpg' : $f;
            }
        }
        return match ($operation) {
            'compress', 'watermark', 'page_numbers', 'protect', 'preview', 'edit_pdf' => 'pdf',
            'split' => 'pdf',
            'extract' => 'txt',
            default => 'pdf',
        };
    }

    private function extensionFromContentType(?string $contentType): ?string
    {
        if (empty($contentType)) {
            return null;
        }
        $lower = strtolower(trim(explode(';', $contentType)[0]));
        $map = [
            'application/pdf' => 'pdf',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.ms-excel' => 'xls',
            'application/msword' => 'doc',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'text/plain' => 'txt',
            'text/html' => 'html',
        ];
        if (isset($map[$lower])) {
            return $map[$lower];
        }
        if (str_contains($lower, 'spreadsheetml')) {
            return 'xlsx';
        }
        if (str_contains($lower, 'wordprocessingml')) {
            return 'docx';
        }
        if (str_contains($lower, 'presentationml')) {
            return 'pptx';
        }
        return null;
    }

    /**
     * Normalize microservice result so frontend always gets download_url and/or content.
     * Microservices may return download_url, output_url, result_url, output_files[], file_url, etc.
     */
    private function normalizeDocConverterResultData(mixed $data): array
    {
        $out = [];
        if (! is_array($data)) {
            return $out;
        }
        $inner = $data['data'] ?? $data;
        if (! is_array($inner)) {
            $inner = $data;
        }
        $resultObj = $data['result'] ?? $inner['result'] ?? null;
        if (is_array($resultObj)) {
            $inner = array_merge($inner, $resultObj);
        }
        $singleUrlKeys = [
            'download_url', 'output_url', 'file_url', 'output_file_url', 'result_url', 'result_file_url',
            'converted_file_url', 'converted_url', 'result_download_url', 'output_file', 'file', 'url', 'link',
        ];
        $downloadUrl = null;
        foreach ($singleUrlKeys as $key) {
            $v = $data[$key] ?? $inner[$key] ?? null;
            if (is_string($v) && $v !== '') {
                $downloadUrl = $v;
                break;
            }
            if (is_array($v) && isset($v['url'])) {
                $downloadUrl = $v['url'];
                break;
            }
        }
        $allUrls = [];
        $arrayKeys = ['download_urls', 'output_files', 'files', 'images', 'outputs', 'result_files', 'converted_files'];
        foreach ($arrayKeys as $key) {
            $arr = $data[$key] ?? $inner[$key] ?? null;
            if (is_array($arr)) {
                foreach ($arr as $item) {
                    $u = null;
                    if (is_string($item)) {
                        $u = $item;
                    } elseif (is_array($item)) {
                        $u = $item['url'] ?? $item['download_url'] ?? $item['file_url'] ?? $item['link'] ?? null;
                    }
                    if (! empty($u)) {
                        $allUrls[] = $u;
                    }
                }
                if (empty($downloadUrl) && isset($allUrls[0])) {
                    $downloadUrl = $allUrls[0];
                }
                if (! empty($allUrls)) {
                    break;
                }
            }
        }
        if (empty($downloadUrl) && ! empty($allUrls)) {
            $downloadUrl = $allUrls[0];
        }
        $output = $data['output'] ?? $inner['output'] ?? null;
        if (is_array($output)) {
            $u = $output['url'] ?? $output['download_url'] ?? $output['file_url'] ?? null;
            if (is_string($u) && $u !== '') {
                if (empty($downloadUrl)) {
                    $downloadUrl = $u;
                }
                if (! in_array($u, $allUrls, true)) {
                    $allUrls[] = $u;
                }
            }
            $arr = $output['files'] ?? $output['output_files'] ?? null;
            if (is_array($arr)) {
                foreach ($arr as $item) {
                    $u = is_array($item) ? ($item['url'] ?? $item['download_url'] ?? $item['file_url'] ?? null) : null;
                    if (! empty($u) && ! in_array($u, $allUrls, true)) {
                        $allUrls[] = $u;
                    }
                }
                if (empty($downloadUrl) && isset($allUrls[0])) {
                    $downloadUrl = $allUrls[0];
                }
            }
        }
        if (! empty($downloadUrl)) {
            $out['download_url'] = $downloadUrl;
        }
        if (count($allUrls) > 1) {
            $out['download_urls'] = array_values(array_unique($allUrls));
        }
        $content = $data['content'] ?? $data['text'] ?? $data['extracted_text'] ?? $data['result_text'] ?? $inner['content'] ?? $inner['text'] ?? $inner['extracted_text'] ?? $inner['result_text'] ?? null;
        if ($content !== null && $content !== '') {
            $out['content'] = is_string($content) ? $content : (is_scalar($content) ? (string) $content : json_encode($content));
        }
        $status = $data['status'] ?? $inner['status'] ?? null;
        if ($status === 'completed' && empty($out['download_url']) && empty($out['download_urls']) && empty($out['content'])) {
            Log::info('[DocConverterController] Result completed but no displayable content after normalization', [
                'data_keys' => array_keys($data),
                'inner_keys' => is_array($inner) ? array_keys($inner) : [],
            ]);
        }
        return $out;
    }
}
