<?php

namespace App\Http\Controllers;

use App\Models\Pembayaran;
use App\Models\Penghunian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\PengaturanSewa;

class PembayaranController extends Controller
{
    /**
     * Menampilkan seluruh pembayaran.
     */
    public function index()
    {
        $pembayaran = Pembayaran::with([
            'penghunian.kamar',
            'penghunian.penghuni',
        ])
            ->orderByDesc('tanggal_bayar')
            ->get();

        return response()->json([
            'message' => 'Data pembayaran berhasil diambil.',
            'data' => $pembayaran,
        ]);
    }

    /**
     * Menampilkan detail pembayaran.
     */
    public function show(int $id)
    {
        $pembayaran = Pembayaran::with([
            'penghunian.kamar',
            'penghunian.penghuni',
        ])->find($id);

        if (!$pembayaran) {
            return response()->json([
                'message' => 'Data pembayaran tidak ditemukan.',
                'error' => [
                    'code' => 'PAYMENT_NOT_FOUND',
                ],
            ], 404);
        }

        return response()->json([
            'message' => 'Detail pembayaran berhasil diambil.',
            'data' => $pembayaran,
        ]);
    }

    /**
     * Mencatat DP, cicilan, atau pelunasan.
     *
     * Untuk DP di bawah minimum, pemilik harus mengirim
     * konfirmasi_dp=true agar pembayaran disimpan.
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
            Rule::in([
                'dp',
                'cicilan',
                'pelunasan',
                'pelunasan_pertama',
                'bulanan',
            ]),
        ],
        'tanggal_bayar' => ['required', 'date'],
        'jumlah' => ['required', 'numeric', 'gt:0'],
        'metode_pembayaran' => ['required', 'string', 'max:30'],
        'bukti_pembayaran' => ['nullable', 'string', 'max:255'],

        // Data tambahan yang dikirim frontend khusus saat DP.
        'skema_sewa' => [
            'required_if:jenis_pembayaran,dp',
            'nullable',
            Rule::in(['bulanan', 'tahunan']),
        ],
        'total_sewa' => [
            'required_if:skema_sewa,tahunan',
            'nullable',
            'numeric',
            'gt:0',
        ],
        'tenggat_pelunasan' => [
            'required_if:jenis_pembayaran,dp',
            'nullable',
            'date',
        ],
    ]);

    return DB::transaction(function () use ($validated) {

        $penghunianInput = Penghunian::with('kamar')
            ->lockForUpdate()
            ->find($validated['id_penghunian']);

        if (!$penghunianInput || $penghunianInput->status !== 'aktif') {
            return response()->json([
                'message' => 'Data penghunian aktif tidak ditemukan.',
                'error' => ['code' => 'OCCUPANCY_NOT_FOUND'],
            ], 404);
        }

        // Gunakan data penghunian yang dipilih tanpa mewajibkan penyewa utama.
        $penghunian = $penghunianInput;

        $jenis = $validated['jenis_pembayaran'];
        $jumlah = (float) $validated['jumlah'];

    // Lanjutkan kode PROSES DP seperti sebelumnya.

        if (!$penghunian) {
            return response()->json([
                'message' => 'Penyewa utama kamar belum ditentukan.',
                'error' => ['code' => 'PRIMARY_TENANT_NOT_FOUND'],
            ], 422);
        }

        $jenis = $validated['jenis_pembayaran'];
        $jumlah = (float) $validated['jumlah'];

        /*
         * PROSES DP
         * Skema, total sewa, DP minimum, dan tenggat
         * disimpan langsung dari request frontend.
         */
        if ($jenis === 'dp') {

            if (
                Pembayaran::where('id_penghunian', $penghunian->id_penghunian)
                    ->where('jenis_pembayaran', 'dp')
                    ->where('status', '!=', 'ditolak')
                    ->exists()
            ) {
                return response()->json([
                    'message' => 'DP untuk kamar ini sudah pernah dicatat.',
                    'error' => ['code' => 'DP_ALREADY_EXISTS'],
                ], 422);
            }

            $kamar = $penghunian->kamar;
            $skema = $validated['skema_sewa'];

            if ($skema === 'bulanan') {
                $totalSewa = (float) ($kamar->harga ?? 0);

                if ($totalSewa <= 0) {
                    return response()->json([
                        'message' => 'Harga sewa bulanan kamar belum diisi.',
                        'error' => ['code' => 'INVALID_MONTHLY_RENT'],
                    ], 422);
                }

                $dpMinimum = $totalSewa * 0.5;

            } else {
                $totalSewa = (float) $validated['total_sewa'];

                $pengaturan = PengaturanSewa::first();

                $dpMinimum = (float) ($pengaturan->dp_minimum_tahunan ?? 0);

                if ($dpMinimum <= 0) {
                    return response()->json([
                        'message' => 'Pengaturan DP minimum tahunan belum tersedia.',
                        'error' => ['code' => 'INVALID_ANNUAL_DP_SETTING'],
                    ], 422);
                }
            }

            if ($jumlah > $totalSewa) {
                return response()->json([
                    'message' => 'Jumlah DP tidak boleh melebihi total sewa.',
                    'error' => ['code' => 'DP_EXCEEDS_RENT'],
                ], 422);
            }

            $penghunian->update([
                'skema_sewa' => $skema,
                'total_sewa' => $totalSewa,
                'dp_minimum' => $dpMinimum,
                'tenggat_pelunasan' => $validated['tenggat_pelunasan'],
                'status_pembayaran' => 'belum_lunas',
            ]);

        } else {

            /*
             * PROSES PEMBAYARAN SETELAH DP
             */
            $skema = $penghunian->skema_sewa;
            $totalSewa = (float) $penghunian->total_sewa;

            if (!in_array($skema, ['bulanan', 'tahunan'], true) || $totalSewa <= 0) {
                return response()->json([
                    'message' => 'Skema dan total sewa belum tersedia. Catat DP terlebih dahulu.',
                    'error' => ['code' => 'RENT_SCHEME_NOT_SET'],
                ], 422);
            }

            if ($skema === 'bulanan' && $jenis === 'cicilan') {
                return response()->json([
                    'message' => 'Cicilan hanya tersedia untuk skema tahunan.',
                    'error' => ['code' => 'INSTALLMENT_NOT_ALLOWED'],
                ], 422);
            }

            if ($skema === 'tahunan' && $jenis === 'bulanan') {
                return response()->json([
                    'message' => 'Tagihan bulanan tidak tersedia untuk skema tahunan.',
                    'error' => ['code' => 'MONTHLY_PAYMENT_NOT_ALLOWED'],
                ], 422);
            }

            $query = Pembayaran::where(
                'id_penghunian',
                $penghunian->id_penghunian
            )->where('status', 'terverifikasi');

            $totalTerbayar = (float) (clone $query)->sum('jumlah');

            if ($skema === 'bulanan') {
                // Pembayaran bulanan dihitung berdasarkan periode berjalan.
                $sisaTagihan = $totalSewa - ($totalTerbayar % $totalSewa);

                if ($sisaTagihan <= 0) {
                    $sisaTagihan = $totalSewa;
                }
            } else {
                $sisaTagihan = max(0, $totalSewa - $totalTerbayar);
            }

            if ($jumlah > $sisaTagihan) {
                return response()->json([
                    'message' => 'Jumlah pembayaran melebihi sisa tagihan.',
                    'error' => [
                        'code' => 'PAYMENT_EXCEEDS_REMAINING',
                        'sisa_tagihan' => $sisaTagihan,
                    ],
                ], 422);
            }

            if (
                in_array($jenis, ['pelunasan', 'pelunasan_pertama'], true)
                && $jumlah != $sisaTagihan
            ) {
                return response()->json([
                    'message' => 'Jumlah pelunasan harus sama dengan sisa tagihan.',
                    'error' => [
                        'code' => 'INVALID_FINAL_PAYMENT_AMOUNT',
                        'sisa_tagihan' => $sisaTagihan,
                    ],
                ], 422);
            }
        }

