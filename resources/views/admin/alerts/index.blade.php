@extends('admin.layout')

@section('title', 'Alertas de chegada')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-gray-600">{{ $alerts->total() }} alertas ativos</p>

        <form method="POST" action="{{ route('admin.alerts.purge') }}"
              onsubmit="return confirm('Apagar {{ $expiredCount }} alertas expirados?')">
            @csrf
            <button type="submit" @disabled($expiredCount === 0)
                    class="rounded bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:bg-gray-300">
                Limpar {{ $expiredCount }} expirados
            </button>
        </form>
    </div>

    @if ($alerts->isEmpty())
        <p class="text-gray-500">Não há alertas ativos.</p>
    @else
        <div class="overflow-x-auto rounded border border-gray-200 bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-600">
                    <tr>
                        <th class="px-3 py-2">Paragem</th>
                        <th class="px-3 py-2">Linha</th>
                        <th class="px-3 py-2">Viagem</th>
                        <th class="px-3 py-2">Chegada prevista</th>
                        <th class="px-3 py-2">Aviso</th>
                        <th class="px-3 py-2">Idade</th>
                        <th class="px-3 py-2">Locale</th>
                        <th class="px-3 py-2">Dispositivo</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($alerts as $alert)
                        <tr>
                            <td class="px-3 py-2 font-mono">{{ $alert->stop_id }}</td>
                            <td class="px-3 py-2">{{ $alert->route_id }} <span class="text-gray-500">(sentido {{ $alert->direction_id }})</span></td>
                            <td class="px-3 py-2 font-mono text-xs">{{ $alert->trip_id }}</td>
                            <td class="whitespace-nowrap px-3 py-2">
                                {{ $alert->estimated_arrival_time->timezone('Europe/Lisbon')->format('d/m H:i') }}
                                @if ($alert->isExpired())
                                    <span class="ml-1 rounded bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-700">Expirado</span>
                                @endif
                            </td>
                            <td class="px-3 py-2">{{ $alert->threshold_minutes }} min</td>
                            <td class="whitespace-nowrap px-3 py-2">{{ $alert->created_at->locale('pt')->diffForHumans() }}</td>
                            <td class="px-3 py-2">{{ $alert->locale }}</td>
                            <td class="px-3 py-2 font-mono text-xs">{{ Str::substr($alert->device_token, 0, 8) }}…</td>
                            <td class="px-3 py-2 text-right">
                                <form method="POST" action="{{ route('admin.alerts.destroy', $alert) }}"
                                      onsubmit="return confirm('Cancelar o alerta #{{ $alert->id }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm text-red-600 hover:underline">Cancelar</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $alerts->links() }}</div>
    @endif
@endsection
