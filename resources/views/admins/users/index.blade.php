@extends('layouts.admin')

@section('content')
    <div class="col-sm-12">
        <div class="card table-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="text-primary">Manajemen User</h4>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                    Tambah User
                </button>
            </div>
            <div class="card-body p-3">
                <div class="table-responsive">
                    <table class="table table-bordered" id="usersTable">
                        <thead>
                            <tr>
                                <th class="text-primary text-center">No</th>
                                <th class="text-primary text-center">Username</th>
                                <th class="text-primary text-center">Nama</th>
                                <th class="text-primary text-center">Role</th>
                                <th class="text-primary text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $u)
                                <tr>
                                    <td class="text-center">{{ $loop->iteration }}</td>
                                    <td class="text-center">{{ $u->Username_User }}</td>
                                    <td class="text-center">{{ $u->Name_User }}</td>
                                    <td class="text-center">
                                        @if ($u->Id_Type_User == 1)
                                            Admin
                                        @elseif($u->Id_Type_User == 2)
                                            Leader
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                            data-bs-target="#editUserModal" data-id="{{ $u->Id_User }}"
                                            data-username="{{ $u->Username_User }}" data-name="{{ $u->Name_User }}"
                                            data-type="{{ $u->Id_Type_User }}"
                                            data-update-url="{{ route('admins.users.update', $u->Id_User) }}">
                                            Edit
                                        </button>
                                        @if ($u->Id_User != session('Id_User'))
                                            <form action="{{ route('admins.users.destroy', $u->Id_User) }}" method="POST"
                                                class="d-inline"
                                                onsubmit="return confirm('Yakin hapus {{ $u->Name_User }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="material-icons-two-tone text-white">delete</i>
                                                </button>
                                            </form>
                                        @endif
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
    <!-- Modal Tambah -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('admins.users.store') }}" method="POST">
                    @csrf
                    <div class="modal-header bg-primary">
                        <h5 class="modal-title text-white">Tambah User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Username</label>
                            <input type="text" name="Username_User" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Nama</label>
                            <input type="text" name="Name_User" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Password</label>
                            <input type="password" name="Password_User" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Role</label>
                            <select name="Id_Type_User" class="form-select" required>
                                <option value="1">Admin</option>
                                <option value="2">Leader</option>
                            </select>
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

    <!-- Modal Edit -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="editUserForm" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" id="edit_user_id" name="id">
                    <div class="modal-header bg-info">
                        <h5 class="modal-title text-white">Edit User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Username</label>
                            <input type="text" name="Username_User" id="edit_username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Nama</label>
                            <input type="text" name="Name_User" id="edit_name_user" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Password (kosongkan jika tidak ingin ganti)</label>
                            <input type="password" name="Password_User" id="edit_password" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label>Role</label>
                            <select name="Id_Type_User" id="edit_type" class="form-select" required>
                                <option value="1">Admin</option>
                                <option value="2">Leader</option>
                            </select>
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
            // Inisialisasi DataTables hanya sekali
            $('#usersTable').DataTable({
                pageLength: -1,
                lengthMenu: [
                    [10, 25, 50, -1],
                    [10, 25, 50, "All"]
                ],
                scrollX: true,
            });

            // Event delegation untuk tombol Edit (penting karena DataTables dinamis)
            $('#usersTable').on('click', '[data-bs-toggle="modal"][data-bs-target="#editUserModal"]', function() {
                const btn = $(this);
                const id = btn.data('id');
                const username = btn.data('username');
                const name = btn.data('name');
                const type = btn.data('type');
                const url = btn.data('update-url');

                // Isi form
                $('#edit_user_id').val(id);
                $('#edit_username').val(username);
                $('#edit_name_user').val(name);
                $('#edit_type').val(type);
                $('#edit_password').val(''); // jangan tampilkan password lama

                // ✅ SET ACTION FORM KE URL YANG BENAR: /admins/users/123
                $('#editUserForm').attr('action', url);
            });
        });
    </script>
@endsection
