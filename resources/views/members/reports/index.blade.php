@extends('layouts.member')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Report</h3>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-body">
                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label for="date">Date</label>
                        <input type="date" name="date" id="date" class="form-control" value="{{ $dateString }}" required>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">Apply</button>
                    </div>
                </form>

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
            </div>
        </div>
    </section>
</div>
@endsection

@section('script')
<script>
    $(document).ready(function() {
        $('#scansTable').DataTable({
            pageLength: 25,
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
    });
</script>
@endsection