        /*
         * Simpan pembayaran.
         * Jenis pembayaran mengikuti nilai dari frontend.
         */
        $pembayaran = Pembayaran::create([
            'id_penghunian' => $penghunian->id_penghunian,
            'jenis_pembayaran' => $jenis,
            'tanggal_bayar' => $validated['tanggal_bayar'],
            'jumlah' => $jumlah,
            'metode_pembayaran' => $validated['metode_pembayaran'],
            'bukti_pembayaran' => $validated['bukti_pembayaran'] ?? null,
            'status' => 'terverifikasi',
        ]);

        /*
         * Perbarui status pembayaran penghunian.
         */
        $totalSewa = (float) $penghunian->total_sewa;

        $totalTerbayar = (float) Pembayaran::where(
            'id_penghunian',
            $penghunian->id_penghunian
        )
            ->where('status', 'terverifikasi')
            ->sum('jumlah');

        $statusPembayaran = 'belum_lunas';

        if ($skema === 'tahunan' && $totalTerbayar >= $totalSewa) {
            $statusPembayaran = 'lunas';
        } elseif (
            $skema === 'bulanan'
            && $totalTerbayar > 0
            && $totalTerbayar % $totalSewa == 0
        ) {
            $statusPembayaran = 'lunas';
        }

        $penghunian->update([
            'status_pembayaran' => $statusPembayaran,
        ]);

