@extends('layouts.admin')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3><i class="bi bi-robot"></i> AI Diagnostic Analytics</h3>
                <p class="text-subtitle text-muted">Analisis cerdas produksi harian menggunakan AI</p>
            </div>
        </div>
    </div>

    <section class="section">
        {{-- Filter Tanggal --}}
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-center" id="dateFilterForm">
                    <div class="col-auto">
                        <label for="analysisDate" class="col-form-label fw-bold">Tanggal Analisis:</label>
                    </div>
                    <div class="col-auto">
                        <input type="date" id="analysisDate" name="date" class="form-control"
                            value="{{ $date }}">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-calendar-check"></i> Pilih Tanggal
                        </button>
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-success" id="btnAnalyze" onclick="runAnalysis()">
                            <i class="bi bi-cpu"></i> Jalankan Analisis AI
                        </button>
                    </div>
                    <div class="col-auto">
                        <a href="{{ route('admins.dashboard') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
                        </a>
                    </div>
                </form>
            </div>
        </div>

        {{-- Metrics Cards (akan diisi via AJAX) --}}
        <div class="row mb-4" id="metricsCards" style="display: none;">
            <div class="col-md-3">
                <div class="card" style="border-left: 4px solid #0d6efd;">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-muted mb-1 small">👥 Manpower</p>
                                <h4 class="mb-0" id="metricManpower">-</h4>
                                <small class="text-muted" id="metricManpowerAvg">Rata-rata 7 hari: -</small>
                            </div>
                            <div id="metricManpowerBadge"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card" style="border-left: 4px solid #198754;">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-muted mb-1 small">🔧 Jam Traktor</p>
                                <h4 class="mb-0" id="metricTractor">-</h4>
                                <small class="text-muted" id="metricTractorAvg">Rata-rata 7 hari: -</small>
                            </div>
                            <div id="metricTractorBadge"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card" style="border-left: 4px solid #dc3545;">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-muted mb-1 small">🚫 Non-Operasional</p>
                                <h4 class="mb-0" id="metricNonOp">-</h4>
                                <small class="text-muted" id="metricNonOpAvg">Rata-rata 7 hari: -</small>
                            </div>
                            <div id="metricNonOpBadge"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card" id="efficiencyCard" style="border-left: 4px solid #6c757d;">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-muted mb-1 small">📊 Efisiensi</p>
                                <h4 class="mb-0" id="metricEfisiensi">-</h4>
                                <small class="text-muted" id="metricEfisiensiAvg">Rata-rata 7 hari: -</small>
                            </div>
                            <div id="metricEfisiensiBadge"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Anomaly Alerts (akan diisi via AJAX) --}}
        <div id="anomalyAlerts" class="mb-4" style="display: none;"></div>

        {{-- AI Insight Result --}}
        <div class="card" id="insightCard">
            <div class="card-header d-flex justify-content-between align-items-center" 
                 style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);">
                <div>
                    <h5 class="mb-0 text-white">
                        <i class="bi bi-stars"></i> AI Diagnostic Report
                    </h5>
                    <small class="text-white-50">Powered by Llama-3 via Groq</small>
                </div>
                <span class="badge bg-light text-dark" id="analysisStatus">
                    <i class="bi bi-hourglass"></i> Menunggu
                </span>
            </div>
            <div class="card-body" id="insightBody" style="min-height: 200px;">
                <div class="text-center text-muted py-5" id="insightPlaceholder">
                    <i class="bi bi-robot" style="font-size: 3rem;"></i>
                    <p class="mt-3 mb-0">Klik tombol <strong>"Jalankan Analisis AI"</strong> untuk memulai analisis diagnostik.</p>
                    <p class="small">AI akan menganalisis data hari ini dan membandingkan dengan data 7 & 30 hari terakhir.</p>
                </div>
                <div id="insightLoading" style="display: none;" class="text-center py-5">
                    <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="text-muted mb-0">🧠 AI sedang menganalisis data produksi...</p>
                    <p class="text-muted small">Mengambil data hari ini, menghitung rata-rata historis, dan mengirim ke AI...</p>
                    <div class="progress mx-auto mt-3" style="max-width: 300px; height: 4px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                             style="width: 100%; background: linear-gradient(90deg, #0f3460, #533483, #e94560);"></div>
                    </div>
                </div>
                <div id="insightResult" style="display: none;">
                    <div id="insightText" style="font-size: 1.05rem; line-height: 1.8; white-space: pre-line;"></div>
                </div>
            </div>
        </div>

        {{-- Detail Data Table (akan diisi via AJAX) --}}
        <div class="row mt-4" id="detailSection" style="display: none;">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-bar-chart"></i> Top Non-Op Hari Ini</h6>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush" id="topNonOpList"></ul>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-tools"></i> Top Penanganan Hari Ini</h6>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush" id="topHandlingList"></ul>
                    </div>
                </div>
            </div>
        </div>

        {{-- Per Area Data (akan diisi via AJAX) --}}
        <div class="card mt-4" id="perAreaCard" style="display: none;">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-building"></i> Data Per Area Hari Ini</h6>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush" id="perAreaList"></ul>
            </div>
        </div>

    </section>
</div>

<style>
    #insightText {
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        color: #2d3436;
    }
    #insightText strong, #insightText b {
        color: #1a1a2e;
    }
    .anomaly-alert {
        padding: 10px 16px;
        border-radius: 8px;
        margin-bottom: 8px;
        font-size: 0.95rem;
        border-left: 4px solid;
    }
    .anomaly-critical {
        background-color: #fff5f5;
        border-color: #dc3545;
        color: #721c24;
    }
    .anomaly-warning {
        background-color: #fffbeb;
        border-color: #ffc107;
        color: #856404;
    }
    .anomaly-info {
        background-color: #f0f7ff;
        border-color: #0d6efd;
        color: #084298;
    }
    .metric-badge-up {
        color: #dc3545;
        font-weight: bold;
        font-size: 0.85rem;
    }
    .metric-badge-down {
        color: #198754;
        font-weight: bold;
        font-size: 0.85rem;
    }
    .metric-badge-stable {
        color: #6c757d;
        font-weight: bold;
        font-size: 0.85rem;
    }
