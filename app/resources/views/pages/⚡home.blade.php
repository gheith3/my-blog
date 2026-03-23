<?php

use Livewire\Component;

new #[\Livewire\Attributes\Layout('layouts.app')] class extends Component {
    //
}; ?>

@php
    $settings = app(\App\Settings\GeneralSettings::class);
@endphp
@section('headMeta')
    <title>{{ app()->getLocale() == 'ar' ? $settings->get('site_name_ar') : $settings->get('site_name') }}</title>
    <meta name="description" content="{{ $settings->get('site_description') }}">
    <!-- Tab icon -->
    @if ($settings->about_image)
        <link rel="icon" href="{{ Storage::disk('public')->url($settings->about_image) }}" type="image/x-icon">
        <link rel="shortcut icon" href="{{ Storage::disk('public')->url($settings->about_image) }}" type="image/x-icon">
    @endif
@endsection



<div>
    <livewire:navigation />
    <livewire:hero />
    <livewire:about />
    <livewire:posts-list />
    <livewire:footer />
</div>