        return response()->json([
            'message' => $jenis === 'dp'
                ? 'DP berhasil dicatat.'
                : 'Pembayaran berhasil dicatat.',
            'data' => [
                'pembayaran' => $pembayaran->load([
                    'penghunian.kamar',
                    'penghunian.penghuni',
                ]),
                'total_sewa' => $totalSewa,
                'total_terbayar' => $totalTerbayar,
                'sisa_tagihan' => max(0, $totalSewa - $totalTerbayar),
                'status_pembayaran' => $statusPembayaran,
            ],
        ], 201);
    });
}

    /**
     * Memperbarui data pembayaran.
     *
     * Pembayaran yang sudah terverifikasi tidak boleh
     * diubah jumlah atau jenisnya secara langsung.
     */
    public function update(Request $request, int $id)
    {
        $pembayaran = Pembayaran::find($id);

        if (!$pembayaran) {
            return response()->json([
                'message' => 'Data pembayaran tidak ditemukan.',
                'error' => [
                    'code' => 'PAYMENT_NOT_FOUND',
                ],
            ], 404);
        }

        if ($pembayaran->status === 'terverifikasi') {
            return response()->json([
                'message' => 'Pembayaran yang sudah terverifikasi tidak dapat diubah.',
                'error' => [
                    'code' => 'PAYMENT_ALREADY_VERIFIED',
                ],
            ], 422);
        }

        $validated = $request->validate([
            'tanggal_bayar' => [
                'sometimes',
                'date',
            ],

            'jumlah' => [
                'sometimes',
                'numeric',
                'gt:0',
            ],

            'metode_pembayaran' => [
                'sometimes',
                'string',
                'max:30',
            ],

            'bukti_pembayaran' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $pembayaran->update($validated);

        return response()->json([
            'message' => 'Data pembayaran berhasil diperbarui.',
            'data' => $pembayaran->fresh()->load([
                'penghunian.kamar',
                'penghunian.penghuni',
            ]),
        ]);
    }

    /**
     * Mengubah status pembayaran.
     *
     * Jika pembayaran terverifikasi, total pembayaran dihitung ulang.
     * Jika total sudah mencapai total sewa, status penghunian menjadi lunas.
     */
    public function updateStatus(Request $request, int $id)
    {
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

        return DB::transaction(function () use ($validated, $id) {

            $pembayaran = Pembayaran::lockForUpdate()->find($id);

            if (!$pembayaran) {
                return response()->json([
                    'message' => 'Data pembayaran tidak ditemukan.',
                    'error' => [
                        'code' => 'PAYMENT_NOT_FOUND',
                    ],
                ], 404);
            }

            $penghunian = Penghunian::lockForUpdate()
                ->find($pembayaran->id_penghunian);

            if (!$penghunian) {
                return response()->json([
                    'message' => 'Data penghunian tidak ditemukan.',
                    'error' => [
                        'code' => 'OCCUPANCY_NOT_FOUND',
                    ],
                ], 404);
            }

            /*
             * Perbarui status pembayaran.
             */
            $pembayaran->update([
                'status' => $validated['status'],
            ]);

            /*
             * Hitung ulang seluruh pembayaran terverifikasi.
             */
            $totalSewa = (float) $penghunian->total_sewa;

            $totalTerbayar = (float) Pembayaran::where(
                'id_penghunian',
                $penghunian->id_penghunian
            )
                ->where('status', 'terverifikasi')
                ->sum('jumlah');

            $sisaTagihan = max(0, $totalSewa - $totalTerbayar);

            /*
             * Simpan status pembayaran pada penghunian.
             */
            if ($totalSewa > 0 && $sisaTagihan <= 0) {
                $statusPembayaran = 'lunas';
            } else {
                $statusPembayaran = 'belum_lunas';
            }

            $penghunian->update([
                'status_pembayaran' => $statusPembayaran,
            ]);

            return response()->json([
                'message' => 'Status pembayaran berhasil diperbarui.',
                'data' => [
                    'pembayaran' => $pembayaran->fresh(),
                    'total_sewa' => $totalSewa,
                    'total_terbayar' => $totalTerbayar,
                    'sisa_tagihan' => $sisaTagihan,
                    'status_pembayaran' => $statusPembayaran,
                ],
            ]);
        });
    }
}