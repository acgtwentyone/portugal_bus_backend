# Painel de administração — Plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Painel interno em `admin.portugal-bus.com` com autenticação Cloudflare Access, registo de ações, gestão de alertas, push manual e estado das tarefas agendadas.

**Architecture:** Grupo de rotas `Route::domain(config('app.admin_domain'))` protegido pelo middleware `admin.access`, que valida o JWT do Cloudflare Access. As páginas usam Blade server-side com controllers em `App\Http\Controllers\Admin`. As ações de escrita passam pelo `AdminAuditLog`. O estado das tarefas agendadas é recolhido por um listener dos eventos do scheduler.

**Tech Stack:** Laravel 12, PHP 8.4, Blade, Tailwind 4 (Vite), `firebase/php-jwt` ^7.1, `kreait/firebase-php` ^7.24, PHPUnit 11 (SQLite em memória).

**Spec:** `docs/superpowers/specs/2026-10-08-admin-panel-design.md`

## Global Constraints

- Todas as rotas do painel ficam dentro de `Route::domain(config('app.admin_domain'))->middleware('admin.access')`, com nomes `admin.*`.
- Os textos da UI estão em português de Portugal. O código e os comentários estão em inglês, como no resto do projeto.
- Não se acrescenta Livewire, Filament nem Inertia. O único pacote novo é `firebase/php-jwt` ^7.1, passado de transitivo a direto.
- Não há worker de filas: nada do painel usa `dispatch()`.
- `admin_actions` só recebe inserções: o modelo não tem `updated_at` e nenhuma rota ou código faz update ou delete.
- Todas as ações de escrita registam um `AdminActionEnum` através do `AdminAuditLog::record()`.
- Os testes do painel fazem pedidos a `http://admin.portugalbus.test/...` e usam o trait `InteractsWithCloudflareAccess` (Task 2).
- Correr `./vendor/bin/pint` antes de cada commit.

## Review Focus

1. **Execução saltada por sobreposição:** o `withoutOverlapping()` não dispara `ScheduledTaskSkipped`. Dispara `ScheduledTaskFinished` com `exitCode === null`. Tem de ficar registado `skipped`, e não `success` nem `failed`. Teste na Task 7.
2. **Tarefa sem nenhuma execução registada** (deploy recente, sync semanal): o painel mostra "Sem execuções registadas" e **não** a marca como atrasada. Teste na Task 8.
3. **Rotação de chaves do Cloudflare:** um token com um `kid` que não está na cache força **uma** nova leitura dos certificados. Se o endpoint estiver em baixo e não houver cache, responde 503. Testes na Task 2.
4. **Alerta apagado pelo comando entre a abertura da página e a ação:** cancelar mostra "Alerta já não existe". Limpar regista só os ids realmente apagados. Testes na Task 4.
5. **Push com zero dispositivos:** a pré-visualização mostra 0 e não mostra o formulário de confirmação. O `sendToMany([])` não chama o FCM. Testes nas Tasks 5 e 6.

---

### Task 1: Regra de expiração partilhada em `ArrivalAlert`

**Files:**
- Create: `database/factories/ArrivalAlertFactory.php`
- Modify: `app/Models/ArrivalAlert.php` (acrescentar `HasFactory`, `isExpired()` e `scopeExpired()`)
- Modify: `app/Console/Commands/ProcessArrivalAlerts.php` (método `expireStaleAlerts`)
- Test: `tests/Feature/ProcessArrivalAlertsTest.php`, `tests/Unit/ArrivalAlertExpiryTest.php` (estende `Tests\TestCase` e usa `RefreshDatabase`, por causa do scope)

**Interfaces:**
- Produces: `ArrivalAlert::isExpired(): bool`, `ArrivalAlert::scopeExpired(Builder $query): void` (uso: `ArrivalAlert::expired()`), `ArrivalAlert::factory()`

- [ ] **Step 1: Criar a factory.** Valores por omissão: `stop_id` 'BLM1', `route_id` '205', `direction_id` 0, `trip_id` 'T1', `estimated_arrival_time` now()+10 min, `threshold_minutes` 5, `device_token` fake sha1, `locale` 'pt'.

