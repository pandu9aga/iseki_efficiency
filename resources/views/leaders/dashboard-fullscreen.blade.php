<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iseki - Efficiency</title>

    <!-- Favicon -->
    <link rel="shortcut icon" href="{{ asset('assets/images/icon.png') }}" type="image/x-icon">

    <!-- CSS -->
    <link rel="stylesheet" href="{{ asset('assets/css/custom-fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/iconly/bold.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/dataTables.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/fixedColumns.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/perfect-scrollbar/perfect-scrollbar.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/bootstrap-icons/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">

    <style>
        html,
        body {
            height: 100%;
        }

        body {
            background-color: #fff5f9;
            margin: 0;
            overflow: hidden;
            /* ❗ hanya satu scroll */
        }

        .fullscreen-container {
            height: 100vh;
            display: flex;
            padding: 10px;
            box-sizing: border-box;
        }

        .fullscreen-card {
            flex: 1;
            display: flex;
            flex-direction: column;
            border-radius: 20px;
            box-shadow: 0 8px 24px rgba(189, 2, 55, 0.12);
            border: 1px solid #ffe6ee;
            overflow: hidden;
        }

        /* header + alert */
        .card-top {
            flex-shrink: 0;
        }

        /* area grafik */
        .chart-section {
            flex: 1;
            position: relative;
            min-height: 0;
            /* ❗ penting agar canvas bisa flex */
        }

        .chart-section canvas {
            position: absolute;
            inset: 0;
        }

        /* area bawah */
        .bottom-section {
            flex-shrink: 0;
        }

        /* Area tabs styling */
        .area-tabs {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .area-tabs .btn {
            font-size: 0.8rem;
            padding: 4px 12px;
        }
    </style>


    <!-- Dynamic Favicon -->
    <script src="/iseki_pro_app/js/dynamic-favicon.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            setDynamicFavicon("monitoring", "Efficiency");
        });
    </script>

    <!-- Dynamic Favicon Assets -->
    <link rel="stylesheet" href="/iseki_pro_app/css/icon.css">
    <script src="/iseki_pro_app/js/dynamic-favicon.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            setDynamicFavicon("monitoring", "Efficiency");
        });
    </script>
</head>

