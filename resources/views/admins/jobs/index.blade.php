@extends('layouts.admin')

@section('content')
    <div class="col-sm-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="text-primary">Kelola Pekerjaan per Area (Admin)</h4>
                <span class="text-muted">Atur pekerjaan untuk setiap area</span>
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

                <p class="text-muted mb-4">
                    Pilih area di bawah untuk mengelola pekerjaan yang tersedia di area tersebut.
                </p>

                <!-- Nav Tabs -->
                <ul class="nav nav-tabs" id="areaTabs" role="tablist">
                    @foreach ($areas as $index => $area)
                        <li class="nav-item" role="presentation">
                            <button
                                class="nav-link {{ $activeAreaId && $activeAreaId == $area->Id_Area ? 'active' : ($index === 0 && !$activeAreaId ? 'active' : '') }}"
                                id="area-{{ $area->Id_Area }}-tab" data-bs-toggle="tab"
                                data-bs-target="#area-{{ $area->Id_Area }}" type="button" role="tab"
                                aria-controls="area-{{ $area->Id_Area }}"
                                aria-selected="{{ $activeAreaId && $activeAreaId == $area->Id_Area ? 'true' : ($index === 0 && !$activeAreaId ? 'true' : 'false') }}">
                                {{ $area->Name_Area }}
                            </button>
                        </li>
                    @endforeach
                </ul>

                <!-- Tab Content -->
                <div class="tab-content mt-3" id="areaTabContent">
                    @foreach ($areas as $index => $area)
                        <div class="tab-pane fade {{ $activeAreaId && $activeAreaId == $area->Id_Area ? 'show active' : ($index === 0 && !$activeAreaId ? 'show active' : '') }}"
                            id="area-{{ $area->Id_Area }}" role="tabpanel" aria-labelledby="area-{{ $area->Id_Area }}-tab">
                            <div class="card border-0">
                                <div class="card-body p-3">
                                    <!-- Form Tambah Pekerjaan untuk Area Ini -->
                                    <div class="mb-4">
                                        <h5>Tambah Pekerjaan untuk Area: {{ $area->Name_Area }}</h5>
                                        <form action="{{ route('admins.jobs.store') }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="Id_Area" value="{{ $area->Id_Area }}">
                                            <div class="row">
                                                <div class="col-md-8">
                                                    <div class="mb-3">
                                                        <label for="Name_Job_Member_{{ $area->Id_Area }}"
                                                            class="form-label">Nama Pekerjaan</label>
                                                        <input type="text"
                                                            class="form-control @error('Name_Job_Member') is-invalid @enderror"
                                                            id="Name_Job_Member_{{ $area->Id_Area }}"
                                                            name="Name_Job_Member" value="{{ old('Name_Job_Member') }}"
                                                            required>
                                                        @error('Name_Job_Member')
                                                            <div class="invalid-feedback">{{ $message }}</div>
                                                        @enderror
                                                    </div>
                                                </div>
                                                <div class="col-md-4 d-flex align-items-end">
                                                    <button type="submit" class="btn btn-primary w-100">Simpan
                                                        Pekerjaan</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- Tabel Pekerjaan untuk Area Ini -->
                                    <h5>Daftar Pekerjaan di Area: {{ $area->Name_Area }}</h5>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Nama Pekerjaan</th>
                                                    <th>Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @php
                                                    $areaJobs = $jobMembers->where('Id_Area', $area->Id_Area);
                                                @endphp
                                                @forelse($areaJobs as $index => $job)
                                                    <tr>
                                                        <td>{{ $job->Name_Job_Member }}</td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-warning"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#editJobModal{{ $job->Id_Job_Member }}">
                                                                Edit
                                                            </button>
                                                            <form action="{{ route('admins.jobs.destroy', $job) }}"
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
                                                        <td colspan="3" class="text-center text-muted">Belum ada data
                                                            pekerjaan untuk area ini.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Modal: Edit (tetap di luar loop tabs karena bisa muncul di mana saja) --}}
    @foreach ($jobMembers as $job)
        <div class="modal fade" id="editJobModal{{ $job->Id_Job_Member }}" tabindex="-1"
            aria-labelledby="editJobModalLabel{{ $job->Id_Job_Member }}" aria-hidden="true">
            <div class="modal-dialog">
                <form action="{{ route('admins.jobs.update', $job) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editJobModalLabel{{ $job->Id_Job_Member }}">Edit Pekerjaan</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="edit_Name_Job_Member_{{ $job->Id_Job_Member }}" class="form-label">Nama
                                    Pekerjaan</label>
                                <input type="text" class="form-control @error('Name_Job_Member') is-invalid @enderror"
                                    id="edit_Name_Job_Member_{{ $job->Id_Job_Member }}" name="Name_Job_Member"
                                    value="{{ old('Name_Job_Member', $job->Name_Job_Member) }}" required>
                                @error('Name_Job_Member')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label for="edit_Id_Area_{{ $job->Id_Job_Member }}" class="form-label">Area (Tidak Dapat
                                    Diubah)</label>
                                <input type="text" class="form-control"
                                    value="{{ $job->area->Name_Area ?? 'Tidak Diketahui' }}" readonly>
                                <!-- Kita tetap kirim Id_Area agar bisa diupdate dengan benar -->
                                <input type="hidden" name="Id_Area" value="{{ $job->Id_Area }}">
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