- [ ] **Step 2: Escrever os testes que fixam o comportamento atual do comando** (passam antes da mudança). Usam `$this->mock(StcpService::class)` e `$this->mock(FcmPushService::class)` e `config(['app.arrival_alert_expiry_grace_minutes' => 15])`:

```php
public function test_alert_within_grace_is_kept_when_trip_missing(): void
// travelTo fixo; ETA = now - 14 min; getArrivals devolve []; artisan app:process-arrival-alerts
// assertDatabaseHas('arrival_alerts', ['id' => $alert->id]); push->shouldNotReceive('send')

public function test_alert_past_grace_is_deleted_without_push(): void
// ETA = now - 16 min; getArrivals devolve [] → assertModelMissing($alert); shouldNotReceive('send')

public function test_alert_fires_and_is_deleted_when_threshold_reached(): void
// getArrivals devolve [['trip_id' => 'T1', 'arrival_minutes' => 3, 'trip_headsign' => 'Bolhão']]
// threshold 5 → send chamado uma vez; assertModelMissing($alert)
```

- [ ] **Step 3: Correr** `php artisan test --filter=ProcessArrivalAlertsTest`. Esperado: 3 testes a PASSAR (o comportamento atual fica fixado).

- [ ] **Step 4: Escrever os testes unitários do modelo (falham):**

```php
// grace 15, travelTo('2026-10-08 10:00:00')
test_is_not_expired_exactly_at_grace_boundary()   // ETA 09:45:00 → isExpired() false
test_is_expired_one_second_after_grace()          // ETA 09:44:59 → true
test_expired_scope_matches_is_expired()           // 3 alertas (ETA 09:30, 09:45, 10:10) → expired()->pluck('id') == [id de 09:30]
```

- [ ] **Step 5: Correr** `php artisan test --filter=ArrivalAlertExpiryTest`. Esperado: FAIL, "Call to undefined method isExpired".

- [ ] **Step 6: Implementar `isExpired()` e `scopeExpired()`.**
  - `isExpired`: `now()->isAfter($this->estimated_arrival_time->clone()->addMinutes(grace))`, que é a mesma comparação de instantes que o comando faz hoje.
  - `scopeExpired`: `where('estimated_arrival_time', '<', now()->subMinutes(grace))`, com `now()` no fuso da app (UTC), que é o mesmo fuso em que o cast lê os valores.
  - No `expireStaleAlerts` do comando, trocar o filtro por `fn (ArrivalAlert $alert) => $alert->isExpired()`.

- [ ] **Step 7: Correr** `php artisan test --filter='ArrivalAlertExpiryTest|ProcessArrivalAlertsTest'`. Esperado: tudo a PASSAR.

- [ ] **Step 8: Commit** com a mensagem `refactor: move arrival alert expiry rule to model (PB-107)`.

---

### Task 2: Autenticação com Cloudflare Access e esqueleto do painel

**Files:**
- Modify: `composer.json` (`composer require firebase/php-jwt:^7.1`)
- Create: `config/admin.php`
- Create: `app/Http/Middleware/VerifyCloudflareAccess.php`
- Modify: `bootstrap/app.php` (alias `admin.access`)
- Modify: `routes/web.php` (substituir o grupo admin; retirar `/admin-placeholder`)
- Delete: `app/Http/Controllers/Admin/StcpController.php`
- Create: `app/Http/Controllers/Admin/DashboardController.php` (só `index`, devolve `view('admin.dashboard')`; a Task 8 completa-o)
- Create: `resources/views/admin/layout.blade.php`, `resources/views/admin/dashboard.blade.php`
- Modify: `.env.example` (variáveis do `config/admin.php` e `ADMIN_DOMAIN`), `phpunit.xml` (`ADMIN_DOMAIN=admin.portugalbus.test`, `API_DOMAIN=api.portugalbus.test`, `LANDING_DOMAIN=portugalbus.test`)
- Create: `tests/Concerns/InteractsWithCloudflareAccess.php`
- Test: `tests/Feature/Admin/CloudflareAccessTest.php`

