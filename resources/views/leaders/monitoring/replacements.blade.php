@extends('layouts.leader')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Monitoring Pergantian (Replacement)</h3>
                <p class="text-subtitle text-muted">Melihat log aktivitas scan traktor oleh member pengganti.</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('leaders.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Monitor Pergantian</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-header pb-0">
                <form method="GET" action="{{ route('leaders.monitoring.replacements') }}" id="filterForm">
                    <div class="row align-items-end g-3">
                        <div class="col-auto">
                            <label class="form-label fw-bold mb-1">Filter</label>
                            <select name="filter_type" id="filterType" class="form-select" onchange="toggleFilter()">
                                <option value="daily" {{ $filterType === 'daily' ? 'selected' : '' }}>Harian</option>
                                <option value="monthly" {{ $filterType === 'monthly' ? 'selected' : '' }}>Bulanan</option>
                            </select>
                        </div>
                        <div class="col-auto" id="dailyFilter" style="{{ $filterType === 'monthly' ? 'display:none;' : '' }}">
                            <label class="form-label fw-bold mb-1">Tanggal</label>
                            <input type="date" name="filter_date" class="form-control" value="{{ $filterDate }}">
                        </div>
                        <div class="col-auto" id="monthlyFilter" style="{{ $filterType === 'daily' ? 'display:none;' : '' }}">
                            <label class="form-label fw-bold mb-1">Bulan</label>
                            <input type="month" name="filter_month" class="form-control" value="{{ $filterMonth }}">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-funnel"></i> Tampilkan
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <span class="badge bg-light-secondary fs-6">
                        {{ $filterType === 'daily' ? 'Tanggal: ' . \Carbon\Carbon::parse($filterDate)->format('d M Y') : 'Bulan: ' . \Carbon\Carbon::parse($filterMonth . '-01')->format('F Y') }}
                    </span>
                    <span class="badge bg-light-info fs-6">Total: {{ $replacements->count() }} data</span>
                </div>

                {{-- DURASI PERGANTIAN SUMMARY --}}
                @if($durationSummary->count() > 0)
                <div class="card mb-3 border border-primary">
                    <div class="card-header py-2 bg-light-primary">
                        <h6 class="mb-0"><i class="bi bi-clock-history"></i> Ringkasan Durasi Pergantian</h6>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>No</th>
                                    <th>Member Pengganti</th>
                                    <th>PIC Digantikan</th>
                                    <th>Jumlah Sesi</th>
                                    <th>Total Durasi</th>
                                    <th>Detail</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($durationSummary as $idx => $dur)
                                <tr>
                                    <td>{{ $idx + 1 }}</td>
                                    <td><strong>{{ $dur['nama_pengganti'] }}</strong></td>
                                    <td>{{ $dur['nama_pic'] }}</td>
                                    <td><span class="badge bg-info">{{ $dur['jumlah_sesi'] }} sesi</span></td>
                                    <td>
                                        <span class="badge bg-warning text-dark fs-6">
                                            {{ $dur['jam'] > 0 ? $dur['jam'] . ' jam ' : '' }}{{ $dur['menit'] }} menit
                                        </span>
                                        <small class="text-muted ms-1">({{ $dur['total_minutes'] }} menit)</small>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#detailReplaceL{{ $idx }}">
                                            <i class="bi bi-eye"></i> Rincian
                                        </button>
                                    </td>
                                </tr>
                                <tr class="collapse" id="detailReplaceL{{ $idx }}">
                                    <td colspan="6" class="p-0">
                                        <div class="bg-light p-2 ms-4 me-4 mb-2 mt-1 rounded border">
                                            <small class="fw-bold text-muted">Rincian per Sesi:</small>
                                            <table class="table table-sm table-bordered mb-0 mt-1" style="font-size: 0.85rem;">
                                                <thead>
                                                    <tr class="table-secondary">
                                                        <th>Sesi</th>
                                                        <th>Durasi</th>
                                                        <th>Waktu Input</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($dur['sessions'] as $ses)
                                                    <tr>
                                                        <td>Sesi {{ $ses['sesi'] }}</td>
                                                        <td><strong>{{ $ses['menit'] }} menit</strong></td>
                                                        <td>{{ $ses['waktu'] }}</td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

                <table class="table table-striped" id="monitorTable">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Member Pengganti</th>
                            <th>PIC Digantikan</th>
                            <th>Tractor Scanned</th>
                            <th>No Urut & Tgl Prod</th>
                            <th>Mower / Collector</th>
                            <th>Waktu Scan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($replacements as $item)
                        <tr>
                            <td>{{ $loop->count - $loop->index }}</td>
                            <td>
                                {{ $item->member ? $item->member->nama : $item->NIK_Replacement }}
                            </td>
                            <td>
                                {{ $item->dailyJob && $item->dailyJob->member ? $item->dailyJob->member->nama : '-' }}
                            </td>
                            <td><span class="badge bg-primary">{{ $item->Name_Tractor }}</span></td>
                            <td>
                                <strong>{{ $item->Sequence_No_Plan }}</strong><br>
                                <small class="text-muted">{{ $item->Production_Date_Plan }}</small>
                            </td>
                            <td>
                                <small>Mower: {{ $item->Model_Mower_Plan ?? '-' }}</small><br>
                                <small>Collector: {{ $item->Model_Collector_Plan ?? '-' }}</small>
                            </td>
                            <td>{{ $item->created_at ? $item->created_at->format('d M Y, H:i') : '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<script>
function toggleFilter() {
    const type = document.getElementById('filterType').value;
    document.getElementById('dailyFilter').style.display = type === 'daily' ? '' : 'none';
    document.getElementById('monthlyFilter').style.display = type === 'monthly' ? '' : 'none';
}

// Init DataTable dengan urutan No descending (terbaru di atas, terlama No.1 di bawah)
document.addEventListener('DOMContentLoaded', function() {
    const monitorTable = document.querySelector('#monitorTable');
    if (monitorTable) {
        new DataTable(monitorTable, { order: [[0, 'desc']] });
    }
});
</script>
@endsection
