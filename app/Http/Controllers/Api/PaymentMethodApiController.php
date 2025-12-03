<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PaymentMethod;
use App\Traits\DetectsRegion;

class PaymentMethodApiController extends Controller
{
    use DetectsRegion;

    /**
     * Return a list of enabled payment methods for the current region.
     */
    public function index(Request $request)
    {
        // Get region from route parameter or URL segment
        $region = $request->route('region') ?? $request->segment(3);
        
        if (!in_array($region, ['local', 'intl'])) {
            return response()->json([
                'error' => 'Invalid region. Use "local" or "intl".',
                'payment_methods' => [],
            ], 400);
        }

        $methods = PaymentMethod::forRegion($region)
            ->orderBy('name', 'asc')
            ->get();

        $response = $methods->map(function ($method) {
            return [
                'id' => $method->id,
                'name' => $method->name,
                'key' => $method->key,
                'logo_url' => $method->logo_url ?? null,
                'description' => $method->description,
            ];
        });

        return response()->json([
            'region' => $region,
            'payment_methods' => $response,
        ]);
    }
}