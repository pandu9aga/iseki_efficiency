<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iseki - Efficiency (Fullscreen)</title>
    <link rel="shortcut icon" href="{{ asset('assets/images/icon.png') }}" type="image/x-icon">
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/bootstrap-icons/bootstrap-icons.css') }}">
    <style>
        html,
        body {
            height: 100%;
            margin: 0;
            background-color: #f8f9fa;
        }

        .fullscreen-container {
            padding: 10px;
            overflow-y: auto;
            height: 100vh;
        }

        .area-card {
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 5px;
            transition: transform 0.2s;
        }

        .area-card:hover {
            transform: translateY(-2px);
        }

        .chart-mini {
            height: 180px;
            position: relative;
        }

        .efficiency-mini {
            padding: 12px;
        }

        .efficiency-value {
            font-size: 1.25rem;
            font-weight: bold;
        }

        .progress {
            height: 6px;
        }

        .area-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 8px;
        }
    </style>

    <!-- Dynamic Favicon -->
    <script src="/iseki_pro_app/js/dynamic-favicon.js"></script>
    <script>document.addEventListener("DOMContentLoaded", function() { setDynamicFavicon("monitoring", "Efficiency"); });</script>

    <!-- Dynamic Favicon Assets -->
    <link rel="stylesheet" href="/iseki_pro_app/css/icon.css">
    <script src="/iseki_pro_app/js/dynamic-favicon.js"></script>
    <script>document.addEventListener("DOMContentLoaded", function() { setDynamicFavicon("monitoring", "Efficiency"); });</script>
</head>

