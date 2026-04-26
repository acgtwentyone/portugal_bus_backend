@extends('layouts.legal')

@section('title', __('legal.privacy_title'))

@section('content')
    <h1>{{ __('legal.privacy_title') }}</h1>
    <p>{{ __('legal.privacy_intro') }}</p>

    <h2>{{ __('legal.privacy_transparency_title') }}</h2>
    <p>{!! __('legal.privacy_transparency_content') !!}</p>

    <h2>{{ __('legal.privacy_location_title') }}</h2>
    <p>{!! __('legal.privacy_location_content') !!}</p>

    <h2>{{ __('legal.privacy_favorites_title') }}</h2>
    <p>{!! __('legal.privacy_favorites_content') !!}</p>

    <h2>{{ __('legal.privacy_analytics_title') }}</h2>
    <p>{!! __('legal.privacy_analytics_content') !!}</p>

    <p style="margin-top: 3rem; font-size: 0.8rem; color: var(--text-muted);">{{ __('legal.last_updated') }}: {{ date('d/m/Y') }}</p>
@endsection