<body data-pc-preset="preset-1" data-pc-theme="light">

    <div class="fullscreen-container">
        <div class="card fullscreen-card">

            <!-- 🔹 BAGIAN ATAS -->
            <div class="card-top p-3">

                @if ($isToday)
                <div class="alert alert-info d-flex align-items-center mb-3">
                    <i class="bi bi-clock me-2"></i>
                    <strong>Jam Operasional Real-Time:</strong>
                    Total {{ $reportMembers }} Member (Start From 07.30)
                </div>
                @endif

                <div class="header-actions mb-2 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">Diagram: <span class="text-primary">{{ $dateString }}</span>
                            <small class="text-muted">| Area: {{ $area->Name_Area }}</small>
                        </h5>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        {{-- Area Tabs --}}
                        @if(isset($assignedAreas) && $assignedAreas->count() > 1)
                        <div class="area-tabs">
                            @foreach($assignedAreas as $a)
                            <!-- <a href="{{ route('leaders.dashboard.fullscreen', ['date' => $dateString, 'area' => $a->Id_Area]) }}"
                               class="btn btn-sm {{ $a->Id_Area == $area->Id_Area ? 'btn-primary' : 'btn-outline-primary' }}">
                                {{ $a->Name_Area }}
                            </a> -->
                            @endforeach
                        </div>
                        @endif
                        <a href="{{ route('leaders.dashboard', ['date' => $dateString, 'area' => $area->Id_Area]) }}" class="btn btn-sm btn-danger exit-fullscreen">Exit Fullscreen</a>
                    </div>
                </div>

            </div>

            <div class="row">
                <div class="col-9">
                    <!-- 🔹 CHART (FULL HEIGHT) -->
                    <div class="chart-section px-3">
                        <canvas id="stackedChart"></canvas>
                    </div>
                </div>

                <div class="col-3">
                    <!-- 🔹 BAGIAN BAWAH -->
                    <div class="bottom-section p-3" id="efficiencyCard">
                        <div class="row">
                            <div class="col-12">
                                <div class="card text-white" id="mainCard">
                                    <div class="card-body text-center py-4">
                                        <h6 class="card-title mb-2">
                                            Efisiensi Hari Ini - 今日の作業効率
                                        </h6>
                                        <h2 class="fw-bold text-white" id="selisihJam">0.00 jam</h2>
                                        <h3 class="mt-1 fs-4 text-white" id="nilaiRupiah">Rp0</h3>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h6 class="card-title mb-3">Operational Ratio</h6>

                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between small mb-1">
                                                <span>Efficiency Operational - 工数低減率</span>
                                                <span id="persenOperasional">0%</span>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar" id="persenOperasionalBar"></div>
                                            </div>
                                        </div>

                                        <div>
                                            <div class="d-flex justify-content-between small mb-1">
                                                <span>Efficiency Non Operational - 非稼働工数率</span>
                                                <span id="persenNonOperasional">0%</span>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-info"
                                                    id="persenNonOperasionalBar"></div>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JS: Core -->
    <script src="{{ asset('assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js') }}"></script>
    <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/apexcharts/apexcharts.js') }}"></script>

    <!-- JS: DataTables -->
    <script src="{{ asset('assets/js/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/js/dataTables.min.js') }}"></script>
    <script src="{{ asset('assets/js/fixedColumns.dataTables.min.js') }}"></script>
    <script src="{{ asset('assets/js/html5-qrcode.min.js') }}"></script>

    <!-- JS: Custom -->
    <script>
        const yearEl = document.querySelector('.year');
        if (yearEl) yearEl.textContent = new Date().getFullYear();
        const table1 = document.querySelector('#table1');
        if (table1) {
            new DataTable(table1);
        }
    </script>
    <script src="{{ asset('assets/js/main.js') }}"></script>

    <!-- Chart.js -->
    <script src="{{ asset('assets/js/chart.js') }}"></script>
    <script src="{{ asset('assets/js/chartjs-plugin-datalabels@2.js') }}"></script>
    <script src="{{ asset('assets/js/chartjs-plugin-annotation.min.js') }}"></script>

    </script>
    <script id="dashboardDataJson" type="application/json">
        @json($dashboardJsData)
    </script>
    <script>
        // 🔁 Auto refresh setiap 10 detik (preserve current URL with area param)
        setTimeout(() => {
            location.reload();
        }, 10000);

        // 🔹 Fungsi: Konversi desimal jam ke format "X jam Y menit"
        function decimalToHoursMinutes(decimal) {
            if (isNaN(decimal)) return '0 jam 0 menit';
            const sign = decimal < 0 ? '-' : '';
            const abs = Math.abs(decimal);
            const totalMinutes = Math.round(abs * 60);
            const jam = Math.floor(totalMinutes / 60);
            const menit = totalMinutes % 60;
            return `${sign}${jam} jam ${menit} menit`;
        }

        const dashboardData = JSON.parse(document.getElementById('dashboardDataJson').textContent);
        const rawScans = dashboardData.rawScans || [];
        const rawCosts = dashboardData.rawCosts || [];
        const rawPowers = dashboardData.rawPowers || [];
        const rawPenanganans = dashboardData.rawPenanganans || [];

        const memberHours = dashboardData.memberHours || 0;
        const reportMembers = dashboardData.reportMembers || 0;
        const powerTotal = dashboardData.powerTotal || 0;

        const scans = Array.isArray(rawScans) ? rawScans : [];
        const costs = Array.isArray(rawCosts) ? rawCosts : [];
        const powers = Array.isArray(rawPowers) ? rawPowers : [];
        const penanganans = Array.isArray(rawPenanganans) ? rawPenanganans : [];

        // 🔢 Hitung total
        const scanTotal = scans.reduce((sum, s) => sum + s.value, 0);
        const costTotal = costs.reduce((sum, c) => sum + c.value, 0);
        const powerTotalCalculated = powers.reduce((sum, p) => sum + p.value, 0);
        const penangananTotal = penanganans.reduce((sum, p) => sum + p.value, 0);
        const reportNetHours = memberHours - powerTotalCalculated;
        const chartNetHours = Math.max(0, reportNetHours); // untuk chart, jangan negatif

        // 🔹 Inisialisasi Chart
        const ctx = document.getElementById('stackedChart').getContext('2d');
        Chart.register(ChartDataLabels);


        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Operational Production'],
                datasets: [{
                        label: 'Handling',
                        data: [penangananTotal],
                        backgroundColor: 'rgba(251, 146, 60, 0.35)',
                        borderColor: 'rgba(251, 146, 60, 1)',
                        borderWidth: 1.5,
                        stack: 'group1',
                        order: 3,
                    },
                    {
                        label: 'Member Hours',
                        data: [chartNetHours],
                        backgroundColor: 'rgba(52, 211, 153, 0.35)',
                        borderColor: 'rgba(52, 211, 153, 1)',
                        borderWidth: 1.5,
                        stack: 'group1',
                        order: 1,
                    },
                    {
                        label: 'Tractor',
                        data: [scanTotal],
                        backgroundColor: 'rgba(56, 189, 248, 0.35)',
                        borderColor: 'rgba(56, 189, 248, 1)',
                        borderWidth: 1.5,
                        stack: 'group2',
                        order: 4,
                    },
                    {
                        label: 'Non Operational',
                        data: [costTotal],
                        backgroundColor: 'rgba(244, 63, 94, 0.35)',
                        borderColor: 'rgba(244, 63, 94, 1)',
                        borderWidth: 1.5,
                        stack: 'group2',
                        order: 5,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        stacked: true
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true
                    }
                },
                plugins: {
                    datalabels: {
                        anchor: 'center',
                        align: 'center',
                        clamp: true,
                        color: '#25396f',
                        formatter: (value, ctx) => {
                            const label = ctx.dataset.label;
                            if (label === 'Member Hours')
                                return `Member Hours: ${decimalToHoursMinutes(reportNetHours)}`;
                            if (label === 'Handling')
                                return `Handling: ${decimalToHoursMinutes(penangananTotal)}`;
                            if (label === 'Tractor')
                                return `Tractor: ${decimalToHoursMinutes(scanTotal)}`;
                            if (label === 'Non Operational')
                                return `Non Operational: ${decimalToHoursMinutes(costTotal)}`;
                            return value ? `${decimalToHoursMinutes(value)}` : "";
                        },
                        font: {
                            weight: 'bold',
                            size: 12
                        }
                    },
                    tooltip: {
                        callbacks: {
                            beforeLabel: function(ctx) {
                                const label = ctx.dataset.label || '';
                                if (label === 'Member Hours') {
                                    return [
                                        `Total Members: ${reportMembers}`,
                                        `Total Hours (sebelum izin): ${decimalToHoursMinutes(memberHours)}`,
                                        `Jam Izin: ${decimalToHoursMinutes(powerTotal)}`,
                                        `Net Hours: ${decimalToHoursMinutes(ctx.parsed.y)}`
                                    ];
                                }
                                if (label === 'Tractor') {
                                    const total = scans.reduce((s, x) => s + x.value, 0);
                                    return [`Total Tractor: ${decimalToHoursMinutes(total)}`];
                                }
                                if (label === 'Non Operational') {
                                    const total = costTotal;
                                    const lines = [`Total Non Operational: ${decimalToHoursMinutes(total)}`];
                                    const labels = costs.map(c => `${c.label} (${decimalToHoursMinutes(c.value)})`);
                                    for (let i = 0; i < labels.length; i += 5) {
                                        lines.push(labels.slice(i, i + 5).join(', '));
                                    }
                                    return lines;
                                }
                                if (label === 'Handling') {
                                    const total = penanganans.reduce((s, x) => s + x.value, 0);
                                    const lines = [`Total Handling: ${decimalToHoursMinutes(total)}`];
                                    const labels = penanganans.map(p => `${p.label} (${decimalToHoursMinutes(p.value)})`);
                                    for (let i = 0; i < labels.length; i += 5) {
                                        lines.push(labels.slice(i, i + 5).join(', '));
                                    }
                                    return lines;
                                }
                                return null;
                            },
                            label: () => ''
                        }
                    },
                    annotation: {
                        annotations: {
                            handlingTopLine: {
                                type: 'line',
                                xMin: -0.05,
                                xMax: 0.05,
                                yMin: Math.max(0, reportNetHours + penangananTotal),
                                yMax: Math.max(0, reportNetHours + penangananTotal),
                                borderColor: 'red',
                                borderWidth: 2,
                                borderDash: [6, 6]
                            },
                            totalOperationalText: {
                                type: 'label',
                                xValue: -0.2,
                                yValue: Math.max(0, reportNetHours + penangananTotal) + 1,
                                backgroundColor: 'transparent',
                                color: '#333',
                                font: {
                                    size: 14,
                                    weight: 'bold'
                                },
                                content: [decimalToHoursMinutes(reportNetHours + penangananTotal)],
                                textAlign: 'center'
                            },
                            totalTractorText: {
                                type: 'label',
                                xValue: 0.2,
                                yValue: scanTotal + costTotal + 1,
                                backgroundColor: 'transparent',
                                color: '#333',
                                font: {
                                    size: 14,
                                    weight: 'bold'
                                },
                                content: [decimalToHoursMinutes(scanTotal + costTotal)],
                                textAlign: 'center'
                            }
                        }
                    }
                }
            }
        });

        // === 🔥 EFISIENSI ===
        const kategori1 = reportNetHours + penangananTotal;
        const kategori2 = scanTotal + costTotal;
        const selisihJam = kategori2 - kategori1;
        const nilaiRupiah = selisihJam * 60000;

        // Sesuaikan rumus dengan Excel: Penghematan (selisihJam) / Total Beban (scanTotal)
        const persenOperasional = scanTotal !== 0 ? (selisihJam / scanTotal) * 100 : 0;
        
        // Sesuaikan rumus dengan Excel: Total NonOp / Total Power
        const areaLainTotal = penanganans
            .filter(p => p.label.toLowerCase().includes('area lain') || p.label.includes('他部署応援'))
            .reduce((sum, p) => sum + p.value, 0);
        const totalPowerForNonOp = memberHours + penangananTotal - areaLainTotal;
        const persenNonOperasional = totalPowerForNonOp !== 0 ? (costTotal / totalPowerForNonOp) * 100 : 0;

        function formatRupiahWithSign(angka) {
            const sign = angka < 0 ? '-' : '';
            return sign + new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0
            }).format(Math.abs(angka));
        }

        document.getElementById('selisihJam').textContent = decimalToHoursMinutes(selisihJam);
        document.getElementById('nilaiRupiah').textContent = formatRupiahWithSign(Math.round(nilaiRupiah));

        const mainCard = document.getElementById('mainCard');
        mainCard.style.backgroundColor = nilaiRupiah >= 0 ? '#34d399' : '#f43f5e';

        document.getElementById('persenOperasional').textContent = persenOperasional.toFixed(0) + '%';
        const absPersenOp = Math.abs(persenOperasional);
        const persenOpBar = document.getElementById('persenOperasionalBar');
        persenOpBar.style.width = Math.min(100, absPersenOp) + '%';
        persenOpBar.className = 'progress-bar';
        persenOpBar.style.backgroundColor = nilaiRupiah >= 0 ? '#34d399' : '#f43f5e';

        document.getElementById('persenNonOperasional').textContent = persenNonOperasional.toFixed(0) + '%';
        const persenNonOpBar = document.getElementById('persenNonOperasionalBar');
        persenNonOpBar.style.width = Math.min(100, persenNonOperasional) + '%';
        persenNonOpBar.className = 'progress-bar';
        persenNonOpBar.style.backgroundColor = '#f43f5e';
    </script>
</body>

</html>