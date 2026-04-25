<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') - Porto:Bus</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #FF750F;
            --primary-dark: #E66400;
            --bg-gradient: linear-gradient(135deg, #fdfdfc 0%, #f7f7f7 100%);
            --glass: rgba(255, 255, 255, 0.8);
            --text-main: #1b1b18;
            --text-muted: #706f6c;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg-gradient: linear-gradient(135deg, #0a0a0a 0%, #161615 100%);
                --glass: rgba(22, 22, 21, 0.8);
                --text-main: #ededec;
                --text-muted: #a1a09a;
            }
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-gradient);
            color: var(--text-main);
            min-height: 100vh;
            line-height: 1.6;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2rem 1rem;
        }

        .container {
            max-width: 800px;
            width: 100%;
            background: var(--glass);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 3rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-top: 2rem;
            animation: fadeIn 0.8s ease-out;
        }

        header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            text-transform: lowercase;
            letter-spacing: -1px;
        }

        .logo span {
            color: var(--text-main);
        }

        h1 {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--text-main);
        }

        h2 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-top: 2rem;
            margin-bottom: 1rem;
            color: var(--primary);
        }

        p {
            margin-bottom: 1rem;
            color: var(--text-muted);
        }

        ul {
            margin-bottom: 1.5rem;
            padding-left: 1.5rem;
            color: var(--text-muted);
        }

        li {
            margin-bottom: 0.5rem;
        }

        footer {
            margin-top: auto;
            padding: 2rem;
            text-align: center;
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 640px) {
            .container {
                padding: 2rem 1.5rem;
            }
            .logo {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">porto:<span>bus</span></div>
    </header>

    <div class="container">
        @yield('content')
    </div>

    <footer>
        &copy; {{ date('Y') }} Portugal Bus. Todos os direitos reservados.
    </footer>
</body>
</html>
