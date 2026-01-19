<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Scan - {{ $areaName ?? 'Area' }}</title>
    <link rel="shortcut icon" href="{{ asset('assets/images/icon.png') }}" type="image/x-icon">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- CSS -->
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/dataTables.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/bootstrap-icons/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
            background-color: #fff5f9;
        }

        /* ========== NAVBAR ========== */
        .top-nav {
            width: 100%;
            max-width: 420px;
            margin-bottom: 20px;
        }

        .nav-links {
            display: flex;
            justify-content: space-between;
            background: white;
            border-radius: 12px;
            padding: 8px;
            box-shadow: 0 4px 12px rgba(189, 2, 55, 0.1);
            border: 1px solid #ffe6ee;
        }

        .nav-link {
            flex: 1;
            text-align: center;
            padding: 12px 0;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            color: #f7b5ca;
            border-radius: 10px;
            transition: all 0.25s ease;
        }

        .nav-link.active,
        .nav-link:hover {
            background: #f7b5ca;
            color: white;
        }

        .report-card {
            background: white;
            border-radius: 20px;
            padding: 32px;
            max-width: 1000px;
            width: 100%;
            box-shadow: 0 8px 24px rgba(189, 2, 55, 0.12);
            border: 1px solid #ffe6ee;
        }

        .date-filter {
            background: #fff0f5;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
            border: 1px solid #ffd8e8;
        }

        @media (max-width: 480px) {
            .top-nav {
                margin-left: 10px;
                margin-right: 10px;
                max-width: 100%;
            }
        }
    </style>
</head>

<body>
    <!-- ========== NAVBAR ATAS ========== -->
    <nav class="top-nav">
        <div class="nav-links">
            <a href="{{ route('area.scan') }}" class="nav-link">Scan</a>
            <a href="{{ route('area.report') }}" class="nav-link active">Report</a>
            <a href="{{ route('logout.area') }}" class="nav-link" onclick="return confirm('Yakin ingin keluar dari sesi Area?')">Logout</a>
        </div>
    </nav>

    <div class="">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="card report-card">
                    <div class="card-header bg-transparent border-0 pb-0">
                        <h3 class="card-title text-primary text-center">Laporan Scan - {{ $areaName ?? 'Area' }}</h3>
                    </div>
                    <div class="card-body pt-0">
                        <!-- Filter Tanggal -->
                        <div class="date-filter">
                            <form method="GET">
                                <div class="row g-3 align-items-center">
                                    <div class="col-auto">
                                        <label for="date" class="col-form-label">Tanggal:</label>
                                    </div>
                                    <div class="col-auto">
                                        <input type="date" id="date" name="date" class="form-control"
                                            value="{{ $dateString }}" required>
                                    </div>
                                    <div class="col-auto">
                                        <button type="submit" class="btn btn-primary">Tampilkan</button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Tabel Data -->
                        <div class="table-responsive">
                            <table class="table table-striped" id="scansTable">
                                <thead>
                                    <tr>
                                        <th>Time Scan</th>
                                        <th>Tractor Name</th>
                                        <th>Hour Weight</th>
                                        <th>Sequence No</th>
                                        <th>Type Plan</th>
                                        <th>Production Date</th>
                                        <th>Member Pengganti</th> <!-- 🔥 TAMBAHAN -->
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($scans as $scan)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($scan->Time_Scan)->format('d-m-Y H:i:s') }}</td>
                                        <td>{{ optional($scan->tractor)->Name_Tractor ?? '—' }}</td>
                                        <td>{{ $scan->Assigned_Hour_Scan ?? '—' }}</td>
                                        <td>{{ $scan->Sequence_No_Plan }}</td>
                                        <td>{{ optional($scan->plan)->Type_Plan ?? '—' }}</td>
                                        <td>{{ $scan->Production_Date_Plan }}</td>
                                        <!-- 🔥 Kolom Member Pengganti -->
                                        <td>
                                            @if($scan->Nik_Replace)
                                            @if(isset($memberMap[$scan->Nik_Replace]))
                                            {{ $memberMap[$scan->Nik_Replace] }}
                                            @else
                                            {{ $scan->Nik_Replace }} <small class="text-muted">(nama tidak ditemukan)</small>
                                            @endif
                                            @else
                                            —
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">Tidak ada data scan untuk tanggal ini.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="{{ asset('assets/js/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/dataTables.min.js') }}"></script>

    <script>
        $(document).ready(function() {
            const hasDataRows = $('#scansTable tbody tr').length > 0 &&
                $('#scansTable tbody tr:first td[colspan]').length === 0;

            if (hasDataRows) {
                $('#scansTable').DataTable({
                    pageLength: 50,
                    responsive: true,
                    order: [
                        [0, 'desc']
                    ],
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
</body>

</html>