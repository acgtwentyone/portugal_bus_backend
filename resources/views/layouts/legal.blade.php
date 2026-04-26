<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') - Porto:Bus</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Favicon & App Icons -->
    <link rel="icon" type="image/png" href="{{ asset('assets/app-logo/logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('assets/app-logo/logo@2x.png') }}">
    <link rel="manifest" href="{{ asset('assets/app-logo/manifest.json') }}">
    <style>
        :root {
            --primary: #2196F3;
            --primary-dark: #1976D2;
            --bg-body: #121212;
            --bg-card: #1E1E1E;
            --text-main: #FFFFFF;
            --text-muted: #A1A1A1;
            --border: #333333;
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            line-height: 1.6;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 3rem 1.5rem;
        }

        .container {
            max-width: 720px;
            width: 100%;
            background-color: var(--bg-card);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            animation: slideUp 0.6s ease-out;
        }

        header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .app-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            animation: pulse 2s infinite ease-in-out;
        }

        .app-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 18px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }

        .logo {
            font-size: 2.25rem;
            font-weight: 700;
            color: white;
            letter-spacing: -0.5px;
        }

        header a {
            text-decoration: none;
            color: inherit;
            display: inline-block;
        }

        .logo span {
            color: var(--primary);
        }

        h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: white;
            border-bottom: 2px solid var(--primary);
            display: inline-block;
            padding-bottom: 0.25rem;
        }

        h2 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-top: 2rem;
            margin-bottom: 1rem;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        p {
            margin-bottom: 1.25rem;
            color: var(--text-muted);
            font-size: 1rem;
        }

        ul {
            margin-bottom: 1.5rem;
            padding-left: 1.25rem;
            color: var(--text-muted);
        }

        li {
            margin-bottom: 0.75rem;
        }

        strong {
            color: var(--text-main);
            font-weight: 600;
        }

        .disclaimer {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border);
            font-size: 0.9rem;
            color: var(--text-muted);
            font-style: italic;
        }

        footer {
            margin-top: auto;
            padding: 3rem 1rem 1rem;
            text-align: center;
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .language-switcher {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }

        .lang-btn {
            background-color: var(--bg-card);
            border: 1px solid var(--border);
            color: var(--text-muted);
            padding: 0.4rem 0.8rem;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            text-transform: uppercase;
        }

        .lang-btn:hover {
            border-color: var(--primary);
            color: white;
        }

        .lang-btn.active {
            background-color: var(--primary);
            border-color: var(--primary);
            color: white;
            box-shadow: 0 0 15px rgba(33, 150, 243, 0.3);
        }

        @media (max-width: 640px) {
            body {
                padding: 1.5rem 1rem;
            }
            .container {
                padding: 1.75rem;
                border-radius: 16px;
            }
            .logo {
                font-size: 1.75rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <a href="{{ route('landing', ['lang' => app()->getLocale()]) }}">
            <div class="app-icon">
                <img src="{{ asset('assets/app-logo/logo@3x.png') }}" alt="Porto:Bus Logo">
            </div>
            <div class="logo">porto:<span>bus</span></div>
        </a>
        
        <div class="language-switcher">
            <a href="?lang=pt" class="lang-btn {{ app()->getLocale() == 'pt' ? 'active' : '' }}">PT</a>
            <a href="?lang=en" class="lang-btn {{ app()->getLocale() == 'en' ? 'active' : '' }}">EN</a>
            <a href="?lang=es" class="lang-btn {{ app()->getLocale() == 'es' ? 'active' : '' }}">ES</a>
        </div>
    </header>

    <div class="container">
        @yield('content')
        
        <div class="disclaimer">
            <strong>{{ __('legal.disclaimer_title') }}:</strong> {!! __('legal.disclaimer_content') !!}
        </div>
    </div>

    <footer>
        <div style="margin-bottom: 1rem; opacity: 0.6;">
            <img src="{{ asset('assets/stcp_logo.png') }}" alt="STCP Logo" style="height: 30px; filter: grayscale(1) brightness(2);">
        </div>
        &copy; {{ date('Y') }} Portugal Bus. {{ __('legal.developed_by') }} Acg.
    </footer>
</body>
</html>
