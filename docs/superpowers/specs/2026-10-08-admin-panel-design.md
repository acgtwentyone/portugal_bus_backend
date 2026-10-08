# Painel de administração — Design

- **Ticket:** [PB-67](https://ae-simplified.atlassian.net/browse/PB-67) (subtarefas PB-106 a PB-110)
- **Data:** 2026-10-08
- **Estado:** aprovado em conversa, aguarda revisão da spec escrita

## Objetivo

Painel interno em `admin.portugal-bus.com` para a operação do dia a dia sem SSH nem
MySQL Workbench. Um único administrador, sem contas de utilizador.

## Âmbito deste corte

| Subtarefa | O que entra |
|---|---|
| PB-106 Autenticação | Cloudflare Access + validação do JWT do Access no Laravel |
| PB-110 Registo de ações | Tabela só de inserção + página de consulta |
| PB-107 Alertas | Listar alertas ativos, cancelar um, limpar expirados |
| PB-109 Push manual | Push para todos os dispositivos, com pré-visualização e confirmação no servidor |
| PB-108 Comandos | **Só leitura:** estado das tarefas agendadas e deteção de atrasos |

### Fora do âmbito

- **Executar comandos a partir do painel.** Os botões de execução ficam adiados: são raros, o SSH resolve, e exigiriam um worker de filas que não existe hoje. Ver "Evolução".
- **Limpeza de alertas "órfãos".** Não há uma definição fiável: a app pode criar alertas sem ter registado o token em `device_tokens`.
- **Agendar `SyncUnirLines`.** Fica manual até a Unir estar ativa na app.
- Gestão de linhas e paragens, dashboards de utilização, push segmentado ou agendado, vários utilizadores ou perfis.

## Contexto do projeto (estado atual)

- Laravel 12, Blade, Tailwind 4 e Vite. Sem Livewire nem Inertia.
- O `config('app.admin_domain')` já existe. Em `routes/web.php` há um grupo `Route::domain(...)` com um `/admin-placeholder`.
- Existe `App\Http\Controllers\Admin\StcpController::forceSync()`, sem rota, que chama `Artisan::call()` de forma síncrona.
- Produção (VPS): o cron do host corre `docker compose exec -T app php artisan schedule:run` todos os minutos. **Não há `queue:work`.**
- O DNS está no Cloudflare e o registo `admin` já está em modo proxied.
- `firebase/php-jwt` está disponível como dependência transitiva do `kreait/firebase-php`.

## Decisões

| Decisão | Escolha | Motivo |
|---|---|---|
| Stack de UI | Blade, Tailwind e controllers normais | Segue o padrão existente; são 4 páginas; sem dependências novas. Filament foi rejeitado: é pesado e pressupõe o seu próprio login. |
| Autenticação | Cloudflare Access + validação do JWT | O ataque é travado antes do servidor; não há passwords para gerir; a validação do JWT fecha o acesso direto à origem. |
| Push | Síncrono, dentro do pedido web | Lotes de 500 tokens, segundos para milhares de dispositivos. Não exige worker. |
| Estado dos comandos | Eventos do scheduler | Não exige worker nem alterações ao cron. |

## Arquitetura

### Rotas e estrutura

```
routes/web.php
  Route::domain(config('app.admin_domain'))->middleware('admin.access')
    GET  /                    Admin\DashboardController@index     (estado das tarefas + contadores)
    GET  /alerts              Admin\AlertController@index
    DELETE /alerts/{alert}    Admin\AlertController@destroy
    POST /alerts/purge        Admin\AlertController@purgeExpired
    GET  /push                Admin\PushController@create
    POST /push/preview        Admin\PushController@preview
    POST /push/send           Admin\PushController@send
    GET  /actions             Admin\AuditLogController@index
```

- A rota `/admin-placeholder` é retirada.
- O `Admin\StcpController` é **removido** (código morto e uma forma insegura de correr comandos).
- Views em `resources/views/admin/`, com o layout `admin/layout.blade.php`: navegação entre as 4 páginas e o email de quem está ligado.

### Configuração

`config/admin.php`, lido do `.env`:

| Variável | Uso |
|---|---|
| `CF_ACCESS_TEAM_DOMAIN` | ex.: `portugalbus.cloudflareaccess.com`; define o `iss` esperado e o URL dos certificados |
| `CF_ACCESS_AUD` | identificador (AUD tag) da aplicação no Access |
| `ADMIN_EMAILS` | lista separada por vírgulas dos emails autorizados |
| `ADMIN_DEV_EMAIL` | email usado no modo local (ver abaixo) |

## PB-106 — Autenticação

### Middleware `VerifyCloudflareAccess` (alias `admin.access`)

1. Lê o header `Cf-Access-Jwt-Assertion`. Se faltar, responde **403**.
2. Valida a assinatura RS256 com as chaves de `https://<CF_ACCESS_TEAM_DOMAIN>/cdn-cgi/access/certs`. As chaves ficam em cache durante 1 hora. Se o `kid` do token não estiver em cache, a cache é renovada uma vez (para cobrir a rotação de chaves).
3. Confirma que o `aud` contém `CF_ACCESS_AUD`, que o `iss` é `https://<CF_ACCESS_TEAM_DOMAIN>` e que o token não expirou.
4. Confirma que o `email` está em `ADMIN_EMAILS`, como defesa caso a política do Access fique larga demais. Se não estiver, responde **403**.
5. Guarda o email no pedido (`$request->attributes->set('admin_email', ...)`) para o registo de ações e o layout.

- **Biblioteca:** `firebase/php-jwt`, passada a dependência direta no `composer.json`.
- **Modo local:** se `app()->environment('local')` e `ADMIN_DEV_EMAIL` estiver definido, a validação é saltada e é usado esse email. Fora de `local`, esta via não existe.

### Configuração manual no Cloudflare (Zero Trust)

1. Access → Applications → Add → **Self-hosted**, com o domínio `admin.portugal-bus.com`.
2. Política **Allow** com `Emails` igual ao email do administrador.
3. Métodos de login: One-time PIN e, opcionalmente, Google.
4. Copiar a **Application Audience (AUD) Tag** para `CF_ACCESS_AUD` e o team domain para `CF_ACCESS_TEAM_DOMAIN`.

## PB-110 — Registo de ações

### Tabela `admin_actions` (só inserção)

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | bigint | |
| `admin_email` | string | vem do middleware |
| `action` | string | valor do `AdminActionEnum` |
| `subject_id` | unsignedBigInteger, nullable | ex.: id do alerta cancelado |
| `payload` | json | detalhes da ação (ver cada secção) |
| `ip` | string, nullable | `CF-Connecting-IP`, com recurso a `$request->ip()` |
| `created_at` | timestamp | sem `updated_at` |

- **`App\AdminActionEnum`:** `AlertCancelled = 'alert.cancelled'`, `AlertsPurged = 'alerts.purged'`, `PushSent = 'push.sent'`.
- **`App\Services\AdminAuditLog::record(AdminActionEnum $action, ?int $subjectId = null, array $payload = [])`:** lê o email e o IP do pedido atual.

### Regras

- Só as ações de escrita são registadas.
- Ações sobre a base de dados (cancelar e limpar alertas) correm na **mesma transação** que o registo.
- O push é registado **depois** do envio, com o resultado real.
- Não há update nem delete, nem pelo painel nem pelo código da aplicação. Guarda-se tudo.

### Página `/actions`

Tabela paginada (50 por página), da mais recente para a mais antiga: data, email, ação e resumo do payload. Sem filtros neste corte.

## PB-107 — Gestão de alertas

### Regra de expiração partilhada

Hoje a regra está dentro de `ProcessArrivalAlerts::expireStaleAlerts()`:
agora (`Europe/Lisbon`) é depois de `estimated_arrival_time + config('app.arrival_alert_expiry_grace_minutes')`.

Esta regra passa para o modelo:

- `ArrivalAlert::isExpired(): bool`
- `ArrivalAlert::scopeExpired(Builder $query)`: o mesmo critério em SQL

O `ProcessArrivalAlerts` passa a usar `isExpired()` no filtro. O comportamento do comando não muda.

### Página `/alerts`

- Ordenada por `estimated_arrival_time` ascendente, 50 por página.
- Colunas: paragem, linha e sentido, `trip_id`, hora estimada, `threshold_minutes`, idade (`created_at` em formato relativo), locale e token cortado (primeiros 8 caracteres e `…`).
- Os alertas expirados levam um selo "Expirado".

### Ações

- **Cancelar** (`DELETE /alerts/{alert}`): apaga o alerta, sem push para o dispositivo, e regista `alert.cancelled` com `subject_id` igual ao id e o payload `{stop_id, route_id, direction_id, trip_id, estimated_arrival_time}`.
- **Limpar expirados** (`POST /alerts/purge`): apaga `ArrivalAlert::expired()` e regista `alerts.purged` com o payload `{count, ids}`.
  - O botão mostra o número ("Limpar N expirados") e fica desativado quando N é 0.
  - Se não houver nada para apagar no momento do pedido, não regista nada.
- As duas ações pedem confirmação no browser e redirecionam com uma mensagem flash.

## PB-109 — Push manual

### Fluxo

1. **`GET /push`:** formulário com título (obrigatório, até 65 caracteres) e texto (obrigatório, até 240), mais o número atual de `device_tokens`.
2. **`POST /push/preview`:** valida e guarda na sessão `{nonce, title, body, recipients}`. Mostra a pré-visualização e "Vai ser enviado para **N dispositivos**". O formulário de confirmação leva o `nonce` e a caixa obrigatória "Confirmo o envio para N dispositivos".
3. **`POST /push/send`:** confirma a caixa e o `nonce` da sessão.
   - O `nonce` é **retirado da sessão antes de enviar**, por isso um refresh ou duplo clique não reenvia.
   - O envio vai para os tokens que existem nesse momento.
   - No fim regista `push.sent` e mostra o resultado.

- **Payload do registo:** `{title, body, recipients_previewed, recipients_sent, success, failure, invalid_tokens_removed}`.
- **`data` do push:** `{"type": "admin_broadcast"}`.
- **Idioma:** um único texto para todos os dispositivos, porque o `device_tokens` não guarda o locale.

### `FcmPushService::sendToMany()`

```php
public function sendToMany(array $tokens, string $title, string $body, array $data = []): MulticastResult
```

- Divide os tokens em lotes de 500 e usa `Messaging::sendMulticast()`.
- Devolve `MulticastResult {successCount, failureCount, invalidTokens}`, que junta os `invalidTokens()` e os `unknownTokens()` do relatório do kreait.
- As falhas de rede de um lote são registadas no log e contam como falha, sem interromper os restantes lotes.
- O `send()` existente não muda.

### Tokens inválidos

Os tokens que o FCM reporta como inválidos ou desconhecidos são apagados de `device_tokens` logo a seguir ao envio. Os alertas não são afetados.

## PB-108 — Estado das tarefas agendadas (só leitura)

### Recolha

O listener `App\Listeners\RecordScheduledTaskRun` ouve os eventos do scheduler. Associa o início ao fim de cada execução pelo `spl_object_id` da tarefa, porque o `schedule:run` corre as tarefas no próprio processo.

| Evento | Efeito |
|---|---|
| `ScheduledTaskStarting` | cria uma linha com `status = running` e `started_at` |
| `ScheduledTaskFinished` | `success` ou `failed` conforme o `exitCode`, mais `finished_at` e `duration_ms` |
| `ScheduledTaskFailed` | `failed` e `error` com a mensagem da exceção |
| `ScheduledTaskSkipped` | cria uma linha `skipped` |

### Tabela `scheduled_task_runs`

| Coluna | Tipo |
|---|---|
| `id` | bigint |
| `command` | string, com índice (`command`, `started_at`) |
| `status` | string: `running`, `success`, `failed` ou `skipped` |
| `started_at` | timestamp |
| `finished_at` | timestamp, nullable |
| `duration_ms` | unsignedInteger, nullable |
| `exit_code` | smallInteger, nullable |
| `error` | text, nullable |

- O modelo `ScheduledTaskRun` usa `Prunable` e guarda **7 dias**.
- O `Schedule::command('model:prune')->daily()` é acrescentado a `routes/console.php`.

### Página inicial `/`

Um cartão por cada tarefa de `Schedule::events()`, por isso as tarefas novas aparecem sem mexer no painel. Cada cartão mostra:

- nome do comando, frequência em texto, a partir da expressão cron, e a próxima execução;
- a última execução: data, duração e estado; se falhou, a mensagem de erro;
- **um aviso de atraso**, se a última execução (`started_at`) for anterior à última data prevista pela expressão cron menos uma tolerância de 2 minutos. Exemplo: `ProcessArrivalAlerts` sem execuções há mais de 3 minutos. Serve para detetar um cron do host parado ou um contentor em baixo;
- as últimas 10 execuções, numa lista que se abre com `<details>`.

Contadores rápidos com link para as páginas: alertas ativos, alertas expirados e dispositivos registados.

## Tratamento de erros

| Situação | Comportamento |
|---|---|
| JWT ausente, inválido ou expirado, ou email não autorizado | 403, sem detalhes na resposta; o motivo vai para o log com nível `warning` |
| Endpoint de certificados do Cloudflare indisponível | usa as chaves em cache; sem cache, responde 503 e regista em log |
| Alerta já apagado (pelo comando) quando se cancela | 404 do route model binding; mostra a mensagem "Alerta já não existe" |
| `nonce` do push ausente ou repetido | redireciona para `/push` com o erro "Pré-visualização expirada, volta a gerar" |
| Falha de um lote no FCM | conta como falha, segue para os outros lotes e fica refletida no registo |

## Testes

Seguem a abordagem TDD. Ficam em `tests/Feature/Admin/` e `tests/Unit/`.

- **Middleware:** chaves RSA geradas no teste e endpoint de certificados simulado com `Http::fake`. Casos: token válido, header em falta, assinatura inválida, `aud` errado, `iss` errado, token expirado, email fora da lista, modo local, e garantia de que o modo local não funciona fora de `local`.
- **Registo:** cada ação de escrita verifica a linha em `admin_actions`; a página lista as ações por ordem.
- **Alertas:** `isExpired()` e `scopeExpired()` nos limites da tolerância; listagem; cancelar; limpar; limpar sem nada para apagar.
- **`ProcessArrivalAlerts`:** hoje não tem testes. **Antes** de mudar o filtro, escrever testes que fixam o comportamento atual da expiração (com `StcpService` e `FcmPushService` simulados): alerta dentro da tolerância mantém-se, alerta fora da tolerância é apagado sem push, alerta cujo limite foi atingido dispara e é apagado.
- **Push:** `sendToMany()` com `Messaging` simulado (lotes de 500, contagens, falha de rede num lote); fluxo de 3 passos; envio sem a caixa marcada; `nonce` repetido; tokens inválidos apagados; registo `push.sent`.
- **Tarefas agendadas:** o listener com os 4 eventos; o cálculo do atraso com `travelTo`; o prune aos 7 dias.

## Critérios de aceitação (PB-67) e onde são cumpridos

| Critério | Coberto por |
|---|---|
| Sem autenticação válida, nada é acessível | Middleware `admin.access` em todo o grupo de rotas e testes do middleware |
| Só se executam comandos da lista fechada | Sem execução neste corte (só leitura); o `StcpController` é removido |
| Cancelar e limpar alertas funciona e fica registado | PB-107 e PB-110 |
| O push só sai depois de confirmação explícita e mostra o número de destinatários | Fluxo de 3 passos com `nonce` e caixa obrigatória |
| Todas as ações de escrita ficam registadas | `AdminAuditLog` em todas as ações e testes |

## Evolução (não faz parte deste corte)

- **Botões de execução de comandos:** acrescentar `queue:work --stop-when-empty` ao scheduler, com `withoutOverlapping()`, e despachar jobs para uma lista fechada, começando pelos syncs.
- **Push com dezenas de milhares de dispositivos:** passar o envio para um job, com o mesmo worker.
- **Atualizar a descrição da PB-108 no Jira** para refletir o corte só de leitura.
