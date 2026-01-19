@extends('layouts.member')

@section('content')
    <div class="page-heading">
        <div class="page-title">
            <div class="row">
                <div class="col-12 col-md-6 order-md-1 order-last">
                    <h3>Scan Tractor</h3>
                </div>
            </div>
        </div>

        <section class="section">
            <div class="card">
                <div class="card-body">
                    <!-- QR Scanner -->
                    <div id="reader" style="max-width: 300px; margin: auto;"></div>
                    <div class="mt-3 text-center">
                        <button id="scanButton" class="btn btn-primary">Mulai Scan</button>
                    </div>
                    <div id="result" class="mt-3"></div>

                    @if (session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <strong>Berhasil!</strong> {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <strong>Gagal!</strong> {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <!-- Form Submit -->
                    <form id="scanForm" method="POST" action="{{ route('members.scan.store') }}" class="mt-4">
                        @csrf
                        <input type="hidden" name="qr_data" id="qrDataInput" required>
                        <input type="hidden" class="form-control" id="Id_Tractor" name="Id_Tractor" required>

                        <div class="mb-3">
                            <label for="Name_Tractor" class="form-label">Nama Tractor</label>
                            <input type="text" class="form-control" id="Name_Tractor" name="Name_Tractor" readonly>
                        </div>

                        <!-- ✅ INPUT NIK: SELALU TAMPIL, TEPAT DI ATAS TOMBOL -->
                        <div class="mb-3">
                            <label for="Nik_Original_Replaced" class="form-label">
                                NIK Anggota Asli (Jika Digantikan)
                            </label>
                            <input type="text" class="form-control" id="Nik_Original_Replaced"
                                name="Nik_Original_Replaced" placeholder="Contoh: 3578012345678901" maxlength="16">
                            <div class="form-text text-muted">
                                Biarkan kosong jika Anda adalah member yang seharusnya melakukan scan.
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success" id="submitBtn" disabled>
                            Submit Scan
                        </button>
                    </form>
                </div>
            </div>
        </section>
    </div>
@endsection

@section('style')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endsection

@section('script')
    <script src="{{ asset('assets/js/html5-qrcode.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            let html5QrCode = null;
            const scanButton = document.getElementById('scanButton');
            const resultDiv = document.getElementById('result');
            const formIdTractor = document.getElementById('Id_Tractor');
            const formNameTractor = document.getElementById('Name_Tractor');
            const submitBtn = document.getElementById('submitBtn');
            const qrDataInput = document.getElementById('qrDataInput');

            function resetForm() {
                if (formIdTractor) formIdTractor.value = '';
                if (formNameTractor) formNameTractor.value = '';
                if (qrDataInput) qrDataInput.value = '';
                if (submitBtn) submitBtn.disabled = true;
            }

            if (!scanButton) return; // aman jika elemen tidak ada

            scanButton.addEventListener('click', () => {
                if (html5QrCode) {
                    html5QrCode.stop().then(() => html5QrCode.clear()).finally(() => {
                        html5QrCode = null;
                        scanButton.textContent = 'Mulai Scan';
                        resultDiv.innerHTML = `<p class="text-info">Kamera dihentikan.</p>`;
                        resetForm();
                    });
                    return;
                }

                html5QrCode = new Html5Qrcode("reader");
                scanButton.textContent = 'Stop Scan';
                resultDiv.innerHTML = `<p class="text-info">Mengaktifkan kamera...</p>`;

                Html5Qrcode.getCameras().then(devices => {
                    if (devices?.length) {
                        html5QrCode.start(
                            devices[0].id, {
                                fps: 10,
                                qrbox: {
                                    width: 200,
                                    height: 200
                                }
                            },
                            (decodedText) => {
                                resultDiv.innerHTML =
                                    `<p class="text-info">Membaca: ${decodedText}</p>`;
                                html5QrCode.stop().finally(() => {
                                    html5QrCode = null;
                                    scanButton.textContent = 'Mulai Scan';
                                });

                                const parts = decodedText.split(';');
                                if (parts.length < 3) {
                                    resultDiv.innerHTML =
                                        `<p class="text-danger">❌ QR tidak valid (format salah).</p>`;
                                    resetForm();
                                    return;
                                }

                                const tractorName = parts[2].trim();
                                if (formNameTractor) formNameTractor.value = tractorName;
                                resultDiv.innerHTML =
                                    `<p class="text-info">Memverifikasi: ${tractorName}...</p>`;

                                fetch("{{ route('members.scan.verify') }}", {
                                        method: 'POST',
                                        headers: {
                                            "X-CSRF-TOKEN": document.querySelector(
                                                'meta[name="csrf-token"]').content,
                                            "Accept": "application/json",
                                            "Content-Type": "application/json"
                                        },
                                        body: JSON.stringify({
                                            name: tractorName
                                        })
                                    })
                                    .then(response => {
                                        if (!response.ok) throw new Error(
                                            'Server tidak merespons.');
                                        return response.json();
                                    })
                                    .then(data => {
                                        if (data.success && formIdTractor) {
                                            formIdTractor.value = data.tractor.Id_Tractor;
                                            qrDataInput.value = decodedText;
                                            submitBtn.disabled = false;
                                            resultDiv.innerHTML =
                                                `<p class="text-success">✅ ${data.tractor.Name_Tractor} (Hour: ${data.tractor.Hour_Tractor})</p>`;
                                        } else {
                                            resultDiv.innerHTML =
                                                `<p class="text-danger">❌ ${data.message || 'Tractor tidak valid.'}</p>`;
                                            resetForm();
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Fetch Error:', error);
                                        resultDiv.innerHTML =
                                            `<p class="text-danger">❌ Gagal verifikasi: ${error.message}</p>`;
                                        resetForm();
                                    });
                            },
                            () => {}
                        );
                    } else {
                        resultDiv.innerHTML =
                            `<p class="text-danger">❌ Tidak ada kamera yang tersedia.</p>`;
                        scanButton.textContent = 'Mulai Scan';
                    }
                }).catch(err => {
                    console.error('Camera Error:', err);
                    resultDiv.innerHTML = `<p class="text-danger">❌ Gagal mengakses kamera.</p>`;
                    scanButton.textContent = 'Mulai Scan';
                });
            });
        });
    </script>
@endsection
