<?php

namespace App\Http\Controllers;

use App\Models\Pembayaran;
use App\Models\Penghunian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PembayaranController extends Controller
{
    /**
     * Menampilkan daftar pembayaran.
     */
    public function index()
    {
        $pembayaran = Pembayaran::with([
            'penghunian.kamar',
            'penghunian.penghuni',
        ])->orderByDesc('tanggal_bayar')->get();

        return response()->json([
            'success' => true,
            'message' => 'Data pembayaran berhasil diambil.',
            'data' => $pembayaran,
        ]);
    }

    /**
     * Menampilkan detail pembayaran.
     */
    public function show($id)
    {
        $pembayaran = Pembayaran::with([
            'penghunian.kamar',
            'penghunian.penghuni',
        ])->find($id);

        if (!$pembayaran) {
            return response()->json([
                'success' => false,
                'message' => 'Data pembayaran tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail pembayaran berhasil diambil.',
            'data' => $pembayaran,
        ]);
    }

    /**
     * Mencatat DP, cicilan, atau pelunasan.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_penghunian' => [
                'required',
                'integer',
                'exists:penghunian,id_penghunian',
            ],
            'jenis_pembayaran' => [
                'required',
                Rule::in(['dp', 'cicilan', 'pelunasan']),
            ],
            'tanggal_bayar' => ['required', 'date'],
            'jumlah' => ['required', 'numeric', 'gt:0'],
            'metode_pembayaran' => [
                'required',
                'string',
                'max:30',
            ],
            'bukti_pembayaran' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        return DB::transaction(function () use ($validated) {
            $penghunianInput = Penghunian::with('kamar')
                ->findOrFail($validated['id_penghunian']);

            if ($penghunianInput->status !== 'aktif') {
                return response()->json([
                    'success' => false,
                    'message' => 'Pembayaran hanya dapat dicatat untuk penghunian aktif.',
                ], 422);
            }

            // Pembayaran harus terhubung ke penghuni utama di kamar tersebut.
            $penghunianUtama = Penghunian::with('kamar')
                ->where('id_kamar', $penghunianInput->id_kamar)
                ->where('status', 'aktif')
                ->where('penyewa_utama', true)
                ->first();

            if (!$penghunianUtama) {
                return response()->json([
                    'success' => false,
                    'message' => 'Penghuni utama aktif belum ditentukan untuk kamar ini.',
                ], 422);
            }

            if ($penghunianUtama->skema_sewa === 'bulanan'
                && $validated['jenis_pembayaran'] === 'cicilan') {
                return response()->json([
                    'success' => false,
                    'message' => 'Jenis pembayaran cicilan hanya tersedia untuk skema tahunan.',
                ], 422);
            }

            if (!in_array($penghunianUtama->skema_sewa, ['bulanan', 'tahunan'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Skema sewa penghunian belum ditentukan.',
                ], 422);
            }

            $pembayaranSebelumnya = Pembayaran::where(
                'id_penghunian',
                $penghunianUtama->id_penghunian
            );

            if ($pembayaranSebelumnya->where('jenis_pembayaran', 'dp')->exists()
                && $validated['jenis_pembayaran'] === 'dp') {
                return response()->json([
                    'success' => false,
                    'message' => 'DP untuk penghunian ini sudah pernah dicatat.',
                ], 422);
            }

            $totalSewa = (float) $penghunianUtama->total_sewa;

            if ($totalSewa <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Total sewa belum ditentukan. Hitung total sewa terlebih dahulu.',
                ], 422);
            }

            $totalTerbayar = (float) Pembayaran::where(
                'id_penghunian',
                $penghunianUtama->id_penghunian
            )->where('status', 'terverifikasi')->sum('jumlah');

            $sisaTagihan = max(0, $totalSewa - $totalTerbayar);
            $jumlah = (float) $validated['jumlah'];

            if ($sisaTagihan <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tagihan penghunian ini sudah lunas.',
                ], 422);
            }

            if ($jumlah > $sisaTagihan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jumlah pembayaran melebihi sisa tagihan.',
                    'sisa_tagihan' => $sisaTagihan,
                ], 422);
            }

            if ($validated['jenis_pembayaran'] === 'dp') {
                $dpMinimum = (float) $penghunianUtama->dp_minimum;

                if ($jumlah < $dpMinimum) {
                    // Peringatan diberikan, tetapi pembayaran tetap dapat dilanjutkan.
                    $peringatan = 'DP di bawah minimum Rp'
                        . number_format($dpMinimum, 0, ',', '.') . '.';
                } else {
                    $peringatan = null;
                }
            } else {
                $peringatan = null;
            }

            $pembayaran = Pembayaran::create([
                'id_penghunian' => $penghunianUtama->id_penghunian,
                'jenis_pembayaran' => $validated['jenis_pembayaran'],
                'tanggal_bayar' => $validated['tanggal_bayar'],
                'jumlah' => $jumlah,
                'metode_pembayaran' => $validated['metode_pembayaran'],
                'bukti_pembayaran' => $validated['bukti_pembayaran'] ?? null,
                'status' => 'menunggu_verifikasi',
            ]);

            $totalSetelahBayar = $totalTerbayar;

            $lunas = $totalSetelahBayar >= $totalSewa;

            return response()->json([
                'success' => true,
                'message' => 'Pembayaran berhasil dicatat.',
                'peringatan' => $peringatan,
                'data' => $pembayaran->load([
                    'penghunian.kamar',
                    'penghunian.penghuni',
                ]),
                'total_sewa' => $totalSewa,
                'total_terbayar' => $totalSetelahBayar,
                'sisa_tagihan' => max(0, $totalSewa - $totalSetelahBayar),
                'lunas' => $lunas,
            ], 201);
        });
    }

    /**
     * Memperbarui data pembayaran.
     */
    public function update(Request $request, $id)
    {
        $pembayaran = Pembayaran::find($id);

        if (!$pembayaran) {
            return response()->json([
                'success' => false,
                'message' => 'Data pembayaran tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'jenis_pembayaran' => [
                'sometimes',
                Rule::in(['dp', 'cicilan', 'pelunasan']),
            ],
            'tanggal_bayar' => ['sometimes', 'date'],
            'jumlah' => ['sometimes', 'numeric', 'gt:0'],
            'metode_pembayaran' => ['sometimes', 'string', 'max:30'],
            'bukti_pembayaran' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $pembayaran->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data pembayaran berhasil diperbarui.',
            'data' => $pembayaran->fresh()->load([
                'penghunian.kamar',
                'penghunian.penghuni',
            ]),
        ]);
    }

/**
 * Memperbarui status pembayaran.
 */
public function updateStatus(Request $request, $id)
{
    $pembayaran = Pembayaran::find($id);

    if (!$pembayaran) {
        return response()->json([
            'success' => false,
            'message' => 'Data pembayaran tidak ditemukan.',
        ], 404);
    }

    $validated = $request->validate([
        'status' => [
            'required',
            Rule::in([
                'menunggu_verifikasi',
                'terverifikasi',
                'ditolak',
            ]),
        ],
    ]);

    $penghunian = Penghunian::with('pembayaran')
        ->find($pembayaran->id_penghunian);

    if (!$penghunian) {
        return response()->json([
            'success' => false,
            'message' => 'Data penghunian tidak ditemukan.',
        ], 404);
    }

    $pembayaran->update([
        'status' => $validated['status'],
    ]);

    $totalSewa = (float) $penghunian->total_sewa;

    $totalTerbayar = (float) Pembayaran::where(
        'id_penghunian',
        $penghunian->id_penghunian
    )->where('status', 'terverifikasi')->sum('jumlah');

    $sisaTagihan = max(0, $totalSewa - $totalTerbayar);

    return response()->json([
        'success' => true,
        'message' => 'Status pembayaran berhasil diperbarui.',
        'data' => $pembayaran->fresh(),
        'total_sewa' => $totalSewa,
        'total_terbayar' => $totalTerbayar,
        'sisa_tagihan' => $sisaTagihan,
        'lunas' => $totalSewa > 0 && $sisaTagihan <= 0,
    ]);
}
}