**Interfaces:**
- Produces:
  - `config('admin.cf_access_team_domain')`, `config('admin.cf_access_aud')`, `config('admin.emails')` (array em minúsculas, a partir de `ADMIN_EMAILS` separado por vírgulas) e `config('admin.dev_email')`.
  - O atributo de pedido `admin_email`: `$request->attributes->get('admin_email')`, partilhado com as views como `$adminEmail` através de `View::share` no middleware.
  - O layout `admin.layout`, com `@yield('title')` e `@yield('content')`, navegação para `admin.dashboard`, `admin.alerts.index`, `admin.push.create` e `admin.actions.index`, e mensagens flash `success` e `error`.
  - A rota `admin.dashboard` (`GET /`).
  - O trait de testes `InteractsWithCloudflareAccess`:
    - `setUpCloudflareAccess(): void` gera um par RSA, configura `admin.*` (team domain `portugalbus.cloudflareaccess.com`, aud `test-aud`, emails `['admin@portugal-bus.com']`) e faz `Http::fake` do endpoint de certificados com o JWK (`kid` `test-kid`).
    - `accessToken(array $claims = [], string $kid = 'test-kid'): string`.
    - `asAdmin(): static` define o header `Cf-Access-Jwt-Assertion` com um token válido.

- [ ] **Step 1: Escrever os testes do middleware (falham).** Todos fazem `$this->get('http://admin.portugalbus.test/')`:

```php
test_valid_token_grants_access()              // asAdmin() → 200, assertSee('admin@portugal-bus.com')
test_missing_header_is_forbidden()            // 403
test_bad_signature_is_forbidden()             // token assinado com outra chave RSA → 403
test_wrong_audience_is_forbidden()            // accessToken(['aud' => ['other']]) → 403
test_wrong_issuer_is_forbidden()              // ['iss' => 'https://evil.cloudflareaccess.com'] → 403
test_expired_token_is_forbidden()             // ['exp' => now()->subMinute()->timestamp] → 403
test_unlisted_email_is_forbidden()            // ['email' => 'x@y.com'] → 403
test_email_match_is_case_insensitive()        // ['email' => 'Admin@Portugal-Bus.com'] → 200
test_unknown_kid_refetches_certs_once()       // cache aquecida com chave antiga; token com kid novo → 200 e Http::assertSentCount(2)
test_certs_unavailable_without_cache_is_503() // Http::fake 500 no endpoint, cache vazia → 503
test_dev_email_bypass_only_in_local()         // app()['env']='local' + dev_email → 200 sem header; em 'production' com dev_email → 403
```

- [ ] **Step 2: Correr** `php artisan test --filter=CloudflareAccessTest`. Esperado: FAIL (a rota não existe ou o middleware não existe).

- [ ] **Step 3: Implementar o `VerifyCloudflareAccess::handle(Request $request, Closure $next): Response`** seguindo os passos 1 a 5 da secção PB-106 da especificação:
  - Os certificados vêm de `https://{team}/cdn-cgi/access/certs` e são convertidos com `JWK::parseKeySet($json['keys'], 'RS256')`.
  - A cache é `Cache::remember('cf_access_certs', 3600, ...)`. Se o `kid` não estiver na cache: `Cache::forget`, uma nova leitura e só depois falha.
  - O decode é feito com `JWT::decode`.
  - `aud` pode ser uma string ou um array, e é confirmado com `in_array`.
  - Os 403 não levam corpo e registam o motivo em `Log::warning`. O 503 só acontece quando não há cache e o pedido HTTP falha.

- [ ] **Step 4: Fazer as rotas, a remoção do código antigo, o layout e a view do dashboard.**
  - Nas rotas, retirar o `/admin-placeholder` e apagar o `StcpController`.
  - A view do dashboard só tem um título "Painel".
  - O layout usa `@vite(['resources/css/app.css'])` e um nav simples em Tailwind.

- [ ] **Step 5: Correr** `php artisan test`. Esperado: tudo a PASSAR, incluindo os testes de exemplo.

- [ ] **Step 6: Commit** com a mensagem `feat: protect admin panel with Cloudflare Access JWT (PB-106)`.

---

### Task 3: Registo de ações

**Files:**
- Create: migração `create_admin_actions_table`, `app/Models/AdminAction.php`, `app/AdminActionEnum.php`, `app/Services/AdminAuditLog.php`
- Create: `app/Http/Controllers/Admin/AuditLogController.php`, `resources/views/admin/actions/index.blade.php`
- Modify: `routes/web.php` (`GET /actions` → `admin.actions.index`)
- Test: `tests/Feature/Admin/AuditLogTest.php`

