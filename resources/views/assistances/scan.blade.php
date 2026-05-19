<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Scan Traktor Perbantuan - Iseki Efficiency</title>
    <link rel="shortcut icon" href="{{ asset('assets/images/icon.png') }}" type="image/x-icon">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
    </style>
</head>

<body>

    <div class="card">
        <h2 class="title">Tractor Scanner</h2>
        <div class="subtitle">Silakan scan barcode traktor</div>

        <div class="info-box">
            <strong>PIC Dibantu:</strong> {{ $dailyJob->member->nama ?? 'Tidak diketahui' }}<br>
            <strong>NIK Perbantuan:</strong> {{ session('assistance_nik') }}
        </div>

        <div id="alert-success" class="alert alert-success"></div>
        <div id="alert-error" class="alert alert-danger"></div>

        <div id="reader"></div>

        <h3 style="font-size: 18px; color: #d81b60; margin-bottom: 12px;">Traktor Berhasil di-Scan:</h3>
        <ul class="list-group" id="scanned-list">
            <!-- Item akan ditambahkan lewat JS -->
        </ul>

        <a href="{{ route('assistances.inputDuration') }}" class="btn-finish" style="display: block; text-align: center; text-decoration: none; box-sizing: border-box;">Selesai Scan</a>
    </div>

    <script>
        $(document).ready(function() {
            let isProcessing = false;
            let lastScanned = "";
            let scanCount = 0;

            function onScanSuccess(decodedText, decodedResult) {
                if (isProcessing) return;

                // Cegah scan ganda cepat untuk kode yang sama
                if (decodedText === lastScanned) {
                    return;
                }

                isProcessing = true;
                lastScanned = decodedText;

                // Bunyikan beep jika browser mendukung
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
                                    <span><strong>${scanCount}.</strong> &nbsp; [${res.sequence_no}] 🚜 ${res.tractor_name}</span>
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
                // Jangan lakukan apa-apa, scanner akan terus mencoba
            }

            let html5QrcodeScanner = new Html5QrcodeScanner(
                "reader", {
                    fps: 10,
                    qrbox: {
                        width: 250,
                        height: 250
                    },
                    videoConstraints: {
                        facingMode: "environment"
                    }
                },
                /* verbose= */
                false);
            html5QrcodeScanner.render(onScanSuccess, onScanFailure);
        });
    </script>
</body>

</html>