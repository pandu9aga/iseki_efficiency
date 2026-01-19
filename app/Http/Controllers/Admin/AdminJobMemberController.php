<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JobMember;
use App\Models\Area;

class AdminJobMemberController extends Controller
{
    public function index()
    {
        // Hanya admin (misal Id_Type_User == 1)
        if (!session()->has('Id_User') || session('Id_Type_User') != 1) {
            abort(403);
        }

        $jobMembers = JobMember::with('area')->get();
        $areas = Area::all();
        $activeAreaId = session('active_area_id');

        return view('admins.jobs.index', compact('jobMembers', 'areas', 'activeAreaId'));
    }

    public function store(Request $request)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 1) {
            abort(403);
        }

        $request->validate([
            'Name_Job_Member' => 'required|string|max:255',
            'Id_Area' => 'required|exists:areas,Id_Area',
        ]);

        $jobMember = JobMember::create($request->only('Name_Job_Member', 'Id_Area'));
        session()->flash('active_area_id', $jobMember->Id_Area);

        return redirect()->route('admins.jobs.manage')->with('success', 'Pekerjaan berhasil ditambahkan.');
    }

    public function update(Request $request, JobMember $jobMember)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 1) {
            abort(403);
        }

        $request->validate([
            'Name_Job_Member' => 'required|string|max:255',
            'Id_Area' => 'required|exists:areas,Id_Area',
        ]);

        $jobMember->update($request->only('Name_Job_Member', 'Id_Area'));
        session()->flash('active_area_id', $jobMember->Id_Area);

        return redirect()->route('admins.jobs.manage')->with('success', 'Pekerjaan berhasil diperbarui.');
    }

    public function destroy(JobMember $jobMember)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 1) {
            abort(403);
        }

        $areaId = $jobMember->Id_Area;
        $jobMember->delete();
        session()->flash('active_area_id', $areaId);

        return redirect()->route('admins.jobs.manage')->with('success', 'Pekerjaan berhasil dihapus.');
    }
}
