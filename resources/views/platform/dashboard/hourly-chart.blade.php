@php
    $chart = $chart ?? [];
    $labels = $chart['labels'] ?? [];
    $datasets = $chart['datasets'] ?? [];
@endphp

<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="card-title mb-0">{{ __('Hourly Activity') }}</h5>
            <small class="text-muted">{{ __('Registrations vs Paid Users vs Amount') }}</small>
        </div>
        <canvas id="hourlyChart" height="120"></canvas>
    </div>
</div>

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        (function () {
            const ctx = document.getElementById('hourlyChart');
            if (!ctx) {
                return;
            }

            const data = {
                labels: {!! json_encode($labels) !!},
                datasets: [
                    {
                        label: '{{ __('New Registrations') }}',
                        data: {!! json_encode($datasets[0]['values'] ?? []) !!},
                        borderColor: '#1f77b4',
                        backgroundColor: 'rgba(31, 119, 180, 0.2)',
                        tension: 0.3,
                        yAxisID: 'y',
                    },
                    {
                        label: '{{ __('Paid Users') }}',
                        data: {!! json_encode($datasets[1]['values'] ?? []) !!},
                        borderColor: '#ff7f0e',
                        backgroundColor: 'rgba(255, 127, 14, 0.2)',
                        tension: 0.3,
                        yAxisID: 'y',
                    },
                    {
                        label: '{{ __('Paid Amount (₹)') }}',
                        data: {!! json_encode($datasets[2]['values'] ?? []) !!},
                        borderColor: '#2ca02c',
                        backgroundColor: 'rgba(44, 160, 44, 0.2)',
                        tension: 0.3,
                        yAxisID: 'y1',
                    }
                ]
            };

            new Chart(ctx, {
                type: 'line',
                data,
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    stacked: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    let value = context.parsed.y;
                                    if (context.dataset.yAxisID === 'y1') {
                                        return `${context.dataset.label}: ₹ ${value.toFixed(2)}`;
                                    }
                                    return `${context.dataset.label}: ${value}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            position: 'left',
                            title: {
                                display: true,
                                text: '{{ __('Count') }}'
                            },
                            ticks: {
                                precision: 0,
                            }
                        },
                        y1: {
                            type: 'linear',
                            position: 'right',
                            grid: {
                                drawOnChartArea: false,
                            },
                            title: {
                                display: true,
                                text: '{{ __('Amount (₹)') }}'
                            },
                            ticks: {
                                callback: function (value) {
                                    return '₹ ' + value.toFixed(2);
                                }
                            }
                        }
                    }
                }
            });
        })();
    </script>
@endpush
