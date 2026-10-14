<?php

namespace App\Http\Controllers;

use App\Models\Kamar;
use App\Models\Penghuni;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PenghuniController extends Controller
{
    private function errorResponse(string $message, int $code, string $status, array $details = [])
    {
        return response()->json([
            'message' => $message,
            'error' => [
                'code' => $code,
                'status' => $status,
                'details' => empty($details) ? (object) [] : $details,
            ],
        ], $code);
    }

    private function validationError($errors)
    {
        return $this->errorResponse('Data yang dikirim tidak valid', 422, 'VALIDATION_ERROR', $errors);
    }

    private function notFound()
    {
        return $this->errorResponse('Data tidak ditemukan', 404, 'NOT_FOUND');
    }

    /** GET /api/penghuni: hanya penghuni yang belum diarsipkan. */
    public function index()
    {
        $penghuni = Penghuni::query()
            ->where('is_aktif', true)
            ->with(['penghunianAktif.kamar'])
            ->orderBy('nama_penghuni')
            ->get()
            ->map(function (Penghuni $item) {
                $aktif = $item->penghunianAktif->first();
                $item->setAttribute('id_kamar', $aktif?->id_kamar);
                $item->setAttribute('is_penyewa_utama', (bool) ($aktif?->is_penyewa_utama ?? false));
                $item->setAttribute('can_catat_dp', (bool) ($aktif?->is_penyewa_utama ?? false));
                return $item;
            });

        return response()->json(['message' => 'Daftar penghuni berhasil diambil', 'data' => $penghuni]);
    }

    /** POST /api/penghuni: buat penghuni sekaligus catat penghunian kamar. */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_penghuni' => ['required', 'string', 'max:100'],
            'no_telepon' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:100'],
            'alamat' => ['nullable', 'string'],
            'id_kamar' => ['required', 'integer', 'exists:kamar,id_kamar'],
            'tanggal_mulai' => ['required', 'date_format:Y-m-d'],
        ], [
            'nama_penghuni.required' => 'Nama penghuni wajib diisi',
            'id_kamar.required' => 'Kamar wajib dipilih',
            'id_kamar.exists' => 'Kamar yang dipilih tidak ditemukan',
            'tanggal_mulai.required' => 'Tanggal mulai wajib diisi',
            'tanggal_mulai.date_format' => 'Tanggal mulai harus berformat YYYY-MM-DD',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $data = $validator->validated();

        // Email dapat menjadi identitas bantu jika nomor telepon belum tersedia.
        // Untuk melengkapi nomor telepon, gunakan PUT pada ID penghuni yang sama.
        if (!empty($data['email']) && Penghuni::where('is_aktif', true)->where('email', $data['email'])->exists()) {
            return $this->errorResponse(
                'Penghuni dengan email tersebut sudah terdaftar. Perbarui data penghuni yang ada.',
                409,
                'CONFLICT',
                ['email' => ['Email sudah digunakan oleh penghuni aktif. Gunakan PUT untuk memperbarui data.']]
            );
        }

        try {
            $penghuni = DB::transaction(function () use ($data) {
                /** @var Kamar|null $kamar */
                $kamar = Kamar::where('id_kamar', $data['id_kamar'])->lockForUpdate()->first();
                if (!$kamar) {
                    throw new \DomainException('Kamar yang dipilih tidak ditemukan.');
                }

                // Kamar berstatus terisi tetap boleh menerima penghuni tambahan.
                // Status kamar kosong tetapi sudah memiliki penghunian aktif dianggap data tidak konsisten.
                $penghunianAktif = DB::table('penghunian')
                    ->where('id_kamar', $kamar->id_kamar)
                    ->where('status', 'aktif')
                    ->lockForUpdate()
                    ->get();

                $penghuni = Penghuni::create([
                    'nama_penghuni' => $data['nama_penghuni'],
                    'no_telepon' => $data['no_telepon'] ?? null,
                    'email' => $data['email'] ?? null,
                    'alamat' => $data['alamat'] ?? null,
                    'is_aktif' => true,
                ]);

                DB::table('penghunian')->insert([
                    'id_kamar' => $kamar->id_kamar,
                    'id_penghuni' => $penghuni->id_penghuni,
                    'tanggal_mulai' => $data['tanggal_mulai'],
                    'tanggal_selesai' => null,
                    'status' => 'aktif',
                    // Penyewa pertama saja yang menjadi penyewa utama secara otomatis.
                    'is_penyewa_utama' => $penghunianAktif->isEmpty(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($kamar->status !== 'terisi') {
                    $kamar->update(['status' => 'terisi']);
                }

                return $penghuni->fresh();
            });
        } catch (\DomainException $e) {
            return $this->errorResponse('Operasi tidak dapat dilakukan', 409, 'CONFLICT', ['id_kamar' => [$e->getMessage()]]);
        }

        $penghunianAktif = DB::table('penghunian')
            ->where('id_penghuni', $penghuni->id_penghuni)
            ->where('status', 'aktif')
            ->first();
        $penghuni->setAttribute('id_kamar', $penghunianAktif?->id_kamar);
        $penghuni->setAttribute('is_penyewa_utama', (bool) ($penghunianAktif?->is_penyewa_utama ?? false));
        $penghuni->setAttribute('can_catat_dp', (bool) ($penghunianAktif?->is_penyewa_utama ?? false));

        return response()->json(['message' => 'Penghuni berhasil ditambahkan', 'data' => $penghuni], 201);
    }

    /** GET /api/penghuni/{id} */
    public function show($id)
    {
        $penghuni = Penghuni::where('is_aktif', true)->with('penghunianAktif.kamar')->find($id);
        if (!$penghuni) {
            return $this->notFound();
        }

        $aktif = $penghuni->penghunianAktif->first();
        $penghuni->setAttribute('id_kamar', $aktif?->id_kamar);
        $penghuni->setAttribute('is_penyewa_utama', (bool) ($aktif?->is_penyewa_utama ?? false));
        $penghuni->setAttribute('can_catat_dp', (bool) ($aktif?->is_penyewa_utama ?? false));

        return response()->json(['message' => 'Data penghuni berhasil diambil', 'data' => $penghuni]);
    }

    /** PUT /api/penghuni/{id}: memperbarui data yang sama, bukan membuat entri baru. */
    public function update(Request $request, $id)
    {
        $penghuni = Penghuni::where('is_aktif', true)->find($id);
        if (!$penghuni) {
            return $this->notFound();
        }

        $validator = Validator::make($request->all(), [
            'nama_penghuni' => ['required', 'string', 'max:100'],
            'no_telepon' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:100', Rule::unique('penghuni', 'email')->ignore($penghuni->id_penghuni, 'id_penghuni')->where('is_aktif', true)],
            'alamat' => ['nullable', 'string'],
        ], [
            'nama_penghuni.required' => 'Nama penghuni wajib diisi',
            'email.unique' => 'Email sudah digunakan oleh penghuni aktif lainnya.',
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $penghuni->update($validator->validated());
        return response()->json(['message' => 'Data penghuni berhasil diperbarui', 'data' => $penghuni->fresh()]);
    }

    /** PATCH /api/penghuni/{id}/penyewa-utama: pindahkan status utama ke penghuni pada kamar yang sama. */
    public function setPenyewaUtama($id)
    {
        $penghuni = Penghuni::where('is_aktif', true)->find($id);
        if (!$penghuni) {
            return $this->notFound();
        }

        $penghunian = DB::table('penghunian')->where('id_penghuni', $id)->where('status', 'aktif')->first();
        if (!$penghunian) {
            return $this->errorResponse('Penghuni tidak sedang menempati kamar', 409, 'CONFLICT', ['id_penghuni' => ['Penghuni tidak memiliki penghunian aktif.']]);
        }

        DB::transaction(function () use ($penghunian, $id) {
            DB::table('penghunian')->where('id_kamar', $penghunian->id_kamar)->where('status', 'aktif')->update([
                'is_penyewa_utama' => false,
                'updated_at' => now(),
            ]);
            DB::table('penghunian')->where('id_penghunian', $penghunian->id_penghunian)->update([
                'is_penyewa_utama' => true,
                'updated_at' => now(),
            ]);
        });

        return response()->json([
            'message' => 'Penyewa utama berhasil diperbarui',
            'data' => ['id_penghuni' => (int) $id, 'id_kamar' => (int) $penghunian->id_kamar, 'is_penyewa_utama' => true, 'can_catat_dp' => true],
        ]);
    }

    /** DELETE /api/penghuni/{id}: arsipkan jika tidak sedang menghuni kamar. */
    public function destroy($id)
    {
        $penghuni = Penghuni::where('is_aktif', true)->find($id);
        if (!$penghuni) {
            return $this->notFound();
        }

        $aktif = DB::table('penghunian')->where('id_penghuni', $id)->where('status', 'aktif')->first();
        if ($aktif) {
            return $this->errorResponse('Operasi tidak dapat dilakukan', 409, 'CONFLICT', [
                'id_penghuni' => ['Penghuni masih memiliki penghunian aktif. Akhiri penghunian terlebih dahulu.'],
            ]);
        }

        $penghuni->update(['is_aktif' => false]);
        return response()->json(['message' => 'Penghuni berhasil diarsipkan']);
    }
}
