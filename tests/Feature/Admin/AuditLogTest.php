<?php

namespace Tests\Feature\Admin;

use App\AdminActionEnum;
use App\Models\AdminAction;
use App\Services\AdminAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\InteractsWithCloudflareAccess;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use InteractsWithCloudflareAccess;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCloudflareAccess();
    }

    public function test_record_stores_email_ip_action_and_payload(): void
    {
        Route::domain(config('app.admin_domain'))->middleware(['web', 'admin.access'])
            ->post('/_test/record', function (AdminAuditLog $log) {
                $log->record(AdminActionEnum::AlertCancelled, 7, ['a' => 1]);

                return 'ok';
            })->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $this->asAdmin()->withHeader('CF-Connecting-IP', '1.2.3.4')
            ->post(self::ADMIN_HOST.'/_test/record')->assertOk();

        $action = AdminAction::sole();
        $this->assertSame(self::ADMIN_EMAIL, $action->admin_email);
        $this->assertSame('1.2.3.4', $action->ip);
        $this->assertSame(AdminActionEnum::AlertCancelled, $action->action);
        $this->assertSame(7, $action->subject_id);
        $this->assertSame(['a' => 1], $action->payload);
        $this->assertNotNull($action->created_at);
    }

    public function test_actions_page_lists_newest_first(): void
    {
        AdminAction::create(['admin_email' => 'old@x.pt', 'action' => AdminActionEnum::PushSent, 'payload' => [], 'created_at' => now()->subDay()]);
        AdminAction::create(['admin_email' => 'new@x.pt', 'action' => AdminActionEnum::AlertsPurged, 'payload' => [], 'created_at' => now()]);

        $this->asAdmin()->get(self::ADMIN_HOST.'/actions')
            ->assertOk()
            ->assertSeeInOrder(['new@x.pt', 'alerts.purged', 'old@x.pt', 'push.sent']);
    }

    public function test_actions_page_requires_access(): void
    {
        $this->get(self::ADMIN_HOST.'/actions')->assertForbidden();
    }
}