</style>
@endsection

@section('script')
<script>
function runAnalysis() {
    const date = document.getElementById('analysisDate').value;
    const btn = document.getElementById('btnAnalyze');
    
    // Show loading
    document.getElementById('insightPlaceholder').style.display = 'none';
    document.getElementById('insightResult').style.display = 'none';
    document.getElementById('insightLoading').style.display = 'block';
    document.getElementById('analysisStatus').innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Memproses...';
    document.getElementById('analysisStatus').className = 'badge bg-warning text-dark';
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menganalisis...';

    fetch("{{ route('admins.ai-insight.analyze') }}?date=" + date, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // ── Tampilkan Metrics Cards ──
            const t = data.metrics.today;
            const a7 = data.metrics.avg7;
            
            document.getElementById('metricManpower').textContent = t.manpower + ' orang';
            document.getElementById('metricManpowerAvg').textContent = 'Rata-rata 7 hari: ' + a7.manpower + ' orang';
            setBadge('metricManpowerBadge', t.manpower, a7.manpower, true);
            
            document.getElementById('metricTractor').textContent = t.scanHours + ' jam (' + t.tractorCount + ' unit)';
            document.getElementById('metricTractorAvg').textContent = 'Rata-rata 7 hari: ' + a7.scanHours + ' jam';
            setBadge('metricTractorBadge', t.scanHours, a7.scanHours, true);
            
            document.getElementById('metricNonOp').textContent = t.nonOp + ' jam';
            document.getElementById('metricNonOpAvg').textContent = 'Rata-rata 7 hari: ' + a7.nonOp + ' jam';
            setBadge('metricNonOpBadge', t.nonOp, a7.nonOp, false); // Non-Op naik = buruk
            
            document.getElementById('metricEfisiensi').textContent = t.efisiensi + '%';
            document.getElementById('metricEfisiensiAvg').textContent = 'Rata-rata 7 hari: ' + a7.efisiensi + '%';
            
            // Warnai kartu efisiensi
            const effCard = document.getElementById('efficiencyCard');
            if (t.efisiensi > 0) {
                effCard.style.borderLeftColor = '#198754';
            } else {
                effCard.style.borderLeftColor = '#dc3545';
            }
            
            document.getElementById('metricsCards').style.display = 'flex';

            // ── Tampilkan Anomali ──
            const anomalyDiv = document.getElementById('anomalyAlerts');
            anomalyDiv.innerHTML = '';
            if (data.metrics.anomalies && data.metrics.anomalies.length > 0) {
                data.metrics.anomalies.forEach(a => {
                    let cls = 'anomaly-info';
                    if (a.includes('KRITIS')) cls = 'anomaly-critical';
                    else if (a.includes('⚠️')) cls = 'anomaly-warning';
                    anomalyDiv.innerHTML += `<div class="anomaly-alert ${cls}">${a}</div>`;
                });
                anomalyDiv.style.display = 'block';
            }

            // ── Tampilkan Top Non-Op ──
            const nonOpList = document.getElementById('topNonOpList');
            nonOpList.innerHTML = '';
            if (data.metrics.topNonOpCategories && data.metrics.topNonOpCategories.length > 0) {
                data.metrics.topNonOpCategories.forEach(c => {
                    nonOpList.innerHTML += `<li class="list-group-item py-2"><i class="bi bi-circle-fill text-danger me-2" style="font-size: 0.5rem;"></i>${c}</li>`;
                });
            } else {
                nonOpList.innerHTML = '<li class="list-group-item text-muted">Tidak ada data</li>';
            }

            // ── Tampilkan Top Handling ──
            const handlingList = document.getElementById('topHandlingList');
            handlingList.innerHTML = '';
            if (data.metrics.topHandlingCategories && data.metrics.topHandlingCategories.length > 0) {
                data.metrics.topHandlingCategories.forEach(c => {
                    handlingList.innerHTML += `<li class="list-group-item py-2"><i class="bi bi-circle-fill text-warning me-2" style="font-size: 0.5rem;"></i>${c}</li>`;
                });
            } else {
                handlingList.innerHTML = '<li class="list-group-item text-muted">Tidak ada data</li>';
            }
            document.getElementById('detailSection').style.display = 'flex';

            // ── Tampilkan Per Area ──
            const areaList = document.getElementById('perAreaList');
            areaList.innerHTML = '';
            if (data.metrics.perAreaData && data.metrics.perAreaData.length > 0) {
                data.metrics.perAreaData.forEach(a => {
                    areaList.innerHTML += `<li class="list-group-item py-2"><i class="bi bi-building me-2"></i>${a}</li>`;
                });
                document.getElementById('perAreaCard').style.display = 'block';
            }

            // ── Tampilkan AI Insight ──
            document.getElementById('insightLoading').style.display = 'none';
            document.getElementById('insightText').textContent = data.insight;
            document.getElementById('insightResult').style.display = 'block';
            document.getElementById('analysisStatus').innerHTML = '<i class="bi bi-check-circle"></i> Selesai';
            document.getElementById('analysisStatus').className = 'badge bg-success';
        } else {
            throw new Error(data.error || 'Unknown error');
        }
    })
    .catch(err => {
        document.getElementById('insightLoading').style.display = 'none';
        document.getElementById('insightText').textContent = '❌ Gagal menganalisis: ' + err.message;
        document.getElementById('insightResult').style.display = 'block';
        document.getElementById('analysisStatus').innerHTML = '<i class="bi bi-x-circle"></i> Gagal';
        document.getElementById('analysisStatus').className = 'badge bg-danger';
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-cpu"></i> Jalankan Analisis AI';
    });
}

function setBadge(elementId, todayVal, avgVal, higherIsBetter) {
    const el = document.getElementById(elementId);
    if (avgVal == 0) {
        el.innerHTML = '<span class="metric-badge-stable">—</span>';
        return;
    }
    const diff = ((todayVal - avgVal) / avgVal * 100).toFixed(0);
    if (Math.abs(diff) < 5) {
        el.innerHTML = '<span class="metric-badge-stable">≈ Stabil</span>';
    } else if (diff > 0) {
        const cls = higherIsBetter ? 'metric-badge-down' : 'metric-badge-up';
        el.innerHTML = `<span class="${cls}">▲ ${diff}%</span>`;
    } else {
        const cls = higherIsBetter ? 'metric-badge-up' : 'metric-badge-down';
        el.innerHTML = `<span class="${cls}">▼ ${Math.abs(diff)}%</span>`;
    }
}
</script>

<style>
    .spin {
        animation: spin 1s linear infinite;
    }
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
</style>
@endsection
