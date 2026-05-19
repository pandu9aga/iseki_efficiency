@extends('layouts.leader')
@section('content')
<div class="page-heading">
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>Daily Production Report</h3>
            </div>
        </div>
    </div>

    <!-- Date Filter -->
    <section class="section">
        <div class="card">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <input type="hidden" name="area" value="{{ $area->Id_Area }}">
                    <div class="col-md-3">
                        <label for="date">Date</label>
                        <input type="date" name="date" id="date" class="form-control"
                            value="{{ $dateString }}" onchange="this.form.submit()">
                    </div>
                    {{-- Button not strictly needed with onchange, but good to keep --}}
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">Apply</button>
                    </div>
                </form>
            </div>
        </div>

        @if(isset($assignedAreas) && $assignedAreas->count() > 1)
        <ul class="nav nav-tabs mb-3">
            @foreach($assignedAreas as $a)
            <li class="nav-item">
                <a class="nav-link {{ $a->Id_Area == $area->Id_Area ? 'active' : '' }}"
                    href="{{ route('leaders.reports.index', ['date' => $dateString, 'area' => $a->Id_Area]) }}">
                    {{ $a->Name_Area }}
                </a>
            </li>
            @endforeach
        </ul>
        @endif
    </section>


    <div class="tab-content" id="reportAreaTabContent">
        <!-- Displaying Single Active Area (Selected via Tab) -->
        <div class="tab-pane fade show active" role="tabpanel">

            {{-- ✅ REPORT PER AREA --}}
            @php
            $areaReport = $areaReports->firstWhere('Id_Area', $area->Id_Area);
            $reportExists = $areaReport !== null;
            @endphp
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-0">Report for {{ $area->Name_Area }}</h5>
                        <span class="badge bg-{{ $reportExists ? 'success' : 'secondary' }}">
                            {{ $reportExists ? 'Recorded' : 'Not Recorded' }}
                        </span>
                    </div>
                    <div>
                        <h5 class="card-title mb-0">Current Member Status</h5>
                        <span class="badge bg-info">Live</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            @if ($reportExists)
                            <p><strong>Member:</strong> {{ $areaReport->Total_Member_Report }}</p>
                            <p><strong>Hour:</strong> {{ number_format($areaReport->Total_Hours_Report, 2) }}</p>
                            @else
                            <p class="text-muted">No report recorded for this area.</p>
                            @endif
                            <form action="{{ route('leaders.reports.report.store') }}" method="POST" class="d-inline">
                                @csrf
                                <input type="hidden" name="date" value="{{ $dateString }}">
                                <input type="hidden" name="Id_Area" value="{{ $area->Id_Area }}">
                                <button type="submit" class="btn btn-{{ $reportExists ? 'warning' : 'success' }}">
                                    {{ $reportExists ? 'Update Report' : 'Set Report' }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            {{-- COST --}}
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Non Operational Cost</h5>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                        data-bs-target="#addCostModal{{ $area->Id_Area }}">
                        Add Cost
                    </button>
                </div>
                <div class="card-body">
                    @php $areaCosts = $costs->where('Id_Area', $area->Id_Area); @endphp
                    @if ($areaCosts->isEmpty())
                    <p class="text-muted">No cost data.</p>
                    @else
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Hours</th>
                                    <th>Start</th>
                                    <th>Description</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($areaCosts as $cost)
                                <tr>
                                    <td>
                                        <div class="text-muted small">
                                            {{ number_format($cost->Non_Operational_Cost, 2) }} hours
                                        </div>
                                        @php $jamMenit = \App\Helpers\Formatter::decimalToJamMenit($cost->Non_Operational_Cost); @endphp
                                        <div style="color: #bd0237; font-weight: 600;">
                                            {{ $jamMenit['text'] }}
                                        </div>
                                        {{-- Rincian member --}}
                                        @php
                                        $applied = $cost->applied_members;
                                        $memberCount = 0;
                                        $infoText = 'Unknown';
                                        if (is_array($applied) && !empty($applied)) {
                                        $memberCount = count($applied);
                                        $names = array_map(
                                        fn($nik) => $allNiks[$nik] ?? $nik,
                                        $applied,
                                        );
                                        $infoText = 'Applied to:<br>' . implode('<br>', $names);
                                        } elseif ($applied === null || $applied === 'all') {
                                        // Backward compatibility untuk data lama
                                        $memberCount = \App\Models\DailyJob::where(
                                        'Production_Date_Plan',
                                        \Carbon\Carbon::parse($cost->Start_Cost)->format('Ymd'),
                                        )
                                        ->where('Id_Area', $cost->Id_Area)
                                        ->distinct('Nik_Daily_Job')
                                        ->count();
                                        $infoText = "Applied to all active members ($memberCount)";
                                        }
                                        $costPerPerson = $memberCount > 0 ? $cost->Non_Operational_Cost / $memberCount : 0;
                                        $jamMenitPerPerson = \App\Helpers\Formatter::decimalToJamMenit($costPerPerson);
                                        @endphp
                                        <div class="small text-muted mt-1">
                                            ({{ $jamMenitPerPerson['text'] }} × {{ $memberCount }} members)
                                        </div>
                                        <button type="button" class="btn btn-link p-0 text-decoration-none"
                                            data-bs-toggle="popover" data-bs-trigger="hover focus"
                                            data-bs-html="true" data-bs-content="{{ $infoText }}"
                                            title="Details">
                                            <i class="bi bi-info-circle text-muted"></i>
                                        </button>
                                    </td>
                                    <td>{{ \Carbon\Carbon::parse($cost->Start_Cost)->format('Y-m-d H:i') }}</td>
                                    <td>{{ $cost->Keterangan_Cost }}</td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-warning"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editCostModal{{ $cost->Id_Cost }}">Edit</button>
                                        <form action="{{ route('leaders.reports.cost.destroy', $cost) }}"
                                            method="POST" class="d-inline">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger"
                                                onclick="return confirm('Delete?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>

            {{-- PERMISSION --}}
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Absensi</h5>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                        data-bs-target="#addPowerModal{{ $area->Id_Area }}">
                        Add Absensi
                    </button>
                </div>
                <div class="card-body">
                    @php $areaPowers = $powers->where('Id_Area', $area->Id_Area); @endphp
                    @if ($areaPowers->isEmpty())
                    <p class="text-muted">No absensi data.</p>
                    @else
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Leave Hours</th>
                                    <th>Start</th>
                                    <th>Member</th>
                                    <th>Description</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($areaPowers as $power)
                                <tr>
                                    <td>
                                        <div class="text-muted small">
                                            {{ number_format($power->Leave_Hour_Power, 2) }} hours
                                        </div>
                                        @php $jamMenitPower = \App\Helpers\Formatter::decimalToJamMenit($power->Leave_Hour_Power); @endphp
                                        <div style="color: #bd0237; font-weight: 600;">
                                            {{ $jamMenitPower['text'] }}
                                        </div>
                                    </td>
                                    <td>{{ \Carbon\Carbon::parse($power->Start_Power)->format('Y-m-d H:i') }}</td>
                                    <td>{{ $power->member->nama ?? '–' }}</td>
                                    <td>{{ $power->Keterangan_Power }}</td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-warning"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editPowerModal{{ $power->Id_Power }}">Edit</button>
                                        <form action="{{ route('leaders.reports.power.destroy', $power) }}"
                                            method="POST" class="d-inline">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger"
                                                onclick="return confirm('Delete?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>

            {{-- TIME HANDLING --}}
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Perbantuan</h5>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                        data-bs-target="#addPenangananModal{{ $area->Id_Area }}">
                        Add Perbantuan
                    </button>
                </div>
                <div class="card-body">
                    @php $areaPenanganans = $penanganans->where('Id_Area', $area->Id_Area); @endphp
                    @if ($areaPenanganans->isEmpty())
                    <p class="text-muted">No perbantuan data.</p>
                    @else
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Hours</th>
                                    <th>Start</th>
                                    <th>Description</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($areaPenanganans as $p)
                                <tr>
                                    <td>
                                        <div class="text-muted small">
                                            {{ number_format($p->Hour_Penanganan, 2) }} hours
                                        </div>
                                        @php $jamMenitPenanganan = \App\Helpers\Formatter::decimalToJamMenit($p->Hour_Penanganan); @endphp
                                        <div style="color: #bd0237; font-weight: 600;">
                                            {{ $jamMenitPenanganan['text'] }}
                                        </div>
                                    </td>
                                    <td>{{ \Carbon\Carbon::parse($p->Start_Penanganan)->format('Y-m-d H:i') }}</td>
                                    <td>{{ $p->Keterangan_Penanganan }}</td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-warning"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editPenangananModal{{ $p->Id_Penanganan }}">Edit</button>
                                        <form action="{{ route('leaders.reports.penanganan.destroy', $p) }}"
                                            method="POST" class="d-inline">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger"
                                                onclick="return confirm('Delete?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Scan Data -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Scan Data</h5>
                </div>
                <div class="card-body">
                    @php $areaScans = $scans->where('Id_Area', $area->Id_Area); @endphp
                    @if ($areaScans->isEmpty())
                    <p class="text-muted">No scan data.</p>
                    @else
                    <div class="table-responsive">
                        <table class="table table-sm table-scan">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Tractor</th>
                                    <th>Assigned Hour</th>
                                    <th>Sequence No</th>
                                    <th>Member Pengganti</th>
                                    <th style="width: 80px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($areaScans as $scan)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($scan->Time_Scan)->format('Y-m-d H:i:s') }}</td>
                                    <td>{{ $scan->tractor?->Name_Tractor ?? '–' }}</td>
                                    <td>{{ $scan->Assigned_Hour_Scan }}</td>
                                    <td>{{ $scan->Sequence_No_Plan ?? '–' }}</td>
                                    <td>
                                        @if($scan->Nik_Replace && isset($memberMap[$scan->Nik_Replace]))
                                        {{ $memberMap[$scan->Nik_Replace] }}
                                        @elseif($scan->Nik_Replace)
                                        {{ $scan->Nik_Replace }} <small class="text-muted">(nama tidak ditemukan)</small>
                                        @else
                                        –
                                        @endif
                                    </td>
                                    <td>
                                        <form action="{{ route('leaders.scan.destroy', $scan->Id_Scan) }}" method="POST" style="display:inline;"> @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger"
                                                onclick="return confirm('Hapus scan ini?')">
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>
        </div>

    </div>
</div>

{{-- MODAL ADD COST PER AREA --}}
@foreach ($areas as $area)
<div class="modal fade" id="addCostModal{{ $area->Id_Area }}" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('leaders.reports.cost.store') }}" method="POST">
            @csrf
            <input type="hidden" name="Id_Area" value="{{ $area->Id_Area }}">
            <input type="hidden" name="date_part" value="{{ $dateString }}">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Cost - {{ $area->Name_Area }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Duration (per person)</label>
                        <div class="input-group">
                            <input type="number" name="jam_cost" class="form-control" placeholder="Jam" min="0" required>
                            <span class="input-group-text">jam</span>
                            <input type="number" name="menit_cost" class="form-control" placeholder="Menit" min="0" max="59" required>
                            <span class="input-group-text">menit</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Apply to Members</label>
                        <div class="form-check mb-1">
                            <input class="form-check-input select-all-members" type="checkbox" id="selectAll-{{ $area->Id_Area }}">
                            <label class="form-check-label" for="selectAll-{{ $area->Id_Area }}">Select All</label>
                        </div>
                        <div style="max-height: 150px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; padding: 0.5rem;">
                            @php $membersHere = $activeMembersByArea[$area->Id_Area] ?? collect(); @endphp
                            @if ($membersHere->isEmpty())
                            <p class="text-muted small mb-0">No active members</p>
                            @else
                            @foreach ($membersHere as $m)
                            <div class="form-check">
                                <input class="form-check-input member-checkbox-{{ $area->Id_Area }}" type="checkbox" name="selected_members[]" value="{{ $m->nik }}" id="member-{{ $m->nik }}">
                                <label class="form-check-label" for="member-{{ $m->nik }}">{{ $m->nama }} ({{ $m->nik }})</label>
                            </div>
                            @endforeach
                            @endif
                        </div>
                        <small class="text-muted">Leave unchecked to apply to all active members.</small>
                    </div>
                    <div class="mb-3">
                        <label>Kategori</label>
                        <select name="kategori_cost" class="form-control" required>
                            <option value="senam">Senam</option>
                            <option value="meeting_maneger">課長朝礼 (meeting maneger)</option>
                            <option value="meeting_maneger_dept">部長朝礼 (meeting maneger Dept)</option>
                            <option value="meeting_pres_dir">社長朝礼 (meeting Pres.Dir)</option>
                            <option value="meeting_team_awal">組内最初ミーティング (meeting team dijam awal)</option>
                            <option value="meeting_team_akhir">組内最後ミーティング (meeting team dijam akhir)</option>
                            <option value="kebersihan_team">組内清掃 (kebersihan team)</option>
                            <option value="check_sheet">チェックシートの点検 (pengecekan check sheet)</option>
                            <option value="pelatihan_pekerja">作業者教育 (pelatihan pekerja)</option>
                            <option value="pengecekkan_type_jarang_nagalir">あまり流れてない機械確認 (Pengecekkan Type Jarang Ngalir)</option>
                            <option value="line_stop_divisi_lain">他部署責任によるﾗｲﾝｽﾄｯﾌﾟ (line stop sebab Divsi lain)</option>
                            <option value="line_stop_team_lain">他チーム責任によるﾗｲﾝｽﾄｯﾌﾟ (line stop sebab team lain)</option>
                            <option value="line_stop_team_sendiri">自チーム責任によるﾗｲﾝｽﾄｯﾌﾟ (line stop sebab team sendiri)</option>
                            <option value="lain_lain">Lain-lain (Manual)</option>
                        </select>
                    </div>
                    <div class="mb-3" id="manualCostDesc{{ $area->Id_Area }}" style="display:none;">
                        <label>Deskripsi Manual</label>
                        <textarea name="Keterangan_Cost" class="form-control"></textarea>
                    </div>
                    <div class="mb-3">
                        <label>Start</label>
                        <input type="time" name="time_part" class="form-control" value="07:30" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endforeach

{{-- MODAL ADD POWER PER AREA --}}
@foreach ($areas as $area)
<div class="modal fade" id="addPowerModal{{ $area->Id_Area }}" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('leaders.reports.power.store') }}" method="POST">
            @csrf
            <input type="hidden" name="Id_Area" value="{{ $area->Id_Area }}">
            <input type="hidden" name="date_part" value="{{ $dateString }}">
            <input type="hidden" name="Leave_Hour_Power">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Absensi - {{ $area->Name_Area }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Member</label>
                        <select name="Id_Member" class="form-control" required>
                            <option value="">-- Pilih Member --</option>
                            @php
                            $productionDateYmd = \Carbon\Carbon::parse($dateString)->format('Ymd');
                            $nksToday = \App\Models\DailyJob::where('Production_Date_Plan', $productionDateYmd)
                            ->where('Id_Area', $area->Id_Area)
                            ->pluck('Nik_Daily_Job')
                            ->unique();
                            $eligibleMembersToday = \App\Models\Member::whereIn('nik', $nksToday)->get();
                            @endphp
                            @if ($eligibleMembersToday->isEmpty())
                            <option disabled>Tidak ada member di-assign di area ini hari ini.</option>
                            @else
                            @foreach ($eligibleMembersToday as $m)
                            <option value="{{ $m->id }}">{{ $m->nama }} ({{ $m->nik }})</option>
                            @endforeach
                            @endif
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Leave Hour</label>
                        <div class="input-group">
                            <input type="number" name="jam_power" class="form-control" placeholder="Jam" min="0" required>
                            <span class="input-group-text">jam</span>
                            <input type="number" name="menit_power" class="form-control" placeholder="Menit" min="0" max="59" required>
                            <span class="input-group-text">menit</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Description</label>
                        <textarea name="Keterangan_Power" class="form-control" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label>Start</label>
                        <input type="time" name="time_part" class="form-control" value="07:30" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endforeach

{{-- MODAL ADD PENANGANAN PER AREA --}}
@foreach ($areas as $area)
<div class="modal fade" id="addPenangananModal{{ $area->Id_Area }}" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('leaders.reports.penanganan.store') }}" method="POST">
            @csrf
            <input type="hidden" name="Id_Area" value="{{ $area->Id_Area }}">
            <input type="hidden" name="date_part" value="{{ $dateString }}">
            <input type="hidden" name="Hour_Penanganan"> {{-- Akan diisi via JS --}}

            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Perbantuan - {{ $area->Name_Area }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">

                    <!-- Durasi per orang -->
                    <div class="mb-3">
                        <label>Duration (per person)</label>
                        <div class="input-group">
                            <input type="number" name="jam_penanganan" class="form-control" placeholder="Jam" min="0" required>
                            <span class="input-group-text">jam</span>
                            <input type="number" name="menit_penanganan" class="form-control" placeholder="Menit" min="0" max="59" required>
                            <span class="input-group-text">menit</span>
                        </div>
                    </div>

                    <!-- Pilih Member -->
                    <div class="mb-3">
                        <label>Apply to Members</label>

                        <!-- Dari Area Ini -->
                        <div class="form-group mb-2">
                            <label class="form-label small">From This Area</label>
                            <input type="text" class="form-control form-control-sm mb-1" placeholder="Cari member..." onkeyup="filterMembers(this, 'area-{{ $area->Id_Area }}')">
                            <div class="member-checkbox-list" id="area-{{ $area->Id_Area }}">
                                @php
                                $membersHere = $activeMembersByArea[$area->Id_Area] ?? collect();
                                @endphp
                                @foreach ($membersHere as $m)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="selected_members_area[]" value="{{ $m->nik }}" id="mem-area-{{ $m->nik }}">
                                    <label class="form-check-label" for="mem-area-{{ $m->nik }}">{{ $m->nama }} ({{ $m->nik }})</label>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Dari Semua Area -->
                        <div class="form-group">
                            <label class="form-label small fw-bold">From All Areas</label>
                            <input
                                type="text"
                                class="form-control form-control-sm mb-2"
                                placeholder="🔍 Cari nama atau NIK..."
                                onkeyup="filterMembers(this, 'all-{{ $area->Id_Area }}')"
                                autocomplete="off">
                            <div
                                class="member-checkbox-list border rounded p-2 bg-light"
                                id="all-{{ $area->Id_Area }}"
                                style="max-height: 200px; overflow-y: auto; font-size: 0.875rem;">
                                @if($allMembers->isEmpty())
                                <div class="text-muted text-center py-2">
                                    <em>Tidak ada member tersedia</em>
                                </div>
                                @else
                                @php
                                // Ambil NIK dari area ini agar tidak duplikat
                                $areaNiks = $membersHere->pluck('nik')->toArray();
                                @endphp
                                @foreach ($allMembers as $m)
                                @php
                                $isInThisArea = in_array($m->nik, $areaNiks);
                                @endphp
                                @if (!$isInThisArea)
                                <div class="form-check mb-1 d-flex align-items-center" style="gap: 0.5rem;">
                                    <input
                                        class="form-check-input flex-shrink-0"
                                        type="checkbox"
                                        name="selected_members_all[]"
                                        value="{{ $m->nik }}"
                                        id="mem-all-{{ $area->Id_Area }}-{{ $m->nik }}">
                                    <label
                                        class="form-check-label flex-grow-1 mb-0 text-truncate"
                                        for="mem-all-{{ $area->Id_Area }}-{{ $m->nik }}"
                                        title="{{ $m->nama }} ({{ $m->nik }}) - {{ $m->area?->Name_Area }}">
                                        <span class="fw-medium">{{ $m->nama }}</span>
                                        <span class="text-muted ms-1">({{ $m->nik }})</span>
                                        @if($m->area?->Name_Area)
                                        <br><small class="text-muted">Area: {{ $m->area->Name_Area }}</small>
                                        @endif
                                    </label>
                                </div>
                                @endif
                                @endforeach
                                @endif
                            </div>
                            <small class="text-muted mt-1">Centang untuk memilih member dari area mana pun.</small>
                        </div>
                        <small class="text-muted">Pilih minimal 1 member dari salah satu daftar.</small>
                    </div>

                    <!-- Kategori -->
                    <div class="mb-3">
                        <label>Kategori</label>
                        <select name="kategori_penanganan" class="form-control" required>
                            <option value="fix_back_up_proses">Fix Back Up Proses / 工程の応援</option>
                            <option value="back_up_absensi">Back Up Absensi / 欠勤応援</option>
                            <option value="bantuan_pic_absensi">Bantuan ke PIC Absensi / 欠勤対応の応援</option>
                            <option value="back_up_line_stop_irregular">Back Up Line Stop / Irregular / イレギュラー対応</option>
                            <option value="perbantuan_area_lain">Perbantuan area lain / 他部署応援 【－】</option>
                            <option value="lembur_produksi">Lembur Produksi / 生産残業</option>
                            <option value="lembur_mente">Lembur Mente / メンテ残業</option>
                        </select>
                    </div>

                    <!-- Start Time -->
                    <div class="mb-3">
                        <label>Start</label>
                        <input type="time" name="time_part" class="form-control" value="07:30" required>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endforeach

{{-- MODAL EDIT COST --}}
@foreach ($costs as $cost)
@php
$costArea = $areas->firstWhere('Id_Area', $cost->Id_Area);
if (!$costArea) continue;
$start = \Carbon\Carbon::parse($cost->Start_Cost);
$datePart = $start->format('Y-m-d');
$productionDateYmd = $start->format('Ymd');
$timePart = $start->format('H:i');
$allActiveNiksAtCostDate = \App\Models\DailyJob::where('Production_Date_Plan', $productionDateYmd)
->where('Id_Area', $cost->Id_Area)
->pluck('Nik_Daily_Job')
->unique()
->toArray();
$eligibleMembersAtCostDate = \App\Models\Member::whereIn('nik', $allActiveNiksAtCostDate)->get();
$applied = $cost->applied_members;
$preselectedNiks = [];
if ($applied === 'all' || $applied === null) {
$preselectedNiks = $allActiveNiksAtCostDate;
} elseif (is_array($applied)) {
$preselectedNiks = $applied;
}
$memberCount = count($preselectedNiks);
$durationPerPerson = $memberCount > 0 ? $cost->Non_Operational_Cost / $memberCount : 0;
$jamPerPerson = floor($durationPerPerson);
$menitPerPerson = round(($durationPerPerson - $jamPerPerson) * 60);
@endphp
<div class="modal fade" id="editCostModal{{ $cost->Id_Cost }}" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('leaders.reports.cost.update', $cost) }}" method="POST">
            @csrf @method('PUT')
            <input type="hidden" name="Id_Area" value="{{ $cost->Id_Area }}">
            <input type="hidden" name="date_part" value="{{ $datePart }}">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Cost - {{ $costArea->Name_Area }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Duration (per person)</label>
                        <div class="input-group">
                            <input type="number" name="jam_cost" class="form-control" value="{{ $jamPerPerson }}" min="0" required>
                            <span class="input-group-text">jam</span>
                            <input type="number" name="menit_cost" class="form-control" value="{{ $menitPerPerson }}" min="0" max="59" required>
                            <span class="input-group-text">menit</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Apply to Members</label>
                        <div class="form-check mb-1">
                            <input class="form-check-input select-all-edit-members" type="checkbox" id="selectAllEdit-{{ $cost->Id_Cost }}">
                            <label class="form-check-label" for="selectAllEdit-{{ $cost->Id_Cost }}">Select All</label>
                        </div>
                        <div style="max-height: 150px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; padding: 0.5rem;">
                            @if ($eligibleMembersAtCostDate->isEmpty())
                            <p class="text-muted small mb-0">No active members on {{ $datePart }}</p>
                            @else
                            @foreach ($eligibleMembersAtCostDate as $m)
                            <div class="form-check">
                                <input class="form-check-input member-edit-checkbox-{{ $cost->Id_Cost }}" type="checkbox" name="selected_members[]" value="{{ $m->nik }}" id="edit-member-{{ $cost->Id_Cost }}-{{ $m->nik }}" {{ in_array($m->nik, $preselectedNiks) ? 'checked' : '' }}>
                                <label class="form-check-label" for="edit-member-{{ $cost->Id_Cost }}-{{ $m->nik }}">{{ $m->nama }} ({{ $m->nik }})</label>
                            </div>
                            @endforeach
                            @endif
                        </div>
                        <small class="text-muted">Leave unchecked to apply to all active members.</small>
                    </div>
                    <div class="mb-3">
                        <label>Kategori</label>
                        <select name="kategori_cost" class="form-control" required>
                            <option value="senam">Senam</option>
                            <option value="meeting_maneger">課長朝礼 (meeting maneger)</option>
                            <option value="meeting_maneger_dept">部長朝礼 (meeting maneger Dept)</option>
                            <option value="meeting_pres_dir">社長朝礼 (meeting Pres.Dir)</option>
                            <option value="meeting_team_awal">組内最初ミーティング (meeting team dijam awal)</option>
                            <option value="meeting_team_akhir">組内最後ミーティング (meeting team dijam akhir)</option>
                            <option value="kebersihan_team">組内清掃 (kebersihan team)</option>
                            <option value="check_sheet">チェックシートの点検 (pengecekan check sheet)</option>
                            <option value="pelatihan_pekerja">作業者教育 (pelatihan pekerja)</option>
                            <option value="pengecekkan_type_jarang_nagalir">あまり流れてない機械確認 (Pengecekkan Type Jarang Ngalir)</option>
                            <option value="line_stop_divisi_lain">他部署責任によるﾗｲﾝｽﾄｯﾌﾟ (line stop sebab Divsi lain)</option>
                            <option value="line_stop_team_lain">他チーム責任によるﾗｲﾝｽﾄｯﾌﾟ (line stop sebab team lain)</option>
                            <option value="line_stop_team_sendiri">自チーム責任によるﾗｲﾝｽﾄｯﾌﾟ (line stop sebab team sendiri)</option>
                            <option value="lain_lain">Lain-lain (Manual)</option>
                        </select>
                    </div>
                    <div class="mb-3" id="editManualCostDesc{{ $cost->Id_Cost }}" style="display:{{ !in_array($cost->Keterangan_Cost, ['Senam', 'Briefing', 'Checksheet']) ? 'block' : 'none' }};">
                        <label>Deskripsi Manual</label>
                        <textarea name="Keterangan_Cost" class="form-control">{{ $cost->Keterangan_Cost }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label>Start</label>
                        <input type="time" name="time_part" class="form-control" value="{{ $timePart }}" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Update</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endforeach

{{-- MODAL EDIT POWER --}}
@foreach ($powers as $power)
@php
$powerArea = $areas->firstWhere('Id_Area', $power->Id_Area);
if (!$powerArea) continue;
$start = \Carbon\Carbon::parse($power->Start_Power);
$datePart = $start->format('Y-m-d');
$productionDateYmd = $start->format('Ymd');
$timePart = $start->format('H:i');
$eligibleMembersAtPowerDate = \App\Models\DailyJob::where('Production_Date_Plan', $productionDateYmd)
->where('Id_Area', $power->Id_Area)
->pluck('Nik_Daily_Job')
->unique()
->map(fn($nik) => \App\Models\Member::where('nik', $nik)->first())
->filter();
$jamPower = floor($power->Leave_Hour_Power);
$menitPower = round(($power->Leave_Hour_Power - $jamPower) * 60);
@endphp
<div class="modal fade" id="editPowerModal{{ $power->Id_Power }}" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('leaders.reports.power.update', $power) }}" method="POST">
            @csrf @method('PUT')
            <input type="hidden" name="Id_Area" value="{{ $power->Id_Area }}">
            <input type="hidden" name="date_part" value="{{ $datePart }}">
            <input type="hidden" name="Leave_Hour_Power">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Absensi - {{ $powerArea->Name_Area }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Member</label>
                        <select name="Id_Member" class="form-control" required>
                            <option value="">-- Pilih --</option>
                            @if ($eligibleMembersAtPowerDate->isEmpty())
                            <option disabled>No assigned members on {{ $datePart }}</option>
                            @else
                            @foreach ($eligibleMembersAtPowerDate as $m)
                            @if ($m)
                            <option value="{{ $m->id }}" {{ $power->Id_Member == $m->id ? 'selected' : '' }}>{{ $m->nama }} ({{ $m->nik }})</option>
                            @endif
                            @endforeach
                            @endif
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Leave Hour</label>
                        <div class="input-group">
                            <input type="number" name="jam_power" class="form-control" value="{{ $jamPower }}" min="0" required>
                            <span class="input-group-text">jam</span>
                            <input type="number" name="menit_power" class="form-control" value="{{ $menitPower }}" min="0" max="59" required>
                            <span class="input-group-text">menit</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Description</label>
                        <textarea name="Keterangan_Power" class="form-control" required>{{ $power->Keterangan_Power }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label>Start</label>
                        <input type="time" name="time_part" class="form-control" value="{{ $timePart }}" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Update</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endforeach

{{-- MODAL EDIT PENANGANAN --}}
@foreach ($penanganans as $p)
@php
$penangananArea = $areas->firstWhere('Id_Area', $p->Id_Area);
if (!$penangananArea) continue;

$start = \Carbon\Carbon::parse($p->Start_Penanganan);
$datePart = $start->format('Y-m-d');
$timePart = $start->format('H:i');

$applied = $p->Applied_Members;
$memberCount = is_array($applied) ? count($applied) : 0;
$durationPerPerson = $memberCount > 0 ? abs($p->Hour_Penanganan) / $memberCount : 0;
$jamDur = floor($durationPerPerson);
$menitDur = round(($durationPerPerson - $jamDur) * 60);

$isNegative = $p->Hour_Penanganan < 0;
    $kategoriMap=[ 'Fix Back Up Proses'=> 'fix_back_up_proses',
    'Back Up Absensi' => 'back_up_absensi',
    'Bantuan ke PIC Absensi' => 'bantuan_pic_absensi',
    'Back Up Line Stop / Irregular' => 'back_up_line_stop_irregular',
    'Lembur Produksi' => 'lembur_produksi',
    'Lembur Mente' => 'lembur_mente',
    ];
    $kategori = $isNegative
    ? 'perbantuan_area_lain'
    : ($kategoriMap[$p->Keterangan_Penanganan] ?? 'lain_lain');

    // 🔥 Ambil member aktif di area ini pada tanggal tersebut
    $productionDateYmd = \Carbon\Carbon::parse($datePart)->format('Ymd');
    $dailyJobNiks = \App\Models\DailyJob::where('Production_Date_Plan', $productionDateYmd)
    ->where('Id_Area', $p->Id_Area)
    ->pluck('Nik_Daily_Job')
    ->unique();

    $membersHere = \App\Models\Member::whereIn('nik', $dailyJobNiks)->get();
    $areaNiks = $membersHere->pluck('nik')->toArray();

    $preselectedArea = [];
    $preselectedAll = [];

    if (is_array($applied)) {
    $preselectedArea = array_values(array_intersect($applied, $areaNiks));
    $preselectedAll = array_values(array_diff($applied, $areaNiks));
    }
    @endphp

    <div class="modal fade" id="editPenangananModal{{ $p->Id_Penanganan }}" tabindex="-1">
        <div class="modal-dialog">
            <form action="{{ route('leaders.reports.penanganan.update', $p) }}" method="POST">
                @csrf @method('PUT')
                <input type="hidden" name="Id_Area" value="{{ $p->Id_Area }}">
                <input type="hidden" name="date_part" value="{{ $datePart }}">

                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Perbantuan - {{ $penangananArea->Name_Area }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">

                        <!-- Durasi per orang -->
                        <div class="mb-3">
                            <label>Duration (per person)</label>
                            <div class="input-group">
                                <input type="number" name="jam_penanganan" class="form-control" value="{{ $jamDur }}" min="0" required>
                                <span class="input-group-text">jam</span>
                                <input type="number" name="menit_penanganan" class="form-control" value="{{ $menitDur }}" min="0" max="59" required>
                                <span class="input-group-text">menit</span>
                            </div>
                        </div>

                        <!-- Pilih Member -->
                        <div class="mb-3">
                            <label>Apply to Members</label>

                            <!-- Dari Area Ini -->
                            <div class="form-group mb-2">
                                <label class="form-label small">From This Area</label>
                                <input type="text" class="form-control form-control-sm mb-1" placeholder="Cari member..." onkeyup="filterMembers(this, 'edit-area-{{ $p->Id_Penanganan }}')">
                                <div class="member-checkbox-list" id="edit-area-{{ $p->Id_Penanganan }}">
                                    @foreach ($membersHere as $m) {{-- ✅ INI YANG BENAR --}}
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="selected_members_area[]" value="{{ $m->nik }}" id="edit-mem-area-{{ $p->Id_Penanganan }}-{{ $m->nik }}" {{ in_array($m->nik, $preselectedArea) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="edit-mem-area-{{ $p->Id_Penanganan }}-{{ $m->nik }}">{{ $m->nama }} ({{ $m->nik }})</label>
                                    </div>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Dari Semua Area -->
                            <div class="form-group">
                                <label class="form-label small fw-bold">From All Areas</label>
                                <input
                                    type="text"
                                    class="form-control form-control-sm mb-2"
                                    placeholder="🔍 Cari nama atau NIK..."
                                    onkeyup="filterMembers(this, 'edit-all-{{ $p->Id_Penanganan }}')"
                                    autocomplete="off">
                                <div
                                    class="member-checkbox-list border rounded p-2 bg-light"
                                    id="edit-all-{{ $p->Id_Penanganan }}"
                                    style="max-height: 200px; overflow-y: auto; font-size: 0.875rem;">
                                    @if($allMembers->isEmpty())
                                    <div class="text-muted text-center py-2">
                                        <em>Tidak ada member tersedia</em>
                                    </div>
                                    @else
                                    @foreach ($allMembers as $m)
                                    @php
                                    $isInArea = in_array($m->nik, $areaNiks);
                                    @endphp
                                    @if (!$isInArea)
                                    <div class="form-check mb-1 d-flex align-items-center" style="gap: 0.5rem;">
                                        <input
                                            class="form-check-input flex-shrink-0"
                                            type="checkbox"
                                            name="selected_members_all[]"
                                            value="{{ $m->nik }}"
                                            id="edit-mem-all-{{ $p->Id_Penanganan }}-{{ $m->nik }}"
                                            {{ in_array($m->nik, $preselectedAll) ? 'checked' : '' }}>
                                        <label
                                            class="form-check-label flex-grow-1 mb-0 text-truncate"
                                            for="edit-mem-all-{{ $p->Id_Penanganan }}-{{ $m->nik }}"
                                            title="{{ $m->nama }} ({{ $m->nik }}) - {{ $m->area?->Name_Area }}">
                                            <span class="fw-medium">{{ $m->nama }}</span>
                                            <span class="text-muted ms-1">({{ $m->nik }})</span>
                                            @if($m->area?->Name_Area)
                                            <br><small class="text-muted">Area: {{ $m->area->Name_Area }}</small>
                                            @endif
                                        </label>
                                    </div>
                                    @endif
                                    @endforeach
                                    @endif
                                </div>
                                <small class="text-muted mt-1">Centang untuk memilih member dari area mana pun.</small>
                            </div>
                        </div>

                        <!-- Kategori -->
                        <div class="mb-3">
                            <label>Kategori</label>
                            <select name="kategori_penanganan" class="form-control" required>
                                <option value="fix_back_up_proses" {{ $kategori === 'fix_back_up_proses' ? 'selected' : '' }}>Fix Back Up Proses / 工程の応援</option>
                                <option value="back_up_absensi" {{ $kategori === 'back_up_absensi' ? 'selected' : '' }}>Back Up Absensi / 欠勤応援</option>
                                <option value="bantuan_pic_absensi" {{ $kategori === 'bantuan_pic_absensi' ? 'selected' : '' }}>Bantuan ke PIC Absensi / 欠勤対応の応援</option>
                                <option value="back_up_line_stop_irregular" {{ $kategori === 'back_up_line_stop_irregular' ? 'selected' : '' }}>Back Up Line Stop / Irregular / イレギュラー対応</option>
                                <option value="perbantuan_area_lain" {{ $kategori === 'perbantuan_area_lain' ? 'selected' : '' }}>Perbantuan area lain / 他部署応援 【－】</option>
                                <option value="lembur_produksi" {{ $kategori === 'lembur_produksi' ? 'selected' : '' }}>Lembur Produksi / 生産残業</option>
                                <option value="lembur_mente" {{ $kategori === 'lembur_mente' ? 'selected' : '' }}>Lembur Mente / メンテ残業</option>
                            </select>
                        </div>

                        <!-- Start Time -->
                        <div class="mb-3">
                            <label>Start</label>
                            <input type="time" name="time_part" class="form-control" value="{{ $timePart }}" required>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Update</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    @endforeach
    @endsection

    @section('style')
    <link href="{{ asset('assets/css/tom-select.bootstrap5.css') }}" rel="stylesheet">
    @endsection

    @section('script')
    <script src="{{ asset('assets/js/tom-select.complete.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            // ✅ Initialize DataTables
            $('.table-scan').DataTable({
                "order": [
                    [0, "desc"]
                ], // Sort by Time
                "pageLength": -1,
                "lengthMenu": [
                    [10, 25, 50, -1],
                    [10, 25, 50, "All"]
                ],
                "searching": true,
                "paging": true,
                "info": true
            });
        });

        // Select All
        document.querySelectorAll('.select-all-edit-members').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const costId = this.id.split('-')[1];
                const cbs = document.querySelectorAll(`.member-edit-checkbox-${costId}`);
                cbs.forEach(cb => cb.checked = this.checked);
            });
        });

        // Kategori lain_lain - Add & Edit
        document.querySelectorAll('[name="kategori_cost"], [name="kategori_penanganan"]').forEach(sel => {
            sel.addEventListener('change', function() {
                const prefix = this.name.includes('cost') ? 'manualCostDesc' : 'manualPenangananDesc';
                const container = this.closest('.modal-body').querySelector(`div[id^="${prefix}"]`);
                if (container) {
                    container.style.display = this.value === 'lain_lain' ? 'block' : 'none';
                    const textarea = container.querySelector('textarea');
                    if (textarea) textarea.required = this.value === 'lain_lain';
                }
            });
        });

        // Konversi jam-menit untuk Power & Penanganan
        function jamMenitToDecimal(jam, menit) {
            return (parseFloat(jam) || 0) + (parseFloat(menit) || 0) / 60;
        }
        document.querySelectorAll('form[method="POST"]').forEach(form => {
            if (form.querySelector('[name="jam_power"]')) {
                form.addEventListener('submit', function(e) {
                    const jam = form.querySelector('[name="jam_power"]').value;
                    const menit = form.querySelector('[name="menit_power"]').value;
                    form.querySelector('[name="Leave_Hour_Power"]').value = jamMenitToDecimal(jam, menit).toFixed(2);
                });
            }
            if (form.querySelector('[name="jam_penanganan"]')) {
                form.addEventListener('submit', function(e) {
                    const jam = form.querySelector('[name="jam_penanganan"]').value;
                    const menit = form.querySelector('[name="menit_penanganan"]').value;
                    form.querySelector('[name="Hour_Penanganan"]').value = jamMenitToDecimal(jam, menit).toFixed(2);
                });
            }
        });

        // Popover
        $(function() {
            $('[data-bs-toggle="popover"]').popover();
        });

        // Tab persistence
        document.addEventListener('DOMContentLoaded', function() {
            const activeTab = sessionStorage.getItem('activeReportTab');
            if (activeTab) {
                const btn = document.querySelector(`button[data-bs-target="${activeTab}"]`);
                if (btn) new bootstrap.Tab(btn).show();
            }
            document.querySelectorAll('#reportAreaTabs button[data-bs-toggle="tab"]').forEach(btn => {
                btn.addEventListener('shown.bs.tab', e => {
                    sessionStorage.setItem('activeReportTab', e.target.getAttribute('data-bs-target'));
                });
            });
        });

        // ✅ FUNGSI FILTER AMAN — TIDAK MENYEMBUNYIKAN CHECKBOX
        function filterMembers(input, containerId) {
            const filter = input.value.toLowerCase().trim();
            const container = document.getElementById(containerId);
            if (!container) return;

            const items = container.querySelectorAll('.form-check');
            items.forEach(item => {
                const label = item.querySelector('.form-check-label');
                if (!label) return;
                const text = label.textContent.toLowerCase();
                if (text.includes(filter)) {
                    item.classList.remove('d-none');
                } else {
                    item.classList.add('d-none');
                }
            });
        }
    </script>
    @endsection