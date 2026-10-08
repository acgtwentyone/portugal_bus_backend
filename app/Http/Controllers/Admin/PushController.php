<?php

namespace App\Http\Controllers\Admin;

use App\AdminActionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PreviewPushRequest;
use App\Models\DeviceToken;
use App\Services\AdminAuditLog;
use App\Services\FcmPushService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Manual push to every registered device: form → preview (server-side) → explicit confirmation.
 * The pending push lives in the session under a one-time nonce, so a refresh or a double
 * click can never send it twice.
 */
class PushController extends Controller
{
    private const PENDING_KEY = 'admin_push_pending';

    public function create(): View
    {
        return view('admin.push.create', ['deviceCount' => DeviceToken::count()]);
    }

    public function preview(PreviewPushRequest $request): View
    {
        $pending = [
            'nonce' => Str::random(40),
            'title' => $request->validated('title'),
            'body' => $request->validated('body'),
            'recipients' => DeviceToken::count(),
        ];

        $request->session()->put(self::PENDING_KEY, $pending);

        return view('admin.push.preview', ['pending' => $pending]);
    }

    public function send(Request $request, FcmPushService $push, AdminAuditLog $log): View|RedirectResponse
    {
        $request->validate([
            'nonce' => ['required', 'string'],
            'confirm' => ['accepted'],
        ]);

        // Pulled before sending: whatever happens next, this preview can't be sent again.
        $pending = $request->session()->pull(self::PENDING_KEY);

        if (! $pending || ! hash_equals($pending['nonce'], $request->input('nonce'))) {
            return redirect()->route('admin.push.create')->with('error', 'Pré-visualização expirada, volta a gerar');
        }

        $tokens = DeviceToken::pluck('token')->all();

        if (! $tokens) {
            return redirect()->route('admin.push.create')->with('error', 'Não há dispositivos registados');
        }

        $result = $push->sendToMany($tokens, $pending['title'], $pending['body'], ['type' => 'admin_broadcast']);

        $removed = $result->invalidTokens ? DeviceToken::whereIn('token', $result->invalidTokens)->delete() : 0;

        $log->record(AdminActionEnum::PushSent, null, [
            'title' => $pending['title'],
            'body' => $pending['body'],
            'recipients_previewed' => $pending['recipients'],
            'recipients_sent' => count($tokens),
            'success' => $result->successCount,
            'failure' => $result->failureCount,
            'invalid_tokens_removed' => $removed,
        ]);

        return view('admin.push.result', [
            'result' => $result,
            'removed' => $removed,
            'recipients' => count($tokens),
        ]);
    }
}
