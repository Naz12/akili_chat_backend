<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\GuestUserService;
use App\Services\Tools\DocConverterClient;
use App\Services\UsageMeterService;
use App\Services\UsageValidatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DocConverterController extends Controller
{
    private const CACHE_PREFIX = 'doc_convert_job:';

    private const CACHE_TTL_SECONDS = 3600;

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
            $user = $request->user();
            $guestSession = ! $user ? $this->guestUserService->getGuestSessionFromRequest($request) : null;
            $sessionId = $request->input('session_id');
            $payload = [
                'user_id' => $user?->id,
                'guest_session_id' => $guestSession?->id,
                'chat_session_id' => $sessionId,
            ];
            Cache::put(self::CACHE_PREFIX . $jobId, $payload, self::CACHE_TTL_SECONDS);
        }

        return response()->json([
            'success' => true,
            'job_id' => $jobId,
            'message' => $result['message'] ?? 'Conversion started.',
        ]);
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

        $result = $this->docConverterClient->getConversionStatus($jobId);
        if (! $result['success']) {
            return response()->json(['success' => false, 'error' => $result['error'] ?? 'Failed to get status.'], 502);
        }

        $data = $result['data'] ?? [];
        return response()->json([
            'success' => true,
            'status' => $data['status'] ?? $result['status'] ?? 'unknown',
            'progress' => $data['progress'] ?? $result['progress'] ?? 0,
            'data' => $data,
        ]);
    }

    /**
     * GET /api/v1/{region}/doc-converter/result?job_id=
     * Returns converted content; records usage when result is successful.
     */
    public function result(Request $request)
    {
        $jobId = $request->query('job_id');
        if (empty($jobId)) {
            return response()->json(['success' => false, 'error' => 'job_id required.'], 400);
        }

        $result = $this->docConverterClient->getConversionResult($jobId);
        if (! $result['success']) {
            return response()->json(['success' => false, 'error' => $result['error'] ?? 'Failed to get result.'], 502);
        }

        $data = $result['data'] ?? [];
        $status = is_array($data) ? ($data['status'] ?? null) : null;
        if ($status === 'completed') {
            $payload = Cache::get(self::CACHE_PREFIX . $jobId);
            if (is_array($payload)) {
                $cost = (int) config('tools.usage.doc_converter.cost', 200);
                $user = isset($payload['user_id']) ? User::find($payload['user_id']) : null;
                $guestSession = isset($payload['guest_session_id']) ? GuestSession::find($payload['guest_session_id']) : null;
                $this->usageMeterService->record($cost, 'doc_converter', $user, $guestSession, $payload['chat_session_id'] ?? null);
                Cache::forget(self::CACHE_PREFIX . $jobId);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
