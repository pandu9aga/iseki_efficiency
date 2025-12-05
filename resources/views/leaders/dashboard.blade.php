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
                                    <input type="date" id="date" name="date" class="form-control" value="{{ $dateString }}">
                                </div>
                                <div class="col-auto">
                                    <button type="submit" class="btn btn-primary">Show</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
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
    // 🔸 Ambil data dari Laravel dengan fallback
    const rawScans = @json($scans->map(function($s) { return ['label' => $s->tractor->Name_Tractor ?? 'Unknown', 'value' => (float)$s->Assigned_Hour_Scan]; })->toArray());
    const rawCosts = @json($costs->map(function($c) { return ['label' => $c->Keterangan_Cost ?? 'Unknown', 'value' => (float)$c->Non_Operational_Cost]; })->toArray());
    const reportHours = {{ (float)$reportHours }};
    const reportMembers = {{ (int)$reportMembers }};
    const rawPowers = @json($powers->map(function($p) { return ['label' => $p->Keterangan_Power ?? 'Unknown', 'value' => (float)$p->Leave_Hour_Power]; })->toArray());
    const rawPenanganans = @json($penanganans->map(function($p) { return ['label' => $p->Keterangan_Penanganan ?? 'Unknown', 'value' => (float)$p->Hour_Penanganan]; })->toArray());
    const powerTotal = {{ (float)$powerTotal }};

    // 🔸 Validasi data dari Laravel
    const scans = Array.isArray(rawScans) ? rawScans : [];
    const costs = Array.isArray(rawCosts) ? rawCosts : [];
    const powers = Array.isArray(rawPowers) ? rawPowers : [];
    const penanganans = Array.isArray(rawPenanganans) ? rawPenanganans : [];

    // 🔸 Hitung total dari data array
    const scanTotal = scans.reduce((sum, s) => sum + s.value, 0);
    const costTotal = costs.reduce((sum, c) => sum + c.value, 0);
    const powerTotalCalculated = powers.reduce((sum, p) => sum + p.value, 0);
    const penangananTotal = penanganans.reduce((sum, p) => sum + p.value, 0);

    // 🔸 Hitung report bersih (setelah dikurangi power)
    const reportNetHours = reportHours - powerTotalCalculated;

    const handlingPeak = reportNetHours + powerTotalCalculated + penangananTotal;

    const ctx = document.getElementById('stackedChart').getContext('2d');

    // Register Plugin Label & Annotation
    Chart.register(ChartDataLabels);
    Chart.register('chartjs-plugin-annotation');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Operational Production'],
            datasets: [
                // Penanganan (paling atas)
                {
                    label: 'Handling',
                    data: [penangananTotal],
                    backgroundColor: 'rgba(255, 159, 64, 0.7)',
                    borderColor: 'rgba(255, 159, 64, 1)',
                    borderWidth: 1,
                    stack: 'group1',
                    order: 4,
                },
                // Power (di bawah penanganan)
                {
                    label: 'Permission',
                    data: [powerTotalCalculated],
                    backgroundColor: 'rgba(153, 102, 255, 0.7)',
                    borderColor: 'rgba(153, 102, 255, 1)',
                    borderWidth: 1,
                    stack: 'group1',
                    order: 3,
                },
                // Report (paling bawah, dikurangi power)
                {
                    label: 'Member Hours',
                    data: [reportNetHours],
                    backgroundColor: 'rgba(75, 192, 192, 0.5)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1,
                    stack: 'group1',
                    order: 1,
                },
                // Scan (di atas cost)
                {
                    label: 'Tractor',
                    data: [scanTotal],
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1,
                    stack: 'group2',
                    order: 5,
                },
                // Cost (di atas semua)
                {
                    label: 'Non Operational',
                    data: [costTotal],
                    backgroundColor: 'rgba(255, 99, 132, 0.7)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1,
                    stack: 'group2',
                    order: 6,
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                x: {
                    stacked: true,
                },
                y: {
                    stacked: true,
                    beginAtZero: true
                }
            },
            plugins: {
                // 🔥 Aktifkan dan konfigurasi datalabels di sini
                datalabels: {
                    anchor: 'center',
                    align: 'center',
                    clamp: true, // supaya tidak hilang ketika keluar area chart
                    color: '#25396f',
                    formatter: function(value, context) {
                        const label = context.dataset.label;

                        if (label === 'Tractor') {
                            return "Tractor : " + scanTotal.toFixed(2) + " jam";
                        }
                        if (label === 'Non Operational') {
                            return "Non Operational : " + costTotal.toFixed(2) + " jam";
                        }
                        if (label === 'Permission') {
                            return "Permission : " + powerTotalCalculated.toFixed(2) + " jam";
                        }
                        if (label === 'Handling') {
                            return "Handling : " + penangananTotal.toFixed(2) + " jam";
                        }
                        if (label === 'Member Hours') {
                            return "Member Hours : " + reportNetHours.toFixed(2) + " jam";
                        }

                        return value ? value.toFixed(2) + " jam" : "";
                    },
                    font: {
                        weight: 'bold',
                        size: 12
                    }
                },
                tooltip: {
                    callbacks: {
                        // Gunakan `beforeLabel` untuk menambahkan konten sebelum label default
                        beforeLabel: function(context) {
                            let label = context.dataset.label || '';
                            if (label === 'Member Hours') {
                                return [
                                    `Total Members: {{ $reportMembers }}`,
                                    `Total Hours: {{ $reportHours }} jam`,
                                    `Net Hours (after permission): ${context.parsed.y.toFixed(2)} jam`
                                ];
                            }
                            if (label === 'Tractor') {
                                const totalJam = scans.reduce((sum, s) => sum + s.value, 0);
                                const labels = scans.map(s => `${s.label} (${s.value} jam)`);
                                const chunks = [];
                                for (let i = 0; i < labels.length; i += 5) {
                                    chunks.push(labels.slice(i, i + 5));
                                }
                                // Gabungkan total dan detail per 5 item
                                const lines = [`Total Tractor: ${totalJam.toFixed(2)} jam`];
                                lines.push(...chunks.map(chunk => `${chunk.join(', ')}`));
                                return lines;
                            }
                            if (label === 'Non Operational') {
                                const totalJam = costs.reduce((sum, c) => sum + c.value, 0);
                                const labels = costs.map(c => `${c.label} (${c.value} jam)`);
                                const chunks = [];
                                for (let i = 0; i < labels.length; i += 5) {
                                    chunks.push(labels.slice(i, i + 5));
                                }
                                const lines = [`Total Non Operational: ${totalJam.toFixed(2)} jam`];
                                lines.push(...chunks.map(chunk => `${chunk.join(', ')}`));
                                return lines;
                            }
                            if (label === 'Permission') {
                                const totalJam = powers.reduce((sum, p) => sum + p.value, 0);
                                const labels = powers.map(p => `${p.label} (${p.value} jam)`);
                                const chunks = [];
                                for (let i = 0; i < labels.length; i += 5) {
                                    chunks.push(labels.slice(i, i + 5));
                                }
                                const lines = [`Total Permission: ${totalJam.toFixed(2)} jam`];
                                lines.push(...chunks.map(chunk => `${chunk.join(', ')}`));
                                return lines;
                            }
                            if (label === 'Handling') {
                                const totalJam = penanganans.reduce((sum, p) => sum + p.value, 0);
                                const labels = penanganans.map(p => `${p.label} (${p.value} jam)`);
                                const chunks = [];
                                for (let i = 0; i < labels.length; i += 5) {
                                    chunks.push(labels.slice(i, i + 5));
                                }
                                const lines = [`Total Handling: ${totalJam.toFixed(2)} jam`];
                                lines.push(...chunks.map(chunk => `${chunk.join(', ')}`));
                                return lines;
                            }
                            return null; // Kembalikan null jika tidak ingin menambahkan sesuatu sebelum label
                        },
                        // Kembalikan label default kosong agar tidak duplikat
                        label: function(context) {
                            return ''; // Biarkan kosong karena kita sudah handle di `beforeLabel`
                        }
                    }
                },
                annotation: {
                    annotations: {
                        handlingToPermission: {
                            type: 'line',
                            xMin: -0.05,
                            xMax: 0.05,
                            yMin: reportNetHours + powerTotalCalculated,
                            yMax: reportNetHours + powerTotalCalculated,
                            borderColor: 'red',
                            borderWidth: 2,
                            borderDash: [6, 6]
                        },
                        topEdgeLine: {
                            type: 'line',
                            xMin: -0.05,
                            xMax: 0.05,
                            yMin: handlingPeak,
                            yMax: handlingPeak,
                            borderColor: 'red',
                            borderWidth: 2,
                            borderDash: [6, 6]
                        },
                        totalOperationalText: {
                            type: 'label',
                            xValue: 0 - 0.2,
                            yValue: handlingPeak + 1, // sedikit di atas bar tertinggi
                            backgroundColor: 'rgba(255,255,255,0.0)',
                            color: '#333',
                            font: {
                                size: 14,
                                weight: 'bold'
                            },
                            content: [
                                (reportNetHours + powerTotalCalculated + penangananTotal).toFixed(2) + ' jam'
                            ],
                            textAlign: 'center'
                        },
                        totalTractorText: {
                            type: 'label',
                            xValue: 0 + 0.2,
                            yValue: scanTotal + costTotal + 1, // di atas bar group2
                            backgroundColor: 'rgba(255,255,255,0.0)',
                            color: '#333',
                            font: {
                                size: 14,
                                weight: 'bold'
                            },
                            content: [
                                (scanTotal + costTotal).toFixed(2) + ' jam'
                            ],
                            textAlign: 'center'
                        }
                    }
                }
            }
        }
    });
</script>
@endsection