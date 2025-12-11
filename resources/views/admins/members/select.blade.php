@extends('layouts.admin')

@section('content')
    <div class="col-sm-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="text-primary">Pilih Member untuk Perhitungan</h4>
                <span class="text-muted">Administrator</span>
            </div>
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        {{ $errors->first('selected_members') ?? 'Pilih minimal 1 member.' }}
                    </div>
                @endif

                <form id="memberSelectionForm" action="{{ route('admins.members.select.store') }}" method="POST">
                    @csrf
                    {{-- ✅ Hidden input untuk menyimpan SEMUA ID yang dipilih --}}
                    <input type="hidden" name="selected_members" id="selectedMembersInput"
                        value="{{ implode(',', $selectedIds) }}">

                    <div class="table-responsive">
                        <table class="table table-bordered" id="membersTable">
                            <thead>
                                <tr>
                                    <th width="5%">Pilih</th>
                                    <th>Nama</th>
                                    <th>NIK</th>
                                    <th>Team</th>
                                    <th>Divisi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($members as $m)
                                    <tr>
                                        <td class="text-center">
                                            {{-- ✅ Checkbox tanpa name, hanya untuk UI --}}
                                            <input type="checkbox" class="member-checkbox" data-id="{{ $m->id }}"
                                                {{ in_array($m->id, $selectedIds) ? 'checked' : '' }}>
                                        </td>
                                        <td>{{ $m->nama }}</td>
                                        <td>{{ $m->nik }}</td>
                                        <td>{{ $m->team ?? '-' }}</td>
                                        <td>{{ $m->division?->nama ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">Simpan Pilihan</button>
                        <a href="{{ route('admins.dashboard') }}" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('style')
    <link rel="stylesheet" href="{{ asset('assets/css/dataTables.min.css') }}">
@endsection

@section('script')
    <script src="{{ asset('assets/js/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/js/dataTables.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            // Inisialisasi DataTable
            const table = $('#membersTable').DataTable({
                paging: false,
                scrollY: '500px',
                scrollCollapse: true,
                searching: true,
                ordering: true,
                info: true,
                scrollX: true,
                language: {
                    search: "Cari:",
                    info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                    paginate: {
                        previous: "«",
                        next: "»"
                    }
                }
            });

            // Sinkronkan checkbox dengan hidden input
            function updateSelectedMembers() {
                const selectedIds = [];
                $('.member-checkbox:checked').each(function() {
                    selectedIds.push($(this).data('id'));
                });
                $('#selectedMembersInput').val(selectedIds.join(','));
            }

            // Saat halaman pertama kali dimuat
            updateSelectedMembers();

            // Saat checkbox diklik
            $(document).on('change', '.member-checkbox', function() {
                updateSelectedMembers();
            });

            // Saat form disubmit
            $('#memberSelectionForm').on('submit', function() {
                updateSelectedMembers(); // Pastikan terakhir kali sinkron
            });
        });
    </script>
@endsection
