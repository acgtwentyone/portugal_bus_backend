<?php

namespace App\Services;

final readonly class MulticastResult
{
    /**
     * @param  list<string>  $invalidTokens  tokens FCM reported as invalid or no longer registered
     */
    public function __construct(
        public int $successCount,
        public int $failureCount,
        public array $invalidTokens,
    ) {}
}
