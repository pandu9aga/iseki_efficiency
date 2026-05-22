@extends('layouts.admin')

@section('content')
<div class="col-sm-12">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="text-primary">Perencanaan Harian</h4>
            <div class="d-flex align-items-center gap-2">
                <span class="text-muted">Tanggal:</span>
                <div class="input-group" style="width:auto;">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="prevPlanDate">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <input type="date" id="datePicker" class="form-control form-control-sm text-center fw-bold"
                        style="width:150px;" value="{{ $dateString }}">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="nextPlanDate">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <ul class="nav nav-tabs mb-3" id="areaPlanningTabs" role="tablist">
                @foreach ($areas as $index => $area)
                <li class="nav-item" role="presentation">
                    <button
                        class="nav-link {{ $activeAreaId == $area->Id_Area ? 'active' : ($index === 0 && !$activeAreaId ? 'active' : '') }}"
                        id="area-{{ $area->Id_Area }}-tab" data-bs-toggle="tab"
                        data-bs-target="#area-{{ $area->Id_Area }}" type="button" role="tab"
                        aria-controls="area-{{ $area->Id_Area }}"
                        aria-selected="{{ $activeAreaId == $area->Id_Area ? 'true' : ($index === 0 && !$activeAreaId ? 'true' : 'false') }}">
                        {{ $area->Name_Area }}
                    </button>
                </li>
                @endforeach
            </ul>

            <form action="{{ route('admins.planning.store') }}" method="POST" id="planningForm">
                @csrf
                <input type="hidden" name="production_date" value="{{ $dateString }}">

                <div class="tab-content" id="areaPlanningTabContent">
                    @foreach ($areas as $index => $area)
                    <div class="tab-pane fade {{ $activeAreaId == $area->Id_Area ? 'show active' : ($index === 0 && !$activeAreaId ? 'show active' : '') }}"
                        id="area-{{ $area->Id_Area }}" role="tabpanel"
                        aria-labelledby="area-{{ $area->Id_Area }}-tab">
                        <div class="card border">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Area: <strong>{{ $area->Name_Area }}</strong></h5>
                            </div>
                            <div class="card-body p-2" style="max-height: 500px; overflow-y: auto;">
                                @if ($area->jobMembers->isEmpty())
                                <p class="text-center text-muted">Tidak ada job untuk area ini.</p>
                                @else
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Job</th>
                                                <th>Member</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($area->jobMembers as $job)
                                            @php
                                            $plan = $planMap[$job->Id_Job_Member] ?? null;
                                            @endphp
                                            <tr>
                                                <td>{{ $job->Name_Job_Member }}</td>
                                                <td>
                                                    <select
                                                        name="assignments[{{ $job->Id_Job_Member }}][member_id]"
                                                        class="form-select tom-select-member">
                                                        <option value="">-- Pilih Member --</option>
                                                        @foreach ($allMembers as $member)
                                                        <option value="{{ $member->id }}"
                                                            @if ($plan && $plan['member_id']==$member->id) selected @endif>
                                                            {{ $member->nama }} ({{ $member->nik }})
                                                        </option>
                                                        @endforeach
                                                    </select>
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
                    @endforeach
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Simpan Rencana Harian</button>
                    <a href="{{ route('admins.dashboard') }}" class="btn btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function changeDate(date) {
        // Ambil area aktif dari sessionStorage atau dari tab yang aktif
        let activeAreaId = sessionStorage.getItem('activePlanningArea');
        if (!activeAreaId) {
            const activeTab = document.querySelector('.nav-link.active');
            if (activeTab) {
                const target = activeTab.getAttribute('data-bs-target');
                if (target) {
                    activeAreaId = target.replace('#area-', '');
                }
            }
        }
        let url = "{{ route('admins.planning.create') }}?date=" + date;
        if (activeAreaId) {
            url += "&area=" + activeAreaId;
        }
        window.location.href = url;
    }

    function shiftPlanDate(delta) {
        const dp = document.getElementById('datePicker');
        if (!dp || !dp.value) return;
        const parts = dp.value.split('-');
        const d = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
        d.setDate(d.getDate() + delta);
        changeDate(`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`);
    }


    document.addEventListener('DOMContentLoaded', function() {
        // Aktifkan tab berdasarkan parameter URL atau sessionStorage
        const urlParams = new URLSearchParams(window.location.search);
        const areaFromUrl = urlParams.get('area');
        const areaFromStorage = sessionStorage.getItem('activePlanningArea');
        const finalAreaId = areaFromUrl || areaFromStorage;

        if (finalAreaId) {
            const tabButton = document.querySelector(`button[data-bs-target="#area-${finalAreaId}"]`);
            if (tabButton) {
                const tab = new bootstrap.Tab(tabButton);
                tab.show();
            }
        }

        // Simpan tab aktif ke sessionStorage saat berpindah
        document.querySelectorAll('#areaPlanningTabs button[data-bs-toggle="tab"]').forEach(button => {
            button.addEventListener('shown.bs.tab', function() {
                const target = this.getAttribute('data-bs-target');
                if (target) {
                    const areaId = target.replace('#area-', '');
                    sessionStorage.setItem('activePlanningArea', areaId);
                }
            });
        });

        // Prev/Next date navigation
        document.getElementById('prevPlanDate')?.addEventListener('click', () => shiftPlanDate(-1));
        document.getElementById('nextPlanDate')?.addEventListener('click', () => shiftPlanDate(1));
        document.getElementById('datePicker')?.addEventListener('change', function() { changeDate(this.value); });

        // Inisialisasi TomSelect
        const initTomSelect = (selector, placeholder) => {
            document.querySelectorAll(selector).forEach(el => {
                if (!el.tomselect) {
                    new TomSelect(el, {
                        placeholder: placeholder,
                        allowEmptyOption: true,
                        plugins: ['dropdown_input', 'clear_button'],
                    });
                }
            });
        };

        initTomSelect('.tom-select-member', '-- Pilih atau Cari Member --');
    });
</script>
@endsection

@section('style')
<link href="{{ asset('assets/css/tom-select.bootstrap5.css') }}" rel="stylesheet">
@endsection

@section('script')
<script src="{{ asset('assets/js/tom-select.complete.min.js') }}"></script>
@endsection