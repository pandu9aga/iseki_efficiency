<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Scan Traktor Perbantuan - Iseki Efficiency</title>
    <link rel="shortcut icon" href="{{ asset('assets/images/icon.png') }}" type="image/x-icon">

    <link rel="stylesheet" href="{{ asset('assets/css/custom-fonts.css') }}">
    <script src="{{ asset('assets/js/jquery.min.js') }}"></script>
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
            padding: 15px;
            background-color: #fff5f9;
            min-height: 100vh;
        }

        .card {
            background: white;
            border-radius: 20px;
            padding: 24px 20px;
            max-width: 480px;
            width: 100%;
            box-shadow: 0 8px 24px rgba(189, 2, 55, 0.12);
            border: 1px solid #ffe6ee;
        }

        .title {
            text-align: center;
            font-weight: 700;
            font-size: 24px;
            color: #d81b60;
            margin-bottom: 10px;
        }

        .subtitle {
            text-align: center;
            font-size: 16px;
            color: #6b5a65;
            margin-bottom: 20px;
            font-weight: 500;
        }

        #reader {
            width: 100%;
            border-radius: 12px;
            overflow: hidden;
            border: 3px solid #f7b5ca;
            box-shadow: 0 4px 12px rgba(189, 2, 55, 0.1);
            margin-bottom: 20px;
        }

        .alert {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 16px;
            font-weight: 600;
            display: none;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        .alert-danger {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }

        .list-group {
            list-style: none;
            padding: 0;
            margin-bottom: 24px;
        }

        .list-group-item {
            background: #fdf9fc;
            border: 1px solid #f0e0e8;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .list-group-item .badge {
            background: #d81b60;
            color: white;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
        }

        .btn-finish {
            width: 100%;
            padding: 18px;
            background: #e53935;
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.25s ease;
        }

        .btn-finish:hover {
            background: #d32f2f;
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(229, 57, 53, 0.3);
        }

        .info-box {
            background: #fff0f5;
            padding: 12px;
            border-radius: 10px;
            border: 1px solid #ffd8e8;
            margin-bottom: 20px;
            font-size: 14px;
            color: #880e4f;
        }

        .input-mode-toggle {
            display: flex;
            gap: 0;
            margin-bottom: 16px;
            border-radius: 12px;
            overflow: hidden;
            border: 2px solid #f0e0e8;
        }

        .input-mode-toggle .toggle-btn {
            flex: 1;
            padding: 14px 12px;
            border: none;
            background: #fdf9fc;
            color: #6b5a65;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .input-mode-toggle .toggle-btn.active {
            background: #f7b5ca;
            color: white;
        }

        .input-mode-toggle .toggle-btn:not(.active):hover {
            background: #ffe6ee;
        }

        .manual-input-container {
            margin-bottom: 16px;
        }

        .manual-input-container .form-control {
            width: 100%;
            padding: 16px;
            border: 2px solid #f0e0e8;
            border-radius: 12px;
            font-size: 16px;
            background: #fdf9fc;
            transition: border-color 0.25s, background 0.25s;
        }

        .manual-input-container .form-control:focus {
            outline: none;
            border-color: #f7b5ca;
            background: white;
            box-shadow: 0 0 0 3px rgba(189, 2, 55, 0.1);
        }

        .manual-input-container .format-hint {
            font-size: 13px;
            color: #880e4f;
            background: #fff0f5;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #ffd8e8;
            margin-bottom: 10px;
            line-height: 1.5;
        }

        .btn-manual-submit {
            width: 100%;
            padding: 16px;
            background: #d81b60;
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.25s ease;
            margin-bottom: 20px;
        }

        .btn-manual-submit:hover {
            background: #c2185b;
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(216, 27, 96, 0.3);
        }

        #scanner-container {
            display: none;
        }
    </style>
</head>

