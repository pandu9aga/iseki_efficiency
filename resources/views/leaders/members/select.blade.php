@extends('layouts.leader')

@section('content')
    <div class="col-sm-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="text-primary">Pilih Member untuk Perhitungan</h4>
                <span class="text-muted">Pilih member</span>
            </div>
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        {{ $errors->first('selected_members') ?? 'Pilih minimal 1 member.' }}
                    </div>
                @endif

                <form action="{{ route('leaders.members.select.store') }}" method="POST">
                    @csrf
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
                                            <input type="checkbox" name="selected_members[]" value="{{ $m->id }}"
                                                id="member-{{ $m->id }}" {{ in_array($m->id, $selectedIds) ? 'checked' : '' }}>
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
                        <a href="{{ route('leaders.dashboard') }}" class="btn btn-secondary">Batal</a>
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
            $('#membersTable').DataTable({
                paging: false,               // nonaktifkan pagination
                scrollY: '500px',           // tinggi area scroll (sesuaikan)
                scrollCollapse: true,       // menyesuaikan tinggi jika data sedikit
                searching: true,            // tetap tampilkan kolom search
                ordering: true,
                info: true,                 // tampilkan info "Showing 1 to X of Y entries"
                scrollX: true,              // horizontal scroll jika perlu
                language: {
                    search: "Cari:",
                    info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                    paginate: {
                        previous: "«",
                        next: "»"
                    }
                }
            });
        });
    </script>
@endsection