**Interfaces:**
- Consumes: o atributo de pedido `admin_email` (Task 2)
- Produces:
  - `enum AdminActionEnum: string { AlertCancelled = 'alert.cancelled'; AlertsPurged = 'alerts.purged'; PushSent = 'push.sent'; }`
  - `AdminAuditLog::record(AdminActionEnum $action, ?int $subjectId = null, array $payload = []): AdminAction`, injetável pelo container. O IP vem de `$request->header('CF-Connecting-IP') ?? $request->ip()`.
  - O modelo `AdminAction`: `const UPDATED_AT = null`, `action` com cast para `AdminActionEnum` e `payload` com cast `array`.

- [ ] **Step 1: Escrever os testes (falham):**

```php
test_record_stores_email_ip_action_and_payload()
// pedido com asAdmin() e header CF-Connecting-IP 1.2.3.4 a uma rota de teste (Route::post registada no teste dentro do grupo admin)
// que chama record(AlertCancelled, 7, ['a' => 1]) → assertDatabaseHas admin_email, ip '1.2.3.4', action 'alert.cancelled', subject_id 7
test_actions_page_lists_newest_first()   // 2 AdminAction com created_at diferentes → assertSeeInOrder([mais recente, mais antiga])
test_actions_page_requires_access()      // sem header → 403
```

- [ ] **Step 2: Correr** `php artisan test --filter=AuditLogTest`. Esperado: FAIL.

- [ ] **Step 3: Implementar a migração** (colunas da especificação), o enum, o modelo, o serviço, o controller (`AdminAction::latest()->paginate(50)`) e a view.
  - A view mostra uma tabela com data, email, ação e o payload em `json_encode` cortado a 120 caracteres.

- [ ] **Step 4: Correr** `php artisan test --filter=AuditLogTest`. Esperado: PASSAR.

- [ ] **Step 5: Commit** com a mensagem `feat: add admin audit log (PB-110)`.

---

### Task 4: Gestão de alertas

**Files:**
- Create: `app/Http/Controllers/Admin/AlertController.php`, `resources/views/admin/alerts/index.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Admin/AlertManagementTest.php`

**Interfaces:**
- Consumes: `ArrivalAlert::expired()`, `isExpired()` e `factory()` (Task 1); `AdminAuditLog::record()` e `AdminActionEnum` (Task 3)
- Produces: as rotas `admin.alerts.index` (`GET /alerts`), `admin.alerts.destroy` (`DELETE /alerts/{alert}`, com `->missing()` a redirecionar para o índice com o erro "Alerta já não existe") e `admin.alerts.purge` (`POST /alerts/purge`).

- [ ] **Step 1: Escrever os testes (falham):**

```php
test_index_lists_alerts_ordered_by_eta_with_expired_badge()  // assertSeeInOrder por ETA; assertSee('Expirado') para o expirado
test_index_truncates_device_token()                          // token 'abcdefgh12345' → assertSee('abcdefgh…'), assertDontSee('abcdefgh12345')
test_cancel_deletes_alert_and_logs()                         // assertModelMissing; admin_actions action 'alert.cancelled', subject_id, payload.trip_id
test_cancel_missing_alert_shows_message()                    // DELETE id inexistente → redirect admin.alerts.index, session error 'Alerta já não existe'; 0 linhas em admin_actions
test_purge_deletes_only_expired_and_logs_ids()               // 2 expirados + 1 ativo → 1 restante; payload count 2, ids dos 2
test_purge_with_nothing_expired_logs_nothing()               // redirect com mensagem; admin_actions vazio
test_purge_logs_only_ids_actually_deleted()                  // lê os ids expirados e depois apaga com whereIn(ids); count == número realmente apagado
```

- [ ] **Step 2: Correr** `php artisan test --filter=AlertManagementTest`. Esperado: FAIL.

- [ ] **Step 3: Implementar o `AlertController`** com `index()`, `destroy(ArrivalAlert $alert, AdminAuditLog $log)` e `purgeExpired(AdminAuditLog $log)`.
  - As ações de escrita correm dentro de `DB::transaction`.
  - O purge faz `$ids = ArrivalAlert::expired()->pluck('id')`, depois `$deleted = ArrivalAlert::whereIn('id', $ids)->delete()`, e só regista se `$deleted > 0`.
  - A view tem um botão "Limpar N expirados" (desativado se N for 0) e formulários com `@method('DELETE')` e `onsubmit="return confirm(...)"`.

