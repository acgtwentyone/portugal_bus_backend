<?php

namespace App\Services;

use App\AdminActionEnum;
use App\Models\AdminAction;
use Illuminate\Http\Request;

class AdminAuditLog
{
    public function __construct(private Request $request) {}

    /**
     * Record who did what, taking the admin email (set by VerifyCloudflareAccess) and the
     * real client IP (Cloudflare's CF-Connecting-IP) from the current request.
     */
    public function record(AdminActionEnum $action, ?int $subjectId = null, array $payload = []): AdminAction
    {
        return AdminAction::create([
            'admin_email' => $this->request->attributes->get('admin_email'),
            'action' => $action,
            'subject_id' => $subjectId,
            'payload' => $payload,
            'ip' => $this->request->header('CF-Connecting-IP') ?? $this->request->ip(),
        ]);
    }
}
