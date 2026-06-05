@extends('layouts.admin')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Dashboard</h3>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-md-12">
                <div class="card mb-4">
                    <div class="card-body">
                        <!-- ✅ TAB DINAMIS: ALL + NAMA AREA -->
                        <ul class="nav nav-tabs mb-3" id="dashboardTab" role="tablist">
                            <!-- Tab All -->
                            <li class="nav-item" role="presentation">
                                <a class="nav-link {{ !request()->filled('area') ? 'active' : '' }}"
                                    href="{{ url()->current() }}?{{ $filterMode }}={{ $dateString }}">
                                    All Areas
                                </a>
                            </li>

                            <!-- Tab per Area (dari database) -->
                            @foreach ($areas as $area)
                            <li class="nav-item" role="presentation">
                                <a class="nav-link {{ request('area') == $area->Id_Area ? 'active' : '' }}"
                                    href="{{ url()->current() }}?{{ $filterMode }}={{ $dateString }}&area={{ $area->Id_Area }}">
                                    {{ $area->Name_Area }}
                                </a>
                            </li>
                            @endforeach
                        </ul>

                        <!-- Form Tanggal / Bulan -->
                        <form method="GET" class="mb-3" id="filterForm">
                            @if(request()->filled('area'))
                            <input type="hidden" name="area" value="{{ request('area') }}">
                            @endif
                            <div class="row g-3 align-items-center">
                                <div class="col-auto">
                                    <label for="filterDateInput" class="col-form-label" id="filterDateLabel">
                                        {{ $filterMode === 'month' ? 'Month:' : 'Date:' }}
                                    </label>
                                </div>
                                <div class="col-auto">
                                    <div class="input-group">
                                        <button type="button" id="prevDateBtn" class="btn btn-outline-primary">
                                            <i class="bi bi-chevron-left"></i>
                                        </button>
                                        <input type="{{ $filterMode === 'month' ? 'month' : 'date' }}"
                                            id="filterDateInput"
                                            name="{{ $filterMode }}"
                                            class="form-control text-center fw-bold"
                                            value="{{ $dateString }}">
                                        <button type="button" id="nextDateBtn" class="btn btn-outline-primary">
                                            <i class="bi bi-chevron-right"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <button type="button" id="toggleDateType" class="btn btn-outline-secondary btn-sm">
                                        {{ $filterMode === 'month' ? 'Date' : 'Month' }}
                                    </button>
                                </div>
                                <div class="col-auto">
                                    <button type="submit" class="btn btn-primary">Show</button>
                                </div>
                                <div class="col-auto">
                                    @if($filterMode === 'month')
                                    <a id="exportLink" href="{{ route('admins.dashboard.export-monthly', ['month' => $dateString, 'area' => request('area')]) }}"
                                        class="btn btn-warning">
                                        <i class="bi bi-file-earmark-spreadsheet"></i> Export Bulanan
                                    </a>
                                    @endif
                                    <a id="exportLinkDaily" href="{{ route('admins.dashboard.export', [$filterMode => $dateString, 'area' => request('area')]) }}"
                                        class="btn btn-success">
                                        <i class="bi bi-file-earmark-excel"></i> Export Excel
                                    </a>
                                    <a id="fullscreenLink" href="{{ route('admins.dashboard.fullscreen', [$filterMode => $dateString, 'area' => request('area')]) }}"
                                        class="btn btn-info">Fullscreen View</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle"></i> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                @endif

                @if ($isToday)
                <div class="alert alert-info d-flex align-items-center">
                    <i class="bi bi-clock me-2"></i>
                    <strong>Jam Operasional Real-Time:</strong>
                    Total {{ $reportMembers }} Member (Start From 07.30)
                </div>
                @endif

                <div class="card">
                    <div class="card-header">
                        <h5>Diagram: <span class="text-primary">{{ $dateString }}</span>
                            @if (request()->filled('area'))
                            <small class="text-muted">
                                | Area:
                                {{ optional($areas->firstWhere('Id_Area', request('area')))->Name_Area ?? 'ID ' . request('area') }}
                            </small>
                            @endif
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="stackedChart"></canvas>

                        {{-- 🔹 KARTU EFISIENSI (SAMA PERSIS DENGAN ADMIN) --}}
                        <div id="efficiencyCard" class="mt-4">
                            <div class="row g-3">
                                <!-- Nilai Utama -->
                                <div class="col-md-6">
                                    <div class="card text-white h-100" id="mainCard">
                                        <div class="card-body text-center py-4">
                                            <h6 class="card-title mb-2">Efisiensi Hari Ini - 今日の作業効率</h6>
                                            <div class="display-6 fw-bold" id="selisihJam">0.00 jam</div>
                                            <div class="mt-1 fs-4" id="nilaiRupiah">Rp0</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Rasio Efisiensi -->
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h6 class="card-title mb-3">Efficency Ratio</h6>

                                            <!-- % Operasional -->
                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between small mb-1">
                                                    <span>Operational Ratio - 工数低減率</span>
                                                    <span id="persenOperasional">0%</span>
                                                </div>
                                                <div class="progress" style="height: 8px;">
                                                    <div class="progress-bar" id="persenOperasionalBar"
                                                        role="progressbar" style="width: 0%"></div>
                                                </div>
                                            </div>

                                            <!-- % Non-Operasional -->
                                            <div>
                                                <div class="d-flex justify-content-between small mb-1">
                                                    <span>Non Operational Ratio - 非稼働工数率</span>
                                                    <span id="persenNonOperasional">0%</span>
                                                </div>
                                                <div class="progress" style="height: 8px;">
                                                    <div class="progress-bar bg-info" id="persenNonOperasionalBar"
                                                        role="progressbar" style="width: 0%"></div>
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
    </section>