<body>

    <div class="fullscreen-container">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <h4>Dashboard Fullscreen — {{ $dateString }} <span id="liveClock" data-istoday="{{ $isToday ? 'true' : 'false' }}"></span></h4>
            @if ($isToday)
            <span class="badge bg-info">Real-time</span>
            @endif
            <a href="{{ route('admins.dashboard') }}" class="btn btn-sm btn-outline-secondary">Exit</a>
        </div>

        <div class="row" id="areasContainer">
            @foreach ($areaData as $data)
            <div class="col-xl-3 col-lg-3 col-md-6 col-sm-12">
                <div class="card area-card">
                    <div class="card-body">
                        <div class="area-title">{{ $data['area']->Name_Area }}</div>

                        <!-- Chart Mini -->
                        <div class="chart-mini mb-2">
                            <canvas id="chart-{{ $data['area']->Id_Area }}"></canvas>
                        </div>

                        <!-- Rincian Jam Kerja (Legend) -->
                        <div class="row g-1 mb-2 text-center" style="font-size: 0.72rem;">
                            <!-- Atas Kiri: Handling -->
                            <div class="col-6">
                                <div class="p-1 rounded" style="background-color: rgba(255, 136, 0, 0.08); border: 1px solid rgba(255, 136, 0, 0.25);">
                                    <span class="fw-bold" style="color: #d47000;">Handling</span>
                                    <div class="fw-bold text-dark">{{ number_format($data['penangananTotal'], 2) }} j</div>
                                </div>
                            </div>
                            <!-- Atas Kanan: Non Op -->
                            <div class="col-6">
                                <div class="p-1 rounded" style="background-color: rgba(235, 30, 80, 0.08); border: 1px solid rgba(235, 30, 80, 0.25);">
                                    <span class="fw-bold" style="color: #c90e3a;">Non Op</span>
                                    <div class="fw-bold text-dark">{{ number_format($data['costTotal'], 2) }} j</div>
                                </div>
                            </div>
                            <!-- Bawah Kiri: Member Net -->
                            <div class="col-6">
                                <div class="p-1 rounded" style="background-color: rgba(0, 200, 150, 0.08); border: 1px solid rgba(0, 200, 150, 0.25);">
                                    <span class="fw-bold" style="color: #008f6c;">Member Net</span>
                                    <div class="fw-bold text-dark">{{ number_format($data['reportNetHours'], 2) }} j</div>
                                </div>
                            </div>
                            <!-- Bawah Kanan: Tractor (92.2%) -->
                            <div class="col-6">
                                <div class="p-1 rounded" style="background-color: rgba(0, 123, 255, 0.08); border: 1px solid rgba(0, 123, 255, 0.25);">
                                    <span class="fw-bold" style="color: #005ccb;">Tractor (92.2%)</span>
                                    <div class="fw-bold text-dark">{{ number_format($data['scanTotal'], 2) }} j</div>
                                </div>
                            </div>
                        </div>

                        <!-- Efisiensi Mini -->
                        <div class="efficiency-mini">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Selisih Jam:</span>
                                <span class="efficiency-value"
                                    id="selisih-{{ $data['area']->Id_Area }}">
                                    {{ number_format($data['selisihJam'], 2) }} jam
                                </span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Nilai:</span>
                                <span class="efficiency-value"
                                    id="rupiah-{{ $data['area']->Id_Area }}">
                                    Rp{{ number_format(abs($data['nilaiRupiah']), 0, ',', '.') }}
                                </span>
                            </div>

                            @php
                            $kategori1 = $data['reportNetHours'] + $data['penangananTotal'];
                            $kategori2 = $data['scanTotal'] + $data['costTotal'];
                            $persenOperasional =
                            $kategori2 != 0 ? (($kategori2 - $data['reportNetHours']) / $kategori2) * 100 : 0;
                            $persenNonOperasional =
                            $kategori2 != 0 ? ($data['costTotal'] / $kategori2) * 100 : 0;
                            $color = $data['nilaiRupiah'] >= 0 ? 'success' : 'danger';
                            @endphp

                            <div class="small mb-1">
                                Operational Ratio:
                                <strong>{{ number_format($persenOperasional, 1) }}%</strong>
                            </div>
                            <div class="progress mb-1">
                                <div class="progress-bar bg-{{ $color }}"
                                    style="width: {{ min(100, abs($persenOperasional)) }}%"></div>
                            </div>

                            <div class="small mb-1">
                                Non-Operational:
                                <strong>{{ number_format($persenNonOperasional, 1) }}%</strong>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-info"
                                    style="width: {{ min(100, $persenNonOperasional) }}%">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Pass all chart data as JSON so JS formatter cannot break Blade syntax --}}
    <script id="chartDataJson" type="application/json">
        @json($chartDataJson)
    </script>

    <!-- JS -->
    <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/chart.js') }}"></script>
    <script src="{{ asset('assets/js/chartjs-plugin-datalabels@2.js') }}"></script>

    <script>
        function decimalToHoursMinutes(decimal) {
            if (isNaN(decimal)) return '0j 0m';
            const sign = decimal < 0 ? '-' : '';
            const abs = Math.abs(decimal);
            const totalMinutes = Math.round(abs * 60);
            const jam = Math.floor(totalMinutes / 60);
            const menit = totalMinutes % 60;
            return `${sign}${jam}j ${menit}m`;
        }

        // Read chart data from JSON script tag (formatter-safe)
        const chartDataList = JSON.parse(document.getElementById('chartDataJson').textContent);

        chartDataList.forEach(function(item) {
            const canvasEl = document.getElementById('chart-' + item.id);
            if (!canvasEl) return;
            const ctx = canvasEl.getContext('2d');

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: [''],
                    datasets: [{
                            label: 'Member Net',
                            data: [item.reportNetHours],
                            backgroundColor: 'rgba(0, 200, 150, 0.95)',
                            stack: 'A',
                            order: 1
                        },
                        {
                            label: 'Handling',
                            data: [item.penangananTotal],
                            backgroundColor: 'rgba(255, 136, 0, 0.95)',
                            stack: 'A',
                            order: 2
                        },
                        {
                            label: 'Tractor',
                            data: [item.scanTotal],
                            backgroundColor: 'rgba(0, 123, 255, 0.95)',
                            stack: 'B',
                            order: 3
                        },
                        {
                            label: 'Non Op',
                            data: [item.costTotal],
                            backgroundColor: 'rgba(235, 30, 80, 0.95)',
                            stack: 'B',
                            order: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            enabled: false
                        },
                        datalabels: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            display: false,
                            stacked: true
                        },
                        y: {
                            display: true,
                            stacked: true,
                            beginAtZero: true,
                            ticks: {
                                font: {
                                    size: 9
                                }
                            }
                        }
                    }
                }
            });
        });

        // Live clock
        function updateClock() {
            const clockElement = document.getElementById('liveClock');
            if (!clockElement) return;

            const isToday = clockElement.getAttribute('data-istoday') === 'true';

            if (!isToday) {
                clockElement.textContent = ' 17:00';
                return;
            }

            const now = new Date();
            let h = now.getHours();
            let m = now.getMinutes();

            if (h >= 17) {
                clockElement.textContent = ' 17:00';
            } else {
                const hours = String(h).padStart(2, '0');
                const minutes = String(m).padStart(2, '0');
                clockElement.textContent = ` ${hours}:${minutes}`;
            }
        }
        setInterval(updateClock, 60000);
        updateClock();

        // Auto refresh
        setTimeout(() => location.reload(), 60000);
    </script>

</body>

</html>