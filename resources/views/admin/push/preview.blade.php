@extends('admin.layout')

@section('title', 'Confirmar push')

@section('content')
    <div class="mb-6 max-w-sm rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
        <p class="text-xs uppercase tracking-wide text-gray-400">Porto:Bus · agora</p>
        <p class="mt-1 font-semibold">{{ $pending['title'] }}</p>
        <p class="text-sm text-gray-700">{{ $pending['body'] }}</p>
    </div>

    @if ($pending['recipients'] === 0)
        <p class="text-red-600">Não há dispositivos registados. Nada a enviar.</p>
        <a href="{{ route('admin.push.create') }}" class="mt-4 inline-block text-sm text-blue-600 hover:underline">Voltar</a>
    @else
        <form method="POST" action="{{ route('admin.push.send') }}" class="max-w-xl space-y-4 rounded border border-amber-300 bg-amber-50 p-4">
            @csrf
            <input type="hidden" name="nonce" value="{{ $pending['nonce'] }}">

            <p>Vai ser enviado para <strong>{{ $pending['recipients'] }} dispositivos</strong>.</p>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="confirm" value="1" required>
                Confirmo o envio para {{ $pending['recipients'] }} dispositivos
            </label>
            @error('confirm') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <div class="flex items-center gap-4">
                <button type="submit" class="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Enviar</button>
                <a href="{{ route('admin.push.create') }}" class="text-sm text-gray-600 hover:underline">Cancelar</a>
            </div>
        </form>
    @endif
@endsection
