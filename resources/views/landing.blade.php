@extends('layouts.legal')

@section('title', 'Porto:Bus - Autocarros do Porto em Tempo Real')

@section('content')
    <style>
        .hero {
            text-align: center;
            padding: 2rem 0;
        }

        .hero h1 {
            font-size: 2.5rem;
            line-height: 1.2;
            margin-bottom: 1rem;
            background: linear-gradient(90deg, #FFFFFF, var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            border-bottom: none;
            display: block;
        }

        .hero p {
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto 2rem;
            color: var(--text-muted);
        }

        .download-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 3rem;
        }

        .btn-download {
            background-color: var(--primary);
            color: white;
            text-decoration: none;
            padding: 1rem 2rem;
            border-radius: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(33, 150, 243, 0.3);
        }

        .btn-download:hover {
            transform: translateY(-2px);
            background-color: var(--primary-dark);
            box-shadow: 0 6px 20px rgba(33, 150, 243, 0.4);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border);
            padding: 1.5rem;
            border-radius: 16px;
            text-align: center;
            transition: border-color 0.3s ease;
        }

        .feature-card:hover {
            border-color: var(--primary);
        }

        .feature-icon {
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 1rem;
            display: block;
        }

        .feature-card h3 {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            color: white;
        }

        .feature-card p {
            font-size: 0.9rem;
            margin-bottom: 0;
        }

        @media (max-width: 640px) {
            .hero h1 {
                font-size: 2rem;
            }
        }
    </style>

    <div class="hero">
        <h1>{{ __('legal.landing_hero_title') }}</h1>
        <p>{{ __('legal.landing_hero_subtitle') }}</p>

        <div class="download-buttons">
            <a href="https://play.google.com/store/apps/details?id=acg.portobus" target="_blank" rel="noopener noreferrer" class="btn-download">
                <svg style="width:24px;height:24px" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M3,20.5V3.5C3,2.91 3.34,2.39 3.84,2.15L13.69,12L3.84,21.85C3.34,21.6 3,21.09 3,20.5M16.81,15.12L6.05,21.34L14.54,12.85L16.81,15.12M20.16,10.81C20.5,11.08 20.75,11.5 20.75,12C20.75,12.5 20.5,12.92 20.16,13.19L17.89,14.5L15.39,12L17.89,9.5L20.16,10.81M6.05,2.66L16.81,8.88L14.54,11.15L6.05,2.66Z" />
                </svg>
                {{ __('legal.landing_download_app') }}
            </a>
        </div>
    </div>

    <h2 style="text-align: center; margin-bottom: 1.5rem;">{{ __('legal.landing_features_title') }}</h2>
    
    <div class="features-grid">
        <div class="feature-card">
            <span class="feature-icon">🕒</span>
            <h3>{{ __('legal.landing_feature_realtime') }}</h3>
            <p>{{ __('legal.landing_feature_realtime_desc') }}</p>
        </div>
        <div class="feature-card">
            <span class="feature-icon">🔒</span>
            <h3>{{ __('legal.landing_feature_privacy') }}</h3>
            <p>{{ __('legal.landing_feature_privacy_desc') }}</p>
        </div>
        <div class="feature-card">
            <span class="feature-icon">📱</span>
            <h3>{{ __('legal.landing_feature_simple') }}</h3>
            <p>{{ __('legal.landing_feature_simple_desc') }}</p>
        </div>
    </div>

    <div style="margin-top: 4rem; text-align: center; opacity: 0.5; font-size: 0.8rem;">
        <p>Aceda às nossas políticas:</p>
        <div style="display: flex; justify-content: center; gap: 1rem;">
            <a href="{{ route('legal.privacy') }}" style="color: var(--primary); text-decoration: none;">Privacy</a>
            <a href="{{ route('legal.terms') }}" style="color: var(--primary); text-decoration: none;">Terms</a>
            <a href="{{ route('legal.privacy_policy') }}" style="color: var(--primary); text-decoration: none;">Policy</a>
        </div>
    </div>
@endsection
