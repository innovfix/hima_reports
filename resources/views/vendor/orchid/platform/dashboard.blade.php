@extends(config('platform.workspace', 'platform::workspace.compact'))

@push('styles')
    <style>
        /* Mini sidebar: icon-only */
        :root { --aside-mini-width: 72px; }

        .aside { transition: width .2s ease; }
        .aside .nav .nav-link { border-radius: 10px; }
        .aside .nav .nav-link .icon { width: 1.25rem; height: 1.25rem; }

        html.aside-mini .aside { width: var(--aside-mini-width) !important; }
        html.aside-mini .aside .header-brand,
        html.aside-mini .aside #headerMenuCollapse .form-group, /* search */
        html.aside-mini .aside .nav .nav-link .nav-link-title,
        html.aside-mini .aside .to-top,
        html.aside-mini .aside footer { display: none !important; }
        html.aside-mini .aside .nav .nav-link { justify-content: center; padding: .75rem .5rem; }

        /* Card-like light workspace */
        .command-bar-wrapper header small { opacity: .8; }

        /* Footer/profile: match sidebar, remove black strip */
        .aside footer .bg-dark { background-color: transparent !important; }

        /* Responsive tweaks */
        @media (max-width: 1199.98px) {
            html.aside-mini .aside { width: var(--aside-mini-width); }
        }
    </style>
@endpush

@section('aside')
    <div class="aside col-xs-12 col-xxl-2 bg-dark d-flex flex-column" data-controller="menu" data-bs-theme="dark">
        <header class="d-xl-block p-3 mt-xl-4 w-100 d-flex align-items-center">
            <a href="#" class="header-toggler d-xl-none me-auto order-first d-flex align-items-center lh-1 link-body-emphasis"
               data-action="click->menu#toggle">
                <x-orchid-icon path="bs.three-dots-vertical" class="icon-menu"/>
                <span class="ms-2">@yield('title')</span>
            </a>

            <a class="header-brand order-last link-body-emphasis" href="{{ route(config('platform.index')) }}">
                @includeFirst([config('platform.template.header'), 'platform::header'])
            </a>

            <button type="button" class="btn btn-sm btn-outline-light ms-2 d-none d-xl-inline-flex"
                    title="{{ __('Collapse sidebar') }}"
                    onclick="document.documentElement.classList.toggle('aside-mini')">
                <x-orchid-icon path="bs.layout-sidebar-inset"/>
            </button>
        </header>

        <nav class="aside-collapse w-100 d-xl-flex flex-column collapse-horizontal text-body-emphasis" id="headerMenuCollapse">
            @include('platform::partials.search')

            <ul class="nav nav-pills flex-column mb-md-1 mb-auto ps-0 gap-1">
                {!! Dashboard::renderMenu() !!}
            </ul>

            <div class="h-100 w-100 position-relative to-top cursor d-none d-md-flex mt-md-5"
                 data-action="click->html-load#goToTop"
                 title="{{ __('Scroll to top') }}">
                <div class="bottom-left w-100 mb-2 ps-3 overflow-hidden">
                    <small data-controller="viewport-entrance-toggle"
                           class="scroll-to-top d-flex align-items-center gap-3"
                           data-viewport-entrance-toggle-class="show">
                        <x-orchid-icon path="bs.chevron-up"/>
                        {{ __('Scroll to top') }}
                    </small>
                </div>
            </div>

            <footer class="position-sticky bottom-0">
                <div class="position-relative overflow-hidden" style="padding-bottom: 10px;">
                    @includeWhen(Auth::check(), 'platform::partials.profile')
                </div>
            </footer>
        </nav>
    </div>
@endsection

@section('workspace')
    @if(Breadcrumbs::has())
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb px-4 mb-2">
                <x-tabuna-breadcrumbs
                    class="breadcrumb-item"
                    active="active"
                />
            </ol>
        </nav>
    @endif

    <div class="order-last order-md-0 command-bar-wrapper">
        <div class="@hasSection('navbar') @else d-none d-md-block @endif layout d-md-flex align-items-center">
            <header class="d-none d-md-block col-xs-12 col-md p-0 me-3">
                <h1 class="m-0 fw-light h3 text-body-emphasis">@yield('title')</h1>
                <small class="text-muted" title="@yield('description')">@yield('description')</small>
            </header>
            <nav class="col-xs-12 col-md-auto ms-md-auto p-0">
                <ul class="nav command-bar justify-content-sm-end justify-content-start d-flex align-items-center gap-2 flex-wrap-reverse flex-sm-nowrap">
                    @yield('navbar')
                </ul>
            </nav>
        </div>
    </div>

    @include('platform::partials.alert')
    @yield('content')
@endsection


