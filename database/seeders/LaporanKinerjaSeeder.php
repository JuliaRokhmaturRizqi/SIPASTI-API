<?php

namespace Database\Seeders;

use App\Models\LaporanKinerja;
use App\Models\PeriodeAkademik;
use App\Models\UnitKerja;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LaporanKinerjaSeeder extends Seeder
{
    /**
     * Seed Data Dummy Realistis Laporan Kinerja Unit untuk Demo UAT Universitas Nurul Huda.
     */
    public function run(): void
    {
        $periode = PeriodeAkademik::where('is_default', true)->first()
            ?? PeriodeAkademik::where('status_aktif', true)->first()
            ?? PeriodeAkademik::firstOrCreate(
                ['nama_periode' => '2026/2027 Ganjil'],
                [
                    'status_aktif'     => true,
                    'status_pelaporan' => PeriodeAkademik::STATUS_BUKA,
                    'is_default'       => true,
                ]
            );

        $pusatIt      = UnitKerja::where('nama_unit', 'Pusat IT Center')->first();
        $biroKeuangan = UnitKerja::where('nama_unit', 'Biro Administrasi Keuangan')->first()
            ?? UnitKerja::where('nama_unit', 'like', '%Keuangan%')->first();
        $labKomputer  = UnitKerja::where('nama_unit', 'Laboratorium Komputer')->first();
        $biroAkademik = UnitKerja::where('nama_unit', 'Biro Administrasi Akademik')->first();

        $dummyReports = [];

        if ($pusatIt) {
            // 1. Pusat IT Center - Bulan: Agustus, Status: Selesai
            $dummyReports[] = [
                'id_unit_kerja'      => $pusatIt->id,
                'id_periode'         => $periode->id,
                'bulan_laporan'      => 'Agustus',
                'nama_kegiatan'      => 'Maintenance & Optimasi Server SIAKAD dan Portal SIPASTI',
                'uraian'             => 'Melaksanakan pemeliharaan rutin (preventive maintenance) pada cluster database SIAKAD dan server aplikasi SIPASTI menjelang masa pengisian KRS dan pelaporan akademik semester ganjil. Kegiatan mencakup upgrade patch keamanan OS Linux Ubuntu Server, optimasi indexing query MySQL, serta konfigurasi automated daily backup ke cloud storage.',
                'link_bukti'         => 'https://drive.google.com/drive/folders/1a2b3c4d-dokumentasi-maintenance-it-unuha',
                'kendala'            => 'Terjadi lonjakan trafik akses bersamaan pada jam sibuk (pukul 09.00 - 11.00 WIB) yang sempat meningkatkan beban CPU server hingga 85%.',
                'dukungan'           => 'Penambahan kapasitas RAM dedicated server dari 32 GB menjadi 64 GB serta perpanjangan lisensi SSL Wildcard unuha.ac.id.',
                'tindak_lanjut'      => 'Menerapkan load balancing berbasis Nginx reverse proxy dan caching Redis untuk mempercepat waktu respons API di seluruh portal akademik.',
                'nama_penandatangan' => 'Ahmad Fauzi, S.Kom., M.Kom. (Kepala Pusat IT Center)',
                'status'             => LaporanKinerja::STATUS_SELESAI,
                'catatan_revisi'     => null,
            ];

            // 2. Pusat IT Center - Bulan: September, Status: Revisi
            $dummyReports[] = [
                'id_unit_kerja'      => $pusatIt->id,
                'id_periode'         => $periode->id,
                'bulan_laporan'      => 'September',
                'nama_kegiatan'      => 'Peremajaan Jaringan Fiber Optik & Access Point Kampus A dan C',
                'uraian'             => 'Melakukan penarikan ulang kabel backbone Fiber Optik antar gedung perkuliahan serta penggantian 18 unit Access Point Wi-Fi 6 guna menunjang kelancaran pembelajaran digital dosen dan mahasiswa di lingkungan Universitas Nurul Huda.',
                'link_bukti'         => 'https://drive.google.com/drive/folders/1x9y8z-berita-acara-instalasi-jar-unuha',
                'kendala'            => 'Beberapa jalur pipa conduit bawah tanah mengalami kerusakan akibat cuaca ekstrem sehingga membutuhkan jalur udara alternatif.',
                'dukungan'           => 'Koordinasi lintas unit dengan bagian sarana prasarana untuk pengadaan tiang penyangga kabel outdoor.',
                'tindak_lanjut'      => 'Melakukan pengujian throughput bandwidth per titik gedung dan pemetaan ulang coverage sinyal Wi-Fi.',
                'nama_penandatangan' => 'Ahmad Fauzi, S.Kom., M.Kom. (Kepala Pusat IT Center)',
                'status'             => LaporanKinerja::STATUS_REVISI,
                'catatan_revisi'     => 'Mohon lampirkan dokumen Berita Acara Serah Terima (BAST) pengujian kecepatan bandwidth per gedung pada tautan bukti dukung sebelum divalidasi selesai.',
            ];
        }

        if ($biroKeuangan) {
            // 3. Biro Keuangan - Bulan: Agustus, Status: Selesai
            $dummyReports[] = [
                'id_unit_kerja'      => $biroKeuangan->id,
                'id_periode'         => $periode->id,
                'bulan_laporan'      => 'Agustus',
                'nama_kegiatan'      => 'Penyusunan Rencana Anggaran Belanja (RAB) Semester Ganjil 2026/2027',
                'uraian'             => 'Menyelenggarakan rapat koordinasi penyusunan dan sinkronisasi Rencana Anggaran Belanja (RAB) bersama seluruh Fakultas, Program Studi, Biro, dan Lembaga di lingkungan Universitas Nurul Huda untuk menetapkan pagu anggaran operasional dan pengembangan mutu akademik.',
                'link_bukti'         => 'https://drive.google.com/drive/folders/2k3l4m-dokumen-rab-ganjil-unuha',
                'kendala'            => 'Masih terdapat 2 unit kerja yang terlambat menyerahkan draf rincian mata anggaran kegiatan tahunan.',
                'dukungan'           => 'Penetapan Surat Edaran Rektor mengenai batas akhir pengajuan Rencana Kerja dan Anggaran Tahunan (RKAT).',
                'tindak_lanjut'      => 'Melakukan verifikasi plafon anggaran per unit dan menerbitkan pengesahan pagu anggaran melalui Sistem Informasi Keuangan.',
                'nama_penandatangan' => 'Hj. Siti Aminah, S.E., M.Si. (Kepala Biro Keuangan)',
                'status'             => LaporanKinerja::STATUS_SELESAI,
                'catatan_revisi'     => null,
            ];

            // 4. Biro Keuangan - Bulan: September, Status: Review
            $dummyReports[] = [
                'id_unit_kerja'      => $biroKeuangan->id,
                'id_periode'         => $periode->id,
                'bulan_laporan'      => 'September',
                'nama_kegiatan'      => 'Rekonsiliasi Pembayaran UKT Mahasiswa & Audit Kas Triwulan III',
                'uraian'             => 'Melaksanakan proses rekonsiliasi data transaksi pembayaran Uang Kuliah Tunggal (UKT) mahasiswa melalui Virtual Account bank mitra dengan data tagihan pada sistem akademik, dilanjutkan dengan pemeriksaan kas internal triwulan III.',
                'link_bukti'         => 'https://drive.google.com/drive/folders/5p6q7r-rekap-rekonsiliasi-ukt-unuha',
                'kendala'            => 'Terdapat 14 transaksi pembayaran mahasiswa yang dilakukan menjelang tengah malam sehingga baru terbukukan pada hari kerja berikutnya (H+1).',
                'dukungan'           => 'Integrasi webhook API real-time antara sistem Host-to-Host Bank Mitra dengan portal keuangan kampus.',
                'tindak_lanjut'      => 'Menyelesaikan berita acara rekonsiliasi bank dan menyerahkan laporan keuangan triwulan kepada Wakil Rektor Bidang Umum & Keuangan.',
                'nama_penandatangan' => 'Hj. Siti Aminah, S.E., M.Si. (Kepala Biro Keuangan)',
                'status'             => LaporanKinerja::STATUS_REVIEW,
                'catatan_revisi'     => null,
            ];
        }

        if ($labKomputer) {
            // 5. Laboratorium Komputer - Bulan: September, Status: Review
            $dummyReports[] = [
                'id_unit_kerja'      => $labKomputer->id,
                'id_periode'         => $periode->id,
                'bulan_laporan'      => 'September',
                'nama_kegiatan'      => 'Kalibrasi Perangkat Praktikum & Instalasi Software Semester Ganjil',
                'uraian'             => 'Melakukan pengecekan perangkat keras pada 40 unit PC klien Laboratorium Komputer, pembaruan sistem operasi, serta instalasi perangkat lunak praktikum pemrograman, jaringan komputer, dan pengolahan data statistik.',
                'link_bukti'         => 'https://drive.google.com/drive/folders/8t9u0v-inventaris-labkom-unuha',
                'kendala'            => 'Terdapat 3 unit PC yang mengalami penurunan kesehatan SSD dan membutuhkan penggantian suku cadang.',
                'dukungan'           => 'Pengadaan 5 unit SSD NVMe 512 GB cadangan melalui pengajuan barang habis pakai laboratorium.',
                'tindak_lanjut'      => 'Menyusun jadwal penggunaan laboratorium terpadu bersama Ketua Program Studi dan asisten praktikum.',
                'nama_penandatangan' => 'M. Rizky Pratama, M.T. (Kepala Laboratorium Komputer)',
                'status'             => LaporanKinerja::STATUS_REVIEW,
                'catatan_revisi'     => null,
            ];
        }

        if ($biroAkademik) {
            // 6. Biro Administrasi Akademik - Bulan: Oktober, Status: Selesai
            $dummyReports[] = [
                'id_unit_kerja'      => $biroAkademik->id,
                'id_periode'         => $periode->id,
                'bulan_laporan'      => 'Oktober',
                'nama_kegiatan'      => 'Monitoring & Evaluasi Pelaporan Akademik PDDIKTI Semester Berjalan',
                'uraian'             => 'Melakukan pendampingan teknis dan validasi kelengkapan dokumen kurikulum, jadwal perkuliahan, nilai akhir, serta berita acara yudisium seluruh program studi di lingkungan Universitas Nurul Huda melalui sistem SIPASTI.',
                'link_bukti'         => 'https://drive.google.com/drive/folders/9w8x7y-monev-akademik-baa-unuha',
                'kendala'            => 'Tidak ada kendala signifikan, koordinasi dengan operator fakultas berjalan lancar.',
                'dukungan'           => 'Sosialisasi berkala fitur terbaru aplikasi SIPASTI kepada seluruh operator fakultas dan unit kerja.',
                'tindak_lanjut'      => 'Melaporkan rekapitulasi capaian kepatuhan pelaporan akademik kepada Rektor dan Senat Universitas.',
                'nama_penandatangan' => 'Wida Oktavia, S.Pd., M.Pd. (Kepala Biro Administrasi Akademik)',
                'status'             => LaporanKinerja::STATUS_SELESAI,
                'catatan_revisi'     => null,
            ];
        }

        DB::transaction(function () use ($dummyReports) {
            foreach ($dummyReports as $report) {
                LaporanKinerja::updateOrCreate(
                    [
                        'id_unit_kerja' => $report['id_unit_kerja'],
                        'id_periode'    => $report['id_periode'],
                        'nama_kegiatan' => $report['nama_kegiatan'],
                    ],
                    $report
                );
            }
        });
    }
}
