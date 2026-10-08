<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') - Porto:Bus Admin</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gray-50 text-gray-900 antialiased">
    <header class="border-b border-gray-200 bg-white">
        <nav class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3">
            <a href="{{ route('admin.dashboard') }}" class="font-semibold">Porto:Bus Admin</a>
            @php
                $links = [
                    'admin.dashboard' => 'Painel',
                    'admin.alerts.index' => 'Alertas',
                    'admin.push.create' => 'Push',
                    'admin.actions.index' => 'Registo',
                ];
            @endphp
            @foreach ($links as $route => $label)
                @if (Route::has($route))
                    <a href="{{ route($route) }}"
                       class="text-sm {{ request()->routeIs($route) ? 'font-semibold text-blue-600' : 'text-gray-600 hover:text-gray-900' }}">{{ $label }}</a>
                @endif
            @endforeach
            <span class="ml-auto text-sm text-gray-500">{{ $adminEmail }}</span>
        </nav>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-6">
        @if (session('success'))
            <div class="mb-4 rounded border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <h1 class="mb-6 text-2xl font-semibold">@yield('title')</h1>

        @yield('content')
    </main>
</body>
</html>
