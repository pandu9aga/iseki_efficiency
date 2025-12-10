<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iseki - Efficiency</title>

    <!-- Favicon -->
    <link rel="shortcut icon" href="{{ asset('assets/images/icon.png') }}" type="image/x-icon">

    <!-- CSS -->
    <link rel="stylesheet" href="{{ asset('assets/css/custom-fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/iconly/bold.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/dataTables.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/fixedColumns.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/perfect-scrollbar/perfect-scrollbar.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/bootstrap-icons/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">

    @yield('style')
</head>

<body data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-layout="vertical" data-pc-direction="ltr"
    data-pc-theme_contrast="" data-pc-theme="light">

    <!-- [ Pre-loader ] start -->
    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
    </div>
    <!-- [ Pre-loader ] End -->

    <!-- ========== SIDEBAR DARI MAZER =========== -->
    <div id="app">
        <div id="sidebar" class="active">
            <div class="sidebar-wrapper active">
                <div class="sidebar-header">
                    <div class="d-flex justify-content-between">
                        <div class="logo">
                            <a href="{{ route('leaders.dashboard') }}">
                                <img src="{{ asset('assets/images/logo/logo.png') }}" alt="Logo" style="width: 200px; height: auto;">
                            </a>
                        </div>
                        <div class="toggler">
                            <a href="#" class="sidebar-hide d-xl-none d-block">
                                <i class="bi bi-x bi-middle"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="sidebar-menu">
                    <ul class="menu">
                        <li class="sidebar-title">Menu</li>

                        <li class="sidebar-item">
                            <a href="{{ route('leaders.dashboard') }}" class='sidebar-link'>
                                <i class="bi bi-grid-fill"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="{{ route('leaders.reports.index') }}" class='sidebar-link'>
                                <i class="bi bi-easel"></i>
                                <span>Report</span>
                            </a>
                        </li>
                        <li class="sidebar-title">Navigation</li>
                        <li class="sidebar-item">
                            <a href="{{ route('leaders.members.index') }}" class='sidebar-link'> <i
                                    class="bi bi-person"></i>
                                <span>Member</span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="{{ route('leaders.members.select') }}" class='sidebar-link'> <i <i
                                    class="bi bi-person-badge-fill"></i>
                                <span>Select Member</span>
                            </a>
                        </li>                        
                        <li class="sidebar-title">Account</li>
                        <li class="sidebar-item">
                            <a href="{{ route('logout') }}" class='sidebar-link'>
                                <i class="bi bi-power"></i>
                                <span>Logout</span>
                            </a>
                        </li>

                    </ul>
                </div>
                <button class="sidebar-toggler btn x"><i data-feather="x"></i></button>
            </div>
        </div>

        <!-- ========== HEADER (MOBILE TOGGLER) =========== -->
        <div id="main">
            <header class="mb-3">
                <a href="#" class="burger-btn d-block d-xl-none">
                    <i class="bi bi-justify fs-3"></i>
                </a>
            </header>

            <!-- ========== KONTEN UTAMA =========== -->
            @yield('content')

            <!-- ========== CUSTOM FOOTER =========== -->
            <footer>
                <div class="footer clearfix mb-0 text-muted">
                    <div class="float-start">
                        <p><span class="year"></span> &copy; Iseki</p>
                    </div>
                    <div class="float-end">
                        <p>Production Efficiency Counter Website</p>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- JS: Core -->
    <script src="{{ asset('assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js') }}"></script>
    <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/apexcharts/apexcharts.js') }}"></script>

    <!-- JS: DataTables -->
    <script src="{{ asset('assets/js/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/js/dataTables.min.js') }}"></script>
    <script src="{{ asset('assets/js/fixedColumns.dataTables.min.js') }}"></script>
    <script src="{{ asset('assets/js/html5-qrcode.min.js') }}"></script>

    <!-- JS: Custom -->
    <script>
        document.querySelector('.year').textContent = new Date().getFullYear();
        // Init DataTable if exists
        const table1 = document.querySelector('#table1');
        if (table1) {
            new DataTable(table1);
        }
    </script>
    <script src="{{ asset('assets/js/main.js') }}"></script>

    <!-- Yield: Modal harus sebelum script agar bisa diakses -->
    @yield('modal')
    @yield('script')
</body>

</html>
