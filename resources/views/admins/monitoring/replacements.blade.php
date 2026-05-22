@extends('layouts.admin')

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
                        <li class="breadcrumb-item"><a href="{{ route('admins.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Monitor Pergantian</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-header pb-0">
                <form method="GET" action="{{ route('admins.monitoring.replacements') }}" id="filterForm">
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
                            <div class="input-group">
                                <button type="button" class="btn btn-outline-secondary" id="prevDate">
                                    <i class="bi bi-chevron-left"></i>
                                </button>
                                <input type="date" name="filter_date" id="filterDateInput" class="form-control text-center fw-bold" value="{{ $filterDate }}">
                                <button type="button" class="btn btn-outline-secondary" id="nextDate">
                                    <i class="bi bi-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-auto" id="monthlyFilter" style="{{ $filterType === 'daily' ? 'display:none;' : '' }}">
                            <label class="form-label fw-bold mb-1">Bulan</label>
                            <input type="month" name="filter_month" class="form-control" value="{{ $filterMonth }}">
                        </div>
                        <div class="col-auto">
                            <label class="form-label fw-bold mb-1">Member Pengganti</label>
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
                            <a href="{{ route('admins.monitoring.replacements.export', request()->all()) }}" class="btn btn-success">
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
                    <span class="badge bg-light-info fs-6">Total: {{ $replacements->count() }} data</span>
                </div>



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

document.addEventListener('DOMContentLoaded', function() {
    // Prev/Next date navigation
    const dateInput = document.getElementById('filterDateInput');
    const form = document.getElementById('filterForm');

    function shiftDate(delta) {
        if (!dateInput || !dateInput.value) return;
        const parts = dateInput.value.split('-');
        const d = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
        d.setDate(d.getDate() + delta);
        dateInput.value = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
        form.submit();
    }

    document.getElementById('prevDate')?.addEventListener('click', () => shiftDate(-1));
    document.getElementById('nextDate')?.addEventListener('click', () => shiftDate(1));

    // Init DataTable
    const monitorTable = document.querySelector('#monitorTable');
    if (monitorTable) {
        new DataTable(monitorTable, { order: [[0, 'desc']] });
    }
});
</script>
@endsection
