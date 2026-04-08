<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iseki - Efficiency</title>
    <link rel="shortcut icon" href="{{ asset('assets/images/icon.png') }}" type="image/x-icon">
    <script src="{{ asset('assets/js/html5-qrcode.min.js') }}"></script>

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

        .login-card {
            background: white;
            border-radius: 20px;
            padding: 48px 32px;
            max-width: 420px;
            width: 100%;
            box-shadow: 0 8px 24px rgba(189, 2, 55, 0.12);
            border: 1px solid #ffe6ee;
        }

        .logo {
            text-align: center;
            font-weight: 700;
            font-size: 28px;
            color: #f7b5ca;
            margin-bottom: 32px;
        }

        .switch-tabs {
            display: flex;
            background: #fff0f5;
            border-radius: 12px;
            padding: 6px;
            margin-bottom: 32px;
            border: 1px solid #ffd8e8;
        }

        .tab-btn {
            flex: 1;
            padding: 12px 0;
            border: none;
            background: transparent;
            font-weight: 600;
            font-size: 15px;
            color: #f7b5ca;
            cursor: pointer;
            border-radius: 10px;
            transition: all 0.25s ease;
        }

        .tab-btn.active {
            background: #f7b5ca;
            color: white;
            box-shadow: 0 2px 8px rgba(189, 2, 55, 0.2);
        }

        .form-group {
            margin-bottom: 22px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            color: #6b5a65;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 13px 16px;
            border: 1px solid #f0e0e8;
            border-radius: 12px;
            font-size: 15px;
            background: #fdf9fc;
            transition: border-color 0.25s, background 0.25s;
        }

        .form-control:focus {
            outline: none;
            border-color: #f7b5ca;
            background: white;
            box-shadow: 0 0 0 3px rgba(189, 2, 55, 0.1);
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: #f7b5ca;
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            letter-spacing: 0.4px;
            transition: all 0.25s ease;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(189, 2, 55, 0.3);
        }

        /* Sembunyikan form yang tidak aktif */
        .form-section {
            display: none;
        }

        .form-section.active {
            display: block;
        }
    </style>

    <!-- Dynamic Favicon -->
    <script src="/iseki_pro_app/js/dynamic-favicon.js"></script>
    <script>document.addEventListener("DOMContentLoaded", function() { setDynamicFavicon("monitoring", "Efficiency"); });</script>

    <!-- Dynamic Favicon Assets -->
    <link rel="stylesheet" href="/iseki_pro_app/css/icon.css">
    <script src="/iseki_pro_app/js/dynamic-favicon.js"></script>
    <script>document.addEventListener("DOMContentLoaded", function() { setDynamicFavicon("monitoring", "Efficiency"); });</script>
</head>

<body>

    <div class="text-center mb-4">
    </div>

    <div class="login-card">
        <div class="logo">
            <img src="{{ asset('assets/images/logo/logo.png') }}" alt="Logo" style="width: 200px; height: auto;">
        </div>

        @if (session('loginError'))
            <div class="alert alert-danger p-2 mb-3 rounded"
                style="background:#ffebee;color:#c62828;border-radius:8px;text-align:center;">
                {{ session('loginError') }}
            </div>
        @endif

        <h4 class="text-center mb-4" style="color: #f7b5ca;">Admin/Leader Login</h4>

        <!-- Admin/Leader Form -->
        <div id="formAdmin">
            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="Username_User" class="form-control" placeholder="Username" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="Password_User" class="form-control" placeholder="Password" required>
                </div>
                <button type="submit" class="btn-login">Login</button>
            </form>
        </div>
    </div>
</body>

</html>