</div>
@endsection

@section('script')
<script src="{{ asset('assets/js/chart.js') }}"></script>
<script src="{{ asset('assets/js/chartjs-plugin-datalabels@2.js') }}"></script>
<script src="{{ asset('assets/js/chartjs-plugin-annotation.min.js') }}"></script>
<script>
    function decimalToHoursMinutes(decimal) {
        if (isNaN(decimal)) return '0 jam 0 menit';
        const sign = decimal < 0 ? '-' : '';
        const abs = Math.abs(decimal);
        const totalMinutes = Math.round(abs * 60);
        const jam = Math.floor(totalMinutes / 60);
        const menit = totalMinutes % 60;
        return `${sign}${jam} jam ${menit} menit`;
    }
</script>
<script id="dashboardDataJson" type="application/json">
    @json($dashboardJsData)
</script>
<script>
    const dashboardData = JSON.parse(document.getElementById('dashboardDataJson').textContent);
    var rawScans = dashboardData.rawScans || [];
    var rawCosts = dashboardData.rawCosts || [];
    var rawPowers = dashboardData.rawPowers || [];
    var rawPenanganans = dashboardData.rawPenanganans || [];
    var memberHours = dashboardData.memberHours || 0;
    var reportMembers = dashboardData.reportMembers || 0;
    var powerTotal = dashboardData.powerTotal || 0;
