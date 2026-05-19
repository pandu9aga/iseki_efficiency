@extends('layouts.admin')

@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Work Day / 稼働日数</h3>
                <p class="text-subtitle text-muted">Jumlah hari kerja per bulan (sudah dikurangi libur)</p>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-body">

                {{-- Filter Tahun --}}
                <form method="GET" class="mb-4 bg-light p-3 rounded">
                    <div class="row align-items-end g-3">
                        <div class="col-auto">
                            <label class="form-label fw-bold">Tahun</label>
                            <select name="tahun" class="form-select" onchange="this.form.submit()">
                                @for ($y = now()->year - 2; $y <= now()->year + 2; $y++)
                                    <option value="{{ $y }}" @selected($tahun == $y)>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-auto">
                            <a href="{{ route('admins.dashboard') }}" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Kembali
                            </a>
                        </div>
                    </div>
                </form>

                {{-- Form Bulk Input --}}
                <form method="POST" action="{{ route('admins.workdays.bulk-update') }}">
                    @csrf
                    <input type="hidden" name="tahun" value="{{ $tahun }}">

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width: 50%;">Bulan</th>
                                    <th class="text-center" style="width: 50%;">Jumlah Hari Kerja</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                $bulanList = [
                                    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
                                    '04' => 'April', '05' => 'Mei', '06' => 'Juni',
                                    '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
                                    '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
                                ];
                                @endphp

                                @foreach($bulanList as $num => $nama)
                                    @php
                                        $key = "$tahun-$num";
                                        $value = $workDayData[$key] ?? null;
                                    @endphp
                                    <tr>
                                        <td class="align-middle">{{ $nama }} {{ $tahun }}</td>
                                        <td>
                                            <input
                                                type="number"
                                                name="workdays[{{ $key }}]"
                                                value="{{ old("workdays.$key", $value) }}"
                                                min="0"
                                                max="31"
                                                class="form-control text-center"
                                                placeholder="Boleh kosong">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Simpan Semua
                        </button>
                    </div>
                </form>

                @if(session('success'))
                <div class="mt-4 alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                @endif

            </div>
        </div>
    </section>
</div>
@endsection
