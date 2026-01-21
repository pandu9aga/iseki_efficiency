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
            padding: 10px;
            /* Lebih kecil di mobile */
            background-color: #fff5f9;
        }

        /* ========== NAVBAR ========== */
        .top-nav {
            width: 100%;
            max-width: 420px;
            margin-bottom: 16px;
        }

        .nav-links {
            display: flex;
            justify-content: space-between;
            background: white;
            border-radius: 12px;
            padding: 6px;
            box-shadow: 0 4px 12px rgba(189, 2, 55, 0.1);
            border: 1px solid #ffe6ee;
        }

        .nav-link {
            flex: 1;
            text-align: center;
            padding: 10px 0;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
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
            border-radius: 16px;
            padding: 20px;
            /* Lebih kecil di mobile */
            max-width: 100%;
            /* Full width di mobile */
            width: 100%;
            box-shadow: 0 6px 16px rgba(189, 2, 55, 0.1);
            border: 1px solid #ffe6ee;
        }

        .date-filter {
            background: #fff0f5;
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 20px;
            border: 1px solid #ffd8e8;
        }

        /* 🔥 Perbaikan Tabel Mobile */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-top: 12px;
        }

        /* Potong teks panjang & tambahkan tooltip */
        #scansTable td,
        #scansTable th {
            white-space: nowrap;
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            vertical-align: middle;
        }

        /* Di layar sangat kecil, sembunyikan kolom non-kritis */
        @media (max-width: 576px) {
            /* Sembunyikan Hour Weight & Type Plan jika perlu */
            /* th:nth-child(3), td:nth-child(3),
            th:nth-child(5), td:nth-child(5) {
                display: none;
            } */

            .report-card {
                padding: 16px;
            }

            .card-title {
                font-size: 1.25rem;
            }

            .date-filter .form-control {
                min-width: 120px;
                font-size: 0.9rem;
            }

            .btn {
                font-size: 0.875rem;
                padding: 0.25rem 0.5rem;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 8px;
            }

            .top-nav {
                margin-left: 0;
                margin-right: 0;
            }

            /* Opsional: tampilkan hanya kolom paling penting */
            /*
            th:not(:nth-child(1)):not(:nth-child(2)):not(:nth-child(4)):not(:nth-child(7)),
            td:not(:nth-child(1)):not(:nth-child(2)):not(:nth-child(4)):not(:nth-child(7)) {
                display: none;
            }
            */
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
                                <div class="row g-2 align-items-center flex-wrap">
                                    <div class="col-auto">
                                        <label for="date" class="col-form-label fw-bold">Tanggal:</label>
                                    </div>
                                    <div class="col-auto">
                                        <input type="date" id="date" name="date" class="form-control form-control-sm"
                                            value="{{ $dateString }}" required>
                                    </div>
                                    <div class="col-auto">
                                        <button type="submit" class="btn btn-primary btn-sm">Tampilkan</button>
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
                                        <th>Tractor</th>
                                        <th>Hour</th>
                                        <th>Seq No</th>
                                        <th>Type</th>
                                        <th>Prod Date</th>
                                        <th>Pengganti</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($scans as $scan)
                                    <tr>
                                        <td title="{{ \Carbon\Carbon::parse($scan->Time_Scan)->format('d-m-Y H:i:s') }}">
                                            {{ \Carbon\Carbon::parse($scan->Time_Scan)->format('d/m H:i') }}
                                        </td>
                                        <td title="{{ optional($scan->tractor)->Name_Tractor ?? '' }}">
                                            {{ Str::limit(optional($scan->tractor)->Name_Tractor ?? '—', 15) }}
                                        </td>
                                        <td>{{ $scan->Assigned_Hour_Scan ?? '—' }}</td>
                                        <td title="{{ $scan->Sequence_No_Plan }}">{{ Str::limit($scan->Sequence_No_Plan, 12) }}</td>
                                        <td>{{ optional($scan->plan)->Type_Plan ? Str::limit($scan->plan->Type_Plan, 10) : '—' }}</td>
                                        <td>{{ $scan->Production_Date_Plan }}</td>
                                        <td>
                                            @if($scan->Nik_Replace)
                                            @if(isset($memberMap[$scan->Nik_Replace]))
                                            {{ Str::limit($memberMap[$scan->Nik_Replace], 15) }}
                                            @else
                                            {{ Str::limit($scan->Nik_Replace, 12) }}
                                            @endif
                                            @else
                                            —
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-3">Tidak ada data scan untuk tanggal ini.</td>
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
                    pageLength: 25, // Lebih sedikit di mobile
                    responsive: false, // Kita handle manual via CSS
                    order: [
                        [0, 'desc']
                    ],
                    language: {
                        search: "Cari:",
                        lengthMenu: "Tampilkan _MENU_",
                        info: "Menampilkan _START_–_END_ dari _TOTAL_",
                        paginate: {
                            previous: "«",
                            next: "»"
                        }
                    },
                    // Nonaktifkan fitur yang mengganggu di mobile
                    autoWidth: false
                });
            }
        });
    </script>
</body>

</html>