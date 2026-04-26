@extends('layouts.legal')

@section('title', __('legal.terms_title'))

@section('content')
    <h1>{{ __('legal.terms_title') }}</h1>
    <p>{{ __('legal.terms_intro') }}</p>

    <h2>{{ __('legal.terms_service_title') }}</h2>
    <p>{!! __('legal.terms_service_content') !!}</p>

    <h2>{{ __('legal.terms_accuracy_title') }}</h2>
    <p>{!! __('legal.terms_accuracy_content') !!}</p>

    <h2>{{ __('legal.terms_usage_title') }}</h2>
    <p>{!! __('legal.terms_usage_content') !!}</p>

    <h2>{{ __('legal.terms_property_title') }}</h2>
    <p>{!! __('legal.terms_property_content') !!}</p>

    <h2>{{ __('legal.terms_changes_title') }}</h2>
    <p>{!! __('legal.terms_changes_content') !!}</p>

    <p style="margin-top: 3rem; font-size: 0.8rem; color: var(--text-muted);">{{ __('legal.last_updated') }}: {{ date('d/m/Y') }}</p>
@endsection
