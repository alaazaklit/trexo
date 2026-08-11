<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Broadcast;
use App\Services\Broadcast\BroadcastService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class BroadcastController extends Controller
{
    public function __construct(private readonly BroadcastService $broadcasts)
    {
    }

    public function index(): View
    {
        return view('admin.broadcasts.index', [
            'pageTitle' => 'Broadcast',
            'broadcasts' => Broadcast::with('sentBy')->latest('id')->paginate(20),
            'accountTypes' => Broadcast::ACCOUNT_TYPES,
            'serviceTypes' => Broadcast::SERVICE_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:120',
            'message' => 'required|string|max:1000',
            'account_type' => 'nullable|in:'.implode(',', Broadcast::ACCOUNT_TYPES),
            'service_type' => 'nullable|in:'.implode(',', Broadcast::SERVICE_TYPES),
        ]);

        if ($validator->fails()) {
            return redirect()->route('admin.broadcasts.index')->withErrors($validator)->withInput();
        }

        $broadcast = $this->broadcasts->send(
            $request->input('title'),
            $request->input('message'),
            $request->input('account_type') ?: null,
            $request->input('service_type') ?: null,
            Auth::id(),
        );

        return redirect()->route('admin.broadcasts.index')
            ->with('success', "Broadcast sent to {$broadcast->recipient_count} recipient(s).");
    }
}
