<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLaporanKinerjaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user || ! $user->is_active) {
            return false;
        }

        // Hanya operator unit (biro, pusat, lab) yang memiliki id_unit_kerja yang diizinkan membuat laporan
        if (! in_array($user->role, ['biro', 'pusat', 'lab'], true) || empty($user->id_unit_kerja)) {
            return false;
        }

        // Otorisasi Jabatan: Hanya staf yang dapat membuat laporan
        if ($user->jabatan !== 'staf') {
            abort(403, 'Hanya staf yang dapat membuat laporan.');
        }

        return true;
    }

    /**
     * Bersihkan input teks & array JSON dari tag HTML/Script berbahaya (XSS Protection) sebelum divalidasi.
     */
    protected function prepareForValidation(): void
    {
        $textFields = [
            'bulan_laporan',
            'nama_kegiatan',
            'uraian',
            'kendala',
            'dukungan',
            'tindak_lanjut',
            'nama_penandatangan',
            'kesimpulan',
            'rekomendasi',
            'catatan_revisi',
        ];

        $sanitized = [];

        foreach ($textFields as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $cleaned = trim(strip_tags($this->input($field)));
                $sanitized[$field] = $cleaned === '' && ! in_array($field, ['nama_kegiatan', 'uraian', 'nama_penandatangan'], true)
                    ? null
                    : $cleaned;
            }
        }

        if ($this->has('link_bukti') && is_string($this->input('link_bukti'))) {
            $trimmedUrl = trim($this->input('link_bukti'));
            $sanitized['link_bukti'] = $trimmedUrl === '' ? null : $trimmedUrl;
        }

        // Decode otomatis jika dikirim sebagai JSON string via multipart/form-data & sanitasi rekursif XSS
        $jsonFields = [
            'identitas_laporan',
            'pembahasan_json',
            'kendala_json',
            'dukungan_json',
            'tindak_lanjut_json',
        ];

        foreach ($jsonFields as $jsonField) {
            if ($this->has($jsonField)) {
                $val = $this->input($jsonField);

                if (is_string($val)) {
                    $trimmed = trim($val);
                    if (
                        (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}')) ||
                        (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']'))
                    ) {
                        $decoded = json_decode($trimmed, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $val = $decoded;
                        }
                    }
                }

                if (is_array($val)) {
                    $sanitized[$jsonField] = $this->sanitizeArrayRecursive($val);
                }
            }
        }

        $this->merge($sanitized);
    }

    /**
     * Sanitasi XSS rekursif pada setiap elemen string di dalam array JSON.
     */
    private function sanitizeArrayRecursive(array $items): array
    {
        $result = [];
        foreach ($items as $key => $value) {
            $cleanKey = is_string($key) ? trim(strip_tags($key)) : $key;
            if (is_array($value)) {
                $result[$cleanKey] = $this->sanitizeArrayRecursive($value);
            } elseif (is_string($value)) {
                $result[$cleanKey] = trim(strip_tags($value));
            } else {
                $result[$cleanKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id_periode'         => ['required', 'integer', Rule::exists('periode_akademik', 'id')],
            'bulan_laporan'      => ['nullable', 'string', 'max:50'],

            // Validasi Kolom Dinamis (JSON / Array)
            'identitas_laporan'  => ['required_without:nama_kegiatan', 'nullable', 'array'],
            'pembahasan_json'    => ['required_without:uraian', 'nullable', 'array'],
            'kendala_json'       => ['nullable', 'array'],
            'dukungan_json'      => ['nullable', 'array'],
            'tindak_lanjut_json' => ['nullable', 'array'],

            // Kolom Teks Pendek
            'kesimpulan'         => ['nullable', 'string'],
            'rekomendasi'        => ['nullable', 'string'],

            // Kolom Legacy / Kompatibilitas
            'nama_kegiatan'      => ['required_without:identitas_laporan', 'nullable', 'string', 'max:255'],
            'uraian'             => ['required_without:pembahasan_json', 'nullable', 'string'],
            'link_bukti'         => ['nullable', 'string', 'url:http,https', 'max:500'],
            'kendala'            => ['nullable', 'string'],
            'dukungan'           => ['nullable', 'string'],
            'tindak_lanjut'      => ['nullable', 'string'],
            'nama_penandatangan' => ['required_without:identitas_laporan', 'nullable', 'string', 'max:255'],

            // Kolom Tanda Tangan Penyusun (Staf)
            'ttd_penyusun'       => ['nullable'],
            'ttd_penyusun_path'  => ['nullable', 'string', 'max:255'],
            'ttd_image'          => ['nullable'],
            'ttd'                => ['nullable'],
            'tanda_tangan'       => ['nullable'],
            'ttd_base64'         => ['nullable', 'string'],
            'ttd_image_path'     => ['nullable', 'string', 'max:255'],

            // Status
            'status_laporan'     => ['nullable', 'string', Rule::in(
                ['menunggu_pimpinan', 'revisi_pimpinan', 'menunggu_admin', 'revisi_admin', 'selesai']
            )],
            'status'             => ['nullable', 'string', Rule::in(['Review', 'Revisi', 'Selesai'])],
        ];
    }

    /**
     * Pesan error kustom dalam Bahasa Indonesia.
     */
    public function messages(): array
    {
        return [
            'id_periode.required'                => 'Periode pelaporan wajib dipilih.',
            'id_periode.exists'                  => 'Periode pelaporan tidak valid.',
            'identitas_laporan.required_without' => 'Identitas laporan wajib diisi.',
            'identitas_laporan.array'            => 'Format identitas laporan harus berupa array/JSON yang valid.',
            'pembahasan_json.required_without'   => 'Data pembahasan kegiatan wajib diisi.',
            'pembahasan_json.array'              => 'Format pembahasan harus berupa array/JSON yang valid.',
            'kendala_json.array'                 => 'Format kendala harus berupa array/JSON yang valid.',
            'dukungan_json.array'                => 'Format dukungan harus berupa array/JSON yang valid.',
            'tindak_lanjut_json.array'           => 'Format tindak lanjut harus berupa array/JSON yang valid.',
            'nama_kegiatan.required_without'     => 'Nama kegiatan wajib diisi.',
            'nama_kegiatan.max'                  => 'Nama kegiatan maksimal 255 karakter.',
            'uraian.required_without'            => 'Uraian kegiatan wajib diisi.',
            'link_bukti.url'                     => 'Format link bukti tidak valid (harus diawali http:// atau https://).',
            'nama_penandatangan.required_without'=> 'Nama penandatangan wajib diisi.',
            'nama_penandatangan.max'             => 'Nama penandatangan maksimal 255 karakter.',
            'status.in'                          => 'Status laporan harus salah satu dari: Review, Revisi, atau Selesai.',
        ];
    }
}
