<?php

namespace App\Http\Controllers\Leader;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\DailyJob;
use App\Models\Area;
use App\Models\JobMember;
use App\Models\Member;
use Illuminate\Support\Facades\DB;

class LeaderDailyPlanningController extends Controller
{
    public function create(Request $request)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 2) {
            abort(403);
        }

        $user = \App\Models\User::with('areas')->findOrFail(session('Id_User'));

        if ($user->areas->isEmpty()) {
            return redirect()->back()->withErrors(['error' => 'Akun Anda belum ditugaskan ke area mana pun.']);
        }

        // Determine active area
        $activeAreaId = $request->query('area');
        if ($activeAreaId) {
            $activeArea = $user->areas->where('Id_Area', $activeAreaId)->first();
            if (!$activeArea) {
                $activeArea = $user->areas->first();
            }
        } else {
            $activeArea = $user->areas->first();
        }

        $area = Area::with('jobMembers')->findOrFail($activeArea->Id_Area);

        $dateString = $request->input('date', now()->format('Y-m-d'));
        $productionDateForQuery = Carbon::parse($dateString)->format('Ymd');

        $allMembers = Member::all();

        $existingPlans = DailyJob::where('Production_Date_Plan', $productionDateForQuery)
            ->where('Id_Area', $area->Id_Area)
            ->get();

        if ($existingPlans->isNotEmpty()) {
            $planMap = $this->buildPlanMap($existingPlans);
        } else {
            $lastPlanDate = DailyJob::where('Id_Area', $area->Id_Area)
                ->max('Production_Date_Plan');

            if ($lastPlanDate) {
                $lastPlans = DailyJob::where('Production_Date_Plan', $lastPlanDate)
                    ->where('Id_Area', $area->Id_Area)
                    ->get();
                $planMap = $this->buildPlanMap($lastPlans);
            } else {
                $planMap = [];
            }
        }

        $assignedAreas = $user->areas;

        return view('leaders.planning.create', compact(
            'area',
            'assignedAreas',
            'allMembers',
            'dateString',
            'planMap'
        ));
    }

    private function buildPlanMap($dailyJobs)
    {
        $planMap = [];
        foreach ($dailyJobs as $plan) {
            $planMap[$plan->Id_Job_Member] = [
                'nik' => $plan->Nik_Daily_Job,
                'type' => $plan->Type_Daily_Job,
                'replace_nik' => $plan->Nik_Replace_Daily_Job,
            ];
        }
        return $planMap;
    }

    public function store(Request $request)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 2) {
            abort(403);
        }

        $request->validate([
            'production_date' => 'required|date',
            'area_id' => 'required|exists:areas,Id_Area',
            'assignments' => 'nullable|array',
            'assignments.*.member_id' => 'nullable|integer|exists:rifa.employees,id',
        ]);

        $productionDateRaw = $request->input('production_date');
        $productionDate = Carbon::parse($productionDateRaw)->format('Ymd');
        $assignments = $request->input('assignments', []);
        $currentAreaId = $request->input('area_id');

        // ✅ SECURITY: Validasi user punya akses ke area ini
        $user = \App\Models\User::with('areas')->findOrFail(session('Id_User'));
        if (!$user->areas->contains('Id_Area', $currentAreaId)) {
            abort(403, 'You are not assigned to this area.');
        }

        // ✅ FIX #2: Guard against empty assignments (prevent accidental deletion)
        if (empty($assignments)) {
            return redirect()->route('leaders.planning.create', [
                'date' => $productionDateRaw,
                'area' => $currentAreaId,
            ])->with('warning', 'Tidak ada assignment yang dikirim. Data lama tidak dihapus.');
        }

        // ✅ FIX #3 & #4: Pre-validate assignments, track skips, block cross-area injection
        $validAssignments = [];
        $skipped = 0;
        $skippedReasons = [];

        foreach ($assignments as $jobId => $data) {
            if (!is_numeric($jobId)) {
                $skipped++;
                continue;
            }

            $memberId = $data['member_id'] ?? null;
            if (!$memberId) {
                $skipped++;
                continue;
            }

            $member = Member::find($memberId);
            $jobMember = JobMember::find($jobId);

            if (!$member) {
                $skipped++;
                $skippedReasons[] = "Job #{$jobId}: Member ID {$memberId} tidak ditemukan";
                continue;
            }

            if (!$jobMember) {
                $skipped++;
                $skippedReasons[] = "Job #{$jobId}: Pekerjaan tidak ditemukan";
                continue;
            }

            // ✅ FIX #4: Block cross-area job injection
            if ($jobMember->Id_Area != $currentAreaId) {
                $skipped++;
                $skippedReasons[] = "Job #{$jobId}: Pekerjaan bukan milik area ini";
                continue;
            }

            $type = 'asli';
            $replaceNikFinal = null;

            $validAssignments[] = [
                'nik' => $member->nik,
                'jobId' => $jobId,
                'areaId' => $jobMember->Id_Area,
                'type' => $type,
                'replaceNik' => $replaceNikFinal,
            ];
        }

        // ✅ FIX #2 (extra): If ALL assignments were invalid, don't delete existing data
        if (empty($validAssignments)) {
            $msg = 'Semua assignment tidak valid, data lama tidak dihapus.';
            if (!empty($skippedReasons)) {
                $msg .= ' Detail: ' . implode('; ', array_slice($skippedReasons, 0, 5));
            }
            return redirect()->route('leaders.planning.create', [
                'date' => $productionDateRaw,
                'area' => $currentAreaId,
            ])->withErrors(['assignments' => $msg]);
        }

        // ✅ FIX #5: Wrap in DB::transaction for atomicity
        DB::transaction(function () use ($productionDate, $currentAreaId, $validAssignments) {
            // Delete existing plans for this area and date (inside transaction)
            DailyJob::where('Production_Date_Plan', $productionDate)
                ->where('Id_Area', $currentAreaId)
                ->delete();

            // Insert all validated assignments
            foreach ($validAssignments as $item) {
                $sequence = 'SEQ_' . $item['jobId'] . '_' . now()->format('Ymd') . '_' . Str::random(5);

                DailyJob::create([
                    'Nik_Daily_Job' => $item['nik'],
                    'Id_Job_Member' => $item['jobId'],
                    'Id_Area' => $item['areaId'],
                    'Sequence_No_Plan' => $sequence,
                    'Production_Date_Plan' => $productionDate,
                    'Type_Daily_Job' => $item['type'],
                    'Nik_Replace_Daily_Job' => $item['replaceNik'],
                ]);
            }
        });

        // ✅ FIX #3: Report skipped assignments to user
        $savedCount = count($validAssignments);
        $message = "Rencana harian berhasil disimpan ({$savedCount} assignment).";
        if ($skipped > 0) {
            $message .= " {$skipped} assignment di-skip.";
        }

        return redirect()->route('leaders.planning.create', [
            'date' => $productionDateRaw,
            'area' => $currentAreaId,
        ])->with('success', $message);
    }
}
