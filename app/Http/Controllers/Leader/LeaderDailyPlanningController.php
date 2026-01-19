<?php

namespace App\Http\Controllers\Leader;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon; // ← TAMBAHKAN INI
use App\Models\DailyJob;
use App\Models\Area;
use App\Models\JobMember;
use App\Models\Member;

class LeaderDailyPlanningController extends Controller
{
    // app/Http/Controllers/Leader/LeaderDailyPlanningController.php

    public function create(Request $request)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 2) {
            abort(403);
        }

        // Ambil user yang sedang login
        $user = \App\Models\User::findOrFail(session('Id_User'));

        // Pastikan user punya Id_Area
        if (!$user->Id_Area) {
            return redirect()->back()->withErrors(['error' => 'Akun Anda belum ditugaskan ke area mana pun.']);
        }

        // Ambil area berdasarkan Id_Area user
        $area = \App\Models\Area::with('jobMembers')->findOrFail($user->Id_Area);

        // Ambil tanggal
        $dateString = $request->input('date', now()->format('Y-m-d'));
        $productionDateForQuery = \Carbon\Carbon::parse($dateString)->format('Ymd');

        // Ambil semua member
        $allMembers = \App\Models\Member::all();

        // Cari rencana untuk area ini pada tanggal tersebut
        $existingPlans = \App\Models\DailyJob::with('member')
            ->where('Production_Date_Plan', $productionDateForQuery)
            ->where('Id_Area', $area->Id_Area)
            ->get();

        if ($existingPlans->isNotEmpty()) {
            $planMap = $this->buildPlanMap($existingPlans);
        } else {
            // Cari rencana terakhir sebelum tanggal ini (untuk area ini saja)
            $lastPlanDate = \App\Models\DailyJob::where('Production_Date_Plan', '<', $productionDateForQuery)
                ->where('Id_Area', $area->Id_Area)
                ->max('Production_Date_Plan');

            if ($lastPlanDate) {
                $lastPlans = \App\Models\DailyJob::with('member')
                    ->where('Production_Date_Plan', $lastPlanDate)
                    ->where('Id_Area', $area->Id_Area)
                    ->get();
                $planMap = $this->buildPlanMap($lastPlans);
            } else {
                $planMap = [];
            }
        }

        // Kirim $area (bukan $areas)
        return view('leaders.planning.create', compact(
            'area',          // ✅ ini yang penting
            'allMembers',
            'dateString',
            'planMap'
        ));
    }    // Helper untuk bangun planMap
    private function buildPlanMap($dailyJobs)
    {
        $planMap = [];
        foreach ($dailyJobs as $plan) {
            $memberId = $plan->member ? $plan->member->id : null;
            $planMap[$plan->Id_Job_Member] = [
                'member_id' => $memberId,
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
            'assignments' => 'nullable|array',
            'assignments.*.member_id' => 'nullable|integer|exists:rifa.employees,id',
            'assignments.*.type' => 'nullable|in:asli,pengganti',
            'assignments.*.replace_nik' => 'nullable|string|max:20',
        ]);

        // 🔥 Konversi tanggal ke format YYYYMMDD
        $productionDateRaw = $request->input('production_date'); // '2025-01-02'
        $productionDate = Carbon::parse($productionDateRaw)->format('Ymd'); // '20250102'

        $assignments = $request->input('assignments', []);

        // Hapus rencana lama untuk tanggal ini (gunakan format baru)
        DailyJob::where('Production_Date_Plan', $productionDate)->delete();

        $firstAreaId = null;

        foreach ($assignments as $jobId => $data) {
            if (!is_numeric($jobId)) continue;

            $memberId = $data['member_id'] ?? null;
            if (!$memberId) continue;

            $member = Member::find($memberId);
            $jobMember = JobMember::find($jobId);

            if (!$member || !$jobMember) continue;

            if (!$firstAreaId) {
                $firstAreaId = $jobMember->Id_Area;
            }

            $type = ($data['type'] ?? 'asli') === 'pengganti' ? 'pengganti' : 'asli';
            $replaceNik = trim($data['replace_nik'] ?? '');

            $replaceNikFinal = null;
            if ($type === 'pengganti' && $replaceNik) {
                $replaceMember = Member::where('nik', $replaceNik)->first();
                $replaceNikFinal = $replaceMember?->nik;
            }

            $sequence = 'SEQ_' . $jobId . '_' . now()->format('Ymd') . '_' . Str::random(5);

            DailyJob::create([
                'Nik_Daily_Job' => $member->nik,
                'Id_Job_Member' => $jobId,
                'Id_Area' => $jobMember->Id_Area,
                'Sequence_No_Plan' => $sequence,
                'Production_Date_Plan' => $productionDate, // 🔥 Sudah format YYYYMMDD
                'Type_Daily_Job' => $type,
                'Nik_Replace_Daily_Job' => $replaceNikFinal,
            ]);
        }

        if ($firstAreaId) {
            session(['active_area_id' => $firstAreaId]);
        }

        // 🔥 Redirect dengan tanggal dalam format Y-m-d (untuk date picker)
        return redirect()->route('leaders.planning.create', ['date' => $productionDateRaw])
            ->with('success', 'Rencana harian berhasil disimpan.');
    }
}