</script>
<script>
    const scans = Array.isArray(rawScans) ? rawScans : [];
    const costs = Array.isArray(rawCosts) ? rawCosts : [];
    const powers = Array.isArray(rawPowers) ? rawPowers : [];
    const penanganans = Array.isArray(rawPenanganans) ? rawPenanganans : [];

    const scanTotal = scans.reduce((sum, s) => sum + s.value, 0);
    const costTotal = costs.reduce((sum, c) => sum + c.value, 0);
    const powerTotalCalculated = powers.reduce((sum, p) => sum + p.value, 0);
    const penangananTotal = penanganans.reduce((sum, p) => sum + p.value, 0);
    const reportNetHours = memberHours - powerTotalCalculated;
    const chartNetHours = Math.max(0, reportNetHours); // untuk chart, jangan negatif

    const ctx = document.getElementById('stackedChart').getContext('2d');
    Chart.register(ChartDataLabels);
    Chart.register('chartjs-plugin-annotation');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Operational Production'],
            datasets: [{
                    label: 'Handling',
                    data: [penangananTotal],
                    backgroundColor: 'rgba(255, 203, 106, 1)',
                    borderColor: 'rgba(255, 115, 0, 1)',
                    borderWidth: 1,
                    stack: 'group1',
                    order: 3,
                },
                {
                    label: 'Member Hours',
                    data: [chartNetHours],
                    backgroundColor: 'rgba(174, 255, 233, 0.95)',
                    borderColor: 'rgba(72, 187, 152, 1)',
                    borderWidth: 1,
                    stack: 'group1',
                    order: 1,
                },
                {
                    label: 'Tractor',
                    data: [scanTotal],
                    backgroundColor: 'rgba(169, 234, 255, 1)',
                    borderColor: 'rgba(57, 120, 197, 1)',
                    borderWidth: 1,
                    stack: 'group2',
                    order: 4,
                },
                {
                    label: 'Non Operational',
                    data: [costTotal],
                    backgroundColor: 'rgba(255, 174, 191, 0.97)',
                    borderColor: 'rgba(232, 56, 94, 1)',
                    borderWidth: 1,
                    stack: 'group2',
                    order: 5,
                }
            ]
        },
        options: {
            responsive: true,
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
                        if (label === 'Tractor') return `Tractor: ${decimalToHoursMinutes(scanTotal)}`;
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
                                const total = costs.reduce((s, x) => s + x.value, 0);
                                return [`Total Non Operational: ${decimalToHoursMinutes(total)}`];
                            }
                            if (label === 'Handling') {
                                const total = penanganans.reduce((s, x) => s + x.value, 0);
                                return [`Total Handling: ${decimalToHoursMinutes(total)}`];
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

    const kategori1 = reportNetHours + penangananTotal;
    const kategori2 = scanTotal + costTotal;
    const selisihJam = kategori2 - kategori1;
    const nilaiRupiah = selisihJam * 60000;
    
    // Sesuaikan rumus dengan Excel: Penghematan (selisihJam) / Total Beban (kategori2)
    const persenOperasional = kategori2 !== 0 ? (selisihJam / kategori2) * 100 : 0;
    
    // Sesuaikan rumus dengan Excel: Total NonOp / Total Power
    const totalPowerForNonOp = memberHours + penangananTotal;
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
    mainCard.style.backgroundColor = nilaiRupiah >= 0 ? '#28a745' : '#dc3545';

    document.getElementById('persenOperasional').textContent = persenOperasional.toFixed(1) + '%';
    const absPersenOp = Math.abs(persenOperasional);
    const persenOpBar = document.getElementById('persenOperasionalBar');
    persenOpBar.style.width = Math.min(100, absPersenOp) + '%';
    persenOpBar.className = 'progress-bar ' + (nilaiRupiah >= 0 ? 'bg-success' : 'bg-danger');

    document.getElementById('persenNonOperasional').textContent = persenNonOperasional.toFixed(1) + '%';
    document.getElementById('persenNonOperasionalBar').style.width = Math.min(100, persenNonOperasional) + '%';
</script>
<script>
    // Toggle Date / Month filter
    document.addEventListener('DOMContentLoaded', function() {
        const input = document.getElementById('filterDateInput');
        const toggleBtn = document.getElementById('toggleDateType');
        const label = document.getElementById('filterDateLabel');
        const prevBtn = document.getElementById('prevDateBtn');
        const nextBtn = document.getElementById('nextDateBtn');
        const form = document.getElementById('filterForm');

        toggleBtn.addEventListener('click', function() {
            if (input.type === 'date') {
                input.type = 'month';
                input.name = 'month';
                input.value = '';
                label.textContent = 'Month:';
                toggleBtn.textContent = 'Date';
            } else {
                input.type = 'date';
                input.name = 'date';
                input.value = '';
                label.textContent = 'Date:';
                toggleBtn.textContent = 'Month';
            }
        });

        if (prevBtn && nextBtn) {
            prevBtn.addEventListener('click', function() {
                if (!input.value) return;
                if (input.type === 'date') {
                    let parts = input.value.split('-');
                    let d = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
                    d.setDate(d.getDate() - 1);
                    let y = d.getFullYear();
                    let m = String(d.getMonth() + 1).padStart(2, '0');
                    let day = String(d.getDate()).padStart(2, '0');
                    input.value = `${y}-${m}-${day}`;
                } else if (input.type === 'month') {
                    let parts = input.value.split('-');
                    let d = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, 1);
                    d.setMonth(d.getMonth() - 1);
                    let y = d.getFullYear();
                    let m = String(d.getMonth() + 1).padStart(2, '0');
                    input.value = `${y}-${m}`;
                }
                form.submit();
            });

            nextBtn.addEventListener('click', function() {
                if (!input.value) return;
                if (input.type === 'date') {
                    let parts = input.value.split('-');
                    let d = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
                    d.setDate(d.getDate() + 1);
                    let y = d.getFullYear();
                    let m = String(d.getMonth() + 1).padStart(2, '0');
                    let day = String(d.getDate()).padStart(2, '0');
                    input.value = `${y}-${m}-${day}`;
                } else if (input.type === 'month') {
                    let parts = input.value.split('-');
                    let d = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, 1);
                    d.setMonth(d.getMonth() + 1);
                    let y = d.getFullYear();
                    let m = String(d.getMonth() + 1).padStart(2, '0');
                    input.value = `${y}-${m}`;
                }
                form.submit();
            });
        }
    });
</script>
@endsection