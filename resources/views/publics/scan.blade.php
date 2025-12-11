<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iseki - Efficiency</title>
    <link rel="shortcut icon" href="{{ asset('assets/images/icon.png') }}" type="image/x-icon">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- CSS -->
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/bootstrap-icons/bootstrap-icons.css') }}">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            /* min-height: 100vh; */
            display: flex;
            flex-direction: column; /* 🔥 Ubah ke column agar navbar di atas */
            align-items: center;
            /* justify-content: center; */
            padding: 20px;
            background-color: #fff5f9;
        }

        /* ========== NAVBAR ========== */
        .top-nav {
            width: 100%;
            max-width: 420px; /* Sesuaikan lebar navbar dengan kartu */
            margin-bottom: 20px; /* Jarak antara navbar dan login card */
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

        .main-content {
            flex-grow: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .scan-card {
            background: white;
            border-radius: 20px;
            padding: 32px;
            max-width: 420px;
            width: 100%;
            box-shadow: 0 8px 24px rgba(189, 2, 55, 0.12);
            border: 1px solid #ffe6ee;
        }

        #reader {
            width: 100%;
            max-width: 300px;
            height: auto;
            margin: auto;
            /* border-radius: 12px;
            overflow: hidden; */
            /* border: 1px solid #f0e0e8;
            background: #fdf9fc; */
        }

        .alert {
            border-radius: 12px;
            border-left: 3px solid;
        }

        @media (max-width: 480px) {
            .top-nav,
            .scan-card {
                margin-left: 10px;
                margin-right: 10px;
                max-width: 100%;
            }

            .scan-card {
                padding: 24px;
            }
        }
    </style>
</head>

<body>
    <!-- ========== NAVBAR ATAS ========== -->
    <nav class="top-nav">
        <div class="nav-links">
            <!-- Ganti href sesuai route Laravel Anda -->
            <a href="{{ route('scan') }}" class="nav-link active">Scan</a>
            <a href="{{ route('report.scan.index') }}" class="nav-link">Report</a>
            <a href="{{ route('login.form') }}" class="nav-link">Login</a>
        </div>
    </nav>

    <!-- ========== MAIN CONTENT ========== -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="row justify-content-center">
                <div class="col-12">
                    <div class="card scan-card">
                        <div class="card-header bg-transparent border-0 pb-0">
                            <h3 class="card-title text-center text-primary mb-0">Scan Tractor</h3>
                        </div>
                        <div class="card-body pt-0">
                            <!-- Scanner -->
                            <div id="reader"></div>
                            <div class="mt-3 text-center">
                                <button id="scanButton" class="btn btn-primary">Scanner Camera</button>
                                <button type="button" class="btn btn-outline-secondary" id="focusUsbBtn">Scanner USB</button>
                            </div>
                            <div id="result" class="mt-3"></div>

                            <!-- Alerts -->
                            @if(session('success'))
                                <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                                    <strong>Sukses!</strong> {{ session('success') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            @endif

                            @if(session('error'))
                                <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                                    <strong>Error!</strong> {{ session('error') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            @endif

                            <!-- Form Submit -->
                            <form id="scanForm" method="POST" action="{{ route('scan.store') }}" class="mt-4">
                                @csrf
                                <input type="hidden" name="qr_data" id="qrDataInput" required>
                                {{-- <input type="hidden" name="Id_Tractor" id="Id_Tractor" required> --}}

                                <input type="text" id="usbScannerInput" style="opacity: 0; position: absolute; pointer-events: none;" autocomplete="off" autofocus>

                                <div class="mb-1">
                                    <label for="Area_Scan" class="form-label">Area Scan</label>
                                    <input type="text" class="form-control" id="Area_Scan" name="Area_Scan" value="Mower Collector" readonly required>
                                </div>

                                <div class="mb-1">
                                    <label for="Name_Tractor" class="form-label">Nama Tractor</label>
                                    <input type="text" class="form-control" id="Name_Tractor" name="Name_Tractor" readonly required>
                                </div>

                                <div class="mb-1">
                                    <label for="Sequence_No_Plan" class="form-label">Nomor Urut</label>
                                    <input type="text" class="form-control" id="Sequence_No_Plan" name="Sequence_No_Plan" readonly required>
                                </div>

                                <div class="mb-1">
                                    <label for="Production_Date_Plan" class="form-label">Tanggal Produksi</label>
                                    <input type="text" class="form-control" id="Production_Date_Plan" name="Production_Date_Plan" readonly required>
                                </div>

                                <div class="mb-1">
                                    <label for="Model_Mower_Plan" class="form-label">Tipe Mower</label>
                                    <input type="text" class="form-control" id="Model_Mower_Plan" name="Model_Mower_Plan" readonly required>
                                </div>

                                <div class="mb-4">
                                    <label for="Model_Collector_Plan" class="form-label">Tipe Collector</label>
                                    <input type="text" class="form-control" id="Model_Collector_Plan" name="Model_Collector_Plan" readonly required>
                                </div>

                                <button type="submit" class="btn btn-success w-100" id="submitBtn" disabled>Simpan Scan</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JS -->
    <script src="{{ asset('assets/js/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/html5-qrcode.min.js') }}"></script>

    <script>
        let html5QrCode = null;
        const scanButton = document.getElementById('scanButton');
        const resultDiv = document.getElementById('result');
        const formIdTractor = document.getElementById('Id_Tractor');
        const formNameTractor = document.getElementById('Name_Tractor');
        const submitBtn = document.getElementById('submitBtn');
        const qrDataInput = document.getElementById('qrDataInput');

        scanButton.addEventListener('click', () => {
            if (html5QrCode) {
                // Stop dan clear jika sedang berjalan
                html5QrCode.stop().then(() => {
                    html5QrCode.clear();
                    html5QrCode = null;
                    scanButton.textContent = '📷 Mulai Scan';
                }).catch(err => {
                    console.error("Gagal menghentikan kamera: ", err);
                    html5QrCode.clear();
                    html5QrCode = null;
                    scanButton.textContent = '📷 Mulai Scan';
                });
                return;
            }

            // Mulai scanner
            scanButton.textContent = '❌ Hentikan Kamera';
            html5QrCode = new Html5Qrcode("reader");

            // 🔥 Ambil kamera, prioritaskan kamera belakang
            Html5Qrcode.getCameras().then(cameras => {
                if (cameras && cameras.length > 0) {
                    // Cari kamera belakang (biasanya berisi kata 'back', 'rear', 'environment')
                    const rearCamera = cameras.find(camera =>
                        camera.label.toLowerCase().includes('back') ||
                        camera.label.toLowerCase().includes('rear') ||
                        camera.label.toLowerCase().includes('environment')
                    );

                    const selectedCameraId = rearCamera ? rearCamera.id : cameras[0].id;

                    html5QrCode.start(
                        selectedCameraId, // 🔥 Gunakan kamera belakang jika ditemukan
                        {
                            fps: 10,
                            qrbox: { width: 200, height: 200 }
                        },
                        (decodedText) => {
                            // Hentikan setelah scan
                            html5QrCode.stop().then(() => {
                                html5QrCode.clear();
                                html5QrCode = null;
                                scanButton.textContent = '📷 Mulai Scan';
                            }).catch(() => {
                                // Jika gagal stop, clear saja
                                html5QrCode.clear();
                                html5QrCode = null;
                                scanButton.textContent = '📷 Mulai Scan';
                            });

                            // Proses hasil scan
                            processScannedCode(decodedText);
                        },
                        (errorMessage) => {
                            // Silent error
                        }
                    ).catch(err => {
                        console.error("Start Error: ", err);
                        resultDiv.innerHTML = `<p class="text-danger">❌ Gagal membuka kamera: ${err}</p>`;
                        scanButton.textContent = '📷 Mulai Scan';
                    });
                } else {
                    resultDiv.innerHTML = `<p class="text-danger">❌ Kamera tidak ditemukan.</p>`;
                    scanButton.textContent = '📷 Mulai Scan';
                }
            }).catch(err => {
                console.error("Camera Access Error: ", err);
                resultDiv.innerHTML = `<p class="text-danger">❌ Izin kamera ditolak atau gagal.</p>`;
                scanButton.textContent = '📷 Mulai Scan';
            });
        });

        function resetForm() {
            formIdTractor.value = '';
            formNameTractor.value = '';
            qrDataInput.value = '';
            submitBtn.disabled = true;
        }

        // 🔥 Fungsi untuk memproses hasil scan dari USB scanner
        function processScannedCode(code) {
            resultDiv.innerHTML = `<p class="text-info">Memproses scan...</p>`;

            const parts = code.split(';');
            if (parts.length < 3) {
                resultDiv.innerHTML = `<p class="text-danger">❌ Kode tidak valid (format salah).</p>`;
                resetForm();
                return;
            }

            // 🔥 Ambil data dari QR
            const sequenceNoRaw = parts[0].trim();
            const productionDate = parts[1].trim();
            const tractorName = parts[2].trim();

            // 🔥 HAPUS baris ini:
            // const paddedSequenceNo = sequenceNoRaw.padStart(5, '0');

            // 🔥 GANTI JADI:
            const sequenceNo = sequenceNoRaw;

            // Isi sementara Nama Tractor dulu
            formNameTractor.value = tractorName;

            // 🔥 Kirim ke server untuk verifikasi Plan dan Tractor
            fetch("{{ route('scan.verify') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    qr_data: code,
                    sequence_no: sequenceNo, // Kirim apa adanya
                    production_date: productionDate,
                    tractor_name: tractorName
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Gunakan nama dari QR
                    document.getElementById('Name_Tractor').value = data.qr_tractor_name;

                    // 🔥 Tampilkan sequence_no apa adanya dari response
                    document.getElementById('Sequence_No_Plan').value = data.plan.Sequence_No_Plan;
                    document.getElementById('Production_Date_Plan').value = data.plan.Production_Date_Plan;
                    document.getElementById('Model_Mower_Plan').value = data.plan.Model_Mower_Plan;
                    document.getElementById('Model_Collector_Plan').value = data.plan.Model_Collector_Plan;
                    qrDataInput.value = code;

                    submitBtn.disabled = false;
                    resultDiv.innerHTML = `<p class="text-success">✅ Data valid: ${data.qr_tractor_name}</p>`;
                } else {
                    resultDiv.innerHTML = `<p class="text-danger">❌ ${data.message}</p>`;
                    resetForm();
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                resultDiv.innerHTML = `<p class="text-danger">❌ Gagal memverifikasi.</p>`;
                resetForm();
            });
        }

        // 🔥 Event listener untuk input scanner USB
        document.getElementById('usbScannerInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                // Scanner biasanya menekan Enter setelah selesai membaca
                const scannedCode = e.target.value.trim();
                if (scannedCode) {
                    processScannedCode(scannedCode);
                }
                e.target.value = ''; // Kosongkan untuk scan berikutnya
            }
        });

        // 🔥 Tombol untuk fokus ke input scanner (jika perlu manual)
        document.getElementById('focusUsbBtn').addEventListener('click', () => {
            document.getElementById('usbScannerInput').focus();
            resultDiv.innerHTML = `<p class="text-info">Input scanner siap. Arahkan scanner ke QR Code.</p>`;
        });
    </script>
</body>

</html>