- [ ] **Step 4: Correr** `php artisan test --filter=AlertManagementTest`. Esperado: PASSAR.

- [ ] **Step 5: Commit** com a mensagem `feat: admin arrival alert management (PB-107)`.

---

### Task 5: `FcmPushService::sendToMany()`

**Files:**
- Create: `app/Services/MulticastResult.php`
- Modify: `app/Services/FcmPushService.php`
- Test: `tests/Unit/FcmPushServiceSendToManyTest.php`

**Interfaces:**
- Produces:
  - `final readonly class MulticastResult { __construct(public int $successCount, public int $failureCount, public array $invalidTokens) }`
  - `FcmPushService::sendToMany(array $tokens, string $title, string $body, array $data = []): MulticastResult`

- [ ] **Step 1: Escrever os testes (falham).** Usam um mock Mockery de `Kreait\Firebase\Contract\Messaging`. Os relatórios são construídos com `MulticastSendReport::withItems([...])`, `SendReport::success(MessageTarget::with('token', $t), [])` e `SendReport::failure(..., new NotFound('...'))`, este último para um token desconhecido.

```php
test_splits_tokens_into_batches_of_500()        // 1201 tokens → sendMulticast chamado 3 vezes (500, 500, 201)
test_aggregates_counts_and_invalid_tokens()     // 2 sucessos + 1 NotFound + 1 InvalidArgument → success 2, failure 2, invalidTokens com os 2 tokens
test_batch_exception_counts_as_failure_and_continues()  // 1.º lote lança MessagingException → failure += 500; 2.º lote processado
test_empty_token_list_does_not_call_fcm()       // shouldNotReceive('sendMulticast'); resultado 0/0/[]
```

- [ ] **Step 2: Correr** `php artisan test --filter=FcmPushServiceSendToManyTest`. Esperado: FAIL.

- [ ] **Step 3: Implementar** com `array_chunk($tokens, 500)`. Para cada lote, construir uma `CloudMessage` com a notificação e os dados, como no `send()`.
  - `invalidTokens` junta `$report->invalidTokens()` e `$report->unknownTokens()`.
  - Num lote que lança `MessagingException`, fazer `Log::warning` e somar o tamanho do lote às falhas.

- [ ] **Step 4: Correr** o filtro. Esperado: PASSAR.

- [ ] **Step 5: Commit** com a mensagem `feat: add FcmPushService::sendToMany with batching (PB-109)`.

---

### Task 6: Fluxo do push manual

**Files:**
- Create: `app/Http/Controllers/Admin/PushController.php`, `app/Http/Requests/Admin/PreviewPushRequest.php`
- Create: `resources/views/admin/push/create.blade.php`, `resources/views/admin/push/preview.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Admin/ManualPushTest.php`

**Interfaces:**
- Consumes: `sendToMany()` e `MulticastResult` (Task 5); `AdminAuditLog` (Task 3); o modelo `DeviceToken`
- Produces: as rotas `admin.push.create` (`GET /push`), `admin.push.preview` (`POST /push/preview`) e `admin.push.send` (`POST /push/send`). Fica na sessão a chave `admin_push_pending` = `{nonce, title, body, recipients}`.

- [ ] **Step 1: Escrever os testes (falham).** O `FcmPushService` é simulado com `$this->mock()`.

```php
test_create_shows_device_count()                      // 3 DeviceToken → assertSee('3')
test_preview_validates_lengths()                      // title 66 chars → erro; body 241 → erro; vazios → erro
test_preview_shows_recipient_count_and_stores_nonce() // assertSee('Vai ser enviado para'); assertSessionHas('admin_push_pending')
test_preview_with_zero_devices_hides_confirm_form()   // assertSee('Não há dispositivos registados'); assertDontSee('name="nonce"', false)
test_send_without_confirmation_is_rejected()          // sem 'confirm' → erro de validação; sendToMany nunca chamado
test_send_with_wrong_or_reused_nonce_redirects()      // 1.º envio OK; 2.º com o mesmo nonce → redirect admin.push.create, error 'Pré-visualização expirada, volta a gerar'; sendToMany chamado 1 vez
test_send_pushes_with_admin_broadcast_data()          // sendToMany recebe os tokens, title, body e ['type' => 'admin_broadcast']
test_send_removes_invalid_tokens_and_logs()           // MulticastResult(2, 1, ['t3']) → t3 apagado; payload: title, body, recipients_previewed, recipients_sent, success 2, failure 1, invalid_tokens_removed 1
```

