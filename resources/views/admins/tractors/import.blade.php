@extends('layouts.admin')
@section('content')
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Import Data Tractor</h5>
            </div>
            <div class="card-body">
                <p><strong>Format file:</strong></p>
                <ul>
                    <li>Ekstensi: <code>.xlsx</code>, <code>.xls</code>, atau <code>.csv</code></li>
                    <li>Semua baris termasuk header akan diimpor</li>
                    <li>Kolom urutan:
                        <ol>
                            <li><code>Nama Tractor</code> - nama tractor</li>
                            <li><code>Group</code> - group tractor</li>
                            <li><code>Jam</code> - jam operasional</li>
                            <li><code>Area</code> - nama area (harus sesuai dengan data di tabel areas, tidak case sensitive)</li>
                        </ol>
                    </li>
                </ul>

                <div class="alert alert-info">
                    <strong>Catatan:</strong> 
                    <ul class="mb-0 mt-2">
                        <li>Kolom Area harus menggunakan nama area yang sudah ada di sistem (misal: "MOWER", "TRANSMISI", dll). Jika area tidak ditemukan, baris tersebut akan diabaikan.</li>
                        <li>Jika nama tractor sama dengan area yang sama, data akan diupdate.</li>
                        <li>Jika nama tractor sama tapi area berbeda, akan ditambahkan sebagai data baru.</li>
                    </ul>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger">{{ $errors->first('error') }}</div>
                @endif

                <form method="POST" enctype="multipart/form-data" action="{{ route('admins.tractors.import') }}">
                    @csrf
                    <div class="mb-3">
                        <label>File Excel/CSV</label>
                        <input type="file" name="file" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-success">Import</button>
                    <a href="{{ route('admins.tractors.index') }}" class="btn btn-secondary">Batal</a>
                </form>
            </div>
        </div>
    </div>
@endsection