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

        #reader,
        #replacementReader {
            width: 100%;
            max-width: 300px;
            margin: auto;
        }

        .alert {
            border-radius: 12px;
            border-left: 3px solid;
        }

        @media (max-width: 480px) {
            .scan-card {
                padding: 24px 16px;
            }
        }
    </style>
</head>

<body>

    <!-- ========== NAVBAR ATAS ========== -->
    <nav class="top-nav">
        <div class="nav-links">
            <a href="{{ route('area.scan') }}" class="nav-link active">Scan</a>
            <a href="{{ route('area.report') }}" class="nav-link">Report</a>
            <a href="{{ route('logout.area') }}" class="nav-link" onclick="return confirm('Yakin ingin keluar dari sesi Area?')">Logout</a>
        </div>
    </nav>

    <!-- ========== MAIN CONTENT ========== -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="row justify-content-center">
                <div class="col-12">
                    <div class="card scan-card">
                        <!-- Judul & Info Area -->
                        <div class="text-center mb-3">
                            <h4 class="text-primary mb-1">Area: {{ $areaName }}</h4>
                            <p class="text-muted small">Scan Tractor</p>
                        </div>

                        <!-- 🔥 Pilihan Pengganti -->
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="isReplacement">
                            <label class="form-check-label" for="isReplacement">Member Pengganti</label>
                        </div>

                        <!-- 🔥 Input NIK Pengganti -->
                        <div class="mb-3" id="replacementNikGroup" style="display: none;">
                            <label for="Nik_Replace_Display" class="form-label">NIK Pengganti *</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="Nik_Replace_Display"
                                    placeholder="NIK atau scan QR">
                                <button class="btn btn-outline-primary" type="button"
                                    id="triggerReplacementCameraScan">📷 Kamera</button>
                            </div>
                            <div id="replacementReader" style="display:none; margin-top:12px;"></div>
                        </div>

                        <!-- Scanner Tractor -->
                        <div id="reader"></div>
                        <div class="mt-3 text-center">
                            <button id="scanButton" class="btn btn-primary">📷 Scanner Camera</button>
                            <button type="button" class="btn btn-outline-secondary" id="focusUsbBtn">Scanner USB</button>
                        </div>
                        <div id="result" class="mt-3"></div>

                        <!-- Alerts -->
                        @if (session('success'))
                        <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                            <strong>Sukses!</strong> {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        @endif

                        @if (session('error'))
                        <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                            <strong>Error!</strong> {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        @endif

                        <!-- Form Submit -->
                        <form id="scanForm" method="POST" action="{{ route('area.scan.store') }}" class="mt-4">
                            @csrf
                            <input type="hidden" name="qr_data" id="qrDataInput" required>
                            <input type="hidden" name="Id_Area" value="{{ $areaId }}">
                            <input type="hidden" name="Nik_Replace" id="finalNikReplace" value="">

                            <input type="text" id="usbScannerInput"
                                style="opacity: 0; position: absolute; pointer-events: none;" autocomplete="off"
                                autofocus>

                            <div class="mb-1">
                                <label for="Name_Tractor" class="form-label">Nama Tractor</label>
                                <input type="text" class="form-control" id="Name_Tractor" name="Name_Tractor"
                                    readonly required>
                            </div>

                            <div class="mb-1">
                                <label for="Sequence_No_Plan" class="form-label">Nomor Urut</label>
                                <input type="text" class="form-control" id="Sequence_No_Plan"
                                    name="Sequence_No_Plan" readonly required>
                            </div>

                            <div class="mb-1">
                                <label for="Production_Date_Plan" class="form-label">Tanggal Produksi</label>
                                <input type="text" class="form-control" id="Production_Date_Plan"
                                    name="Production_Date_Plan" readonly required>
                            </div>

                            <div class="mb-1">
                                <label for="Model_Mower_Plan" class="form-label">Tipe Mower</label>
                                <input type="text" class="form-control" id="Model_Mower_Plan"
                                    name="Model_Mower_Plan" readonly required>
                            </div>

                            <div class="mb-4">
                                <label for="Model_Collector_Plan" class="form-label">Tipe Collector</label>
                                <input type="text" class="form-control" id="Model_Collector_Plan"
                                    name="Model_Collector_Plan" readonly required>
                            </div>

                            <button type="submit" class="btn btn-success w-100" id="submitBtn" disabled>Simpan Scan</button>
                        </form>
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
        // Force Html5Qrcode to run without external worker (100% offline)
        window.qrWorkerPath = null;

        let html5QrCode = null;
        let replacementHtml5QrCode = null;

        const scanButton = document.getElementById('scanButton');
        const resultDiv = document.getElementById('result');
        const submitBtn = document.getElementById('submitBtn');
        const qrDataInput = document.getElementById('qrDataInput');
        const isReplacement = document.getElementById('isReplacement');
        const replacementNikGroup = document.getElementById('replacementNikGroup');
        const nikReplaceDisplay = document.getElementById('Nik_Replace_Display');
        const finalNikReplace = document.getElementById('finalNikReplace');
        const triggerReplacementCameraScan = document.getElementById('triggerReplacementCameraScan');
        const replacementReader = document.getElementById('replacementReader');

        // === Update tombol submit berdasarkan validasi ===
        function updateSubmitButton() {
            const hasQr = !!qrDataInput.value.trim();
            const isRep = isReplacement?.checked;
            const nikRep = finalNikReplace?.value.trim();

            let valid = hasQr;
            if (valid && isRep) {
                valid = !!nikRep;
            }
            submitBtn.disabled = !valid;
        }

        // === Reset semua field ===
        function resetForm() {
            qrDataInput.value = '';
            finalNikReplace.value = '';
            nikReplaceDisplay.value = '';
            if (isReplacement) isReplacement.checked = false;
            replacementNikGroup.style.display = 'none';
            document.getElementById('Name_Tractor').value = '';
            document.getElementById('Sequence_No_Plan').value = '';
            document.getElementById('Production_Date_Plan').value = '';
            document.getElementById('Model_Mower_Plan').value = '';
            document.getElementById('Model_Collector_Plan').value = '';
            updateSubmitButton();
        }

        // === Toggle input NIK pengganti ===
        isReplacement?.addEventListener('change', function() {
            if (this.checked) {
                replacementNikGroup.style.display = 'block';
            } else {
                replacementNikGroup.style.display = 'none';
                nikReplaceDisplay.value = '';
                finalNikReplace.value = '';
                updateSubmitButton();
            }
        });

        // === Binding input manual NIK ===
        nikReplaceDisplay?.addEventListener('input', function() {
            finalNikReplace.value = this.value.trim();
            updateSubmitButton();
        });

        // === Scanner Tractor (QR utama) ===
        scanButton?.addEventListener('click', () => {
            if (html5QrCode) {
                html5QrCode.stop().then(() => {
                    html5QrCode.clear();
                    html5QrCode = null;
                    scanButton.textContent = '📷 Scanner Camera';
                }).catch(() => {
                    html5QrCode = null;
                    scanButton.textContent = '📷 Scanner Camera';
                });
                return;
            }

            scanButton.textContent = '❌ Hentikan Kamera';
            html5QrCode = new Html5Qrcode("reader", {
                fps: 10,
                qrbox: {
                    width: 200,
                    height: 200
                },
                experimentalFeatures: {
                    useBarCodeDetectorIfSupported: false
                },
                formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE]
            });

            Html5Qrcode.getCameras().then(cameras => {
                if (cameras?.length) {
                    const rearCamera = cameras.find(c => /back|rear|environment/i.test(c.label));
                    const camId = rearCamera ? rearCamera.id : cameras[0].id;

                    html5QrCode.start(camId, {}, (decodedText) => {
                        html5QrCode.stop().then(() => html5QrCode.clear()).catch(() => {});
                        html5QrCode = null;
                        scanButton.textContent = '📷 Scanner Camera';
                        processScannedCode(decodedText);
                    }, () => {});
                } else {
                    resultDiv.innerHTML = `<p class="text-danger">❌ Kamera tidak tersedia.</p>`;
                    scanButton.textContent = '📷 Scanner Camera';
                }
            }).catch(() => {
                resultDiv.innerHTML = `<p class="text-danger">❌ Gagal mengakses kamera.</p>`;
                scanButton.textContent = '📷 Scanner Camera';
            });
        });

        // === SCANNER KAMERA UNTUK NIK PENGGANTI ===
        triggerReplacementCameraScan?.addEventListener('click', () => {
            if (replacementHtml5QrCode) {
                replacementHtml5QrCode.stop().then(() => {
                    replacementHtml5QrCode.clear();
                    replacementHtml5QrCode = null;
                    triggerReplacementCameraScan.textContent = '📷 Kamera';
                    replacementReader.style.display = 'none';
                }).catch(() => {
                    replacementHtml5QrCode = null;
                    triggerReplacementCameraScan.textContent = '📷 Kamera';
                    replacementReader.style.display = 'none';
                });
                return;
            }

            triggerReplacementCameraScan.textContent = '❌ Hentikan';
            replacementReader.style.display = 'block';
            replacementHtml5QrCode = new Html5Qrcode("replacementReader", {
                fps: 10,
                qrbox: {
                    width: 200,
                    height: 200
                },
                experimentalFeatures: {
                    useBarCodeDetectorIfSupported: false
                },
                formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE]
            });

            Html5Qrcode.getCameras().then(cameras => {
                if (cameras?.length) {
                    const rearCamera = cameras.find(c => /back|rear|environment/i.test(c.label));
                    const camId = rearCamera ? rearCamera.id : cameras[0].id;

                    replacementHtml5QrCode.start(camId, {}, (decodedText) => {
                        replacementHtml5QrCode.stop().then(() => replacementHtml5QrCode.clear()).catch(() => {});
                        replacementHtml5QrCode = null;
                        triggerReplacementCameraScan.textContent = '📷 Kamera';
                        replacementReader.style.display = 'none';

                        nikReplaceDisplay.value = decodedText.trim();
                        finalNikReplace.value = decodedText.trim();
                        updateSubmitButton();
                    }, () => {
                        // Callback error (opsional)
                    });
                } else {
                    alert('Kamera tidak tersedia.');
                    triggerReplacementCameraScan.textContent = '📷 Kamera';
                    replacementReader.style.display = 'none';
                }
            }).catch(() => {
                alert('Gagal mengakses kamera.');
                triggerReplacementCameraScan.textContent = '📷 Kamera';
                replacementReader.style.display = 'none';
            });
        });

        // === Proses hasil scan QR Tractor ===
        function processScannedCode(code) {
            resultDiv.innerHTML = `<p class="text-info">Memproses scan...</p>`;
            const parts = code.split(';');
            if (parts.length < 3) {
                resultDiv.innerHTML = `<p class="text-danger">❌ Format QR tidak valid.</p>`;
                resetForm();
                return;
            }

            const [sequenceNo, productionDate, tractorName] = parts;

            fetch("{{ route('area.scan.verify') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        qr_data: code,
                        sequence_no: sequenceNo.trim(),
                        production_date: productionDate.trim(),
                        tractor_name: tractorName.trim()
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('Name_Tractor').value = data.qr_tractor_name;
                        document.getElementById('Sequence_No_Plan').value = data.plan.Sequence_No_Plan;
                        document.getElementById('Production_Date_Plan').value = data.plan.Production_Date_Plan;
                        document.getElementById('Model_Mower_Plan').value = data.plan.Model_Mower_Plan;
                        document.getElementById('Model_Collector_Plan').value = data.plan.Model_Collector_Plan;
                        qrDataInput.value = code;
                        updateSubmitButton();
                        resultDiv.innerHTML = `<p class="text-success">✅ Data valid: ${data.qr_tractor_name}</p>`;
                    } else {
                        resultDiv.innerHTML = `<p class="text-danger">❌ ${data.message}</p>`;
                        resetForm();
                    }
                })
                .catch(() => {
                    resultDiv.innerHTML = `<p class="text-danger">❌ Gagal verifikasi data.</p>`;
                    resetForm();
                });
        }

        // === USB Scanner input ===
        document.getElementById('usbScannerInput')?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                const code = e.target.value.trim();
                if (code) processScannedCode(code);
                e.target.value = '';
            }
        });

        document.getElementById('focusUsbBtn')?.addEventListener('click', () => {
            document.getElementById('usbScannerInput').focus();
            resultDiv.innerHTML = `<p class="text-info">Input scanner siap.</p>`;
        });

        // === Reset form jika ada pesan sukses ===
        @if(session('success'))
        resetForm();
        @endif

        // Inisialisasi status tombol
        updateSubmitButton();
    </script>
</body>

</html>