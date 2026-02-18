<?php

namespace App\Http\Controllers\Leader;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JobMember;
use App\Models\Area;
use App\Models\User;

class LeaderJobMemberController extends Controller
{
    public function index(Request $request)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 2) {
            abort(403);
        }

        // ✅ Ambil user dengan relasi areas
        $user = User::with('areas')->findOrFail(session('Id_User'));

        if ($user->areas->isEmpty()) {
            return redirect()->back()->withErrors(['error' => 'Akun Anda belum ditugaskan ke area mana pun.']);
        }

        // ✅ Tentukan active area
        $activeAreaId = $request->query('area');
        if ($activeAreaId) {
            $activeArea = $user->areas->where('Id_Area', $activeAreaId)->first();
            if (!$activeArea) {
                $activeArea = $user->areas->first();
            }
        } else {
            $activeArea = $user->areas->first();
        }

        $area = $activeArea;
        $activeAreaId = $area->Id_Area;

        // ✅ Hanya ambil JobMember untuk area ini
        $jobMembers = JobMember::where('Id_Area', $activeAreaId)->get();

        $assignedAreas = $user->areas;

        return view('leaders.jobs.index', compact('jobMembers', 'area', 'assignedAreas'));
    }

    public function store(Request $request)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 2) {
            abort(403);
        }

        $request->validate([
            'Name_Job_Member' => 'required|string|max:255',
            'Id_Area' => 'required|exists:areas,Id_Area',
        ]);

        // ✅ PERBAIKAN: Validasi user assigned ke area ini (MULTI-AREA)
        $user = User::with('areas')->findOrFail(session('Id_User'));
        if (!$user->areas->contains('Id_Area', $request->Id_Area)) {
            abort(403, 'You are not assigned to this area.');
        }

        JobMember::create([
            'Name_Job_Member' => $request->Name_Job_Member,
            'Id_Area' => $request->Id_Area,
        ]);

        return redirect()->route('leaders.jobs.manage', ['area' => $request->Id_Area])
            ->with('success', 'Pekerjaan berhasil ditambahkan.');
    }

    public function update(Request $request, JobMember $jobMember)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 2) {
            abort(403);
        }

        $request->validate([
            'Name_Job_Member' => 'required|string|max:255',
        ]);

        // ✅ PERBAIKAN: Gunakan relasi areas (MULTI-AREA)
        $user = User::with('areas')->findOrFail(session('Id_User'));

        if (!$user->areas->contains('Id_Area', $jobMember->Id_Area)) {
            abort(403, 'You cannot edit jobs from areas you are not assigned to.');
        }

        $jobMember->update($request->only('Name_Job_Member'));

        // ✅ Redirect dengan area parameter
        return redirect()->route('leaders.jobs.manage', ['area' => $jobMember->Id_Area])
            ->with('success', 'Pekerjaan berhasil diperbarui.');
    }

    public function destroy(JobMember $jobMember)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 2) {
            abort(403);
        }

        // ✅ PERBAIKAN: Gunakan relasi areas (MULTI-AREA)
        $user = User::with('areas')->findOrFail(session('Id_User'));

        if (!$user->areas->contains('Id_Area', $jobMember->Id_Area)) {
            abort(403, 'You cannot delete jobs from areas you are not assigned to.');
        }

        $areaId = $jobMember->Id_Area; // Simpan sebelum dihapus
        $jobMember->delete();

        // ✅ Redirect dengan area parameter
        return redirect()->route('leaders.jobs.manage', ['area' => $areaId])
            ->with('success', 'Pekerjaan berhasil dihapus.');
    }
}
