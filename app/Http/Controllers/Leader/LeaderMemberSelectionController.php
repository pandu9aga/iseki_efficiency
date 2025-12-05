<?php

namespace App\Http\Controllers\Leader;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Member;
use App\Models\ListMember;

class LeaderMemberSelectionController extends Controller
{
    public function create()
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 2) {
            abort(403);
        }

        $members = Member::with('division')->get();
        $selectedIds = ListMember::pluck('Id_Member')->toArray();

        return view('leaders.members.select', compact('members', 'selectedIds'));
    }

    public function store(Request $request)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 2) {
            abort(403);
        }

        $request->validate([
            'selected_members' => 'required|array|min:1', // Hapus max:10
            // Validasi manual
        ]);

        $selectedIds = $request->input('selected_members');

        // Validasi: pastikan semua ID yang dipilih valid di tabel employees (koneksi 'rifa')
        $validIds = Member::whereIn('id', $selectedIds)->pluck('id')->toArray();

        if (count($validIds) !== count($selectedIds)) {
            $invalidIds = array_diff($selectedIds, $validIds);
            return back()->withErrors([
                'selected_members' => 'Beberapa ID member tidak valid: ' . implode(', ', $invalidIds)
            ]);
        }

        // Ambil ID member yang sudah ada di list_member
        $existingIds = ListMember::pluck('Id_Member')->toArray();

        // ID yang harus dihapus: yang sebelumnya ada tapi tidak dipilih lagi
        $toDelete = array_diff($existingIds, $selectedIds);

        // ID yang harus ditambahkan: yang dipilih tapi belum ada
        $toInsert = array_diff($selectedIds, $existingIds);

        // Hapus dari list_member
        if ($toDelete) {
            ListMember::whereIn('Id_Member', $toDelete)->delete();
        }

        // Insert ke list_member (hanya yang belum ada)
        if ($toInsert) {
            $dataToInsert = array_map(function ($id) {
                return ['Id_Member' => $id];
            }, $toInsert);

            ListMember::insert($dataToInsert);
        }

        return redirect()->route('leaders.members.select') // atau route lain
            ->with('success', 'Berhasil memperbarui daftar member.');
    }
}
