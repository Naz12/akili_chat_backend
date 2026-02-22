<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GeneratedFile;
use App\Models\Plan;
use App\Services\GuestUserService;
use App\Services\Tools\DiagramMicroserviceClient;
use App\Services\UsageValidatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DiagramController extends Controller
{
    public function __construct(
        protected DiagramMicroserviceClient $diagramClient,
        protected UsageValidatorService $usageValidator,
        protected GuestUserService $guestUserService
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
     * POST /api/v1/{region}/diagram/generate
     */
    public function generate(Request $request)
    {
        if ($response = $this->checkQuota($request)) {
            return $response;
        }
        $request->validate([
            'prompt' => 'required|string|max:8000',
            'diagram_type' => 'nullable|string|max:50',
            'output_format' => 'nullable|string|max:20',
        ]);

        $result = $this->diagramClient->generateDiagram(
            $request->input('prompt'),
            $request->input('diagram_type', 'flowchart'),
            $request->input('output_format', 'png')
        );

        if (! $result['success']) {
            return response()->json(['success' => false, 'error' => $result['error'] ?? 'Diagram service is temporarily unavailable. Please try again later.'], 502);
        }
        if (empty($result['job_id'])) {
            return response()->json(['success' => false, 'error' => 'Diagram service did not return a job. Please try again.'], 502);
        }
        return response()->json([
            'success' => true,
            'job_id' => $result['job_id'],
            'message' => $result['message'] ?? 'Diagram generation started.',
        ]);
    }

    /**
     * GET /api/v1/{region}/diagram/status?job_id=
     */
    public function status(Request $request)
    {
        $jobId = $request->query('job_id');
        if (empty($jobId)) {
            return response()->json(['success' => false, 'error' => 'job_id required.'], 400);
        }

        $result = $this->diagramClient->getJobStatus($jobId);
        if (! $result['success']) {
            return response()->json(['success' => false, 'error' => $result['error'] ?? 'Failed to get status.'], 502);
        }

        $data = $result['data'] ?? [];
        $raw = $data['status'] ?? $result['status'] ?? 'unknown';
        $status = ($raw === 'complete' || $raw === 'done') ? 'completed' : $raw;
        $response = [
            'success' => true,
            'status' => $status,
            'progress' => $data['progress'] ?? $result['progress'] ?? 0,
            'data' => $data,
        ];
        if ($status === 'failed') {
            $response['error'] = $data['error'] ?? $data['message'] ?? 'Diagram generation failed. Please try again.';
        }
        return response()->json($response);
    }

    /**
     * GET /api/v1/{region}/diagram/result?job_id=&session_id=&message_id=
     * Fetches result from microservice, downloads and stores the image in backend storage,
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
            $result = $this->diagramClient->getJobResult($jobId);
            if (! $result['success']) {
                return response()->json(['success' => false, 'error' => $result['error'] ?? 'Failed to get result.'], 502);
            }

            $data = $result['data'] ?? [];
            $inner = is_array($data) && isset($data['data']) ? $data['data'] : $data;
            $response = ['success' => true, 'data' => $inner];

            Log::info('[DiagramController] result: microservice response', [
                'job_id' => $jobId,
                'attempt' => $attempt + 1,
                'data_keys' => is_array($data) ? array_keys($data) : [],
            ]);

            if (! is_array($inner)) {
                return response()->json($response);
            }

            $nested = is_array($inner['data'] ?? null) ? $inner['data'] : [];
            $downloadUrl = $inner['download_url'] ?? $inner['image_url'] ?? $inner['diagram_url'] ?? $inner['result_url'] ?? $data['download_url'] ?? $data['image_url'] ?? $nested['download_url'] ?? $nested['image_url'] ?? null;
            $downloadUrl = is_string($downloadUrl) ? trim($downloadUrl) : null;
            if ($downloadUrl === '') {
                $downloadUrl = null;
            }
            $imageBase64 = $inner['image_base64'] ?? $inner['file_content'] ?? $inner['image'] ?? $inner['png_base64'] ?? $inner['image_data'] ?? $inner['result'] ?? $inner['output']
                ?? $data['image_base64'] ?? $data['file_content'] ?? $data['image'] ?? $data['png_base64'] ?? $data['image_data'] ?? $data['result'] ?? $data['output']
                ?? $nested['image_base64'] ?? $nested['file_content'] ?? $nested['image'] ?? $nested['png_base64'] ?? $nested['image_data'] ?? $nested['result'] ?? $nested['output'] ?? null;

            if (! empty($downloadUrl) || ! empty($imageBase64)) {
                break;
            }
            $attempt++;
            if ($attempt < $maxAttempts) {
                sleep(2);
            }
        }

        $fileId = (string) Str::uuid();
        $ext = $inner['output_format'] ?? $data['output_format'] ?? 'png';
        if (! preg_match('/^[a-z0-9]+$/i', $ext)) {
            $ext = 'png';
        }
        $filename = 'diagram.' . $ext;
        $path = 'diagrams/' . $fileId . '.' . $ext;

        if (! Storage::disk('local')->exists('diagrams')) {
            Storage::disk('local')->makeDirectory('diagrams');
        }

        $fileMeta = $this->generatedFileMetaForChat($request);

        // Download and store (same pattern as zooys AIDiagramService::downloadAndStoreImage)
        if (! empty($downloadUrl)) {
            $apiKey = config('services.diagram.api_key');
            $hasKey = ! empty($apiKey);
            Log::info('[DiagramController] Fetching diagram image', [
                'job_id' => $jobId,
                'has_api_key' => $hasKey,
                'url_host' => parse_url($downloadUrl, PHP_URL_HOST),
            ]);
            try {
                $headers = $hasKey ? ['X-API-Key' => $apiKey] : [];
                $imageResponse = Http::withHeaders($headers)->timeout(60)->get($downloadUrl);
                if ($imageResponse->successful()) {
                    $written = Storage::disk('local')->put($path, $imageResponse->body());
                    if ($written) {
                        GeneratedFile::create(array_merge([
                            'id' => $fileId,
                            'path' => $path,
                            'filename' => $filename,
                            'type' => 'diagram',
                        ], $fileMeta));
                        $response['file_id'] = $fileId;
                        $response['filename'] = $filename;
                        Log::info('[DiagramController] Diagram saved', ['job_id' => $jobId, 'file_id' => $fileId]);
                    } else {
                        Log::error('[DiagramController] Failed to save diagram file from URL', ['path' => $path, 'job_id' => $jobId]);
                    }
                } else {
                    Log::warning('[DiagramController] Diagram download URL returned non-2xx (check X-API-Key)', [
                        'job_id' => $jobId,
                        'status' => $imageResponse->status(),
                        'has_api_key' => $hasKey,
                        'url' => $downloadUrl,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('[DiagramController] Failed to fetch diagram from URL', ['url' => $downloadUrl, 'error' => $e->getMessage(), 'job_id' => $jobId]);
            }
        } elseif (! empty($imageBase64)) {
            $decoded = base64_decode($imageBase64, true);
            if ($decoded !== false && strlen($decoded) > 0) {
                $written = Storage::disk('local')->put($path, $decoded);
                if ($written) {
                    GeneratedFile::create(array_merge([
                        'id' => $fileId,
                        'path' => $path,
                        'filename' => $filename,
                        'type' => 'diagram',
                    ], $fileMeta));
                    $response['file_id'] = $fileId;
                    $response['filename'] = $filename;
                } else {
                    Log::error('[DiagramController] Failed to save diagram file from base64', ['path' => $path, 'job_id' => $jobId]);
                }
            }
        }

        if (empty($response['file_id'])) {
            Log::warning('[DiagramController] Result has no image', [
                'job_id' => $jobId,
                'had_download_url' => ! empty($downloadUrl),
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
     * GET /api/v1/{region}/diagram/files/{fileId}/download
     * Serves the diagram file from backend storage (persisted for chat history).
     */
    public function download(Request $request, string $fileId)
    {
        $file = GeneratedFile::where('id', $fileId)->where('type', 'diagram')->first();
        if (! $file) {
            Log::warning('[DiagramController] Download: no record', ['file_id' => $fileId]);
            return response()->json(['error' => 'File not found.'], 404);
        }
        if (! Storage::disk('local')->exists($file->path)) {
            Log::warning('[DiagramController] Download: record exists but file missing (check storage or use shared disk for multiple servers)', ['file_id' => $fileId, 'path' => $file->path]);
            return response()->json(['error' => 'File not found.'], 404);
        }
        return Storage::disk('local')->download($file->path, $file->filename ?? $fileId . '.png');
    }
}
