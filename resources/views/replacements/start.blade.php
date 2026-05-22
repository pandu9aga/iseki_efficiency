<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mulai Pergantian - Iseki Efficiency</title>
    <link rel="shortcut icon" href="{{ asset('assets/images/icon.png') }}" type="image/x-icon">
    <link rel="stylesheet" href="{{ asset('assets/css/custom-fonts.css') }}">
    <script src="{{ asset('assets/js/jquery.min.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('assets/css/select2.min.css') }}">
    <script src="{{ asset('assets/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/js/html5-qrcode.min.js') }}"></script>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',sans-serif}
        body{display:flex;flex-direction:column;align-items:center;padding:20px;background-color:#fff5f9;min-height:100vh}
        .card{background:white;border-radius:20px;padding:32px 24px;max-width:480px;width:100%;box-shadow:0 8px 24px rgba(189,2,55,0.12);border:1px solid #ffe6ee;margin-top:20px}
        .title{text-align:center;font-weight:700;font-size:26px;color:#f7b5ca;margin-bottom:24px}
        .form-group{margin-bottom:24px}
        .form-group label{display:block;font-size:16px;color:#6b5a65;margin-bottom:8px;font-weight:600}
        .form-control{width:100%;padding:16px;border:2px solid #f0e0e8;border-radius:12px;font-size:18px;background:#fdf9fc;transition:border-color .25s,background .25s}
        .form-control:focus{outline:none;border-color:#f7b5ca;background:white;box-shadow:0 0 0 3px rgba(189,2,55,0.1)}
        .btn-submit{width:100%;padding:18px;background:#f7b5ca;color:white;border:none;border-radius:12px;font-weight:700;font-size:18px;cursor:pointer;letter-spacing:.5px;transition:all .25s ease;margin-top:10px}
        .btn-submit:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(189,2,55,0.3)}
        .btn-back{display:block;text-align:center;margin-top:20px;color:#d81b60;font-weight:600;text-decoration:none;font-size:16px}
        .select2-container .select2-selection--single{height:56px;border:2px solid #f0e0e8;border-radius:12px;background:#fdf9fc;display:flex;align-items:center;padding:0 8px}
        .select2-container--default .select2-selection--single .select2-selection__rendered{font-size:18px;color:#333;line-height:normal}
        .select2-container--default .select2-selection--single .select2-selection__arrow{height:52px;right:10px}
        .select2-dropdown{border:2px solid #f7b5ca;border-radius:12px;overflow:hidden}
        .select2-results__option{padding:12px 16px;font-size:16px}
        .select2-container--default .select2-results__option--highlighted[aria-selected]{background-color:#f7b5ca}
        .alert{padding:16px;border-radius:12px;margin-bottom:24px;text-align:center;font-size:16px;font-weight:500}
        .alert-danger{background:#ffebee;color:#c62828;border:1px solid #ffcdd2}
        #loading{display:none;text-align:center;margin-top:10px;color:#f7b5ca;font-weight:bold}
        .input-mode-toggle{display:flex;gap:0;margin-bottom:16px;border-radius:12px;overflow:hidden;border:2px solid #f0e0e8}
        .input-mode-toggle .toggle-btn{flex:1;padding:14px 12px;border:none;background:#fdf9fc;color:#6b5a65;font-weight:600;font-size:15px;cursor:pointer;transition:all .25s ease;display:flex;align-items:center;justify-content:center;gap:8px}
        .input-mode-toggle .toggle-btn.active{background:#f7b5ca;color:white}
        .input-mode-toggle .toggle-btn:not(.active):hover{background:#ffe6ee}
        #nik-scanner-container{display:none;margin-bottom:16px}
        #nik-reader{width:100%;border-radius:12px;overflow:hidden;border:3px solid #f7b5ca;box-shadow:0 4px 12px rgba(189,2,55,0.1)}
        .scan-instruction{text-align:center;font-size:14px;color:#880e4f;background:#fff0f5;padding:10px;border-radius:10px;border:1px solid #ffd8e8;margin-bottom:12px}
        .nik-scanned-display{display:none;background:#e8f5e9;border:1px solid #c8e6c9;border-radius:12px;padding:14px 16px;margin-bottom:12px;text-align:center}
        .nik-scanned-display .nik-value{font-size:20px;font-weight:700;color:#2e7d32}
        .nik-scanned-display .nik-label{font-size:13px;color:#388e3c;margin-bottom:4px}
        .btn-rescan{display:inline-block;margin-top:8px;padding:6px 16px;background:#d81b60;color:white;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s}
        .btn-rescan:hover{background:#c2185b}
    </style>
</head>
<body>
    <div class="card">
        <h2 class="title">🔄 Mulai Pergantian</h2>

        @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div id="error-message" class="alert alert-danger" style="display:none;"></div>

        <form method="POST" action="{{ route('replacements.storeStart') }}" id="startForm">
            @csrf
            <div class="form-group">
                <label>Scan / Ketik NIK Pengganti</label>

                <div class="input-mode-toggle">
                    <button type="button" class="toggle-btn active" id="btn-mode-manual" onclick="switchMode('manual')">
                        <span>⌨️</span> Ketik Manual
                    </button>
                    <button type="button" class="toggle-btn" id="btn-mode-scan" onclick="switchMode('scan')">
                        <span>📷</span> Scan Barcode
                    </button>
                </div>

                <div id="manual-input-container">
                    <input type="number" name="nik" id="nik" class="form-control" placeholder="Input NIK" autocomplete="off">
                </div>

                <div id="nik-scanner-container">
                    <div class="scan-instruction">📸 Arahkan kamera ke barcode NIK karyawan</div>
                    <div id="nik-reader"></div>
                </div>

                <div class="nik-scanned-display" id="nik-scanned-display">
                    <div class="nik-label">NIK Terdeteksi:</div>
                    <div class="nik-value" id="nik-scanned-value"></div>
                    <button type="button" class="btn-rescan" onclick="rescanNik()">🔄 Scan Ulang</button>
                </div>

                <input type="hidden" name="nik_scanned" id="nik_scanned" value="">
                <div id="loading">Memeriksa NIK...</div>
                <div id="nik-success" style="display:none; color:#388e3c; font-weight:bold; margin-top:8px;">✅ NIK Valid: <span id="nama-pengganti"></span></div>
            </div>

            <div class="form-group">
                <label>Pilih PIC yang Digantikan (Hari Ini)</label>
                <select name="id_daily_job" id="id_daily_job" class="form-control select2" required disabled>
                    <option value="">Pilih PIC...</option>
                    @foreach($dailyJobs as $job)
                    @if($job->member)
                    <option value="{{ $job->Id_Daily_Job }}">
                        {{ $job->member->nik }} - {{ $job->member->nama }}
                    </option>
                    @endif
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-submit" id="btn-submit" disabled>Lanjut Scan Traktor</button>
        </form>
        <a href="{{ route('login.form') }}" class="btn-back">⬅ Kembali ke Login</a>
    </div>

    <script>
        let nikScanner = null;
        let currentMode = 'manual';

        $(document).ready(function() {
            $('.select2').select2({ placeholder: "Cari Nama atau NIK...", width: '100%' });

            let debounceTimer;
            $('#nik').on('input', function() {
                clearTimeout(debounceTimer);
                let nik = $(this).val();
                $('#nik-success').hide();
                $('#error-message').hide();
                $('#id_daily_job').prop('disabled', true);
                $('#btn-submit').prop('disabled', true);
                if (nik.length >= 3) {
                    $('#loading').show();
                    debounceTimer = setTimeout(function() { verifyNik(nik); }, 500);
                } else {
                    $('#loading').hide();
                }
            });

            $('#startForm').on('submit', function(e) {
                let nikVal = currentMode === 'manual' ? $('#nik').val() : $('#nik_scanned').val();
                if (!nikVal || nikVal.length < 3) {
                    e.preventDefault();
                    $('#error-message').text('NIK belum diisi atau tidak valid.').show();
                    return false;
                }
                if (currentMode === 'scan') {
                    $('#nik').val($('#nik_scanned').val());
                }
            });
        });

        function switchMode(mode) {
            currentMode = mode;
            $('#nik-success').hide();
            $('#error-message').hide();
            $('#id_daily_job').prop('disabled', true);
            $('#btn-submit').prop('disabled', true);
            $('#nik-scanned-display').hide();
            $('#loading').hide();
            if (mode === 'manual') {
                $('#btn-mode-manual').addClass('active');
                $('#btn-mode-scan').removeClass('active');
                $('#manual-input-container').show();
                $('#nik-scanner-container').hide();
                stopNikScanner();
            } else {
                $('#btn-mode-scan').addClass('active');
                $('#btn-mode-manual').removeClass('active');
                $('#manual-input-container').hide();
                $('#nik-scanner-container').show();
                $('#nik').val('');
                startNikScanner();
            }
        }

        function startNikScanner() {
            if (nikScanner) { try { nikScanner.clear(); } catch(e){} nikScanner = null; }
            $('#nik-reader').html('');
            nikScanner = new Html5QrcodeScanner("nik-reader",
                { fps: 10, qrbox: {width:250, height:120}, rememberLastUsedCamera: true, videoConstraints: { facingMode: "environment" } },
                false
            );
            nikScanner.render(onNikScanSuccess, function(){});
        }

        function stopNikScanner() {
            if (nikScanner) { try { nikScanner.clear(); } catch(e){} nikScanner = null; }
        }

        function onNikScanSuccess(decodedText) {
            let scannedNik = decodedText.replace(/\D/g, '');
            if (scannedNik.length < 3) {
                $('#error-message').text('Barcode tidak mengandung NIK yang valid.').show();
                setTimeout(()=>{ $('#error-message').fadeOut(); }, 3000);
                return;
            }
            stopNikScanner();
            $('#nik-scanner-container').hide();
            $('#nik-scanned-display').show();
            $('#nik-scanned-value').text(scannedNik);
            $('#nik_scanned').val(scannedNik);
            if ('speechSynthesis' in window) {
                window.speechSynthesis.speak(new SpeechSynthesisUtterance("Beep"));
            }
            $('#loading').show();
            verifyNik(scannedNik);
        }

        function rescanNik() {
            $('#nik-scanned-display').hide();
            $('#nik_scanned').val('');
            $('#nik-success').hide();
            $('#error-message').hide();
            $('#id_daily_job').prop('disabled', true);
            $('#btn-submit').prop('disabled', true);
            $('#loading').hide();
            $('#nik-scanner-container').show();
            startNikScanner();
        }

        function verifyNik(nik) {
            $.ajax({
                url: "{{ route('replacements.verifyNik') }}",
                type: "POST",
                data: { _token: "{{ csrf_token() }}", nik: nik },
                success: function(res) {
                    $('#loading').hide();
                    if (res.success) {
                        $('#nik-success').show();
                        $('#nama-pengganti').text(res.member.nama);
                        $('#id_daily_job').prop('disabled', false);
                        $('#btn-submit').prop('disabled', false);
                    } else {
                        $('#error-message').text(res.message).show();
                    }
                },
                error: function() {
                    $('#loading').hide();
                    $('#error-message').text("Terjadi kesalahan sistem saat cek NIK.").show();
                }
            });
        }
    </script>
</body>
</html>