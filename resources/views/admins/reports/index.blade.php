@extends('layouts.admin') {{-- Ganti 'layouts.leader' dengan 'layouts.admin' --}}

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Daily Production Report</h3>
            </div>
        </div>
    </div>

    <!-- Date Filter -->
    <section class="section">
        <div class="card">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="date">Date</label>
                        <input type="date" name="date" id="date" class="form-control" value="{{ $dateString }}" required>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">Apply</button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <!-- Total Hour Member -->
    <section class="section">
        <div class="card">
            <div class="card-header">
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="card-title">Reported Data</h5>
                        <span class="badge bg-{{ $reportExists ? 'success' : 'secondary' }}">
                            {{ $reportExists ? 'Recorded' : 'Not Recorded' }}
                        </span>
                    </div>
                    <div class="col-md-6">
                        <h5 class="card-title">Current Member Status</h5>
                        <span class="badge bg-info">Live</span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        @if($reportExists)
                            <p><strong>Member:</strong> {{ $recordedReport->Total_Member_Report }}</p>
                            <p><strong>Hour:</strong> {{ number_format($recordedReport->Total_Hours_Report, 2) }}</p>
                        @else
                            <p class="text-muted">No report recorded yet.</p>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <p><strong>Active Members:</strong> {{ $currentTotalMembers }}</p>
                        <p><strong>Calculated Hours:</strong> {{ number_format($currentTotalHours, 2) }} ({{ $currentTotalMembers }} × 8 hours)</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Non Operational Cost -->
    <section class="section">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Non Operational Cost</h5>
            </div>
            <div class="card-body">
                @if($costs->isEmpty())
                    <p class="text-muted">No cost data for this day.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Hours</th>
                                    <th>Start</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($costs as $cost)
                                <tr>
                                    <td>
                                        {{ $cost->Non_Operational_Cost }}
                                        @php
                                            $jam = floor($cost->Non_Operational_Cost);
                                            $menit = round(($cost->Non_Operational_Cost - $jam) * 60);
                                        @endphp
                                        <small class="text-muted d-block">({{ $jam }} jam {{ $menit }} menit)</small>
                                    </td>
                                    <td>{{ \Carbon\Carbon::parse($cost->Start_Cost)->format('Y-m-d H:i') }}</td>
                                    <td>{{ $cost->Keterangan_Cost ?? '-' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </section>

    <!-- Permission (Power) -->
    <section class="section">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Permission</h5>
            </div>
            <div class="card-body">
                @if($powers->isEmpty())
                    <p class="text-muted">No permission data for this day.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Leave Hours</th>
                                    <th>Start</th>
                                    <th>Member</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($powers as $power)
                                <tr>
                                    <td>
                                        {{ $power->Leave_Hour_Power }}
                                        @php
                                            $jam = floor($power->Leave_Hour_Power);
                                            $menit = round(($power->Leave_Hour_Power - $jam) * 60);
                                        @endphp
                                        <small class="text-muted d-block">({{ $jam }} jam {{ $menit }} menit)</small>
                                    </td>
                                    <td>{{ \Carbon\Carbon::parse($power->Start_Power)->format('Y-m-d H:i') }}</td>
                                    <td>{{ $power->member->nama ?? 'Unknown' }}</td>
                                    <td>{{ $power->Keterangan_Power ?? '-' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </section>

    <!-- Time Handling (Penanganan) -->
    <section class="section">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Time Handling</h5>
            </div>
            <div class="card-body">
                @if($penanganans->isEmpty())
                    <p class="text-muted">No handling data for this day.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Hours</th>
                                    <th>Start</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($penanganans as $p)
                                <tr>
                                    <td>
                                        {{ $p->Hour_Penanganan }}
                                        @php
                                            $jam = floor($p->Hour_Penanganan);
                                            $menit = round(($p->Hour_Penanganan - $jam) * 60);
                                        @endphp
                                        <small class="text-muted d-block">({{ $jam }} jam {{ $menit }} menit)</small>
                                    </td>
                                    <td>{{ \Carbon\Carbon::parse($p->Start_Penanganan)->format('Y-m-d H:i') }}</td>
                                    <td>{{ $p->Keterangan_Penanganan }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </section>

    <!-- Scan Data -->
    <section class="section">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Scan Data</h5>
            </div>
            <div class="card-body">
                @if($scans->isEmpty())
                    <p class="text-muted">No scan data for this day.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm" id="scansTable">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Member</th>
                                    <th>Tractor</th>
                                    <th>Assigned Hour</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($scans as $scan)
                                <tr>
                                    <td>{{ $scan->Time_Scan }}</td>
                                    <td>{{ $scan->member->nama ?? 'Unknown' }}</td>
                                    <td>{{ $scan->tractor->Name_Tractor ?? 'Unknown' }}</td>
                                    <td>
                                        {{ $scan->Assigned_Hour_Scan }}
                                        @php
                                            $jam = floor($scan->Assigned_Hour_Scan);
                                            $menit = round(($scan->Assigned_Hour_Scan - $jam) * 60);
                                        @endphp
                                        <small class="text-muted d-block">({{ $jam }} jam {{ $menit }} menit)</small>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </section>
</div>
@endsection

@section('style')
{{-- Tambahkan jika DataTables atau TomSelect diperlukan --}}
<link href="{{ asset('assets/css/dataTables.dataTables.min.css') }}" rel="stylesheet">
@endsection

@section('script')
<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script src="{{ asset('assets/js/dataTables.min.js') }}"></script>
<script>
$(document).ready(function() {
    if ($('#scansTable').length) {
        $('#scansTable').DataTable({
            pageLength: 50,
            responsive: true,
            language: {
                search: "Cari:",
                lengthMenu: "Tampilkan _MENU_ data",
                info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                paginate: {
                    previous: "«",
                    next: "»"
                }
            }
        });
    }
});
</script>
@endsection