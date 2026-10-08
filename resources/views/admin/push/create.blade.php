@extends('admin.layout')

@section('title', 'Push manual')

@section('content')
    <p class="mb-4 text-sm text-gray-600">
        Envia uma notificação para <strong>todos</strong> os dispositivos: {{ $deviceCount }} dispositivos registados.
    </p>

    <form method="POST" action="{{ route('admin.push.preview') }}" class="max-w-xl space-y-4 rounded border border-gray-200 bg-white p-4">
        @csrf

        <div>
            <label for="title" class="block text-sm font-medium">Título <span class="text-gray-400">(máx. 65)</span></label>
            <input id="title" name="title" type="text" maxlength="65" required value="{{ old('title') }}"
                   class="mt-1 w-full rounded border border-gray-300 px-3 py-2">
            @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="body" class="block text-sm font-medium">Texto <span class="text-gray-400">(máx. 240)</span></label>
            <textarea id="body" name="body" rows="4" maxlength="240" required
                      class="mt-1 w-full rounded border border-gray-300 px-3 py-2">{{ old('body') }}</textarea>
            @error('body') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
            Pré-visualizar
        </button>
    </form>
@endsection
