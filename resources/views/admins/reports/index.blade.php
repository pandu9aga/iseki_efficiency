@extends('layouts.admin')

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
                        <div class="col-md-3">
                            <label for="date">Date</label>
                            <input type="date" name="date" id="date" class="form-control"
                                value="{{ $dateString }}" required>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary">Apply</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <!-- Total Hour Member -->
        <section class="section">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="card-title">Reported Data</h5>
                            <span class="badge bg-{{ $reportExists ? 'success' : 'secondary' }}">
                                {{ $reportExists ? 'Recorded' : 'Not Recorded' }}
                            </span>
                        </div>
                        <div class="col-md-6">
                            <h5 class="card-title">Current Member Status</h5>
                            <span class="badge bg-info">Live</span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            @if ($reportExists)
                                <p><strong>Member:</strong> {{ $recordedReport->Total_Member_Report }}</p>
                                <p><strong>Hour:</strong> {{ number_format($recordedReport->Total_Hours_Report, 2) }}</p>
                            @else
                                <p class="text-muted">No report recorded yet.</p>
                            @endif
                            <form action="{{ route('admins.reports.report.store') }}" method="POST" class="d-inline">
                                @csrf
                                <input type="hidden" name="date" value="{{ $dateString }}">
                                <button type="submit" class="btn btn-{{ $reportExists ? 'warning' : 'success' }}">
                                    {{ $reportExists ? 'Update Report' : 'Set Report' }}
                                </button>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Active Members:</strong> {{ $currentTotalMembers }}</p>
                            <p><strong>Calculated Hours:</strong> {{ number_format($currentTotalHours, 2) }}
                                ({{ $currentTotalMembers }} × 8 hours)</p>
                            <a href="{{ route('admins.members.select') }}" class="btn btn-outline-primary">Edit Member</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Non Operational Cost -->
        <section class="section">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title">Non Operational Cost (Impact: ×{{ $currentTotalMembers }} members)</h5>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCostModal">
                        Add
                    </button>
                </div>
                <div class="card-body">
                    @if ($costs->isEmpty())
                        <p class="text-muted">No cost data for this day.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Hours (Impact)</th>
                                        <th>Start</th>
                                        <th>Description</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($costs as $cost)
                                        @php
                                            $original = $cost->Non_Operational_Cost;
                                            $impact = $original * $currentTotalMembers;
                                            $jamImpact = floor($impact);
                                            $menitImpact = round(($impact - $jamImpact) * 60);
                                        @endphp
                                        <tr>
                                            <td>
                                                {{ number_format($impact, 2) }}
                                                <br>
                                                <small class="text-muted">
                                                    ({{ $jamImpact }} jam {{ $menitImpact }} menit)
                                                    <br>
                                                    ({{ number_format($original, 2) }} × {{ $currentTotalMembers }})
                                                </small>
                                            </td>
                                            <td>{{ \Carbon\Carbon::parse($cost->Start_Cost)->format('Y-m-d H:i') }}</td>
                                            <td>{{ $cost->Keterangan_Cost ?? '-' }}</td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal"
                                                    data-bs-target="#editCostModal{{ $cost->Id_Cost }}">Edit</button>
                                                <form action="{{ route('admins.reports.cost.destroy', $cost) }}"
                                                    method="POST" class="d-inline">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger"
                                                        onclick="return confirm('Delete this cost?')">Delete</button>
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
        </section>

        <!-- Permission (Power) -->
        <section class="section">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title">Permission</h5>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPowerModal">
                        Add
                    </button>
                </div>
                <div class="card-body">
                    @if ($powers->isEmpty())
                        <p class="text-muted">No permission data for this day.</p>
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
                                    @foreach ($powers as $power)
                                        <tr>
                                            <td>
                                                {{ $power->Leave_Hour_Power }}
                                                @php
                                                    $jam = floor($power->Leave_Hour_Power);
                                                    $menit = round(($power->Leave_Hour_Power - $jam) * 60);
                                                @endphp
                                                <small class="text-muted d-block">({{ $jam }} jam
                                                    {{ $menit }} menit)</small>
                                            </td>
                                            <td>{{ \Carbon\Carbon::parse($power->Start_Power)->format('Y-m-d H:i') }}</td>
                                            <td>{{ $power->member->nama ?? 'Unknown' }}</td>
                                            <td>{{ $power->Keterangan_Power ?? '-' }}</td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal"
                                                    data-bs-target="#editPowerModal{{ $power->Id_Power }}">Edit</button>
                                                <form action="{{ route('admins.reports.power.destroy', $power) }}"
                                                    method="POST" class="d-inline">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger"
                                                        onclick="return confirm('Delete this permission?')">Delete</button>
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
        </section>

        <!-- Time Handling (Penanganan) -->
        <section class="section">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title">Time Handling</h5>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                        data-bs-target="#addPenangananModal">
                        Add
                    </button>
                </div>
                <div class="card-body">
                    @if ($penanganans->isEmpty())
                        <p class="text-muted">No handling data for this day.</p>
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
                                    @foreach ($penanganans as $p)
                                        <tr>
                                            <td>
                                                {{ number_format($p->Hour_Penanganan, 2) }}
                                                @php
                                                    $sign = $p->Hour_Penanganan < 0 ? '-' : '';
                                                    $abs = abs($p->Hour_Penanganan);
                                                    $jam = floor($abs);
                                                    $menit = round(($abs - $jam) * 60);
                                                @endphp
                                                <small class="text-muted d-block">
                                                    ({{ $sign }}{{ $jam }} jam {{ $menit }}
                                                    menit)
                                                </small>
                                            </td>
                                            <td>{{ \Carbon\Carbon::parse($p->Start_Penanganan)->format('Y-m-d H:i') }}</td>
                                            <td>{{ $p->Keterangan_Penanganan }}</td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-warning"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editPenangananModal{{ $p->Id_Penanganan }}">Edit</button>
                                                <form action="{{ route('admins.reports.penanganan.destroy', $p) }}"
                                                    method="POST" class="d-inline">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger"
                                                        onclick="return confirm('Delete this handling?')">Delete</button>
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
        </section>

        <!-- Scan Data -->
        <section class="section">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title">Scan Data</h5>
                </div>
                <div class="card-body">
                    @if ($scans->isEmpty())
                        <p class="text-muted">No scan data for this day.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm" id="scansTable">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Area</th>
                                        <th>Tractor</th>
                                        <th>Assigned Hour</th>
                                        <th>Sequence No</th>
                                        <th>Type Plan</th>
                                        <th>Production Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($scans as $scan)
                                        <tr>
                                            <td>{{ $scan->Time_Scan }}</td>
                                            <td>{{ $scan->Area_Scan ?? 'Unknown' }}</td>
                                            <td>{{ $scan->tractor->Name_Tractor ?? 'Unknown' }}</td>
                                            <td>
                                                {{ $scan->Assigned_Hour_Scan }}
                                                @php
                                                    $jam = floor($scan->Assigned_Hour_Scan);
                                                    $menit = round(($scan->Assigned_Hour_Scan - $jam) * 60);
                                                @endphp
                                                <small class="text-muted d-block">({{ $jam }} jam
                                                    {{ $menit }} menit)</small>
                                            </td>
                                            <td>{{ $scan->Sequence_No_Plan }}</td>
                                            <td>{{ optional($scan->plan)->Type_Plan ?? 'Unknown' }}</td>
                                            <td>{{ $scan->Production_Date_Plan }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </section>
    </div>

    {{-- MODAL: Add Cost --}}
    <div class="modal fade" id="addCostModal" tabindex="-1">
        <div class="modal-dialog">
            <form action="{{ route('admins.reports.cost.store') }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Non Operational Cost</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Duration</label>
                            <div class="input-group">
                                <input type="number" name="jam_cost" class="form-control" placeholder="Jam"
                                    min="0" required>
                                <span class="input-group-text">jam</span>
                                <input type="number" name="menit_cost" class="form-control" placeholder="Menit"
                                    min="0" max="59" required>
                                <span class="input-group-text">menit</span>
                            </div>
                            <input type="hidden" name="Non_Operational_Cost">
                        </div>
                        <div class="mb-3">
                            <label>Kategori Non Operational</label>
                            <select name="kategori_cost" class="form-control" id="kategoriCost" required>
                                <option value="">-- Pilih Kategori --</option>
                                <option value="senam">Senam</option>
                                <option value="briefing">Briefing</option>
                                <option value="checksheet">Checksheet</option>
                                <option value="lain_lain">Lain-lain (Manual)</option>
                            </select>
                        </div>
                        <div class="mb-3" id="manualCostDescriptionContainer" style="display: none;">
                            <label>Deskripsi Manual</label>
                            <textarea name="Keterangan_Cost" class="form-control" placeholder="Masukkan deskripsi bebas..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label>Start</label>
                            <input type="date" name="date_part" class="form-control" value="{{ $dateString }}"
                                readonly>
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

    {{-- MODAL: Edit Cost --}}
    @foreach ($costs as $cost)
        <div class="modal fade" id="editCostModal{{ $cost->Id_Cost }}" tabindex="-1">
            <div class="modal-dialog">
                <form action="{{ route('admins.reports.cost.update', $cost) }}" method="POST">
                    @csrf @method('PUT')
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Cost</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label>Duration</label>
                                <div class="input-group">
                                    <input type="number" name="jam_cost" class="form-control" placeholder="Jam"
                                        min="0" required>
                                    <span class="input-group-text">jam</span>
                                    <input type="number" name="menit_cost" class="form-control" placeholder="Menit"
                                        min="0" max="59" required>
                                    <span class="input-group-text">menit</span>
                                </div>
                                <input type="hidden" name="Non_Operational_Cost">
                            </div>
                            <div class="mb-3">
                                <label>Kategori Non Operational</label>
                                <select name="kategori_cost" class="form-control" id="kategoriCost{{ $cost->Id_Cost }}"
                                    required>
                                    <option value="">-- Pilih Kategori --</option>
                                    <option value="senam" {{ $cost->Keterangan_Cost == 'Senam' ? 'selected' : '' }}>Senam
                                    </option>
                                    <option value="briefing" {{ $cost->Keterangan_Cost == 'Briefing' ? 'selected' : '' }}>
                                        Briefing</option>
                                    <option value="checksheet"
                                        {{ $cost->Keterangan_Cost == 'Checksheet' ? 'selected' : '' }}>Checksheet</option>
                                    <option value="lain_lain"
                                        {{ !in_array($cost->Keterangan_Cost, ['Senam', 'Briefing', 'Checksheet']) ? 'selected' : '' }}>
                                        Lain-lain (Manual)</option>
                                </select>
                            </div>
                            <div class="mb-3" id="manualCostDescriptionContainer{{ $cost->Id_Cost }}"
                                style="display: {{ !in_array($cost->Keterangan_Cost, ['Senam', 'Briefing', 'Checksheet']) ? 'block' : 'none' }};">
                                <label>Deskripsi Manual</label>
                                <textarea name="Keterangan_Cost" class="form-control" placeholder="Masukkan deskripsi bebas...">{{ $cost->Keterangan_Cost }}</textarea>
                            </div>
                            @php
                                $costTime = \Carbon\Carbon::parse($cost->Start_Cost)->format('H:i');
                            @endphp
                            <div class="mb-3">
                                <label>Start</label>
                                <input type="date" name="date_part" class="form-control" value="{{ $dateString }}"
                                    readonly>
                                <input type="time" name="time_part" class="form-control" value="{{ $costTime }}"
                                    required>
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

    {{-- MODAL: Add Power --}}
    <div class="modal fade" id="addPowerModal" tabindex="-1">
        <div class="modal-dialog">
            <form action="{{ route('admins.reports.power.store') }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Permission</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Member</label>
                            <select name="Id_Member" class="form-control tom-select" required>
                                <option value="">-- Select --</option>
                                @foreach ($activeMembers as $lm)
                                    <option value="{{ $lm->Id_Member }}">{{ $lm->member->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Leave Hour</label>
                            <div class="input-group">
                                <input type="number" name="jam_power" class="form-control" placeholder="Jam"
                                    min="0" required>
                                <span class="input-group-text">jam</span>
                                <input type="number" name="menit_power" class="form-control" placeholder="Menit"
                                    min="0" max="59" required>
                                <span class="input-group-text">menit</span>
                            </div>
                            <input type="hidden" name="Leave_Hour_Power">
                        </div>
                        <div class="mb-3">
                            <label>Description</label>
                            <textarea name="Keterangan_Power" class="form-control" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label>Start</label>
                            <input type="date" name="date_part" class="form-control" value="{{ $dateString }}"
                                readonly>
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

    {{-- MODAL: Edit Power --}}
    @foreach ($powers as $power)
        <div class="modal fade" id="editPowerModal{{ $power->Id_Power }}" tabindex="-1">
            <div class="modal-dialog">
                <form action="{{ route('admins.reports.power.update', $power) }}" method="POST">
                    @csrf @method('PUT')
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Permission</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label>Member</label>
                                <select name="Id_Member" class="form-control tom-select" required>
                                    <option value="">-- Select --</option>
                                    @foreach ($activeMembers as $lm)
                                        <option value="{{ $lm->Id_Member }}"
                                            {{ $lm->Id_Member == $power->Id_Member ? 'selected' : '' }}>
                                            {{ $lm->member->nama }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label>Leave Hour</label>
                                <div class="input-group">
                                    <input type="number" name="jam_power" class="form-control" placeholder="Jam"
                                        min="0" required>
                                    <span class="input-group-text">jam</span>
                                    <input type="number" name="menit_power" class="form-control" placeholder="Menit"
                                        min="0" max="59" required>
                                    <span class="input-group-text">menit</span>
                                </div>
                                <input type="hidden" name="Leave_Hour_Power">
                            </div>
                            <div class="mb-3">
                                <label>Description</label>
                                <textarea name="Keterangan_Power" class="form-control" required>{{ $power->Keterangan_Power }}</textarea>
                            </div>
                            @php
                                $powerTime = \Carbon\Carbon::parse($power->Start_Power)->format('H:i');
                            @endphp
                            <div class="mb-3">
                                <label>Start</label>
                                <input type="date" name="date_part" class="form-control" value="{{ $dateString }}"
                                    readonly>
                                <input type="time" name="time_part" class="form-control" value="{{ $powerTime }}"
                                    required>
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

    {{-- MODAL: Add Penanganan --}}
    <div class="modal fade" id="addPenangananModal" tabindex="-1">
        <div class="modal-dialog">
            <form action="{{ route('admins.reports.penanganan.store') }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Time Handling</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Hour</label>
                            <div class="input-group">
                                <input type="number" name="jam_penanganan" class="form-control" placeholder="Jam"
                                    min="0" required>
                                <span class="input-group-text">jam</span>
                                <input type="number" name="menit_penanganan" class="form-control" placeholder="Menit"
                                    min="0" max="59" required>
                                <span class="input-group-text">menit</span>
                            </div>
                            <input type="hidden" name="Hour_Penanganan">
                        </div>
                        <div class="mb-3">
                            <label>Kategori Penanganan</label>
                            <select name="kategori_penanganan" class="form-control" id="kategoriPenanganan" required>
                                <option value="">-- Pilih Kategori --</option>
                                <option value="fix_back_up_proses">Fix Back Up Proses / 工程の応援</option>
                                <option value="back_up_absensi">Back Up Absensi / 欠勤応援</option>
                                <option value="bantuan_pic_absensi">Bantuan ke PIC Absensi / 欠勤対応の応援</option>
                                <option value="back_up_line_stop">Back Up Line Stop / Irregular / イレギュラー対応</option>
                                <option value="perbantuan_area_lain">Perbantuan area lain / 他部署応援 【－】</option>
                                <option value="lembur_produksi">Lembur Produksi / 生産残業</option>
                                <option value="lembur_mante">Lembur Mente / メンテ残業</option>
                                <option value="lain_lain">Lain-lain (Manual)</option>
                            </select>
                        </div>
                        <div class="mb-3" id="manualDescriptionContainer" style="display: none;">
                            <label>Deskripsi Manual</label>
                            <textarea name="Keterangan_Penanganan" class="form-control" placeholder="Masukkan deskripsi bebas..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label>Start</label>
                            <input type="date" name="date_part" class="form-control" value="{{ $dateString }}"
                                readonly>
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

    {{-- MODAL: Edit Penanganan --}}
    @foreach ($penanganans as $p)
        <div class="modal fade" id="editPenangananModal{{ $p->Id_Penanganan }}" tabindex="-1">
            <div class="modal-dialog">
                <form action="{{ route('admins.reports.penanganan.update', $p) }}" method="POST">
                    @csrf @method('PUT')
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Time Handling</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label>Hour</label>
                                <div class="input-group">
                                    <input type="number" name="jam_penanganan" class="form-control" placeholder="Jam"
                                        min="0" required>
                                    <span class="input-group-text">jam</span>
                                    <input type="number" name="menit_penanganan" class="form-control"
                                        placeholder="Menit" min="0" max="59" required>
                                    <span class="input-group-text">menit</span>
                                </div>
                                <input type="hidden" name="Hour_Penanganan">
                            </div>
                            <div class="mb-3">
                                <label>Kategori Penanganan</label>
                                <select name="kategori_penanganan" class="form-control"
                                    id="kategoriPenanganan{{ $p->Id_Penanganan }}" required>
                                    <option value="">-- Pilih Kategori --</option>
                                    <option value="fix_back_up_proses"
                                        {{ $p->Keterangan_Penanganan == 'Fix Back Up Proses / 工程の応援' ? 'selected' : '' }}>
                                        Fix Back Up Proses / 工程の応援</option>
                                    <option value="back_up_absensi"
                                        {{ $p->Keterangan_Penanganan == 'Back Up Absensi / 欠勤応援' ? 'selected' : '' }}>Back
                                        Up Absensi / 欠勤応援</option>
                                    <option value="bantuan_pic_absensi"
                                        {{ $p->Keterangan_Penanganan == 'Bantuan ke PIC Absensi / 欠勤対応の応援' ? 'selected' : '' }}>
                                        Bantuan ke PIC Absensi / 欠勤対応の応援</option>
                                    <option value="back_up_line_stop"
                                        {{ $p->Keterangan_Penanganan == 'Back Up Line Stop / Irregular / イレギュラー対応' ? 'selected' : '' }}>
                                        Back Up Line Stop / Irregular / イレギュラー対応</option>
                                    <option value="perbantuan_area_lain"
                                        {{ $p->Keterangan_Penanganan == 'Perbantuan area lain / 他部署応援 【－】' ? 'selected' : '' }}>
                                        Perbantuan area lain / 他部署応援 【－】</option>
                                    <option value="lembur_produksi"
                                        {{ $p->Keterangan_Penanganan == 'Lembur Produksi / 生産残業' ? 'selected' : '' }}>
                                        Lembur Produksi / 生産残業</option>
                                    <option value="lembur_mante"
                                        {{ $p->Keterangan_Penanganan == 'Lembur Mente / メンテ残業' ? 'selected' : '' }}>
                                        Lembur Mente / メンテ残業</option>
                                    <option value="lain_lain"
                                        {{ !in_array($p->Keterangan_Penanganan, [
                                            'Fix Back Up Proses / 工程の応援',
                                            'Back Up Absensi / 欠勤応援',
                                            'Bantuan ke PIC Absensi / 欠勤対応の応援',
                                            'Back Up Line Stop / Irregular / イレギュラー対応',
                                            'Perbantuan area lain / 他部署応援 【－】',
                                            'Lembur Produksi / 生産残業',
                                            'Lembur Mente / メンテ残業',
                                        ])
                                            ? 'selected'
                                            : '' }}>
                                        Lain-lain (Manual)</option>
                                </select>
                            </div>
                            <div class="mb-3" id="manualDescriptionContainer{{ $p->Id_Penanganan }}"
                                style="display: {{ !in_array($p->Keterangan_Penanganan, [
                                    'Fix Back Up Proses / 工程の応援',
                                    'Back Up Absensi / 欠勤応援',
                                    'Bantuan ke PIC Absensi / 欠勤対応の応援',
                                    'Back Up Line Stop / Irregular / イレギュラー対応',
                                    'Perbantuan area lain / 他部署応援 【－】',
                                    'Lembur Produksi / 生産残業',
                                    'Lembur Mente / メンテ残業',
                                ])
                                    ? 'block'
                                    : 'none' }};">
                                <label>Deskripsi Manual</label>
                                <textarea name="Keterangan_Penanganan" class="form-control" placeholder="Masukkan deskripsi bebas...">{{ $p->Keterangan_Penanganan }}</textarea>
                            </div>
                            <div class="mb-3">
                                <label>Catatan Internal (Opsional)</label>
                                <input type="text" name="catatan_internal" class="form-control"
                                    value="{{ $p->catatan_internal ?? '' }}"
                                    placeholder="Misal: untuk laporan internal...">
                            </div>
                            @php
                                $penangananTime = \Carbon\Carbon::parse($p->Start_Penanganan)->format('H:i');
                            @endphp
                            <div class="mb-3">
                                <label>Start</label>
                                <input type="date" name="date_part" class="form-control" value="{{ $dateString }}"
                                    readonly>
                                <input type="time" name="time_part" class="form-control"
                                    value="{{ $penangananTime }}" required>
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
    <script src="{{ asset('assets/js/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/js/dataTables.min.js') }}"></script>
    <script>
        function jamMenitToDecimal(jam, menit) {
            jam = parseFloat(jam) || 0;
            menit = parseFloat(menit) || 0;
            return jam + menit / 60;
        }

        // COST
        document.querySelector('#addCostModal form')?.addEventListener('submit', function(e) {
            const jam = this.querySelector('[name="jam_cost"]').value || 0;
            const menit = this.querySelector('[name="menit_cost"]').value || 0;
            this.querySelector('[name="Non_Operational_Cost"]').value = jamMenitToDecimal(jam, menit).toFixed(2);
        });
        @foreach ($costs as $cost)
            document.querySelector('#editCostModal{{ $cost->Id_Cost }} form')?.addEventListener('submit', function(e) {
                const jam = this.querySelector('[name="jam_cost"]').value || 0;
                const menit = this.querySelector('[name="menit_cost"]').value || 0;
                this.querySelector('[name="Non_Operational_Cost"]').value = jamMenitToDecimal(jam, menit).toFixed(
                    2);
            });
        @endforeach

        // POWER
        document.querySelector('#addPowerModal form')?.addEventListener('submit', function(e) {
            const jam = this.querySelector('[name="jam_power"]').value || 0;
            const menit = this.querySelector('[name="menit_power"]').value || 0;
            this.querySelector('[name="Leave_Hour_Power"]').value = jamMenitToDecimal(jam, menit).toFixed(2);
        });
        @foreach ($powers as $power)
            document.querySelector('#editPowerModal{{ $power->Id_Power }} form')?.addEventListener('submit', function(e) {
                const jam = this.querySelector('[name="jam_power"]').value || 0;
                const menit = this.querySelector('[name="menit_power"]').value || 0;
                this.querySelector('[name="Leave_Hour_Power"]').value = jamMenitToDecimal(jam, menit).toFixed(2);
            });
        @endforeach

        // PENANGANAN
        document.querySelector('#addPenangananModal form')?.addEventListener('submit', function(e) {
            const jam = this.querySelector('[name="jam_penanganan"]').value || 0;
            const menit = this.querySelector('[name="menit_penanganan"]').value || 0;
            this.querySelector('[name="Hour_Penanganan"]').value = jamMenitToDecimal(jam, menit).toFixed(2);
        });
        @foreach ($penanganans as $p)
            document.querySelector('#editPenangananModal{{ $p->Id_Penanganan }} form')?.addEventListener('submit',
                function(e) {
                    const jam = this.querySelector('[name="jam_penanganan"]').value || 0;
                    const menit = this.querySelector('[name="menit_penanganan"]').value || 0;
                    this.querySelector('[name="Hour_Penanganan"]').value = jamMenitToDecimal(jam, menit).toFixed(2);
                });
        @endforeach

        // TomSelect & Kategori
        document.addEventListener('DOMContentLoaded', function() {
            // TomSelect
            document.querySelectorAll('.tom-select').forEach(select => {
                new TomSelect(select, {
                    placeholder: '-- Select Member --',
                    allowEmptyOption: true,
                    plugins: ['dropdown_input']
                });
            });

            // ✅ COST: Handler untuk Add dan Edit
            const setupCostHandler = (selectId, containerId) => {
                const select = document.getElementById(selectId);
                const container = document.getElementById(containerId);
                if (!select || !container) return;

                select.addEventListener('change', function() {
                    if (this.value === 'lain_lain') {
                        container.style.display = 'block';
                        container.querySelector('textarea').required = true;
                    } else {
                        container.style.display = 'none';
                        container.querySelector('textarea').required = false;
                        // Isi otomatis
                        const map = {
                            senam: 'Senam',
                            briefing: 'Briefing',
                            checksheet: 'Checksheet'
                        };
                        container.querySelector('textarea').value = map[this.value] || '';
                    }
                });
            };

            // Add Cost
            setupCostHandler('kategoriCost', 'manualCostDescriptionContainer');

            // Edit Cost
            @foreach ($costs as $cost)
                setupCostHandler('kategoriCost{{ $cost->Id_Cost }}',
                    'manualCostDescriptionContainer{{ $cost->Id_Cost }}');
            @endforeach

            // ✅ PENANGANAN: Handler
            const setupPenangananHandler = (selectId, containerId) => {
                const select = document.getElementById(selectId);
                const container = document.getElementById(containerId);
                if (!select || !container) return;

                select.addEventListener('change', function() {
                    if (this.value === 'lain_lain') {
                        container.style.display = 'block';
                        container.querySelector('textarea').required = true;
                    } else {
                        container.style.display = 'none';
                        container.querySelector('textarea').required = false;
                        container.querySelector('textarea').value = this.options[this.selectedIndex]
                            .text;
                    }
                });
            };

            setupPenangananHandler('kategoriPenanganan', 'manualDescriptionContainer');
            @foreach ($penanganans as $p)
                setupPenangananHandler('kategoriPenanganan{{ $p->Id_Penanganan }}',
                    'manualDescriptionContainer{{ $p->Id_Penanganan }}');
            @endforeach
        });

        // DataTable
        $(document).ready(function() {
            if ($('#scansTable').length) {
                $('#scansTable').DataTable({
                    pageLength: 50,
                    responsive: true,
                    language: {
                        search: "Cari:",
                        lengthMenu: "Tampilkan _MENU_ data",
                        info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                        paginate: {
                            previous: "«",
                            next: "»"
                        }
                    }
                });
            }
        });
    </script>
@endsection
