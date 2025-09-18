@push('head')
    <meta name="robots" content="noindex"/>
    <meta name="google" content="notranslate">
    <link href="{{ asset('/vendor/orchid/favicon.svg') }}" sizes="any" type="image/svg+xml" id="favicon" rel="icon">
    <meta name="theme-color" content="#21252a">
@endpush

<div class="d-flex align-items-center gap-2">
    @auth
        <x-orchid-icon path="bs.house" class="d-inline d-xl-none"/>
    @endauth

    <p class="my-0 fw-semibold text-truncate {{ auth()->check() ? 'd-none d-xl-block' : '' }}" style="max-width: 160px;">
        {{ config('app.name', 'Hima Reports') }}
    </p>
</div>


