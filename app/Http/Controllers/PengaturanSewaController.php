<?php

namespace App\Http\Controllers;

use App\Models\PengaturanSewa;
use Illuminate\Http\Request;

class PengaturanSewaController extends Controller
{
    public function index()
    {
        $pengaturan = PengaturanSewa::first();

        return view('pengaturan-sewa', compact('pengaturan'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'dp_minimum_tahunan' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,2',
            ],
        ], [
            'dp_minimum_tahunan.required' =>
                'DP minimum tahunan wajib diisi.',
            'dp_minimum_tahunan.numeric' =>
                'DP minimum tahunan harus berupa angka.',
            'dp_minimum_tahunan.gt' =>
                'DP minimum tahunan harus lebih dari nol.',
            'dp_minimum_tahunan.decimal' =>
                'DP minimum tahunan maksimal memiliki dua angka desimal.',
        ]);

        $pengaturan = PengaturanSewa::first();

        if ($pengaturan) {
            $pengaturan->update($data);
        } else {
            $pengaturan = PengaturanSewa::create($data);
        }

        return redirect()
            ->route('pengaturan-sewa.index')
            ->with('success', 'Pengaturan DP minimum tahunan berhasil disimpan.');
    }
}