<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GeneratedFile;
use App\Models\Plan;
use App\Services\GuestUserService;
use App\Services\Tools\PptMicroserviceClient;
use App\Services\UsageValidatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PresentationController extends Controller
{
    public function __construct(
        protected PptMicroserviceClient $pptClient,
        protected UsageValidatorService $usageValidator,
        protected GuestUserService $guestUserService
    ) {}

    private function checkQuota(Request $request): ?\Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $guestSession = null;
        $plan = null;
        if (!$user) {
            $guestSession = $this->guestUserService->getGuestSessionFromRequest($request);
            $region = $request->segment(3) ?? 'local';
            $plan = Plan::where('is_default', true)->where('region', $region)->first() ?? Plan::where('is_default', true)->first();
        }
        $result = $this->usageValidator->checkQuota($user, false, $guestSession, $plan);
        if (!empty($result['error'])) {
            return response()->json(['error' => $result['message'] ?? 'Quota exceeded.'], 403);
        }
        return null;
    }

    /**
     * POST /api/v1/{region}/presentations/generate-outline
     */
    public function generateOutline(Request $request)
    {
        if ($response = $this->checkQuota($request)) {
            return $response;
        }
        $request->validate([
            'content' => 'required|string|max:32000',
            'language' => 'nullable|string|max:50',
            'tone' => 'nullable|string|max:50',
            'length' => 'nullable|string|max:50',
        ]);

        $result = $this->pptClient->submitOutline(
            $request->input('content'),
            $request->input('language', 'English'),
            $request->input('tone', 'Professional'),
            $request->input('length', 'Medium')
        );

        if (!$result['success']) {
            return response()->json(['success' => false, 'error' => $result['error'] ?? 'Presentation service is temporarily unavailable. Please try again later.'], 502);
        }
        if (empty($result['job_id'])) {
            return response()->json(['success' => false, 'error' => 'Presentation service did not return a job. Please try again.'], 502);
        }

        return response()->json([
            'success' => true,
            'job_id' => $result['job_id'],
            'message' => $result['message'] ?? 'Outline generation started.',
        ]);
    }

    /**
     * POST /api/v1/{region}/presentations/generate-content
     */
    public function generateContent(Request $request)
    {
        if ($response = $this->checkQuota($request)) {
            return $response;
        }
        $request->validate([
            'outline' => 'required|array',
            'outline.title' => 'nullable|string',
            'outline.slides' => 'nullable|array',
            'language' => 'nullable|string|max:50',
            'tone' => 'nullable|string|max:50',
            'detail_level' => 'nullable|string|max:50',
        ]);

        $result = $this->pptClient->submitContent(
            $request->input('outline'),
            $request->input('language', 'English'),
            $request->input('tone', 'Professional'),
            $request->input('detail_level', 'Medium')
        );

        if (!$result['success']) {
            return response()->json(['success' => false, 'error' => $result['error'] ?? 'Failed to submit content.'], 502);
        }

        return response()->json([
            'success' => true,
            'job_id' => $result['job_id'],
            'message' => $result['message'] ?? 'Content generation started.',
        ]);
    }

    /**
     * POST /api/v1/{region}/presentations/export
     */
    public function export(Request $request)
    {
        if ($response = $this->checkQuota($request)) {
            return $response;
        }
        $request->validate([
            'content' => 'required|array',
            'content.title' => 'nullable|string',
            'content.slides' => 'nullable|array',
            'random_id' => 'required|string|max:64',
            'template' => 'nullable|string|max:100',
            'color_scheme' => 'nullable|string|max:50',
            'font_style' => 'nullable|string|max:50',
        ]);

        $content = $request->input('content');
        $contentForExport = $this->transformContentForExport($content);

        $result = $this->pptClient->submitExport(
            $contentForExport,
            $request->input('random_id'),
            $request->input('template', 'corporate_blue'),
            $request->input('color_scheme', 'blue'),
            $request->input('font_style', 'modern'),
            $request->user()?->id
        );

        if (!$result['success']) {
            return response()->json(['success' => false, 'error' => $result['error'] ?? 'Failed to submit export.'], 502);
        }

        return response()->json([
            'success' => true,
            'job_id' => $result['job_id'],
            'message' => $result['message'] ?? 'Export started.',
        ]);
    }

    /**
     * Normalize content for tools/ppt POST /export (same shape as zooys processExportJob).
     * Frontend sends slides with { title, content }; tools/ppt expects slide_number, header, subheaders, slide_type, content (string).
     */
    private function transformContentForExport(array $content): array
    {
        $title = $content['title'] ?? 'Presentation';
        $slidesRaw = $content['slides'] ?? [];
        if (! is_array($slidesRaw)) {
            $slidesRaw = [];
        }

        $slides = [];
        foreach ($slidesRaw as $i => $slide) {
            if (! is_array($slide)) {
                continue;
            }
            $contentVal = $slide['content'] ?? '';
            if (is_array($contentVal)) {
                $contentVal = implode("\n", $contentVal);
            }
            $slides[] = [
                'slide_number' => (int) ($slide['slide_number'] ?? $i + 1),
                'header' => (string) ($slide['header'] ?? $slide['title'] ?? ''),
                'subheaders' => isset($slide['subheaders']) && is_array($slide['subheaders']) ? $slide['subheaders'] : [],
                'slide_type' => (string) ($slide['slide_type'] ?? 'content'),
                'content' => (string) $contentVal,
            ];
        }

        return [
            'title' => (string) $title,
            'slides' => $slides,
        ];
    }

    /**
     * GET /api/v1/{region}/presentations/templates
     * Returns available presentation styles for step 3 (user chooses before export).
     */
    public function templates(Request $request)
    {
        $templates = $this->pptClient->getTemplates();
        return response()->json([
            'success' => true,
            'templates' => $templates,
        ]);
    }

    /**
     * GET /api/v1/{region}/presentations/status?job_id=
     */
    public function status(Request $request)
    {
        $jobId = $request->query('job_id');
        if (empty($jobId)) {
            return response()->json(['success' => false, 'error' => 'job_id required.'], 400);
        }

        $result = $this->pptClient->getJobStatus($jobId);
        if (!$result['success']) {
            return response()->json(['success' => false, 'error' => $result['error'] ?? 'Failed to get status.'], 502);
        }

        $data = $result['data'] ?? [];
        $raw = $data['status'] ?? $result['status'] ?? 'unknown';
        $status = ($raw === 'complete' || $raw === 'done') ? 'completed' : $raw;
        return response()->json([
            'success' => true,
            'status' => $status,
            'progress' => $data['progress'] ?? $result['progress'] ?? 0,
            'data' => $data,
        ]);
    }

    /**
     * GET /api/v1/{region}/presentations/result?job_id=&session_id=&message_id=
     * Fetches result from microservice, downloads and stores the file in backend storage,
     * and returns file_id. Files are persisted so they remain available in chat history.
     * Optional session_id and message_id link the file to the chat message.
     */
    public function result(Request $request)
    {
        $jobId = $request->query('job_id');
        if (empty($jobId)) {
            return response()->json(['success' => false, 'error' => 'job_id required.'], 400);
        }

        $maxAttempts = 3;
        $attempt = 0;
        $response = null;

        while ($attempt < $maxAttempts) {
            $result = $this->pptClient->getJobResult($jobId);
            if (!$result['success']) {
                return response()->json(['success' => false, 'error' => $result['error'] ?? 'Failed to get result.'], 502);
            }

            $data = $result['data'] ?? [];
            $inner = is_array($data) && isset($data['data']) ? $data['data'] : $data;
            $response = ['success' => true, 'data' => $inner, 'metadata' => is_array($data) && isset($data['metadata']) ? $data['metadata'] : null];
            if (! is_array($inner)) {
                return response()->json($response);
            }

            $nested = is_array($inner['data'] ?? null) ? $inner['data'] : [];
            $resultBlob = is_array($inner['result'] ?? null) ? $inner['result'] : (is_array($data['result'] ?? null) ? $data['result'] : []);
            $downloadUrl = $inner['download_url'] ?? $inner['file_url'] ?? $inner['output_url'] ?? $inner['result_url'] ?? $data['download_url'] ?? $data['file_url'] ?? $data['output_url'] ?? $data['result_url'] ?? $nested['download_url'] ?? $nested['file_url'] ?? null;
            $downloadUrl = is_string($downloadUrl) ? trim($downloadUrl) : null;
            if ($downloadUrl === '') {
                $downloadUrl = null;
            }
            if (empty($downloadUrl)) {
                $downloadUrl = $inner['file'] ?? $data['file'] ?? null;
                $downloadUrl = is_string($downloadUrl) ? trim($downloadUrl) : null;
            }
            $fileContentB64 = $inner['file_content'] ?? $inner['output_base64'] ?? $inner['content_base64']
                ?? $data['file_content'] ?? $data['output_base64'] ?? $data['content_base64'] ?? $nested['file_content']
                ?? (is_string($resultBlob['file_content'] ?? null) ? $resultBlob['file_content'] : null)
                ?? null;
            $rawBody = $data['_raw_body'] ?? $inner['_raw_body'] ?? null;

            if (! empty($downloadUrl) || ! empty($fileContentB64) || (! empty($rawBody) && is_string($rawBody))) {
                break;
            }
            $attempt++;
            if ($attempt < $maxAttempts) {
                sleep(2);
            }
        }

        $fileId = (string) Str::uuid();
        $rawName = $inner['filename'] ?? $data['filename'] ?? 'presentation';
        $filename = str_ends_with(strtolower($rawName), '.pptx') ? $rawName : $rawName . '.pptx';
        $path = 'presentations/' . $fileId . '.pptx';

        // Ensure storage/app/private and presentations dir exist (same root cause as "no file was produced")
        $privateRoot = storage_path('app/private');
        $presentationsDir = $privateRoot . DIRECTORY_SEPARATOR . 'presentations';
        if (! is_dir($privateRoot)) {
            @mkdir($privateRoot, 0775, true);
        }
        if (! is_dir($presentationsDir)) {
            @mkdir($presentationsDir, 0775, true);
        }
        if (! Storage::disk('local')->exists('presentations')) {
            Storage::disk('local')->makeDirectory('presentations');
        }

        $fileMeta = $this->generatedFileMetaForChat($request);

        // Export result may be returned as binary (FileResponse) instead of JSON
        $rawBody = $data['_raw_body'] ?? $inner['_raw_body'] ?? null;
        if (empty($response['file_id']) && ! empty($rawBody) && is_string($rawBody)) {
            $written = Storage::disk('local')->put($path, $rawBody);
            if ($written) {
                GeneratedFile::create(array_merge([
                    'id' => $fileId, 'path' => $path, 'filename' => $filename, 'type' => 'presentation',
                ], $fileMeta));
                $response['file_id'] = $fileId;
                $response['filename'] = $filename;
                unset($inner['_raw_body'], $inner['_content_type']);
                $response['data'] = $inner;
                Log::info('[PresentationController] Presentation saved from binary response', ['job_id' => $jobId, 'file_id' => $fileId]);
            } else {
                Log::error('[PresentationController] Failed to save presentation from binary (storage not writable?)', [
                    'job_id' => $jobId,
                    'hint' => 'Run ./fix-permissions.sh on the server.',
                ]);
                $response['error'] = 'Storage directory is not writable. On the server run: ./fix-permissions.sh from the backend directory.';
            }
        }

        if (empty($response['file_id']) && ! empty($downloadUrl)) {
            try {
                $headers = [];
                $apiKey = config('services.presentation.api_key');
                if (! empty($apiKey)) {
                    $headers['X-API-Key'] = $apiKey;
                }
                $fileResponse = Http::withHeaders($headers)->timeout(60)->get($downloadUrl);
                if ($fileResponse->successful()) {
                    $written = Storage::disk('local')->put($path, $fileResponse->body());
                    if ($written) {
                        GeneratedFile::create(array_merge([
                            'id' => $fileId, 'path' => $path, 'filename' => $filename, 'type' => 'presentation',
                        ], $fileMeta));
                        $response['file_id'] = $fileId;
                        $response['filename'] = $filename;
                        unset($inner['file_id'], $inner['file_content'], $inner['download_url'], $inner['file_url']);
                        $response['data'] = $inner;
                    } else {
                        Log::error('[PresentationController] Failed to save presentation file from URL (storage not writable?)', [
                            'path' => $path,
                            'full_path' => storage_path('app/private/' . $path),
                            'job_id' => $jobId,
                            'hint' => 'Run ./fix-permissions.sh on the server.',
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[PresentationController] Failed to fetch presentation from URL', ['url' => $downloadUrl, 'error' => $e->getMessage()]);
            }
        } elseif (! empty($fileContentB64)) {
            $decoded = base64_decode($fileContentB64, true);
            if ($decoded !== false && strlen($decoded) > 0) {
                $written = Storage::disk('local')->put($path, $decoded);
                if ($written) {
                    GeneratedFile::create(array_merge([
                        'id' => $fileId, 'path' => $path, 'filename' => $filename, 'type' => 'presentation',
                    ], $fileMeta));
                    $response['file_id'] = $fileId;
                    $response['filename'] = $filename;
                    unset($inner['file_id'], $inner['file_content']);
                    $response['data'] = $inner;
                    Log::info('[PresentationController] Presentation saved from base64', ['job_id' => $jobId, 'file_id' => $fileId]);
                } else {
                    Log::error('[PresentationController] Failed to save presentation file (storage not writable?)', [
                        'path' => $path,
                        'job_id' => $jobId,
                        'hint' => 'Run ./fix-permissions.sh on the server.',
                    ]);
                    $response['error'] = 'Storage directory is not writable. On the server run: ./fix-permissions.sh from the backend directory.';
                }
            } else {
                Log::warning('[PresentationController] Presentation file_content decode failed or empty', ['job_id' => $jobId]);
            }
        }

        if (empty($response['file_id'])) {
            Log::warning('[PresentationController] Result has no file', [
                'job_id' => $jobId,
                'had_download_url' => ! empty($downloadUrl),
                'had_file_content' => ! empty($fileContentB64),
                'data_keys' => array_keys($data),
                'inner_keys' => array_keys($inner),
            ]);
        }

        return response()->json($response);
    }

    /**
     * Optional meta to link a generated file to the chat message (for history/audit).
     */
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

    /**
     * GET /api/v1/{region}/presentations/files/{fileId}/download
     * Serves the presentation file from backend storage (persisted for chat history).
     */
    public function download(Request $request, string $fileId)
    {
        $file = GeneratedFile::where('id', $fileId)->where('type', 'presentation')->first();
        if (! $file) {
            Log::warning('[PresentationController] Download: no record', ['file_id' => $fileId]);
            return response()->json(['error' => 'File not found.'], 404);
        }
        if (! Storage::disk('local')->exists($file->path)) {
            Log::warning('[PresentationController] Download: record exists but file missing (check storage or use shared disk for multiple servers)', ['file_id' => $fileId, 'path' => $file->path]);
            return response()->json(['error' => 'File not found.'], 404);
        }
        return Storage::disk('local')->download($file->path, $file->filename ?? $fileId . '.pptx');
    }
}
