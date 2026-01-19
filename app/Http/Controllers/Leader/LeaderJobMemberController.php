<?php

namespace App\Http\Controllers\Leader;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JobMember;
use App\Models\Area;
use App\Models\User; // ← tambahkan ini

class LeaderJobMemberController extends Controller
{
    public function index()
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 2) {
            abort(403);
        }

        // Ambil Id_Area dari user yang login
        $user = User::findOrFail(session('Id_User'));
        if (!$user->Id_Area) {
            return redirect()->back()->withErrors(['error' => 'Akun Anda belum ditugaskan ke area mana pun.']);
        }

        $areaId = $user->Id_Area;
        $area = Area::findOrFail($areaId);

        // Hanya ambil JobMember untuk area ini
        $jobMembers = JobMember::where('Id_Area', $areaId)->get();

        return view('leaders.jobs.index', compact('jobMembers', 'area'));
    }

    public function store(Request $request)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 2) {
            abort(403);
        }

        // Ambil area dari user, bukan dari request
        $user = User::findOrFail(session('Id_User'));
        $areaId = $user->Id_Area;

        $request->validate([
            'Name_Job_Member' => 'required|string|max:255',
            // Tidak perlu validasi Id_Area dari input
        ]);

        JobMember::create([
            'Name_Job_Member' => $request->Name_Job_Member,
            'Id_Area' => $areaId, // ← selalu pakai area user
        ]);

        return redirect()->route('leaders.jobs.manage')
            ->with('success', 'Pekerjaan berhasil ditambahkan.');
    }

    public function update(Request $request, JobMember $jobMember)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 2) {
            abort(403);
        }

        // Pastikan job ini milik area leader yang sedang login
        $user = User::findOrFail(session('Id_User'));
        if ($jobMember->Id_Area !== $user->Id_Area) {
            abort(403, 'You cannot edit jobs from other areas.');
        }

        $request->validate([
            'Name_Job_Member' => 'required|string|max:255',
        ]);

        $jobMember->update($request->only('Name_Job_Member'));

        return redirect()->route('leaders.jobs.manage')
            ->with('success', 'Pekerjaan berhasil diperbarui.');
    }

    public function destroy(JobMember $jobMember)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 2) {
            abort(403);
        }

        $user = User::findOrFail(session('Id_User'));
        if ($jobMember->Id_Area !== $user->Id_Area) {
            abort(403, 'You cannot delete jobs from other areas.');
        }

        $jobMember->delete();

        return redirect()->route('leaders.jobs.manage')
            ->with('success', 'Pekerjaan berhasil dihapus.');
    }
}