- [ ] **Step 2: Correr** `php artisan test --filter=ManualPushTest`. Esperado: FAIL.

- [ ] **Step 3: Implementar.**
  - `PreviewPushRequest`: `title` required|string|max:65; `body` required|string|max:240.
  - `send` valida `nonce` required e `confirm` accepted. Depois faz `session()->pull('admin_push_pending')` **antes** de enviar e compara o nonce com `hash_equals`.
  - Se o nonce falhar, ou se houver 0 tokens no momento do envio, redireciona com erro e não regista nada.
  - Depois do envio, apagar `DeviceToken::whereIn('token', $result->invalidTokens)` e registar `PushSent`.
  - A view de pré-visualização mostra a notificação num cartão estilo telemóvel e, abaixo, o formulário com a caixa "Confirmo o envio para N dispositivos".

- [ ] **Step 4: Correr** o filtro. Esperado: PASSAR.

- [ ] **Step 5: Commit** com a mensagem `feat: admin manual push to all devices (PB-109)`.

---

### Task 7: Registo das execuções agendadas

**Files:**
- Create: migração `create_scheduled_task_runs_table`, `app/Models/ScheduledTaskRun.php`, `app/Listeners/RecordScheduledTaskRun.php`
- Modify: `app/Providers/AppServiceProvider.php` (singleton e `Event::listen` dos 4 eventos)
- Modify: `routes/console.php` (`Schedule::command('model:prune')->daily()`)
- Test: `tests/Feature/RecordScheduledTaskRunTest.php`

**Interfaces:**
- Produces:
  - O modelo `ScheduledTaskRun`, com os casts `started_at`/`finished_at` datetime e as constantes `STATUS_RUNNING`, `STATUS_SUCCESS`, `STATUS_FAILED` e `STATUS_SKIPPED` ('running', 'success', 'failed', 'skipped'). Tem `prunable()` para linhas com `started_at` anterior a now()-7 dias.
  - `ScheduledTaskRun::commandName(Illuminate\Console\Scheduling\Event $event): string`: o texto do `$event->command` depois de `'artisan' ` (por exemplo `app:process-arrival-alerts`), ou `$event->getSummaryForDisplay()` se não for um comando artisan.
  - O listener `RecordScheduledTaskRun`, com `handleStarting`, `handleFinished`, `handleFailed` e `handleSkipped`. Fica registado como **singleton**, porque guarda `array<int, int>` de `spl_object_id($event)` para o id da linha.

- [ ] **Step 1: Escrever os testes (falham).** O `Event` é construído com `app(Schedule::class)->command('app:process-arrival-alerts')` e os eventos são disparados com `event(new ScheduledTaskStarting($task))` e afins.

```php
test_starting_then_finished_with_zero_exit_records_success()  // $task->exitCode = 0; runtime 1.25 → status success, duration_ms 1250, finished_at preenchido
test_finished_with_nonzero_exit_records_failed()               // exitCode 1 → failed, exit_code 1
test_finished_with_null_exit_code_records_skipped()            // exitCode null (salto por withoutOverlapping) → skipped
test_failed_event_records_error_message()                      // ScheduledTaskFailed com RuntimeException('boom') → failed, error 'boom', finished_at preenchido
test_skipped_event_creates_skipped_row()                       // sem Starting → nova linha skipped
test_command_name_is_extracted()                               // command 'app:process-arrival-alerts'
test_prune_removes_runs_older_than_7_days()                    // artisan model:prune --model=App\\Models\\ScheduledTaskRun
```

- [ ] **Step 2: Correr** `php artisan test --filter=RecordScheduledTaskRunTest`. Esperado: FAIL.

- [ ] **Step 3: Implementar** a migração (colunas da especificação), o modelo, o listener e o registo no provider, e acrescentar o `model:prune` diário ao `routes/console.php`.
  - O `handleFinished` sem linha `running` correspondente não faz nada.

