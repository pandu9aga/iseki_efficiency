@extends('layouts.admin')

@section('content')
    <div class="col-sm-12">
        <div class="card table-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="text-primary">Manajemen Area</h4>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAreaModal">
                    Tambah Area
                </button>
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
                    <table class="table table-bordered" id="areasTable">
                        <thead>
                            <tr>
                                <th class="text-primary text-center">No</th>
                                <th class="text-primary text-center">Nama Area</th>
                                <th class="text-primary text-center">Password</th>
                                <th class="text-primary text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($areas as $area)
                                <tr>
                                    <td class="text-center">{{ $loop->iteration }}</td>
                                    <td class="text-center">{{ $area->Name_Area }}</td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center align-items-center gap-2">
                                            <span class="password-text">{{ str_repeat('•', strlen($area->Password_Area)) }}</span>
                                            <button type="button" class="btn btn-sm btn-outline-secondary toggle-password"
                                                data-password="{{ $area->Password_Area }}" title="Tampilkan Password">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                            data-bs-target="#editAreaModal" data-id="{{ $area->Id_Area }}"
                                            data-name="{{ $area->Name_Area }}" data-password="{{ $area->Password_Area }}"
                                            data-update-url="{{ route('admins.areas.update', $area->Id_Area) }}">
                                            Edit
                                        </button>
                                        <form action="{{ route('admins.areas.destroy', $area->Id_Area) }}"
                                            method="POST" class="d-inline"
                                            onsubmit="return confirm('Yakin hapus {{ $area->Name_Area }}?');">
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
    <!-- Modal Tambah Area -->
    <div class="modal fade" id="addAreaModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('admins.areas.store') }}" method="POST">
                    @csrf
                    <div class="modal-header bg-primary">
                        <h5 class="modal-title text-white">Tambah Area</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Nama Area</label>
                            <input type="text" name="Name_Area" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Password</label>
                            <input type="text" name="Password_Area" class="form-control" required>
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

    <!-- Modal Edit Area -->
    <div class="modal fade" id="editAreaModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="editAreaForm" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-header bg-info">
                        <h5 class="modal-title text-white">Edit Area</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Nama Area</label>
                            <input type="text" name="Name_Area" id="edit_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Password</label>
                            <input type="text" name="Password_Area" id="edit_password" class="form-control" required>
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

        .toggle-password {
            padding: 0.25rem 0.5rem;
        }
    </style>
@endsection

@section('script')
    <script>
        $(document).ready(function() {
            $('#areasTable').DataTable({
                pageLength: -1,
                lengthMenu: [
                    [10, 25, 50, -1],
                    [10, 25, 50, "All"]
                ],
                scrollX: true,
            });

            // Toggle password visibility
            $(document).on('click', '.toggle-password', function() {
                const btn = $(this);
                const passwordText = btn.siblings('.password-text');
                const actualPassword = btn.data('password');

                if (passwordText.text() === actualPassword) {
                    passwordText.text(str_repeat('•', actualPassword.length));
                    btn.html('<i class="bi bi-eye"></i>');
                } else {
                    passwordText.text(actualPassword);
                    btn.html('<i class="bi bi-eye-slash"></i>');
                }
            });

            function str_repeat(str, count) {
                return Array(count + 1).join(str);
            }

            // Handle modal edit
            $('#areasTable').on('click', '[data-bs-toggle="modal"][data-bs-target="#editAreaModal"]', function() {
                const btn = $(this);
                const id = btn.data('id');
                const name = btn.data('name');
                const password = btn.data('password');
                const url = btn.data('update-url');

                $('#edit_name').val(name);
                $('#edit_password').val(password);

                $('#editAreaForm').attr('action', url);

                if ($('#editAreaForm input[name="_token"]').length === 0) {
                    $('#editAreaForm').prepend('<input type="hidden" name="_token" value="' + $('meta[name="csrf-token"]').attr('content') + '">');
                }
                if ($('#editAreaForm input[name="_method"]').length === 0) {
                    $('#editAreaForm').prepend('<input type="hidden" name="_method" value="PUT">');
                }
            });
        });
    </script>
@endsection