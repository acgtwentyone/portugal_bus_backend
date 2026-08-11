<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FcmPushService
{
    public function __construct(private Messaging $messaging)
    {
    }

    /**
     * Send a push notification to a single device token.
     *
     * Returns false (and logs) instead of throwing when the send fails, so a bad
     * or expired token never stops the caller from processing the rest of a batch.
     */
    public function send(string $deviceToken, string $title, string $body, array $data = []): bool
    {
        $message = CloudMessage::new()
            ->toToken($deviceToken)
            ->withNotification(Notification::create($title, $body))
            ->withData($data);

        try {
            $this->messaging->send($message);

            return true;
        } catch (MessagingException $e) {
            Log::warning("FCM send failed for token {$deviceToken}: " . $e->getMessage());

            return false;
        }
    }
}
