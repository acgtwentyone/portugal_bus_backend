@extends('admin.layout')

@section('title', 'Painel')

@section('content')
    @php
        $statusIcon = ['success' => '✅', 'failed' => '❌', 'skipped' => '⏭️', 'running' => '⏳'];
        $lisbon = fn ($date) => $date?->timezone('Europe/Lisbon')->format('d/m H:i:s');
    @endphp

    <div class="mb-8 grid gap-4 sm:grid-cols-3">
        <a href="{{ route('admin.alerts.index') }}" class="rounded border border-gray-200 bg-white p-4 hover:border-blue-400">
            <p class="text-sm text-gray-500">Alertas ativos</p>
            <p class="text-2xl font-semibold">{{ $activeAlerts }}</p>
        </a>
        <a href="{{ route('admin.alerts.index') }}" class="rounded border border-gray-200 bg-white p-4 hover:border-blue-400">
            <p class="text-sm text-gray-500">Alertas expirados</p>
            <p class="text-2xl font-semibold {{ $expiredAlerts ? 'text-red-600' : '' }}">{{ $expiredAlerts }}</p>
        </a>
        <a href="{{ route('admin.push.create') }}" class="rounded border border-gray-200 bg-white p-4 hover:border-blue-400">
            <p class="text-sm text-gray-500">Dispositivos registados</p>
            <p class="text-2xl font-semibold">{{ $devices }}</p>
        </a>
    </div>

    <h2 class="mb-3 text-lg font-semibold">Tarefas agendadas</h2>

    <div class="space-y-4">
        @foreach ($tasks as $task)
            <div class="rounded border {{ $task['late'] ? 'border-red-400' : 'border-gray-200' }} bg-white p-4">
                <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <p class="font-mono font-medium">{{ $task['command'] }}</p>
                    <p class="text-sm text-gray-500">{{ $task['frequency'] }}</p>
                    @if ($task['late'])
                        <span class="rounded bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">Atrasado</span>
                    @endif
                    <p class="ml-auto text-sm text-gray-500">Próxima: {{ $lisbon($task['next_run']) }}</p>
                </div>

                @if ($run = $task['last_run'])
                    <p class="mt-2 text-sm">
                        Última: {{ $statusIcon[$run->status] ?? '' }} {{ $lisbon($run->started_at) }}
                        @if ($run->duration_ms !== null)
                            <span class="text-gray-500">· {{ number_format($run->duration_ms / 1000, 2) }}s</span>
                        @endif
                    </p>
                    @if ($run->error)
                        <p class="mt-1 rounded bg-red-50 px-2 py-1 font-mono text-xs text-red-700">{{ $run->error }}</p>
                    @endif

                    <details class="mt-2 text-sm">
                        <summary class="cursor-pointer text-gray-600">Últimas execuções</summary>
                        <ul class="mt-2 space-y-0.5 font-mono text-xs">
                            @foreach ($task['recent'] as $recent)
                                <li>
                                    {{ $statusIcon[$recent->status] ?? '' }} {{ $lisbon($recent->started_at) }}
                                    @if ($recent->duration_ms !== null) · {{ number_format($recent->duration_ms / 1000, 2) }}s @endif
                                    @if ($recent->exit_code) · exit {{ $recent->exit_code }} @endif
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @else
                    <p class="mt-2 text-sm text-gray-500">Sem execuções registadas</p>
                @endif
            </div>
        @endforeach
    </div>
@endsection
