@extends('layouts.leader')

@section('content')
<div class="col-sm-12">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="text-primary">Perencanaan Harian</h4>
            <div>
                <span class="text-muted me-2">Tanggal:</span>
                <input type="date" id="datePicker" class="form-control d-inline-block" style="width: auto;"
                    value="{{ $dateString }}" onchange="changeDate(this.value)">
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
                                        <th>Type</th>
                                        <th>Replace Member (Jika Pengganti)</th>
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
                                        <td>
                                            <select
                                                name="assignments[{{ $job->Id_Job_Member }}][type]"
                                                class="form-select type-select"
                                                onchange="toggleReplaceField(this)">
                                                <option value="asli"
                                                    @if ($plan && $plan['type']=='asli' ) selected @endif>
                                                    Asli
                                                </option>
                                                <option value="pengganti"
                                                    @if ($plan && $plan['type']=='pengganti' ) selected @endif>
                                                    Pengganti
                                                </option>
                                            </select>
                                        </td>
                                        <td>
                                            <select
                                                name="assignments[{{ $job->Id_Job_Member }}][replace_nik]"
                                                class="form-select replace-member-select"
                                                style="display: {{ $plan && $plan['type'] == 'pengganti' ? 'block' : 'none' }};">
                                                <option value="">-- Pilih Member yang Menggantikan --</option>
                                                @foreach ($allMembers as $member)
                                                <option value="{{ $member->nik }}"
                                                    @if ($plan && $plan['replace_nik']==$member->nik) selected @endif>
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
        // Keep current Area when changing date
        const urlParams = new URLSearchParams(window.location.search);
        const area = urlParams.get('area');
        let url = "{{ route('leaders.planning.create') }}?date=" + date;
        if (area) {
            url += "&area=" + area;
        }
        window.location.href = url;
    }

    function toggleReplaceField(typeSelect) {
        const row = typeSelect.closest('tr');
        const select = row.querySelector('.replace-member-select');
        if (typeSelect.value === 'pengganti') {
            select.style.display = 'block';
        } else {
            select.style.display = 'none';
            select.value = '';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.type-select').forEach(toggleReplaceField);

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
        initTomSelect('.replace-member-select', '-- Pilih Member yang Digantikan --');

        const form = document.getElementById('planningForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                let hasError = false;
                document.querySelectorAll('.type-select').forEach(select => {
                    const row = select.closest('tr');
                    const memberSelect = row.querySelector('[name$="[member_id]"]');
                    const replaceSelect = row.querySelector('.replace-member-select');

                    if (memberSelect?.value && select.value === 'pengganti' && !replaceSelect?.value) {
                        hasError = true;
                        replaceSelect?.classList.add('is-invalid');
                    } else {
                        replaceSelect?.classList.remove('is-invalid');
                    }
                });

                if (hasError) {
                    e.preventDefault();
                    alert('Silakan pilih member yang digantikan untuk tipe "Pengganti".');
                }
            });
        }
    });
</script>
@endsection

@section('style')
<link href="{{ asset('assets/css/tom-select.bootstrap5.css') }}" rel="stylesheet">
@endsection

@section('script')
<script src="{{ asset('assets/js/tom-select.complete.min.js') }}"></script>
@endsection