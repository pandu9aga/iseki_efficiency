<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Member;
use App\Models\ListMember;

class AdminMemberSelectionController extends Controller
{
    public function create()
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 1) {
            abort(403);
        }

        $members = Member::with('division')->get();
        $selectedIds = ListMember::pluck('Id_Member')->toArray();

        return view('admins.members.select', compact('members', 'selectedIds'));
    }

    public function store(Request $request)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 1) {
            abort(403);
        }

        $request->validate([
            'selected_members' => 'required|array|min:1',
        ]);

        $selectedIds = $request->input('selected_members');
        $validIds = Member::whereIn('id', $selectedIds)->pluck('id')->toArray();

        if (count($validIds) !== count($selectedIds)) {
            $invalidIds = array_diff($selectedIds, $validIds);
            return back()->withErrors([
                'selected_members' => 'Beberapa ID member tidak valid: ' . implode(', ', $invalidIds)
            ]);
        }

        $existingIds = ListMember::pluck('Id_Member')->toArray();
        $toDelete = array_diff($existingIds, $selectedIds);
        $toInsert = array_diff($selectedIds, $existingIds);

        if ($toDelete) {
            ListMember::whereIn('Id_Member', $toDelete)->delete();
        }

        if ($toInsert) {
            $dataToInsert = array_map(fn($id) => ['Id_Member' => $id], $toInsert);
            ListMember::insert($dataToInsert);
        }

        // ✅ Redirect ke halaman report, bukan kembali ke select
        return redirect()->route('admins.reports.index')
            ->with('success', 'Berhasil memperbarui daftar member.');
    }
}
