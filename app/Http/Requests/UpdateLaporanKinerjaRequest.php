<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLaporanKinerjaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Pengecekan kepemilikan record (Anti-IDOR) dilakukan secara eksplisit di Controller
     * agar selalu mengembalikan pesan error JSON 403 Forbidden yang terstandarisasi.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user || ! $user->is_active) {
            return false;
        }

        return in_array($user->role, ['admin', 'biro', 'pusat', 'lab'], true);
    }

    /**
     * Bersihkan input teks dari tag HTML/Script berbahaya (XSS Protection) sebelum divalidasi.
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

        $this->merge($sanitized);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id_periode'         => ['sometimes', 'required', 'integer', Rule::exists('periode_akademik', 'id')],
            'bulan_laporan'      => ['nullable', 'string', 'max:50'],
            'nama_kegiatan'      => ['required', 'string', 'max:255'],
            'uraian'             => ['required', 'string'],
            'link_bukti'         => ['nullable', 'string', 'url:http,https', 'max:500'],
            'kendala'            => ['nullable', 'string'],
            'dukungan'           => ['nullable', 'string'],
            'tindak_lanjut'      => ['nullable', 'string'],
            'nama_penandatangan' => ['required', 'string', 'max:255'],
            'ttd_image'          => ['nullable'],
            'ttd'                => ['nullable'],
            'tanda_tangan'       => ['nullable'],
            'ttd_base64'         => ['nullable', 'string'],
            'ttd_image_path'     => ['nullable', 'string', 'max:255'],
            'status'             => ['nullable', 'string', Rule::in(['Review', 'Revisi', 'Selesai'])],
        ];
    }

    /**
     * Pesan error kustom dalam Bahasa Indonesia.
     */
    public function messages(): array
    {
        return [
            'id_periode.exists'           => 'Periode pelaporan tidak valid.',
            'nama_kegiatan.required'      => 'Nama kegiatan wajib diisi.',
            'nama_kegiatan.max'           => 'Nama kegiatan maksimal 255 karakter.',
            'uraian.required'             => 'Uraian kegiatan wajib diisi.',
            'link_bukti.url'              => 'Format link bukti tidak valid (harus diawali http:// atau https://).',
            'nama_penandatangan.required' => 'Nama penandatangan wajib diisi.',
            'nama_penandatangan.max'      => 'Nama penandatangan maksimal 255 karakter.',
            'status.in'                   => 'Status laporan harus salah satu dari: Review, Revisi, atau Selesai.',
        ];
    }
}
