<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | Iseki Efisiensi</title>
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
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background-color: #fff5f9;
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

        .btn-scan {
            width: 100%;
            padding: 12px;
            background: #fff8fb;
            color: #f7b5ca;
            border: 1px solid #ffd8e6;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            margin-top: 20px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-scan:hover {
            background: #ffeff7;
            color: #e296ae;
            border-color: #ffb8d1;
        }

        #reader {
            width: 100%;
            height: 220px;
            margin-top: 20px;
            border-radius: 12px;
            overflow: hidden;
            display: none;
            border: 1px solid #f5e8ef;
            background: #fdf9fc;
        }

        .alert {
            padding: 12px 16px;
            background: #fff2f6;
            color: #f7b5ca;
            border-radius: 12px;
            margin-bottom: 28px;
            font-size: 14px;
            font-weight: 500;
            border-left: 3px solid #f7b5ca;
        }

        .form-section {
            display: none;
            animation: fadeIn 0.35s ease;
        }

        .form-section.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 40px 20px;
            }

            .logo h2 {
                font-size: 26px;
            }
        }
    </style>
</head>

<body>
    <div class="login-card">
        <div class="logo">
            <img src="{{ asset('assets/images/logo/logo.png') }}" alt="Logo" style="width: 200px; height: auto;">
        </div>

        @if (session('loginError'))
            <div class="alert">
                {{ session('loginError') }}
            </div>
        @endif

        <div class="switch-tabs">
            <button class="tab-btn active" data-target="member">Member</button>
            <button class="tab-btn" data-target="admin">Admin</button>
        </div>

        <!-- Member Form -->
        <div id="formMember" class="form-section active">
            <form id="memberLoginForm" method="POST" action="{{ route('login.member') }}">
                @csrf
                <div class="form-group">
                    <label for="nikInput">NIK</label>
                    <input type="text" id="nikInput" name="NIK_Member" class="form-control" placeholder="Masukkan atau scan NIK" required>
                </div>
                <div id="reader"></div>

                <button type="submit" class="btn-login">Member Login</button>
                <button type="button" class="btn-scan" id="btnScan">📷 Scan QR NIK</button>
            </form>
        </div>

        <!-- Admin Form -->
        <div id="formAdmin" class="form-section">
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
                <button type="submit" class="btn-login">Admin Login</button>
            </form>
        </div>
    </div>

    <script>
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.form-section').forEach(f => f.classList.remove('active'));
                btn.classList.add('active');
                const target = btn.dataset.target;
                document.getElementById('form' + target.charAt(0).toUpperCase() + target.slice(1)).classList.add('active');
            });
        });

        let html5QrCode = null;
        const reader = document.getElementById('reader');
        const btnScan = document.getElementById('btnScan');
        const nikInput = document.getElementById('nikInput');
        const memberForm = document.getElementById('memberLoginForm');

        btnScan.addEventListener('click', () => {
            if (reader.style.display === 'block') {
                if (html5QrCode) {
                    html5QrCode.stop().then(() => html5QrCode.clear()).catch(() => {});
                    html5QrCode = null;
                }
                reader.style.display = 'none';
                btnScan.textContent = '📷 Scan Barcode NIK';
                return;
            }

            reader.style.display = 'block';
            btnScan.textContent = '❌ Tutup Kamera';
            html5QrCode = new Html5Qrcode("reader");

            Html5Qrcode.getCameras().then(devices => {
                if (devices?.length) {
                    html5QrCode.start(
                        devices[0].id, {
                            fps: 10,
                            qrbox: { width: 200, height: 200 }
                        },
                        (decodedText) => {
                            // ✅ Ambil bagian pertama sebelum ';'
                            const nik = decodedText.split(';')[0].trim();

                            // Isi input
                            nikInput.value = nik;

                            // ✅ Auto-submit form
                            memberForm.submit();
                        },
                        (errorMessage) => {
                            // Error scanning (opsional)
                        }
                    );
                }
            }).catch(() => {
                alert("Kamera tidak tersedia atau izin ditolak.");
                reader.style.display = 'none';
                btnScan.textContent = '📷 Scan Barcode NIK';
            });
        });
    </script>
</body>

</html>