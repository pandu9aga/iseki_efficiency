@extends('layouts.leader')

@section('content')
<div class="col-sm-12">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="text-primary">Kelola Pekerjaan - {{ $area->Name_Area }}</h4>
            <span class="text-muted">Area: {{ $area->Name_Area }}</span>
        </div>
        <div class="card-body">
            @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <!-- Form Tambah Pekerjaan -->
            <div class="mb-4">
                <h5>Tambah Pekerjaan</h5>
                <form action="{{ route('leaders.jobs.store') }}" method="POST">
                    @csrf
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="Name_Job_Member" class="form-label">Nama Pekerjaan</label>
                                <input type="text"
                                    class="form-control @error('Name_Job_Member') is-invalid @enderror"
                                    name="Name_Job_Member" value="{{ old('Name_Job_Member') }}" required>
                                @error('Name_Job_Member')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Simpan Pekerjaan</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Tabel Pekerjaan -->
            <h5>Daftar Pekerjaan</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Nama Pekerjaan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($jobMembers as $job)
                        <tr>
                            <td>{{ $job->Name_Job_Member }}</td>
                            <td>
                                <button type="button" class="btn btn-sm btn-warning"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editJobModal{{ $job->Id_Job_Member }}">
                                    Edit
                                </button>
                                <form action="{{ route('leaders.jobs.destroy', $job) }}"
                                    method="POST" class="d-inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger"
                                        onclick="return confirm('Hapus pekerjaan ini?')">
                                        Hapus
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="2" class="text-center text-muted">Belum ada data pekerjaan.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Modal Edit --}}
@foreach ($jobMembers as $job)
<div class="modal fade" id="editJobModal{{ $job->Id_Job_Member }}" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('leaders.jobs.update', $job) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Pekerjaan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Pekerjaan</label>
                        <input type="text" class="form-control" name="Name_Job_Member"
                            value="{{ old('Name_Job_Member', $job->Name_Job_Member) }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Area</label>
                        <input type="text" class="form-control" value="{{ $area->Name_Area }}" readonly>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning">Perbarui</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endforeach
@endsection