@extends('admin.layout')

@section('title', 'Push enviado')

@section('content')
    <ul class="max-w-xl space-y-1 rounded border border-gray-200 bg-white p-4 text-sm">
        <li>Destinatários: <strong>{{ $recipients }}</strong></li>
        <li class="text-green-700">{{ $result->successCount }} enviados</li>
        <li class="{{ $result->failureCount ? 'text-red-700' : 'text-gray-500' }}">{{ $result->failureCount }} falhados</li>
        <li class="text-gray-500">{{ $removed }} tokens inválidos removidos</li>
    </ul>

    <a href="{{ route('admin.push.create') }}" class="mt-4 inline-block text-sm text-blue-600 hover:underline">Novo push</a>
@endsection
