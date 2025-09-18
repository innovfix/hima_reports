<div class="d-flex align-items-center justify-content-between p-2 mx-2 mb-3 rounded-3 bg-dark-subtle text-body">
    <a href="{{ route(config('platform.profile', 'platform.profile')) }}" class="d-flex align-items-center gap-2 text-decoration-none">
        @if($image = Auth::user()->presenter()->image())
            <img src="{{$image}}" alt="{{ Auth::user()->presenter()->title()}}" class="rounded-circle" style="width:32px;height:32px;object-fit:cover;">
        @endif
        <small class="d-flex flex-column lh-1">
            <span class="fw-semibold text-body">{{Auth::user()->presenter()->title()}}</span>
            <span class="text-muted">{{Auth::user()->presenter()->subTitle()}}</span>
        </small>
    </a>
    {{-- Disabled notifications to avoid querying non-existent columns --}}
    <div class="ms-2"></div>
</div>

