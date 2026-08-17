<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Broadcast;
use App\Models\User;
use App\Services\Broadcast\BroadcastService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class BroadcastController extends Controller
{
    // Where the parsed-but-not-yet-sent Excel recipient list lives between
    // the preview and confirm steps. Only ever holds recipient IDs (and the
    // title/message/filename to send with) — never the uploaded file itself,
    // so nothing about the upload lingers beyond generating this list.
    private const EXCEL_SESSION_KEY = 'broadcast.excel_pending';

    public function __construct(private readonly BroadcastService $broadcasts) {}

    public function index(): View
    {
        return view('admin.broadcasts.index', [
            'pageTitle' => 'Broadcast',
            'broadcasts' => Broadcast::with('sentBy')->latest('id')->paginate(20),
            'accountTypes' => Broadcast::ACCOUNT_TYPES,
            'serviceTypes' => Broadcast::SERVICE_TYPES,
            'excelPending' => session(self::EXCEL_SESSION_KEY),
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

    // Step 1 of the Excel path: validate + parse the file and stash a
    // preview (title/message/matched recipient IDs/summary counts) in the
    // session. Nothing is sent yet — the admin still has to confirm.
    public function excelPreview(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:120',
            'message' => 'required|string|max:1000',
            // Extension/mime check here is just a fast first filter — the
            // service verifies the file's actual internal structure too, so
            // a renamed non-Excel file doesn't get through on extension alone.
            'file' => 'required|file|mimes:xlsx,xls|max:5120',
        ]);

        if ($validator->fails()) {
            return redirect()->route('admin.broadcasts.index')->withErrors($validator)->withInput();
        }

        try {
            $result = $this->broadcasts->parseExcelRecipients($request->file('file'));
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('admin.broadcasts.index')
                ->withErrors(['file' => $e->getMessage()])
                ->withInput();
        }

        if ($result['final_count'] === 0) {
            return redirect()->route('admin.broadcasts.index')
                ->withErrors(['file' => 'No valid, registered recipients were found in this file.'])
                ->withInput();
        }

        session()->put(self::EXCEL_SESSION_KEY, [
            'title' => $request->input('title'),
            'message' => $request->input('message'),
            'source_file_name' => $request->file('file')->getClientOriginalName(),
            'recipients' => $result['recipients']->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
            ])->all(),
            'summary' => [
                'total_rows' => $result['total_rows'],
                'empty_rows_ignored' => $result['empty_rows_ignored'],
                'invalid_count' => $result['invalid_count'],
                'invalid_samples' => $result['invalid_samples'],
                'duplicate_count' => $result['duplicate_count'],
                'not_found_count' => $result['not_found_count'],
                'not_found_numbers' => $result['not_found_numbers'],
                'final_count' => $result['final_count'],
            ],
        ]);

        return redirect()->route('admin.broadcasts.index');
    }

    // Step 2: admin has reviewed the preview panel and explicitly confirms —
    // re-fetches the recipients fresh by ID (in case anything changed since
    // preview) and sends through the exact same pipeline a filtration
    // broadcast uses.
    public function excelSend(Request $request): RedirectResponse
    {
        $pending = session(self::EXCEL_SESSION_KEY);

        if (! $pending) {
            return redirect()->route('admin.broadcasts.index')
                ->withErrors(['file' => 'Nothing to send — upload and preview an Excel file first.']);
        }

        $ids = collect($pending['recipients'])->pluck('id');
        $recipients = User::query()
            ->where('account_status', 'active')
            ->whereNotNull('type')
            ->whereIn('id', $ids)
            ->get();

        $broadcast = $this->broadcasts->sendToRecipients(
            $pending['title'],
            $pending['message'],
            $recipients,
            null,
            null,
            Auth::id(),
            Broadcast::SOURCE_EXCEL,
            $pending['source_file_name'],
        );

        session()->forget(self::EXCEL_SESSION_KEY);

        return redirect()->route('admin.broadcasts.index')
            ->with('success', "Broadcast sent to {$broadcast->recipient_count} recipient(s).");
    }

    public function excelCancel(): RedirectResponse
    {
        session()->forget(self::EXCEL_SESSION_KEY);

        return redirect()->route('admin.broadcasts.index');
    }
}
