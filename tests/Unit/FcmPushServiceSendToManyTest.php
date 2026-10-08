<?php

namespace Tests\Unit;

use App\Services\FcmPushService;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\ApiConnectionFailed;
use Kreait\Firebase\Exception\Messaging\InvalidArgument;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\Messaging\ServerUnavailable;
use Kreait\Firebase\Messaging\MessageTarget;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\SendReport;
use Mockery;
use Tests\TestCase;

class FcmPushServiceSendToManyTest extends TestCase
{
    public function test_splits_tokens_into_batches_of_500(): void
    {
        $tokens = array_map(fn (int $i) => "token-{$i}", range(1, 1201));
        $batchSizes = [];

        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('sendMulticast')->times(3)->andReturnUsing(function ($message, array $batch) use (&$batchSizes) {
            $batchSizes[] = count($batch);

            return $this->reportFor(array_fill_keys($batch, null));
        });

        $result = (new FcmPushService($messaging))->sendToMany($tokens, 'Título', 'Texto');

        $this->assertSame([500, 500, 201], $batchSizes);
        $this->assertSame(1201, $result->successCount);
    }

    public function test_aggregates_counts_and_invalid_tokens(): void
    {
        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('sendMulticast')->once()->andReturn($this->reportFor([
            'ok-1' => null,
            'ok-2' => null,
            'gone' => new NotFound('Requested entity was not found.'),
            'bad' => new InvalidArgument('The registration token is not a valid FCM registration token'),
            'flaky' => new ServerUnavailable('Server unavailable'),
        ]));

        $result = (new FcmPushService($messaging))->sendToMany(['ok-1', 'ok-2', 'gone', 'bad', 'flaky'], 'Título', 'Texto');

        $this->assertSame(2, $result->successCount);
        $this->assertSame(3, $result->failureCount);
        $this->assertEqualsCanonicalizing(['gone', 'bad'], $result->invalidTokens);
    }

    public function test_batch_exception_counts_as_failure_and_continues(): void
    {
        $tokens = array_map(fn (int $i) => "token-{$i}", range(1, 501));

        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('sendMulticast')->twice()->andReturnUsing(function ($message, array $batch) {
            if (count($batch) === 500) {
                throw new ApiConnectionFailed('timeout');
            }

            return $this->reportFor(array_fill_keys($batch, null));
        });

        $result = (new FcmPushService($messaging))->sendToMany($tokens, 'Título', 'Texto');

        $this->assertSame(1, $result->successCount);
        $this->assertSame(500, $result->failureCount);
        $this->assertSame([], $result->invalidTokens);
    }

    public function test_empty_token_list_does_not_call_fcm(): void
    {
        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldNotReceive('sendMulticast');

        $result = (new FcmPushService($messaging))->sendToMany([], 'Título', 'Texto');

        $this->assertSame([0, 0, []], [$result->successCount, $result->failureCount, $result->invalidTokens]);
    }

    /**
     * @param  array<string, \Throwable|null>  $outcomes  token => null (success) or the send error
     */
    private function reportFor(array $outcomes): MulticastSendReport
    {
        return MulticastSendReport::withItems(array_map(
            fn (string $token, ?\Throwable $error) => $error
                ? SendReport::failure(MessageTarget::with(MessageTarget::TOKEN, $token), $error)
                : SendReport::success(MessageTarget::with(MessageTarget::TOKEN, $token), []),
            array_keys($outcomes),
            $outcomes,
        ));
    }
}
