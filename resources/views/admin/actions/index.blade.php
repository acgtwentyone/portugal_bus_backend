@extends('admin.layout')

@section('title', 'Registo de ações')

@section('content')
    @if ($actions->isEmpty())
        <p class="text-gray-500">Ainda não há ações registadas.</p>
    @else
        <div class="overflow-x-auto rounded border border-gray-200 bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-600">
                    <tr>
                        <th class="px-3 py-2">Data</th>
                        <th class="px-3 py-2">Quem</th>
                        <th class="px-3 py-2">Ação</th>
                        <th class="px-3 py-2">Detalhes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($actions as $action)
                        <tr>
                            <td class="whitespace-nowrap px-3 py-2">{{ $action->created_at->timezone('Europe/Lisbon')->format('Y-m-d H:i:s') }}</td>
                            <td class="px-3 py-2">{{ $action->admin_email }}</td>
                            <td class="px-3 py-2 font-mono">{{ $action->action->value }}</td>
                            <td class="px-3 py-2 font-mono text-xs text-gray-600">
                                {{ Str::limit(json_encode($action->payload, JSON_UNESCAPED_UNICODE), 120) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $actions->links() }}</div>
    @endif
@endsection
