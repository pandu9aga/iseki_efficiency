@extends('layouts.leader')

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

            <!-- Area Tabs -->
            @if($assignedAreas->count() > 1)
            <ul class="nav nav-tabs mb-3">
                @foreach($assignedAreas as $a)
                <li class="nav-item">
                    <a class="nav-link {{ $a->Id_Area == $area->Id_Area ? 'active' : '' }}"
                        href="{{ route('leaders.planning.create', ['date' => $dateString, 'area' => $a->Id_Area]) }}">
                        {{ $a->Name_Area }}
                    </a>
                </li>
                @endforeach
            </ul>
            @endif

            <form action="{{ route('leaders.planning.store') }}" method="POST" id="planningForm">
                @csrf
                <input type="hidden" name="production_date" value="{{ $dateString }}">
                <input type="hidden" name="area_id" value="{{ $area->Id_Area }}"> <!-- Important for deletion scope -->

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
                                                    @if ($plan && $plan['nik']==$member->nik) selected @endif>
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

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Simpan Rencana Harian</button>
                    <a href="{{ route('leaders.dashboard') }}" class="btn btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function changeDate(date) {
        const urlParams = new URLSearchParams(window.location.search);
        const area = urlParams.get('area');
        let url = "{{ route('leaders.planning.create') }}?date=" + date;
        if (area) {
            url += "&area=" + area;
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
        // Prev/Next date navigation
        document.getElementById('prevPlanDate')?.addEventListener('click', () => shiftPlanDate(-1));
        document.getElementById('nextPlanDate')?.addEventListener('click', () => shiftPlanDate(1));
        document.getElementById('datePicker')?.addEventListener('change', function() { changeDate(this.value); });

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