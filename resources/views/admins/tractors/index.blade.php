@extends('layouts.admin')

@section('content')
    <div class="col-sm-12">
        <div class="card table-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="text-primary">Manajemen Tractor</h4>
                <div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTractorModal">
                        Tambah Tractor
                    </button>
                    <a href="{{ route('admins.tractors.import.form') }}" class="btn btn-success ms-2">
                        Import Excel
                    </a>
                </div>
            </div>
            <div class="card-body p-3">
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
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

                <div class="table-responsive">
                    <table class="table table-bordered" id="tractorsTable">
                        <thead>
                            <tr>
                                <th class="text-primary text-center">No</th>
                                <th class="text-primary text-center">Nama Tractor</th>
                                <th class="text-primary text-center">Group</th>
                                <th class="text-primary text-center">Jam</th>
                                <th class="text-primary text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tractors as $t)
                                <tr>
                                    <td class="text-center">{{ $loop->iteration }}</td>
                                    <td class="text-center">{{ $t->Name_Tractor }}</td>
                                    <td class="text-center">{{ $t->Group_Tractor }}</td>
                                    <td class="text-center">{{ $t->Hour_Tractor }}</td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                            data-bs-target="#editTractorModal" data-id="{{ $t->Id_Tractor }}"
                                            data-name="{{ $t->Name_Tractor }}" data-group="{{ $t->Group_Tractor }}"
                                            data-hour="{{ $t->Hour_Tractor }}"
                                            data-update-url="{{ route('admins.tractors.update', $t->Id_Tractor) }}">
                                            Edit
                                        </button>
                                        <form action="{{ route('admins.tractors.destroy', $t->Id_Tractor) }}"
                                            method="POST" class="d-inline"
                                            onsubmit="return confirm('Yakin hapus {{ $t->Name_Tractor }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('modal')
    <!-- Modal Tambah Tractor -->
    <div class="modal fade" id="addTractorModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('admins.tractors.store') }}" method="POST">
                    @csrf
                    <div class="modal-header bg-primary">
                        <h5 class="modal-title text-white">Tambah Tractor</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Nama Tractor</label>
                            <input type="text" name="Name_Tractor" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Group</label>
                            <input type="text" name="Group_Tractor" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Jam</label>
                            <input type="number" name="Hour_Tractor" class="form-control" min="0" step="0.01" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit Tractor -->
    <div class="modal fade" id="editTractorModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="editTractorForm" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" id="edit_tractor_id" name="id">
                    <div class="modal-header bg-info">
                        <h5 class="modal-title text-white">Edit Tractor</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Nama Tractor</label>
                            <input type="text" name="Name_Tractor" id="edit_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Group</label>
                            <input type="text" name="Group_Tractor" id="edit_group" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Jam</label>
                            <input type="number" name="Hour_Tractor" id="edit_hour" class="form-control"
                                min="0" step="0.01" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('style')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        .modal-backdrop {
            z-index: 1040;
        }

        .modal {
            z-index: 1050;
        }
    </style>
@endsection

@section('script')
    <script>
        $(document).ready(function() {
            $('#tractorsTable').DataTable({
                pageLength: -1,
                lengthMenu: [
                    [10, 25, 50, -1],
                    [10, 25, 50, "All"]
                ],
                scrollX: true,
            });

            // Handle modal edit
            $('#tractorsTable').on('click', '[data-bs-toggle="modal"][data-bs-target="#editTractorModal"]', function() {
                const btn = $(this);
                const id = btn.data('id');
                const name = btn.data('name');
                const group = btn.data('group');
                const hour = btn.data('hour');
                const url = btn.data('update-url');

                // Pastikan token CSRF ada di form
                $('#edit_tractor_id').val(id);
                $('#edit_name').val(name);
                $('#edit_group').val(group);
                $('#edit_hour').val(hour);

                // Isi action URL dan tambahkan token CSRF jika belum ada
                $('#editTractorForm').attr('action', url);

                // Cek apakah input _token sudah ada
                if ($('#editTractorForm input[name="_token"]').length === 0) {
                    $('#editTractorForm').prepend('<input type="hidden" name="_token" value="' + $('meta[name="csrf-token"]').attr('content') + '">');
                }
                // Cek apakah input _method sudah ada
                if ($('#editTractorForm input[name="_method"]').length === 0) {
                    $('#editTractorForm').prepend('<input type="hidden" name="_method" value="PUT">');
                }
            });
        });
    </script>
@endsection
