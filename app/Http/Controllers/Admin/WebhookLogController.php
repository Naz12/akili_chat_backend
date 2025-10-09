<?php


namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Webhook;

class WebhookLogController extends Controller
{
    public function index()
    {
        $logs = Webhook::latest()->paginate(25);
        return view('admin.webhooks.index', compact('logs'));
    }

    public function show($id)
    {
        $log = Webhook::findOrFail($id);
        return view('admin.webhooks.show', compact('log'));
    }
}