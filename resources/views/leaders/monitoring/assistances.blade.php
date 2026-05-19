@extends('layouts.leader')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Monitoring Perbantuan (Assistance)</h3>
                <p class="text-subtitle text-muted">Melihat log aktivitas scan traktor oleh member perbantuan.</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('leaders.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Monitor Perbantuan</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-header pb-0">
                <form method="GET" action="{{ route('leaders.monitoring.assistances') }}" id="filterForm">
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
                            <label class="form-label fw-bold mb-1">Member Perbantuan</label>
                            <select name="member_nik" class="form-select">
                                <option value="">Semua Member</option>
                                @foreach($members as $m)
                                    <option value="{{ $m->nik }}" {{ $selectedMemberNik === $m->nik ? 'selected' : '' }}>
                                        {{ $m->nama }} ({{ $m->nik }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-funnel"></i> Tampilkan
                            </button>
                            <a href="{{ route('leaders.monitoring.assistances.export', request()->all()) }}" class="btn btn-success">
                                <i class="bi bi-file-earmark-excel"></i> Download Excel
                            </a>
                        </div>
                    </div>
                </form>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <span class="badge bg-light-secondary fs-6">
                        {{ $filterType === 'daily' ? 'Tanggal: ' . \Carbon\Carbon::parse($filterDate)->format('d M Y') : 'Bulan: ' . \Carbon\Carbon::parse($filterMonth . '-01')->format('F Y') }}
                    </span>
                    <span class="badge bg-light-info fs-6">Total: {{ $assistances->count() }} data</span>
                </div>



                <table class="table table-striped" id="monitorTable">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Member Perbantuan</th>
                            <th>PIC Dibantu</th>
                            <th>Tractor Scanned</th>
                            <th>No Urut & Tgl Prod</th>
                            <th>Mower / Collector</th>
                            <th>Waktu Scan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($assistances as $item)
                        <tr>
                            <td>{{ $loop->count - $loop->index }}</td>
                            <td>
                                {{ $item->member ? $item->member->nama : $item->NIK_Assistance }}
                            </td>
                            <td>
                                {{ $item->dailyJob && $item->dailyJob->member ? $item->dailyJob->member->nama : '-' }}
                            </td>
                            <td><span class="badge bg-success">{{ $item->Name_Tractor }}</span></td>
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
