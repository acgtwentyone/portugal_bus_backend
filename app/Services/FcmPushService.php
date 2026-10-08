<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FcmPushService
{
    public function __construct(private Messaging $messaging) {}

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
            Log::warning("FCM send failed for token {$deviceToken}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Send the same notification to many devices, in batches of 500 (FCM's multicast limit).
     *
     * A batch that fails as a whole is logged and counted as failed, so one bad batch
     * never stops the rest from going out.
     *
     * @param  list<string>  $tokens
     */
    public function sendToMany(array $tokens, string $title, string $body, array $data = []): MulticastResult
    {
        $message = CloudMessage::new()
            ->withNotification(Notification::create($title, $body))
            ->withData($data);

        $successCount = 0;
        $failureCount = 0;
        $invalidTokens = [];

        foreach (array_chunk($tokens, 500) as $batch) {
            try {
                $report = $this->messaging->sendMulticast($message, $batch);
            } catch (MessagingException $e) {
                Log::warning('FCM multicast batch of '.count($batch).' failed: '.$e->getMessage());
                $failureCount += count($batch);

                continue;
            }

            $successCount += $report->successes()->count();
            $failureCount += $report->failures()->count();
            array_push($invalidTokens, ...$report->invalidTokens(), ...$report->unknownTokens());
        }

        return new MulticastResult($successCount, $failureCount, array_values(array_unique($invalidTokens)));
    }
}
