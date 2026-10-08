<?php

namespace Tests\Feature\Admin;

use App\Models\AdminAction;
use App\Models\DeviceToken;
use App\Services\FcmPushService;
use App\Services\MulticastResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCloudflareAccess;
use Tests\TestCase;

class ManualPushTest extends TestCase
{
    use InteractsWithCloudflareAccess;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCloudflareAccess();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_create_shows_device_count(): void
    {
        $this->createTokens(['t1', 't2', 't3']);

        $this->asAdmin()->get(self::ADMIN_HOST.'/push')
            ->assertOk()
            ->assertSee('3 dispositivos registados');
    }

    public function test_preview_validates_lengths(): void
    {
        $this->asAdmin()->post(self::ADMIN_HOST.'/push/preview', ['title' => str_repeat('a', 66), 'body' => str_repeat('b', 241)])
            ->assertSessionHasErrors(['title', 'body']);

        $this->asAdmin()->post(self::ADMIN_HOST.'/push/preview', ['title' => '', 'body' => ''])
            ->assertSessionHasErrors(['title', 'body']);
    }

    public function test_preview_shows_recipient_count_and_stores_nonce(): void
    {
        $this->createTokens(['t1', 't2']);

        $this->asAdmin()->post(self::ADMIN_HOST.'/push/preview', ['title' => 'Olá', 'body' => 'Novidades'])
            ->assertOk()
            ->assertSee('Vai ser enviado para')
            ->assertSee('2 dispositivos')
            ->assertSee('name="nonce"', false)
            ->assertSessionHas('admin_push_pending.recipients', 2);
    }

    public function test_preview_with_zero_devices_hides_confirm_form(): void
    {
        $this->asAdmin()->post(self::ADMIN_HOST.'/push/preview', ['title' => 'Olá', 'body' => 'Novidades'])
            ->assertOk()
            ->assertSee('Não há dispositivos registados')
            ->assertDontSee('name="nonce"', false);
    }

    public function test_send_without_confirmation_is_rejected(): void
    {
        $this->createTokens(['t1']);
        $this->mock(FcmPushService::class)->shouldNotReceive('sendToMany');

        $this->asAdmin()->post(self::ADMIN_HOST.'/push/preview', ['title' => 'Olá', 'body' => 'Novidades']);
        $nonce = session('admin_push_pending.nonce');

        $this->asAdmin()->post(self::ADMIN_HOST.'/push/send', ['nonce' => $nonce])
            ->assertSessionHasErrors('confirm');

        $this->assertSame(0, AdminAction::count());
    }

    public function test_send_with_wrong_or_reused_nonce_redirects(): void
    {
        $this->createTokens(['t1']);
        $this->mock(FcmPushService::class)->shouldReceive('sendToMany')->once()->andReturn(new MulticastResult(1, 0, []));

        $this->asAdmin()->post(self::ADMIN_HOST.'/push/preview', ['title' => 'Olá', 'body' => 'Novidades']);
        $nonce = session('admin_push_pending.nonce');

        $this->asAdmin()->post(self::ADMIN_HOST.'/push/send', ['nonce' => 'wrong', 'confirm' => '1'])
            ->assertRedirect(route('admin.push.create'))
            ->assertSessionHas('error', 'Pré-visualização expirada, volta a gerar');

        // The wrong nonce consumed the pending push, so preview again for a real send.
        $this->asAdmin()->post(self::ADMIN_HOST.'/push/preview', ['title' => 'Olá', 'body' => 'Novidades']);
        $nonce = session('admin_push_pending.nonce');

        $this->asAdmin()->post(self::ADMIN_HOST.'/push/send', ['nonce' => $nonce, 'confirm' => '1'])->assertOk();

        $this->asAdmin()->post(self::ADMIN_HOST.'/push/send', ['nonce' => $nonce, 'confirm' => '1'])
            ->assertRedirect(route('admin.push.create'))
            ->assertSessionHas('error', 'Pré-visualização expirada, volta a gerar');

        $this->assertSame(1, AdminAction::count());
    }

    public function test_send_pushes_with_admin_broadcast_data(): void
    {
        $this->createTokens(['t1', 't2']);
        $this->mock(FcmPushService::class)->shouldReceive('sendToMany')->once()
            ->withArgs(fn ($tokens, $title, $body, $data) => $tokens === ['t1', 't2'] && $title === 'Olá'
                && $body === 'Novidades' && $data === ['type' => 'admin_broadcast'])
            ->andReturn(new MulticastResult(2, 0, []));

        $this->previewAndSend('Olá', 'Novidades')->assertOk()->assertSee('2 enviados');
    }

    public function test_send_removes_invalid_tokens_and_logs(): void
    {
        $this->createTokens(['t1', 't2', 't3']);
        $this->mock(FcmPushService::class)->shouldReceive('sendToMany')->andReturn(new MulticastResult(2, 1, ['t3']));

        $this->previewAndSend('Olá', 'Novidades');

        $this->assertSame(['t1', 't2'], DeviceToken::orderBy('token')->pluck('token')->all());
        $action = AdminAction::sole();
        $this->assertSame('push.sent', $action->action->value);
        $this->assertSame([
            'title' => 'Olá',
            'body' => 'Novidades',
            'recipients_previewed' => 3,
            'recipients_sent' => 3,
            'success' => 2,
            'failure' => 1,
            'invalid_tokens_removed' => 1,
        ], $action->payload);
    }

    private function previewAndSend(string $title, string $body): \Illuminate\Testing\TestResponse
    {
        $this->asAdmin()->post(self::ADMIN_HOST.'/push/preview', ['title' => $title, 'body' => $body]);

        return $this->asAdmin()->post(self::ADMIN_HOST.'/push/send', [
            'nonce' => session('admin_push_pending.nonce'),
            'confirm' => '1',
        ]);
    }

    private function createTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            DeviceToken::create(['token' => $token]);
        }
    }
}
