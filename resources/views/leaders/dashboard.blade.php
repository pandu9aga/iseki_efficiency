@extends('layouts.leader')

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
                            <form method="GET" id="dateForm">
                                <div class="row g-3 align-items-center">
                                    <div class="col-auto">
                                        <label for="date" class="col-form-label">Date:</label>
                                    </div>
                                    <div class="col-auto">
                                        <input type="date" id="date" name="date" class="form-control"
                                            value="{{ $dateString }}">
                                    </div>
                                    <div class="col-auto">
                                        <button type="submit" class="btn btn-primary">Show</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    @if ($isToday)
                        <div class="alert alert-info d-flex align-items-center">
                            <i class="bi bi-clock me-2"></i>
                            <strong>Jam Operasional Real-Time:</strong>
                            Total {{ $reportMembers }} Member (Start From 07.30)
                        </div>
                    @endif

                    <div class="card">
                        <div class="card-header">
                            <h5>Diagram: <span class="text-primary">{{ $dateString }}</span></h5>
                        </div>
                        <div class="card-body">
                            <canvas id="stackedChart"></canvas>
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
        // 🔹 Fungsi: Konversi desimal jam ke format "X jam Y menit" (termasuk negatif)
        function decimalToHoursMinutes(decimal) {
            if (isNaN(decimal)) return '0 jam 0 menit';

            const sign = decimal < 0 ? '-' : '';
            const abs = Math.abs(decimal);
            const totalMinutes = Math.round(abs * 60);
            const jam = Math.floor(totalMinutes / 60);
            const menit = totalMinutes % 60;

            return `${sign}${jam} jam ${menit} menit`;
        }

        // 🔹 Ambil data dari controller
        const rawScans = @json($scans->map(fn($s) => ['label' => $s->tractor?->Name_Tractor ?? 'Unknown', 'value' => (float) $s->Assigned_Hour_Scan])->toArray());
        const rawCosts = @json($costs->map(fn($c) => ['label' => $c->Keterangan_Cost ?? 'Unknown', 'value' => (float) $c->Non_Operational_Cost])->toArray());
        const rawPowers = @json($powers->map(fn($p) => ['label' => $p->Keterangan_Power ?? 'Unknown', 'value' => (float) $p->Leave_Hour_Power])->toArray());
        const rawPenanganans = @json($penanganans->map(fn($p) => ['label' => $p->Keterangan_Penanganan ?? 'Unknown', 'value' => (float) $p->Hour_Penanganan])->toArray());

        const memberHours = {{ (float) $memberHours }};
        const reportMembers = {{ (int) $reportMembers }};
        const powerTotal = {{ (float) $powerTotal }};

        const scans = Array.isArray(rawScans) ? rawScans : [];
        const costs = Array.isArray(rawCosts) ? rawCosts : [];
        const powers = Array.isArray(rawPowers) ? rawPowers : [];
        const penanganans = Array.isArray(rawPenanganans) ? rawPenanganans : [];

        const scanTotal = scans.reduce((sum, s) => sum + s.value, 0);
        const costTotal = costs.reduce((sum, c) => sum + c.value, 0);
        const powerTotalCalculated = powers.reduce((sum, p) => sum + p.value, 0);
        const penangananTotal = penanganans.reduce((sum, p) => sum + p.value, 0);

        const reportNetHours = memberHours - powerTotalCalculated;

        // 🔹 Inisialisasi Chart
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
                        backgroundColor: 'rgba(255, 159, 64, 0.7)',
                        borderColor: 'rgba(255, 159, 64, 1)',
                        borderWidth: 1,
                        stack: 'group1',
                        order: 3,
                    },
                    {
                        label: 'Member Hours',
                        data: [reportNetHours],
                        backgroundColor: 'rgba(75, 192, 192, 0.5)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1,
                        stack: 'group1',
                        order: 1,
                    },
                    {
                        label: 'Tractor',
                        data: [scanTotal],
                        backgroundColor: 'rgba(54, 162, 235, 0.7)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1,
                        stack: 'group2',
                        order: 4,
                    },
                    {
                        label: 'Non Operational',
                        data: [costTotal],
                        backgroundColor: 'rgba(255, 99, 132, 0.7)',
                        borderColor: 'rgba(255, 99, 132, 1)',
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
                                    const lines = [`Total Tractor: ${decimalToHoursMinutes(total)}`];
                                    const labels = scans.map(s =>
                                        `${s.label} (${decimalToHoursMinutes(s.value)})`);
                                    for (let i = 0; i < labels.length; i += 5) {
                                        lines.push(labels.slice(i, i + 5).join(', '));
                                    }
                                    return lines;
                                }
                                if (label === 'Non Operational') {
                                    const total = costs.reduce((s, x) => s + x.value, 0);
                                    const lines = [`Total Non Operational: ${decimalToHoursMinutes(total)}`];
                                    const labels = costs.map(c =>
                                        `${c.label} (${decimalToHoursMinutes(c.value)})`);
                                    for (let i = 0; i < labels.length; i += 5) {
                                        lines.push(labels.slice(i, i + 5).join(', '));
                                    }
                                    return lines;
                                }
                                if (label === 'Handling') {
                                    const total = penanganans.reduce((s, x) => s + x.value, 0);
                                    const lines = [`Total Handling: ${decimalToHoursMinutes(total)}`];
                                    const labels = penanganans.map(p =>
                                        `${p.label} (${decimalToHoursMinutes(p.value)})`);
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
                                yMin: reportNetHours + penangananTotal,
                                yMax: reportNetHours + penangananTotal,
                                borderColor: 'red',
                                borderWidth: 2,
                                borderDash: [6, 6]
                            },
                            totalOperationalText: {
                                type: 'label',
                                xValue: -0.2,
                                yValue: reportNetHours + penangananTotal + 1,
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
    </script>
@endsection
