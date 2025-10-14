<div class="row g-3">
    @foreach($metrics as $metric)
        <div class="col-sm-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="h5 mb-2">{{ $metric['title'] }}</div>
                    <div class="display-6 fw-bold">{{ $metric['value'] }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>
