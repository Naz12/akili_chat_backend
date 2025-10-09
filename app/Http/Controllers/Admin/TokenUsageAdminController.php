<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TokenUsage;
use Illuminate\Http\Request;

class TokenUsageAdminController extends Controller
{
    public function index(Request $request)
    {
        $query = TokenUsage::with(['user', 'engine'])->latest();

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('engine_id')) {
            $query->where('engine_id', $request->engine_id);
        }

        $usages = $query->paginate(20);

        return view('admin.token_usages.index', compact('usages'));
    }
}