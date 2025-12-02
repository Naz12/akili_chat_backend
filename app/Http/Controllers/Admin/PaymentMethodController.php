<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ExportService;
use Illuminate\Http\Request;
use App\Models\PaymentMethod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PaymentMethodController extends Controller
{
    public function index()
    {
        $methods = PaymentMethod::all();
        return view('admin.payment_methods.index', compact('methods'));
    }

    public function create()
    {
        return view('admin.payment_methods.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'key' => 'required|string|unique:payment_methods,key',
            'description' => 'nullable|string',
            'is_enabled' => 'sometimes|boolean',
            'config' => 'nullable|array',
        ]);

        PaymentMethod::create([
            ...$validated,
            'is_enabled' => $request->boolean('is_enabled'),
            'config' => $request->config ?? [],
        ]);

        return redirect()->route('admin.payment-methods.index')->with('success', 'Payment method added.');
    }

    public function edit(PaymentMethod $paymentMethod)
    {
        return view('admin.payment_methods.edit', compact('paymentMethod'));
    }

    public function update(Request $request, PaymentMethod $paymentMethod)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'key' => 'required|string|unique:payment_methods,key,' . $paymentMethod->id,
            'description' => 'nullable|string',
            'is_enabled' => 'sometimes|boolean',
            'config' => 'nullable|array',
        ]);

        $paymentMethod->update([
            ...$validated,
            'is_enabled' => $request->boolean('is_enabled'),
            'config' => $request->config ?? [],
        ]);

        return redirect()->route('admin.payment-methods.index')->with('success', 'Updated successfully.');
    }

    public function destroy(PaymentMethod $paymentMethod)
    {
        $paymentMethod->delete();
        return back()->with('success', 'Deleted successfully.');
    }

    /**
     * Export payment methods to Excel or PDF
     */
    public function export(Request $request)
    {
        try {
            $format = $request->get('format', 'excel');
            $range = $request->get('range', 'all'); // Payment methods are usually small

            $query = PaymentMethod::query();

            // Apply date filters (works with all ranges)
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Get data based on range
            if ($range === 'current') {
                $methods = $query->paginate(20);
                $methods = $methods->items();
            } elseif ($range === 'custom') {
                $limit = min((int) $request->get('limit', 100), 10000); // Max 10,000
                $methods = $query->limit($limit)->get();
            } else {
                $methods = $query->get();
            }

            $exportData = Collection::make($methods)->map(function ($method) {
                return [
                    'ID' => $method->id,
                    'Name' => $method->name,
                    'Key' => $method->key,
                    'Status' => $method->is_enabled ? 'Enabled' : 'Disabled',
                    'Config' => json_encode($method->config ?? []),
                    'Description' => $method->description ?? '-',
                    'Created' => $method->created_at->format('Y-m-d'),
                ];
            });

            $headers = ['ID', 'Name', 'Key', 'Status', 'Config', 'Description', 'Created'];
            $filename = 'payment_methods_' . now()->format('Y-m-d_H-i-s');

            if ($format === 'pdf') {
                return ExportService::exportToPdf($exportData, $headers, 'Payment Methods Export', $filename);
            }

            return ExportService::exportToExcel($exportData, $headers, $filename);
        } catch (\Exception $e) {
            Log::error('Payment methods export error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }
}