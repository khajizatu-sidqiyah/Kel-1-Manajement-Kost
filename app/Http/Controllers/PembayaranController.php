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
                Rule::in(['dp', 'cicilan', 'pelunasan']),
            ],

            'tanggal_bayar' => [
                'required',
                'date',
            ],

            'jumlah' => [
                'required',
                'numeric',
                'gt:0',
            ],

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

            /*
             * Ambil penghunian yang dikirim.
             */
            $penghunianInput = Penghunian::with('kamar')
                ->lockForUpdate()
                ->find($validated['id_penghunian']);

            if (!$penghunianInput) {
                return response()->json([
                    'message' => 'Data penghunian tidak ditemukan.',
                    'error' => [
                        'code' => 'OCCUPANCY_NOT_FOUND',
                    ],
                ], 404);
            }

            if ($penghunianInput->status !== 'aktif') {
                return response()->json([
                    'message' => 'Pembayaran hanya dapat dicatat untuk penghunian aktif.',
                    'error' => [
                        'code' => 'OCCUPANCY_NOT_ACTIVE',
                    ],
                ], 422);
            }

            /*
             * Cari penyewa utama pada kamar yang sama.
             */
            $penghunianUtama = Penghunian::with('kamar')
                ->where('id_kamar', $penghunianInput->id_kamar)
                ->where('status', 'aktif')
                ->where('penyewa_utama', true)
                ->lockForUpdate()
                ->first();

            /*
             * Pastikan skema sewa sudah dipilih.
             */
            if (!in_array(
                $penghunianUtama->skema_sewa,
                ['bulanan', 'tahunan'],
                true
            )) {
                return response()->json([
                    'message' => 'Silakan tentukan skema sewa terlebih dahulu.',
                    'error' => [
                        'code' => 'RENT_SCHEME_NOT_SELECTED',
                    ],
                ], 422);
            }

            $jenis = $validated['jenis_pembayaran'];
            $jumlah = (float) $validated['jumlah'];

            /*
             * Cicilan hanya tersedia untuk skema tahunan.
             */
            if (
                $penghunianUtama->skema_sewa === 'bulanan'
                && $jenis === 'cicilan'
            ) {
                return response()->json([
                    'message' => 'Cicilan hanya tersedia untuk skema sewa tahunan.',
                    'error' => [
                        'code' => 'INSTALLMENT_NOT_ALLOWED',
                    ],
                ], 422);
            }

            /*
             * Ambil riwayat pembayaran penyewa utama.
             */
            $queryPembayaran = Pembayaran::where(
                'id_penghunian',
                $penghunianUtama->id_penghunian
            );

            /*
             * DP hanya boleh dicatat satu kali.
             * Termasuk DP yang masih menunggu verifikasi.
             */
            if (
                $jenis === 'dp'
                && (clone $queryPembayaran)
                    ->where('jenis_pembayaran', 'dp')
                    ->where('status', '!=', 'ditolak')
                    ->exists()
            ) {
                return response()->json([
                    'message' => 'DP untuk penghunian ini sudah pernah dicatat.',
                    'error' => [
                        'code' => 'DP_ALREADY_EXISTS',
                    ],
                ], 422);
            }

            /*
             * Pastikan total sewa sudah dihitung.
             */
            $totalSewa = (float) $penghunianUtama->total_sewa;

            if ($totalSewa <= 0) {
                return response()->json([
                    'message' => 'Total sewa belum ditentukan. Hitung total sewa terlebih dahulu.',
                    'error' => [
                        'code' => 'RENT_TOTAL_NOT_SET',
                    ],
                ], 422);
            }

            /*
             * Hitung pembayaran yang sudah terverifikasi.
             */
            $totalTerbayar = (float) (clone $queryPembayaran)
                ->where('status', 'terverifikasi')
                ->sum('jumlah');

            $sisaTagihan = max(0, $totalSewa - $totalTerbayar);

            if ($sisaTagihan <= 0) {
                return response()->json([
                    'message' => 'Tagihan penghunian ini sudah lunas.',
                    'error' => [
                        'code' => 'INVOICE_ALREADY_PAID',
                    ],
                ], 422);
            }

            /*
             * Jumlah pembayaran tidak boleh melebihi sisa tagihan.
             */
            if ($jumlah > $sisaTagihan) {
                return response()->json([
                    'message' => 'Jumlah pembayaran melebihi sisa tagihan.',
                    'error' => [
                        'code' => 'PAYMENT_EXCEEDS_REMAINING',
                        'sisa_tagihan' => $sisaTagihan,
                    ],
                ], 422);
            }

            /*
             * Pelunasan harus sama persis dengan sisa tagihan.
             */
            if ($jenis === 'pelunasan' && $jumlah != $sisaTagihan) {
                return response()->json([
                    'message' => 'Jumlah pelunasan harus sama dengan sisa tagihan.',
                    'error' => [
                        'code' => 'INVALID_FINAL_PAYMENT_AMOUNT',
                        'sisa_tagihan' => $sisaTagihan,
                    ],
                ], 422);
            }

             /*
             * DP di bawah minimum langsung ditolak.
             * Tidak ada tahap konfirmasi/verifikasi karena pembayaran dicatat pemilik.
             */
            if ($jenis === 'dp') {
                $dpMinimum = (float) ($penghunianUtama->dp_minimum ?? 0);

                if ($dpMinimum <= 0) {
                    return response()->json([
                        'message' => 'DP minimum belum dihitung. Pilih skema sewa dan hitung DP terlebih dahulu.',
                        'error' => [
                            'code' => 'DP_MINIMUM_NOT_SET',
                        ],
                    ], 422);
                }

                if ($jumlah < $dpMinimum) {
                    return response()->json([
                        'message' => 'Pembayaran DP ditolak karena jumlahnya kurang dari DP minimum.',
                        'error' => [
                            'code' => 'DP_BELOW_MINIMUM',
                            'dp_minimum' => $dpMinimum,
                            'jumlah_dp' => $jumlah,
                        ],
                    ], 422);
                }
            }

            /*
             * Pemilik mencatat pembayaran, sehingga langsung dianggap sah
             * dan tidak masuk antrean menunggu verifikasi.
             */
            $pembayaran = Pembayaran::create([
                'id_penghunian' => $penghunianUtama->id_penghunian,
                'jenis_pembayaran' => $jenis,
                'tanggal_bayar' => $validated['tanggal_bayar'],
                'jumlah' => $jumlah,
                'metode_pembayaran' => $validated['metode_pembayaran'],
                'bukti_pembayaran' => $validated['bukti_pembayaran'] ?? null,
                'status' => 'terverifikasi',
            ]);

            $totalTerbayarSetelahCatat = $totalTerbayar + $jumlah;
            $sisaSetelahCatat = max(0, $totalSewa - $totalTerbayarSetelahCatat);
            $statusPembayaran = $sisaSetelahCatat <= 0 ? 'lunas' : 'belum_lunas';

            $penghunianUtama->update([
                'status_pembayaran' => $statusPembayaran,
            ]);

            return response()->json([
                'message' => 'Pembayaran berhasil dicatat.',
                'data' => [
                    'pembayaran' => $pembayaran->load([
                        'penghunian.kamar',
                        'penghunian.penghuni',
                    ]),
                    'total_sewa' => $totalSewa,
                    'total_terbayar' => $totalTerbayarSetelahCatat,
                    'sisa_tagihan' => $sisaSetelahCatat,
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