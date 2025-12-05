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

                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <strong>Berhasil!</strong> {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if(session('error'))
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
                        <input type="text" class="form-control" id="Name_Tractor" name="Name_Tractor" required>
                    </div>

                    <button type="submit" class="btn btn-success" id="submitBtn" disabled>Submit Scan</button>
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
            html5QrCode.stop()
            .then(() => html5QrCode.clear())
            .catch(() => {})
            .finally(() => {
                html5QrCode = null;
                scanButton.textContent = 'Mulai Scan';
            });

            html5QrCode = null;
            scanButton.textContent = 'Mulai Scan';
            resultDiv.innerHTML = `<p class="text-info">Kamera dihentikan.</p>`;
            // Juga reset form saat kamera dihentikan
            formIdTractor.value = '';
            formNameTractor.value = '';
            qrDataInput.value = '';
            submitBtn.disabled = true;
            return;
        }

        html5QrCode = new Html5Qrcode("reader");
        scanButton.textContent = 'Stop Scan';
        resultDiv.innerHTML = `<p class="text-info">Mengaktifkan kamera...</p>`;

        Html5Qrcode.getCameras().then(devices => {
            if (devices?.length) {
                html5QrCode.start(
                    devices[0].id,
                    { fps: 10, qrbox: { width: 200, height: 200 } },
                    (decodedText) => {
                        resultDiv.innerHTML = `<p class="text-info">Membaca: ${decodedText}</p>`;
                        html5QrCode.stop()
                        .then(() => html5QrCode.clear())
                        .catch(() => {})
                        .finally(() => {
                            html5QrCode = null;
                            scanButton.textContent = 'Mulai Scan';
                        });


                        // Proses QR
                        const parts = decodedText.split(';');
                        if (parts.length < 3) {
                            resultDiv.innerHTML = `<p class="text-danger">❌ QR tidak valid (format salah).</p>`;
                            scanButton.textContent = 'Mulai Scan';
                            html5QrCode = null;
                            // Reset form jika QR tidak valid
                            formIdTractor.value = '';
                            formNameTractor.value = '';
                            qrDataInput.value = '';
                            submitBtn.disabled = true;
                            return;
                        }

                        const tractorName = parts[2].trim(); // Ambil NAMA TRAKTOR dari QR
                        formNameTractor.value = tractorName;

                        resultDiv.innerHTML = `<p class="text-info">Memverifikasi Nama Tractor: ${tractorName}...</p>`;


                        // ✅ Kirim ke server untuk verifikasi
                        fetch("{{ route('members.scan.verify') }}", {
                            method: 'POST',
                            headers: {
                                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
                                "Accept": "application/json",
                                "Content-Type": "application/json"
                            },
                            body: JSON.stringify({ name: tractorName })
                        })

                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                // Isi form
                                formIdTractor.value = data.tractor.Id_Tractor;
                                qrDataInput.value = decodedText;
                                submitBtn.disabled = false;

                                resultDiv.innerHTML =
                                `<p class="text-success">✅ Ditemukan: ${data.tractor.Name_Tractor} (Hour: ${data.tractor.Hour_Tractor})</p>`;
                            } else {
                                resultDiv.innerHTML = `<p class="text-danger">❌ ${data.message}</p>`;
                                formIdTractor.value = '';
                                formNameTractor.value = '';
                                qrDataInput.value = '';
                                submitBtn.disabled = true;
                            }
                        })
                        .catch(error => {
                            console.error('Fetch Error:', error);
                            resultDiv.innerHTML = `<p class="text-danger">❌ Gagal memverifikasi QR: ${error.message}</p>`;
                            formIdTractor.value = '';
                            formNameTractor.value = '';
                            qrDataInput.value = '';
                            submitBtn.disabled = true;
                        });

                        scanButton.textContent = 'Mulai Scan';
                        html5QrCode = null;
                    },
                    (errorMessage) => {
                        // Silent error
                    }
                );
            } else {
                resultDiv.innerHTML = `<p class="text-danger">❌ Tidak ada kamera yang tersedia.</p>`;
                scanButton.textContent = 'Mulai Scan';
            }
        }).catch(() => {
            resultDiv.innerHTML = `<p class="text-danger">❌ Gagal mengakses kamera.</p>`;
            scanButton.textContent = 'Mulai Scan';
        });
    });
</script>
@endsection