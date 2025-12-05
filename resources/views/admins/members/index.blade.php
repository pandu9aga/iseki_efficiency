@extends('layouts.admin')

@section('content')
    <div class="col-sm-12">
        <div class="card table-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="text-primary">Data Member</h4>
            </div>
            <div class="card-body p-3">
                <div class="table-responsive">
                    <table class="table table-bordered" id="membersTable">
                        <thead>
                            <tr>
                                <th class="text-primary text-center">No</th>
                                <th class="text-primary">Nama</th>
                                <th class="text-primary text-center">NIK</th>
                                <th class="text-primary text-center">Divisi</th>
                                <th class="text-primary text-center">Team</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($members as $member)
                                <tr>
                                    <td class="text-center">{{ $loop->iteration }}</td>
                                    <td>{{ $member->nama }}</td>
                                    <td class="text-center">{{ $member->nik }}</td>
                                    <td class="text-center">{{ $member->division?->nama ?? '-' }}</td>
                                    <td class="text-center">{{ $member->team ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted">Tidak ada data member.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        $(document).ready(function() {
            $('#membersTable').DataTable({
                pageLength: -1,
                lengthMenu: [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                scrollX: true,
                responsive: true
            });
        });
    </script>
@endsection
