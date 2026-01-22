<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Area;

class AreaController extends Controller
{
    public function index()
    {
        $areas = Area::all();
        return view('admins.areas.index', compact('areas'));
    }

    public function create()
    {
        return view('admins.areas.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'Name_Area' => 'required|string|max:255|unique:areas',
            'Password_Area' => 'required|string|max:255',
        ]);

        Area::create($request->only(['Name_Area', 'Password_Area']));
        return redirect()->route('admins.areas.index')->with('success', 'Area ditambahkan.');
    }

    public function edit(Area $area)
    {
        return view('admins.areas.edit', compact('area'));
    }

    public function update(Request $request, Area $area)
    {
        $request->validate([
            'Name_Area' => 'required|string|max:255|unique:areas,Name_Area,' . $area->Id_Area . ',Id_Area',
            'Password_Area' => 'required|string|max:255',
        ]);

        $area->update($request->only(['Name_Area', 'Password_Area']));
        return redirect()->route('admins.areas.index')->with('success', 'Area diupdate.');
    }

    public function destroy(Area $area)
    {
        $area->delete();
        return redirect()->route('admins.areas.index')->with('success', 'Area dihapus.');
    }
}