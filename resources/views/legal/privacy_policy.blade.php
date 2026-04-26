@extends('layouts.legal')

@section('title', __('legal.policy_title'))

@section('content')
    <h1>{{ __('legal.policy_title') }}</h1>
    <p>{{ __('legal.policy_intro') }}</p>

    <h2>{{ __('legal.policy_collection_title') }}</h2>
    <ul>
        <li><strong>{{ __('legal.policy_collection_device') }}:</strong> {{ __('legal.policy_collection_device_content') }}</li>
        <li><strong>{{ __('legal.policy_collection_search') }}:</strong> {{ __('legal.policy_collection_search_content') }}</li>
        <li><strong>{{ __('legal.policy_collection_errors') }}:</strong> {{ __('legal.policy_collection_errors_content') }}</li>
    </ul>

    <h2>{{ __('legal.policy_processing_title') }}</h2>
    <p>{!! __('legal.policy_processing_content') !!}</p>

    <h2>{{ __('legal.policy_security_title') }}</h2>
    <p>{!! __('legal.policy_security_content') !!}</p>

    <h2>{{ __('legal.policy_rights_title') }}</h2>
    <p>{!! __('legal.policy_rights_content') !!}</p>

    <h2>{{ __('legal.policy_contact_title') }}</h2>
    <p>{!! __('legal.policy_contact_content') !!}</p>

    <p style="margin-top: 3rem; font-size: 0.8rem; color: var(--text-muted);">{{ __('legal.last_updated') }}: {{ date('d/m/Y') }}</p>
@endsection