- [ ] **Step 4: Correr** `php artisan test`. Esperado: tudo a PASSAR.

- [ ] **Step 5: Commit** com a mensagem `feat: record scheduled task runs (PB-108)`.

---

### Task 8: Dashboard com o estado das tarefas e os contadores

**Files:**
- Create: `app/Services/ScheduledTaskStatus.php`
- Modify: `app/Http/Controllers/Admin/DashboardController.php`, `resources/views/admin/dashboard.blade.php`
- Test: `tests/Unit/ScheduledTaskStatusTest.php` (estende `Tests\TestCase` e usa `RefreshDatabase`), `tests/Feature/Admin/DashboardTest.php`

**Interfaces:**
- Consumes: `ScheduledTaskRun` e `commandName()` (Task 7); `ArrivalAlert::expired()` (Task 1); `DeviceToken`
- Produces: `ScheduledTaskStatus::forEvent(Event $event): array`, que devolve as chaves `command`, `frequency`, `next_run` (Carbon), `last_run` (?ScheduledTaskRun), `recent` (Collection de 10) e `late` (bool).
  - **Frequência:** `* * * * *` dá "todos os minutos".
  - `m h * * *` dá "todos os dias às HH:MM".
  - `m h * * d,d` dá os dias abreviados ("sáb e dom às HH:MM", com 0 = dom e 6 = sáb).
  - Qualquer outra expressão aparece tal como está.
  - Em todos os casos acrescenta-se o fuso se não for UTC.
- **Regra do atraso** (igual à especificação):
  - `$due = (new CronExpression($event->expression))->getPreviousRunDate(now($event->timezone)->subMinutes(2), 0, true)`.
  - `late = $lastRun !== null && $lastRun->started_at < $due`.
  - Sem execuções, `late` é false e a view mostra "Sem execuções registadas".

- [ ] **Step 1: Escrever os testes (falham):**

```php
// Unit, travelTo('2026-10-08 10:05:30 UTC')
test_every_minute_task_is_late_after_three_minutes()   // última execução 10:02:00 → late true
test_every_minute_task_is_not_late_with_recent_run()   // última execução 10:04:00 → false
test_task_without_runs_is_not_late()                   // nenhuma linha → late false, last_run null
test_weekend_task_is_late_when_saturday_run_missing()  // weekends()->at('03:00') Europe/Lisbon; travelTo domingo 2026-10-11 12:00 Lisboa; última execução sexta → true
test_frequency_texts()                                 // '* * * * *' → 'todos os minutos'; '0 3 * * 6,0' com Europe/Lisbon → 'sáb e dom às 03:00 (Europe/Lisbon)'
// Feature
test_dashboard_shows_a_card_per_scheduled_task()       // assertSee('app:sync-stcp-lines'), assertSee('app:process-arrival-alerts')
test_dashboard_shows_counters()                        // 2 alertas (1 expirado), 3 tokens → assertSee nos números, com links para admin.alerts.index e admin.push.create
test_dashboard_shows_late_warning_and_last_error()     // linha failed com error 'boom' há 10 min → assertSee('Atrasado'), assertSee('boom')
```

- [ ] **Step 2: Correr** `php artisan test --filter='ScheduledTaskStatusTest|DashboardTest'`. Esperado: FAIL.

- [ ] **Step 3: Implementar o `ScheduledTaskStatus`** e o `DashboardController::index(Schedule $schedule, ScheduledTaskStatus $status)`, com os cartões a partir de `$schedule->events()`, e a view.
  - A view tem os cartões com estado ✅/❌/⏭️, um selo vermelho "Atrasado", `<details>` com as 10 execuções recentes e os três contadores.
  - Excluir o próprio `model:prune` dos cartões não é necessário: aparece como as outras tarefas.

- [ ] **Step 4: Correr** `php artisan test`. Esperado: tudo a PASSAR.

- [ ] **Step 5: Commit** com a mensagem `feat: admin dashboard with scheduled task status (PB-108)`.

---

### Task 9: Verificação final

- [ ] **Step 1:** Correr `./vendor/bin/pint --test && php artisan test`. Esperado: sem diferenças de estilo e todos os testes a passar.
- [ ] **Step 2:** Correr `npm run build`. Esperado: o build do Vite termina sem erros (as views do admin usam classes Tailwind).
