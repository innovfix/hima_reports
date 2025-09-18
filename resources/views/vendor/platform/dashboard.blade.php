@extends(config('platform.workspace', 'platform::workspace.compact'))

@push('styles')
    <style>
        /* Mini sidebar: icon-only */
        :root { --aside-mini-width: 76px; }

        .aside { transition: width .2s ease; }
        .aside .nav .nav-link { border-radius: 10px; }
        .aside .nav .nav-link .icon { width: 1.25rem; height: 1.25rem; }

        html.aside-mini .aside {
            width: var(--aside-mini-width) !important;
            max-width: var(--aside-mini-width) !important;
            flex: 0 0 var(--aside-mini-width) !important;
        }
        html.aside-mini .aside .header-brand,
        html.aside-mini .aside #headerMenuCollapse .form-group, /* search */
        html.aside-mini .aside .nav .nav-link .nav-link-title,
        html.aside-mini .aside .to-top,
        html.aside-mini .aside footer { display: none !important; }
        html.aside-mini .aside .nav .nav-link { justify-content: center; padding: .75rem .5rem; }

        /* Reduce brand gap and prevent wrap */
        .header-brand p { white-space: nowrap; max-width: 160px; overflow: hidden; text-overflow: ellipsis; }
        .aside .header-brand { margin-left: .25rem; }

        /* Card-like light workspace */
        .command-bar-wrapper header small { opacity: .8; }

        /* Footer/profile: match sidebar, remove black strip */
        .aside footer .bg-dark { background-color: transparent !important; }
        .aside footer { padding-top: .5rem; }

        /* Responsive tweaks */
        @media (max-width: 1199.98px) {
            html.aside-mini .aside { width: var(--aside-mini-width); }
        }

        /* Table alignment & wrapping improvements */
        .table th, .table td { vertical-align: middle; }
        .table th { white-space: nowrap; }
        .table td { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .table tbody tr > td { padding-top: .65rem; padding-bottom: .65rem; }
    </style>
@endpush

@section('aside')
    <div class="aside col-xs-12 col-xxl-2 bg-dark d-flex flex-column" data-controller="menu" data-bs-theme="dark">
        <header class="d-xl-block p-3 mt-xl-2 w-100 d-flex align-items-center">
            <a href="#" class="header-toggler d-xl-none me-auto order-first d-flex align-items-center lh-1 link-body-emphasis"
               data-action="click->menu#toggle">
                <x-orchid-icon path="bs.three-dots-vertical" class="icon-menu"/>
                <span class="ms-2">@yield('title')</span>
            </a>

            <a class="header-brand order-last link-body-emphasis" href="{{ route(config('platform.index')) }}">
                @includeFirst([config('platform.template.header'), 'platform::header'])
            </a>

            <button type="button" id="asideToggleBtn" class="btn btn-sm btn-light text-dark ms-2"
                    title="{{ __('Toggle sidebar') }}"
                    onclick="(function(){var r=document.documentElement; r.classList.toggle('aside-mini'); try{localStorage.setItem('aside-mini', r.classList.contains('aside-mini')?'1':'0');}catch(e){}})()">
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

@push('scripts')
    <script>
        try { if (localStorage.getItem('aside-mini') === '1') { document.documentElement.classList.add('aside-mini'); } } catch(e){}
    </script>
    <script>
        // Build export URL preserving current filters and only exporting visible columns
        (function(){
                function getVisibleColumns(){
                var cols = [];
                document.querySelectorAll('th[data-column], th[data-key]').forEach(function(th){
                    var style = window.getComputedStyle(th);
                    if (style.display !== 'none' && th.offsetWidth > 0) {
                        // Prefer data-key (actual model column) falling back to data-column (slug)
                        var key = th.getAttribute('data-key') || th.getAttribute('data-column');
                        if (key) cols.push(key);
                    }
                });
                return cols;
            }

            function attachExport(){
                var anchors = Array.from(document.querySelectorAll('a')).filter(a => a.href && a.href.indexOf('/admin/users/export') !== -1);
                anchors.forEach(function(a){
                    a.addEventListener('click', function(e){
                        // let browser handle middle-click / ctrl-click
                        if (e.ctrlKey || e.metaKey || e.button === 1) return;
                        e.preventDefault();
                        var cols = getVisibleColumns();
                        var qs = window.location.search || '';
                        var joiner = qs.indexOf('?') === -1 && qs.indexOf('&') === -1 ? '?' : '&';
                        var url = a.href.split('?')[0] + qs + (cols.length ? (qs ? '&' : '?') + 'columns=' + encodeURIComponent(cols.join(',')) : '');
                        window.location.href = url;
                    });
                });
            }

            // Try attach immediately or after small delay (dynamic content)
            document.addEventListener('DOMContentLoaded', function(){
                attachExport();
                setTimeout(attachExport, 500);
            });
        })();
    </script>
@endpush


