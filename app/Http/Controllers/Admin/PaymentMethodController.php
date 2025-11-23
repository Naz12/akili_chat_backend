<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PaymentMethod;

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
}