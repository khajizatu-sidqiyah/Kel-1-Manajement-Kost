<?php

namespace App\Http\Controllers;

use App\Models\Kamar;
use App\Models\Pembayaran;
use App\Models\Penghunian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PembayaranController extends Controller
{
    /**
     * Format response error mengikuti API contract.
     */
    private function errorResponse(
        int $httpStatus,
        string $message,
        string $status,
        array $details = []
    ) {
        return response()->json([
            'message' => $message,
            'error' => [
                'code' => $httpStatus,
                'message' => $message,
                'status' => $status,
                'details' => $details,
            ],
        ], $httpStatus);
    }

    /**
     * Validasi request dan mengembalikan response error
     * apabila data tidak sesuai.
     */
    private function validateRequest(
        Request $request,
        array $rules,
        array $messages = []
    ) {
        $validator = Validator::make(
            $request->all(),
            $rules,
            $messages
        );

        if ($validator->fails()) {
            return $this->errorResponse(
                422,
                'Validasi request gagal.',
                'VALIDATION_ERROR',
                $validator->errors()->toArray()
            );
        }

        return $validator->validated();
    }

    /**
     * Menampilkan seluruh data pembayaran.
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
            return $this->errorResponse(
                404,
                'Data pembayaran tidak ditemukan.',
                'NOT_FOUND'
            );
        }

        return response()->json([
            'message' => 'Detail pembayaran berhasil diambil.',
            'data' => $pembayaran,
        ]);
    }

    /**
     * Mencatat DP, cicilan, atau pelunasan.
     *
     * Semua pembayaran dicatat atas penghunian penyewa utama.
     * Pembayaran yang dicatat oleh pemilik langsung terverifikasi.
     */
    public function store(Request $request)
    {
        $validated = $this->validateRequest(
            $request,
            [
                'id_penghunian' => [
                    'required',
                    'integer',
                    'min:1',
                    'exists:penghunian,id_penghunian',
                ],
                'jenis_pembayaran' => [
                    'required',
                    'string',
                    Rule::in([
                        'dp',
                        'cicilan',
                        'pelunasan',
                    ]),
                ],
                'tanggal_bayar' => [
                    'sometimes',
                    'nullable',
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
                ],
                'bukti_pembayaran' => [
                    'sometimes',
                    'nullable',
                    'string',
                ],
            ],
            [
                'id_penghunian.required' =>
                    'ID penghunian wajib diisi.',
                'id_penghunian.exists' =>
                    'Data penghunian tidak ditemukan.',
                'jenis_pembayaran.required' =>
                    'Jenis pembayaran wajib diisi.',
                'jenis_pembayaran.in' =>
                    'Jenis pembayaran hanya dp, cicilan, atau pelunasan.',
                'jumlah.required' =>
                    'Jumlah pembayaran wajib diisi.',
                'jumlah.numeric' =>
                    'Jumlah pembayaran harus berupa angka.',
                'jumlah.gt' =>
                    'Jumlah pembayaran harus lebih besar dari nol.',
                'metode_pembayaran.required' =>
                    'Metode pembayaran wajib diisi.',
            ]
        );

        if ($validated instanceof \Illuminate\Http\JsonResponse) {
            return $validated;
        }

        return DB::transaction(function () use ($validated) {

            /*
             * 1. Memeriksa penghunian yang dikirim.
             */
            $penghunianInput = Penghunian::find(
                $validated['id_penghunian']
            );

            if (
                !$penghunianInput ||
                $penghunianInput->status !== 'aktif'
            ) {
                return $this->errorResponse(
                    422,
                    'Pembayaran hanya dapat dicatat untuk penghunian aktif.',
                    'OCCUPANCY_NOT_ACTIVE'
                );
            }

            /*
             * 2. Mengunci kamar untuk mencegah pencatatan
             *    pembayaran ganda secara bersamaan.
             */
            Kamar::where(
                'id_kamar',
                $penghunianInput->id_kamar
            )
                ->lockForUpdate()
                ->first();

            /*
             * 3. Mengambil seluruh penghuni aktif pada kamar.
             */
            $penghunianAktif = Penghunian::where(
                'id_kamar',
                $penghunianInput->id_kamar
            )
                ->where('status', 'aktif')
                ->lockForUpdate()
                ->get();

            /*
             * 4. Menentukan penyewa utama.
             */
            $penghunian = $penghunianAktif->firstWhere(
                'penyewa_utama',
                true
            );

            /*
             * Jika hanya ada satu penghuni aktif,
             * otomatis ditetapkan sebagai penyewa utama.
             */
            if (
                !$penghunian &&
                $penghunianAktif->count() === 1
            ) {
                $penghunian = $penghunianAktif->first();

                $penghunian->update([
                    'penyewa_utama' => true,
                ]);
            }

            if (!$penghunian) {
                return $this->errorResponse(
                    422,
                    'Penyewa utama kamar belum ditentukan.',
                    'PRIMARY_TENANT_NOT_FOUND'
                );
            }

            /*
             * 5. Mengambil skema dan total sewa dari database.
             */
            $jenis = $validated['jenis_pembayaran'];

            $jumlah = round(
                (float) $validated['jumlah'],
                2
            );

            $skema = $penghunian->skema_sewa;

            $totalSewa = round(
                (float) ($penghunian->total_sewa ?? 0),
                2
            );

            if (
                !in_array($skema, ['bulanan', 'tahunan'], true) ||
                $totalSewa <= 0
            ) {
                return $this->errorResponse(
                    422,
                    'Pilih dan hitung skema sewa terlebih dahulu sebelum mencatat pembayaran.',
                    'RENT_SCHEME_NOT_SET'
                );
            }

            /*
             * 6. Memastikan jenis pembayaran sesuai skema.
             *
             * Bulanan: DP dan pelunasan.
             * Tahunan: DP, cicilan, dan pelunasan.
             */
            if (
                $skema === 'bulanan' &&
                $jenis === 'cicilan'
            ) {
                return $this->errorResponse(
                    422,
                    'Jenis pembayaran cicilan hanya tersedia untuk skema tahunan.',
                    'INSTALLMENT_NOT_ALLOWED'
                );
            }

            /*
             * 7. Validasi khusus pembayaran DP.
             */
            if ($jenis === 'dp') {

                /*
                 * DP hanya boleh dicatat satu kali.
                 */
                $sudahAdaDp = Pembayaran::where(
                    'id_penghunian',
                    $penghunian->id_penghunian
                )
                    ->where('jenis_pembayaran', 'dp')
                    ->exists();

                if ($sudahAdaDp) {
                    return $this->errorResponse(
                        422,
                        'DP untuk kamar ini sudah pernah dicatat.',
                        'DP_ALREADY_EXISTS'
                    );
                }

                /*
                 * Memastikan DP minimum sudah dihitung.
                 */
                $dpMinimum = round(
                    (float) ($penghunian->dp_minimum ?? 0),
                    2
                );

                if ($dpMinimum <= 0) {
                    return $this->errorResponse(
                        422,
                        'DP minimum belum dihitung. Hitung skema sewa terlebih dahulu.',
                        'DP_MINIMUM_NOT_SET'
                    );
                }

                /*
                 * DP tidak boleh melebihi total sewa.
                 */
                if ($jumlah > $totalSewa) {
                    return $this->errorResponse(
                        422,
                        'Jumlah DP tidak boleh melebihi total sewa.',
                        'DP_EXCEEDS_RENT',
                        [
                            'sisa_tagihan' => $totalSewa,
                        ]
                    );
                }

                /*
                 * DP di bawah minimum langsung ditolak.
                 */
                if ($jumlah < $dpMinimum) {
                    return $this->errorResponse(
                        422,
                        'Jumlah DP tidak boleh kurang dari DP minimum.',
                        'DP_BELOW_MINIMUM',
                        [
                            'dp_minimum' => $dpMinimum,
                            'jumlah_dp' => $jumlah,
                        ]
                    );
                }
            }

            /*
             * 8. Menghitung total pembayaran yang sudah
             *    terverifikasi sebelum pembayaran baru.
             */
            $totalTerbayarSebelum = round(
                (float) Pembayaran::where(
                    'id_penghunian',
                    $penghunian->id_penghunian
                )
                    ->where('status', 'terverifikasi')
                    ->sum('jumlah'),
                2
            );

            $sisaSebelum = max(
                0,
                round(
                    $totalSewa - $totalTerbayarSebelum,
                    2
                )
            );
            /*
            * 9. Validasi cicilan dan pelunasan.
            */
            if ($jenis !== 'dp') {

                if ($sisaSebelum <= 0) {
                    return $this->errorResponse(
                        422,
                        'Tagihan kamar sudah lunas.',
                        'RENT_ALREADY_PAID'
                    );
                }

                if ($jumlah > $sisaSebelum) {
                    return $this->errorResponse(
                        422,
                        'Jumlah pembayaran melebihi sisa tagihan.',
                        'PAYMENT_EXCEEDS_REMAINING',
                        [
                            'sisa_tagihan' => $sisaSebelum,
                        ]
                    );
                }

                /*
                * Cicilan harus lebih kecil dari sisa tagihan.
                * Cicilan tidak boleh sekaligus menghabiskan tagihan.
                */
                if (
                    $jenis === 'cicilan' &&
                    $jumlah >= $sisaSebelum
                ) {
                    return $this->errorResponse(
                        422,
                        'Jumlah cicilan harus lebih kecil dari sisa tagihan. Gunakan pelunasan jika ingin membayar seluruh sisa tagihan.',
                        'INVALID_INSTALLMENT_AMOUNT',
                        [
                            'sisa_tagihan' => $sisaSebelum,
                            'jumlah_cicilan' => $jumlah,
                        ]
                    );
                }

                /*
                * Pelunasan harus sama dengan sisa tagihan.
                */
                if (
                    $jenis === 'pelunasan' &&
                    round($jumlah, 2) !== round($sisaSebelum, 2)
                ) {
                    return $this->errorResponse(
                        422,
                        'Jumlah pelunasan harus sama dengan sisa tagihan.',
                        'INVALID_FINAL_PAYMENT_AMOUNT',
                        [
                            'sisa_tagihan' => $sisaSebelum,
                            'jumlah_pelunasan' => $jumlah,
                        ]
                    );
                }
            }
            /*
             * 10. Menyimpan pembayaran.
             *
             * tanggal_bayar opsional sesuai kontrak API.
             * Jika tidak dikirim, nilainya disimpan sebagai null.
             */
            $pembayaran = Pembayaran::create([
                'id_penghunian' =>
                    $penghunian->id_penghunian,
                'jenis_pembayaran' => $jenis,
                'tanggal_bayar' =>
                    $validated['tanggal_bayar'] ?? null,
                'jumlah' => $jumlah,
                'metode_pembayaran' =>
                    $validated['metode_pembayaran'],
                'bukti_pembayaran' =>
                    $validated['bukti_pembayaran'] ?? null,
                'status' => 'terverifikasi',
            ]);

            /*
             * 11. Menghitung kembali total pembayaran.
             */
            $totalTerbayar = round(
                (float) Pembayaran::where(
                    'id_penghunian',
                    $penghunian->id_penghunian
                )
                    ->where('status', 'terverifikasi')
                    ->sum('jumlah'),
                2
            );

            $sisaTagihan = max(
                0,
                round($totalSewa - $totalTerbayar, 2)
            );

            $statusPembayaran = $sisaTagihan <= 0
                ? 'lunas'
                : 'belum_lunas';

            /*
             * 12. Memperbarui status pembayaran penghunian.
             */
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
                    'sisa_tagihan' => $sisaTagihan,
                    'status_pembayaran' => $statusPembayaran,
                ],
            ], 201);
        });
    }

    /**
     * Memperbarui pembayaran yang belum terverifikasi.
     */
    public function update(Request $request, int $id)
    {
        $pembayaran = Pembayaran::find($id);

        if (!$pembayaran) {
            return $this->errorResponse(
                404,
                'Data pembayaran tidak ditemukan.',
                'NOT_FOUND'
            );
        }

        if ($pembayaran->status === 'terverifikasi') {
            return $this->errorResponse(
                422,
                'Pembayaran yang sudah terverifikasi tidak dapat diubah.',
                'PAYMENT_ALREADY_VERIFIED'
            );
        }

        /*
         * Update bersifat parsial.
         * Field yang tidak dikirim tidak akan diubah.
         */
        $validated = $this->validateRequest(
            $request,
            [
                'tanggal_bayar' => [
                    'sometimes',
                    'nullable',
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
                ],
                'bukti_pembayaran' => [
                    'sometimes',
                    'nullable',
                    'string',
                ],
            ],
            [
                'jumlah.gt' =>
                    'Jumlah pembayaran harus lebih besar dari nol.',
            ]
        );

        if ($validated instanceof \Illuminate\Http\JsonResponse) {
            return $validated;
        }

        if (empty($validated)) {
            return $this->errorResponse(
                422,
                'Tidak ada data yang dikirim untuk diperbarui.',
                'VALIDATION_ERROR',
                [
                    'request' => [
                        'Minimal satu field harus dikirim.',
                    ],
                ]
            );
        }

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
     * Mengubah status pembayaran dan menghitung ulang
     * status pembayaran pada penghunian.
     */
    public function updateStatus(Request $request, int $id)
    {
        $validated = $this->validateRequest(
            $request,
            [
                'status' => [
                    'required',
                    'string',
                    Rule::in([
                        'menunggu_verifikasi',
                        'terverifikasi',
                        'ditolak',
                    ]),
                ],
            ],
            [
                'status.required' =>
                    'Status pembayaran wajib diisi.',
                'status.in' =>
                    'Status hanya boleh menunggu_verifikasi, terverifikasi, atau ditolak.',
            ]
        );

        if ($validated instanceof \Illuminate\Http\JsonResponse) {
            return $validated;
        }

        return DB::transaction(function () use ($validated, $id) {

            $pembayaran = Pembayaran::lockForUpdate()
                ->find($id);

            if (!$pembayaran) {
                return $this->errorResponse(
                    404,
                    'Data pembayaran tidak ditemukan.',
                    'NOT_FOUND'
                );
            }

            $penghunian = Penghunian::lockForUpdate()
                ->find($pembayaran->id_penghunian);

            if (!$penghunian) {
                return $this->errorResponse(
                    404,
                    'Data penghunian tidak ditemukan.',
                    'NOT_FOUND'
                );
            }

            $totalSewa = round(
                (float) ($penghunian->total_sewa ?? 0),
                2
            );

            /*
             * Mencegah verifikasi yang menyebabkan
             * total pembayaran melebihi total sewa.
             */
            if (
                $validated['status'] === 'terverifikasi' &&
                $totalSewa > 0
            ) {
                $pembayaranLain = (float) Pembayaran::where(
                    'id_penghunian',
                    $penghunian->id_penghunian
                )
                    ->where('status', 'terverifikasi')
                    ->where(
                        'id_pembayaran',
                        '!=',
                        $pembayaran->id_pembayaran
                    )
                    ->sum('jumlah');

                if (
                    round(
                        $pembayaranLain +
                        (float) $pembayaran->jumlah,
                        2
                    ) > $totalSewa
                ) {
                    return $this->errorResponse(
                        422,
                        'Verifikasi menyebabkan pembayaran melebihi total sewa.',
                        'PAYMENT_EXCEEDS_RENT'
                    );
                }
            }

            $pembayaran->update([
                'status' => $validated['status'],
            ]);

            /*
             * Menghitung ulang total pembayaran terverifikasi.
             */
            $totalTerbayar = round(
                (float) Pembayaran::where(
                    'id_penghunian',
                    $penghunian->id_penghunian
                )
                    ->where('status', 'terverifikasi')
                    ->sum('jumlah'),
                2
            );

            $sisaTagihan = max(
                0,
                round($totalSewa - $totalTerbayar, 2)
            );

            $statusPembayaran = (
                $totalSewa > 0 &&
                $sisaTagihan <= 0
            )
                ? 'lunas'
                : 'belum_lunas';

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
