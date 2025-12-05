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
                    <li>Baris pertama: header (tidak diimpor)</li>
                    <li>Kolom urutan: <code>Nama Tractor</code>, <code>Group</code>, <code>Jam</code></li>
                </ul>

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