<body>

    <div class="card">
        <h2 class="title">Tractor Scanner</h2>
        <div class="subtitle">Input data traktor</div>

        <div class="info-box">
            <strong>PIC Dibantu:</strong> {{ $dailyJob->member->nama ?? 'Tidak diketahui' }}<br>
            <strong>NIK Perbantuan:</strong> {{ session('assistance_nik') }}
        </div>

        <div id="alert-success" class="alert alert-success"></div>
        <div id="alert-error" class="alert alert-danger"></div>

        <div class="input-mode-toggle">
            <button type="button" class="toggle-btn active" id="btn-mode-manual" onclick="switchMode('manual')">
                Ketik Manual
            </button>
            <button type="button" class="toggle-btn" id="btn-mode-scan" onclick="switchMode('scan')">
                Scan QR
            </button>
        </div>

        <div id="manual-container" class="manual-input-container">
            <div class="format-hint">
                <strong>Format:</strong> No.Urut;Tanggal;NamaTraktor<br>
                <small>Contoh: <code>101;20260603;TR-001</code> &mdash; atau scan pakai alat scanner barcode</small>
            </div>
            <input type="text" id="manual-input" class="form-control" placeholder="Ketik data QR manual atau scan dgn alat scanner..." autocomplete="off" autofocus>
            <button type="button" class="btn-manual-submit" onclick="submitManual()">Kirim</button>
        </div>

        <div id="scanner-container">
            <div id="reader"></div>
        </div>

        <h3 style="font-size: 18px; color: #d81b60; margin-bottom: 12px;">Traktor Berhasil di-Scan:</h3>
        <ul class="list-group" id="scanned-list">
        </ul>

        <a href="{{ route('assistances.finish') }}" class="btn-finish" style="display: block; text-align: center; text-decoration: none; box-sizing: border-box;">Selesai Scan</a>
    </div>

    <script>
        $(document).ready(function() {
            let isProcessing = false;
            let lastScanned = "";
            let scanCount = 0;

            let html5QrcodeScanner = null;

            function initScanner() {
                if (html5QrcodeScanner) {
                    try { html5QrcodeScanner.clear(); } catch(e) {}
                }
                $('#reader').html('');
                html5QrcodeScanner = new Html5QrcodeScanner(
                    "reader", {
                        fps: 10,
                        qrbox: { width: 250, height: 250 },
                        videoConstraints: { facingMode: "environment" }
                    },
                    false
                );
                html5QrcodeScanner.render(onScanSuccess, onScanFailure);
            }

            function stopScanner() {
                if (html5QrcodeScanner) {
                    try { html5QrcodeScanner.clear(); } catch(e) {}
                    html5QrcodeScanner = null;
                }
            }

            function onScanSuccess(decodedText, decodedResult) {
                if (isProcessing) return;
                if (decodedText === lastScanned) return;

                isProcessing = true;
                lastScanned = decodedText;

                if ('speechSynthesis' in window) {
                    var msg = new SpeechSynthesisUtterance("Beep");
                    window.speechSynthesis.speak(msg);
                }

                $('#alert-success').hide();
                $('#alert-error').hide();

                $.ajax({
                    url: "{{ route('assistances.storeScan') }}",
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        tractor_name: decodedText
                    },
                    success: function(res) {
                        if (res.success) {
                            scanCount++;
                            $('#alert-success').text(res.message).show();
                            $('#scanned-list').prepend(
                                `<li class="list-group-item">
                                    <span><strong>${scanCount}.</strong> &nbsp; [${res.sequence_no}] ${res.tractor_name}</span>
                                    <span class="badge">Tersimpan</span>
                                </li>`
                            );
                            setTimeout(() => {
                                $('#alert-success').fadeOut();
                            }, 2000);
                        } else {
                            $('#alert-error').text(res.message).show();
                            setTimeout(() => {
                                $('#alert-error').fadeOut();
                            }, 3000);
                        }
                        setTimeout(() => {
                            isProcessing = false;
                            lastScanned = "";
                        }, 1500);
                    },
                    error: function(xhr) {
                        let msg = "Gagal memproses data.";
                        if (xhr.status === 401) {
                            window.location.href = "{{ route('assistances.start') }}";
                        }
                        $('#alert-error').text(msg).show();
                        setTimeout(() => {
                            isProcessing = false;
                            lastScanned = "";
                            $('#alert-error').fadeOut();
                        }, 3000);
                    }
                });
            }

            function onScanFailure(error) {
            }

            $('#manual-input').on('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    submitManual();
                }
            });

            window.submitManual = function() {
                let val = $('#manual-input').val().trim();
                if (!val) {
                    $('#alert-error').text('Silakan isi data traktor terlebih dahulu.').show();
                    setTimeout(() => { $('#alert-error').fadeOut(); }, 3000);
                    return;
                }
                if (isProcessing) return;
                isProcessing = true;

                $('#alert-success').hide();
                $('#alert-error').hide();

                $.ajax({
                    url: "{{ route('assistances.storeScan') }}",
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        tractor_name: val
                    },
                    success: function(res) {
                        if (res.success) {
                            scanCount++;
                            $('#alert-success').text(res.message).show();
                            $('#scanned-list').prepend(
                                `<li class="list-group-item">
                                    <span><strong>${scanCount}.</strong> &nbsp; [${res.sequence_no}] ${res.tractor_name}</span>
                                    <span class="badge">Tersimpan</span>
                                </li>`
                            );
                            $('#manual-input').val('');
                            $('#manual-input').focus();
                            setTimeout(() => {
                                $('#alert-success').fadeOut();
                            }, 2000);
                        } else {
                            $('#alert-error').text(res.message).show();
                            setTimeout(() => {
                                $('#alert-error').fadeOut();
                            }, 3000);
                        }
                        setTimeout(() => {
                            isProcessing = false;
                        }, 1500);
                    },
                    error: function(xhr) {
                        let msg = "Gagal memproses data.";
                        if (xhr.status === 401) {
                            window.location.href = "{{ route('assistances.start') }}";
                        }
                        $('#alert-error').text(msg).show();
                        setTimeout(() => {
                            isProcessing = false;
                            $('#alert-error').fadeOut();
                        }, 3000);
                    }
                });
            };

            window.switchMode = function(mode) {
                if (mode === 'manual') {
                    $('#btn-mode-manual').addClass('active');
                    $('#btn-mode-scan').removeClass('active');
                    $('#manual-container').show();
                    $('#scanner-container').hide();
                    stopScanner();
                    setTimeout(() => { $('#manual-input').focus(); }, 100);
                } else {
                    $('#btn-mode-scan').addClass('active');
                    $('#btn-mode-manual').removeClass('active');
                    $('#manual-container').hide();
                    $('#scanner-container').show();
                    initScanner();
                }
            };
        });
    </script>
</body>

</html>
