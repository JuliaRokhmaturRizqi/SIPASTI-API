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
     * Seed Data Dummy Realistis Laporan Kinerja Unit (Dynamic JSON & Approval Workflow)
     * untuk Demo UAT Universitas Nurul Huda.
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
                'identitas_laporan'  => [
                    'nama_unit'        => $pusatIt->nama_unit,
                    'periode'          => $periode->nama_periode,
                    'bulan'            => 'Agustus',
                    'penanggung_jawab' => 'Ahmad Fauzi, S.Kom., M.Kom.',
                    'jabatan_pimpinan' => 'Kepala Pusat IT Center',
                    'nama_penyusun'    => 'Budi Santoso, S.Kom.',
                    'jabatan_penyusun' => 'Staf Administrasi & Jaringan IT',
                ],
                'pembahasan_json'    => [
                    [
                        'program_kerja' => 'Maintenance & Optimasi Server SIAKAD dan Portal SIPASTI',
                        'uraian'        => 'Melaksanakan pemeliharaan rutin (preventive maintenance) pada cluster database SIAKAD dan server aplikasi SIPASTI menjelang masa pengisian KRS dan pelaporan akademik semester ganjil.',
                        'indikator'     => 'Uptime Server >= 99.8%',
                        'target'        => '100%',
                        'capaian'       => '100%',
                        'status'        => 'Tercapai',
                        'bukti'         => 'https://drive.google.com/drive/folders/1a2b3c4d-dokumentasi-maintenance-it-unuha',
                    ],
                ],
                'kendala_json'       => [
                    [
                        'kategori' => 'Infrastruktur Server',
                        'uraian'   => 'Terjadi lonjakan trafik akses bersamaan pada jam sibuk (pukul 09.00 - 11.00 WIB) yang sempat meningkatkan beban CPU server hingga 85%.',
                        'dampak'   => 'Waktu muat halaman portal sempat melambat sekitar 2-3 detik.',
                    ],
                ],
                'dukungan_json'      => [
                    [
                        'kebutuhan' => 'Penambahan kapasitas RAM dedicated server dari 32 GB menjadi 64 GB serta perpanjangan lisensi SSL Wildcard unuha.ac.id.',
                        'alasan'    => 'Menjamin stabilitas layanan SIAKAD dan SIPASTI pada periode puncak pengisian KRS.',
                        'prioritas' => 'Tinggi',
                    ],
                ],
                'tindak_lanjut_json' => [
                    [
                        'rencana'          => 'Menerapkan load balancing berbasis Nginx reverse proxy dan caching Redis.',
                        'target_waktu'     => 'September 2026',
                        'penanggung_jawab' => 'Tim Infrastruktur Pusat IT Center',
                    ],
                ],
                'kesimpulan'         => 'Seluruh agenda pemeliharaan dan optimasi server Pusat IT Center pada bulan Agustus berjalan sesuai target dengan tingkat ketersediaan layanan (uptime) mencapai 99.9%.',
                'rekomendasi'        => 'Perlu dilakukan upgrade kapasitas memori server sebelum periode UTS dan UAS berlangsung.',
                'nama_penandatangan' => 'Ahmad Fauzi, S.Kom., M.Kom.',
                'status_laporan'     => LaporanKinerja::STATUS_SELESAI,
                'catatan_revisi'     => 'Laporan sudah lengkap dan sesuai capaian teknis.',
                'catatan_admin'      => null,
            ];

            // 2. Pusat IT Center - Bulan: September, Status: Menunggu Pimpinan
            $dummyReports[] = [
                'id_unit_kerja'      => $pusatIt->id,
                'id_periode'         => $periode->id,
                'bulan_laporan'      => 'September',
                'identitas_laporan'  => [
                    'nama_unit'        => $pusatIt->nama_unit,
                    'periode'          => $periode->nama_periode,
                    'bulan'            => 'September',
                    'penanggung_jawab' => 'Ahmad Fauzi, S.Kom., M.Kom.',
                    'jabatan_pimpinan' => 'Kepala Pusat IT Center',
                    'nama_penyusun'    => 'Budi Santoso, S.Kom.',
                    'jabatan_penyusun' => 'Staf Administrasi & Jaringan IT',
                ],
                'pembahasan_json'    => [
                    [
                        'program_kerja' => 'Peremajaan Jaringan Fiber Optik & Access Point Kampus A dan C',
                        'uraian'        => 'Melakukan penarikan ulang kabel backbone Fiber Optik antar gedung perkuliahan serta penggantian 18 unit Access Point Wi-Fi 6 guna menunjang kelancaran pembelajaran digital.',
                        'indikator'     => 'Jumlah Titik Access Point Aktif',
                        'target'        => '18 Titik',
                        'capaian'       => '18 Titik (100%)',
                        'status'        => 'Tercapai',
                        'bukti'         => 'https://drive.google.com/drive/folders/1x9y8z-berita-acara-instalasi-jar-unuha',
                    ],
                ],
                'kendala_json'       => [
                    [
                        'kategori' => 'Jaringan & Sarana',
                        'uraian'   => 'Beberapa jalur pipa conduit bawah tanah mengalami kerusakan akibat cuaca ekstrem sehingga membutuhkan jalur udara alternatif.',
                        'dampak'   => 'Waktu instalasi di Gedung C bertambah 2 hari kerja.',
                    ],
                ],
                'dukungan_json'      => [
                    [
                        'kebutuhan' => 'Koordinasi lintas unit dengan Biro Sarana dan Prasarana untuk pengadaan tiang penyangga kabel outdoor.',
                        'alasan'    => 'Melindungi kabel backbone Fiber Optik dari gangguan fisik dan cuaca.',
                        'prioritas' => 'Sedang',
                    ],
                ],
                'tindak_lanjut_json' => [
                    [
                        'rencana'          => 'Melakukan pengujian throughput bandwidth per titik gedung dan pemetaan ulang coverage sinyal Wi-Fi.',
                        'target_waktu'     => 'Oktober 2026',
                        'penanggung_jawab' => 'Divisi Network & Infrastruktur IT',
                    ],
                ],
                'kesimpulan'         => 'Instalasi 18 titik Access Point Wi-Fi 6 telah selesai 100% dan siap diuji coba bersama pengguna di Kampus A dan C.',
                'rekomendasi'        => 'Penjadwalan inspeksi berkala jalur kabel fiber optik setiap awal semester.',
                'nama_penandatangan' => 'Ahmad Fauzi, S.Kom., M.Kom.',
                'status_laporan'     => LaporanKinerja::STATUS_MENUNGGU_PIMPINAN,
                'catatan_revisi'     => null,
                'catatan_admin'      => null,
            ];
        }

        if ($biroKeuangan) {
            // 3. Biro Keuangan - Bulan: Agustus, Status: Selesai
            $dummyReports[] = [
                'id_unit_kerja'      => $biroKeuangan->id,
                'id_periode'         => $periode->id,
                'bulan_laporan'      => 'Agustus',
                'identitas_laporan'  => [
                    'nama_unit'        => $biroKeuangan->nama_unit,
                    'periode'          => $periode->nama_periode,
                    'bulan'            => 'Agustus',
                    'penanggung_jawab' => 'Hj. Siti Aminah, S.E., M.Si.',
                    'jabatan_pimpinan' => 'Kepala Biro Administrasi Keuangan',
                    'nama_penyusun'    => 'Rina Kartika, S.Ak.',
                    'jabatan_penyusun' => 'Staf Administrasi Keuangan',
                ],
                'pembahasan_json'    => [
                    [
                        'program_kerja' => 'Penyusunan Rencana Anggaran Belanja (RAB) Semester Ganjil 2026/2027',
                        'uraian'        => 'Menyelenggarakan rapat koordinasi penyusunan dan sinkronisasi Rencana Anggaran Belanja (RAB) bersama seluruh Fakultas, Program Studi, Biro, dan Pusat.',
                        'indikator'     => 'Persentase Unit Mengumpulkan RAB',
                        'target'        => '100% Unit Kerja',
                        'capaian'       => '100% Unit Kerja',
                        'status'        => 'Tercapai',
                        'bukti'         => 'https://drive.google.com/drive/folders/2k3l4m-dokumen-rab-ganjil-unuha',
                    ],
                ],
                'kendala_json'       => [
                    [
                        'kategori' => 'Administrasi Anggaran',
                        'uraian'   => 'Terdapat 2 unit kerja yang sempat terlambat menyerahkan draf rincian mata anggaran kegiatan tahunan.',
                        'dampak'   => 'Proses kompilasi draf awal mundur 1 hari dari jadwal semula.',
                    ],
                ],
                'dukungan_json'      => [
                    [
                        'kebutuhan' => 'Penetapan Surat Edaran Rektor mengenai batas akhir pengajuan Rencana Kerja dan Anggaran Tahunan (RKAT).',
                        'alasan'    => 'Meningkatkan ketertiban waktu pengajuan anggaran seluruh unit kerja.',
                        'prioritas' => 'Tinggi',
                    ],
                ],
                'tindak_lanjut_json' => [
                    [
                        'rencana'          => 'Melakukan verifikasi plafon anggaran per unit dan menerbitkan pengesahan pagu anggaran.',
                        'target_waktu'     => 'September 2026',
                        'penanggung_jawab' => 'Kepala Biro Administrasi Keuangan',
                    ],
                ],
                'kesimpulan'         => 'Penyusunan dan sinkronisasi RAB Semester Ganjil 2026/2027 telah tuntas untuk seluruh unit kerja.',
                'rekomendasi'        => 'Integrasi modul pengajuan RAB daring pada pengembangan tahap berikutnya.',
                'nama_penandatangan' => 'Hj. Siti Aminah, S.E., M.Si.',
                'status_laporan'     => LaporanKinerja::STATUS_SELESAI,
                'catatan_revisi'     => 'Disetujui untuk diteruskan ke Admin.',
                'catatan_admin'      => null,
            ];

            // 4. Biro Keuangan - Bulan: September, Status: Menunggu Admin
            $dummyReports[] = [
                'id_unit_kerja'      => $biroKeuangan->id,
                'id_periode'         => $periode->id,
                'bulan_laporan'      => 'September',
                'identitas_laporan'  => [
                    'nama_unit'        => $biroKeuangan->nama_unit,
                    'periode'          => $periode->nama_periode,
                    'bulan'            => 'September',
                    'penanggung_jawab' => 'Hj. Siti Aminah, S.E., M.Si.',
                    'jabatan_pimpinan' => 'Kepala Biro Administrasi Keuangan',
                    'nama_penyusun'    => 'Rina Kartika, S.Ak.',
                    'jabatan_penyusun' => 'Staf Administrasi Keuangan',
                ],
                'pembahasan_json'    => [
                    [
                        'program_kerja' => 'Rekonsiliasi Pembayaran UKT Mahasiswa & Audit Kas Triwulan III',
                        'uraian'        => 'Melaksanakan proses rekonsiliasi data transaksi pembayaran Uang Kuliah Tunggal (UKT) mahasiswa melalui Virtual Account bank mitra dengan data tagihan pada sistem akademik.',
                        'indikator'     => 'Kesesuaian Data Mutasi Bank & SIAKAD',
                        'target'        => '100% Akurat',
                        'capaian'       => '100% Akurat',
                        'status'        => 'Tercapai',
                        'bukti'         => 'https://drive.google.com/drive/folders/5p6q7r-rekap-rekonsiliasi-ukt-unuha',
                    ],
                ],
                'kendala_json'       => [
                    [
                        'kategori' => 'Sistem Pembayaran',
                        'uraian'   => 'Terdapat 14 transaksi pembayaran mahasiswa yang dilakukan menjelang tengah malam sehingga baru terbukukan pada hari kerja berikutnya (H+1).',
                        'dampak'   => 'Memerlukan verifikasi manual pada laporan settlement pagi hari.',
                    ],
                ],
                'dukungan_json'      => [
                    [
                        'kebutuhan' => 'Integrasi webhook API real-time antara sistem Host-to-Host Bank Mitra dengan portal keuangan kampus.',
                        'alasan'    => 'Mempercepat proses verifikasi otomatis pembayaran UKT di luar jam kerja.',
                        'prioritas' => 'Tinggi',
                    ],
                ],
                'tindak_lanjut_json' => [
                    [
                        'rencana'          => 'Menyelesaikan berita acara rekonsiliasi bank dan menyerahkan laporan keuangan triwulan kepada Wakil Rektor II.',
                        'target_waktu'     => 'Oktober 2026',
                        'penanggung_jawab' => 'Subbagian Akuntansi & Pelaporan',
                    ],
                ],
                'kesimpulan'         => 'Rekonsiliasi pembayaran UKT dan audit kas internal triwulan III telah diselesaikan tanpa selisih.',
                'rekomendasi'        => 'Otomatisasi sinkronisasi mutasi malam hari bersama Pusat IT Center dan Bank Mitra.',
                'nama_penandatangan' => 'Hj. Siti Aminah, S.E., M.Si.',
                'status_laporan'     => LaporanKinerja::STATUS_MENUNGGU_ADMIN,
                'catatan_revisi'     => 'Telah diperiksa dan disetujui Kepala Biro Administrasi Keuangan.',
                'catatan_admin'      => null,
            ];
        }

        if ($labKomputer) {
            // 5. Laboratorium Komputer - Bulan: September, Status: Menunggu Pimpinan
            $dummyReports[] = [
                'id_unit_kerja'      => $labKomputer->id,
                'id_periode'         => $periode->id,
                'bulan_laporan'      => 'September',
                'identitas_laporan'  => [
                    'nama_unit'        => $labKomputer->nama_unit,
                    'periode'          => $periode->nama_periode,
                    'bulan'            => 'September',
                    'penanggung_jawab' => 'M. Rizky Pratama, M.T.',
                    'jabatan_pimpinan' => 'Kepala Laboratorium Komputer',
                    'nama_penyusun'    => 'Dedi Kurniawan, S.Kom.',
                    'jabatan_penyusun' => 'Laboran & Teknisi Komputer',
                ],
                'pembahasan_json'    => [
                    [
                        'program_kerja' => 'Kalibrasi Perangkat Praktikum & Instalasi Software Semester Ganjil',
                        'uraian'        => 'Melakukan pengecekan perangkat keras pada 40 unit PC klien Laboratorium Komputer, pembaruan sistem operasi, serta instalasi perangkat lunak praktikum pemrograman.',
                        'indikator'     => 'Jumlah Unit PC Siap Pakai',
                        'target'        => '40 Unit PC',
                        'capaian'       => '37 Unit PC (92.5%)',
                        'status'        => 'Berjalan',
                        'bukti'         => 'https://drive.google.com/drive/folders/8t9u0v-inventaris-labkom-unuha',
                    ],
                ],
                'kendala_json'       => [
                    [
                        'kategori' => 'Perangkat Keras',
                        'uraian'   => 'Terdapat 3 unit PC yang mengalami penurunan kesehatan SSD dan membutuhkan penggantian suku cadang.',
                        'dampak'   => '3 unit PC cadangan belum dapat digunakan untuk praktikum.',
                    ],
                ],
                'dukungan_json'      => [
                    [
                        'kebutuhan' => 'Pengadaan 5 unit SSD NVMe 512 GB cadangan melalui pengajuan barang habis pakai laboratorium.',
                        'alasan'    => 'Memastikan seluruh 40 kursi praktikum berfungsi optimal.',
                        'prioritas' => 'Tinggi',
                    ],
                ],
                'tindak_lanjut_json' => [
                    [
                        'rencana'          => 'Menyusun jadwal penggunaan laboratorium terpadu bersama Ketua Program Studi dan asisten praktikum.',
                        'target_waktu'     => 'Oktober 2026',
                        'penanggung_jawab' => 'Kepala Laboratorium Komputer',
                    ],
                ],
                'kesimpulan'         => 'Sebanyak 37 dari 40 unit PC Laboratorium Komputer telah siap digunakan untuk praktikum Semester Ganjil 2026/2027.',
                'rekomendasi'        => 'Percepatan pengadaan suku cadang penyimpanan (SSD) sebelum minggu ke-3 perkuliahan.',
                'nama_penandatangan' => 'M. Rizky Pratama, M.T.',
                'status_laporan'     => LaporanKinerja::STATUS_MENUNGGU_PIMPINAN,
                'catatan_revisi'     => null,
                'catatan_admin'      => null,
            ];
        }

        if ($biroAkademik) {
            // 6. Biro Administrasi Akademik - Bulan: Oktober, Status: Selesai
            $dummyReports[] = [
                'id_unit_kerja'      => $biroAkademik->id,
                'id_periode'         => $periode->id,
                'bulan_laporan'      => 'Oktober',
                'identitas_laporan'  => [
                    'nama_unit'        => $biroAkademik->nama_unit,
                    'periode'          => $periode->nama_periode,
                    'bulan'            => 'Oktober',
                    'penanggung_jawab' => 'Wida Oktavia, S.Pd., M.Pd.',
                    'jabatan_pimpinan' => 'Kepala Biro Administrasi Akademik',
                    'nama_penyusun'    => 'Hendri Saputra, S.Pd.',
                    'jabatan_penyusun' => 'Staf Administrasi Akademik',
                ],
                'pembahasan_json'    => [
                    [
                        'program_kerja' => 'Monitoring & Evaluasi Pelaporan Akademik PDDIKTI Semester Berjalan',
                        'uraian'        => 'Melakukan pendampingan teknis dan validasi kelengkapan dokumen kurikulum, jadwal perkuliahan, nilai akhir, serta berita acara yudisium seluruh program studi melalui sistem SIPASTI.',
                        'indikator'     => 'Kepatuhan Pelaporan Prodi',
                        'target'        => '100% Prodi',
                        'capaian'       => '100% Prodi',
                        'status'        => 'Tercapai',
                        'bukti'         => 'https://drive.google.com/drive/folders/9w8x7y-monev-akademik-baa-unuha',
                    ],
                ],
                'kendala_json'       => [
                    [
                        'kategori' => 'Koordinasi',
                        'uraian'   => 'Tidak ada kendala signifikan, koordinasi dengan operator fakultas berjalan lancar.',
                        'dampak'   => 'Minimal',
                    ],
                ],
                'dukungan_json'      => [
                    [
                        'kebutuhan' => 'Sosialisasi berkala fitur terbaru aplikasi SIPASTI kepada seluruh operator fakultas dan unit kerja.',
                        'alasan'    => 'Mempertahankan ketepatan waktu pelaporan setiap bulan dan semester.',
                        'prioritas' => 'Sedang',
                    ],
                ],
                'tindak_lanjut_json' => [
                    [
                        'rencana'          => 'Melaporkan rekapitulasi capaian kepatuhan pelaporan akademik kepada Rektor dan Senat Universitas.',
                        'target_waktu'     => 'November 2026',
                        'penanggung_jawab' => 'Kepala Biro Administrasi Akademik',
                    ],
                ],
                'kesimpulan'         => 'Monitoring dan evaluasi pelaporan akademik berjalan tertib dan seluruh program studi telah memenuhi target pelaporan.',
                'rekomendasi'        => 'Pemberian apresiasi bagi unit kerja dan fakultas dengan pelaporan tercepat.',
                'nama_penandatangan' => 'Wida Oktavia, S.Pd., M.Pd.',
                'status_laporan'     => LaporanKinerja::STATUS_SELESAI,
                'catatan_revisi'     => 'Disetujui.',
                'catatan_admin'      => null,
            ];
        }

        DB::transaction(function () use ($dummyReports) {
            foreach ($dummyReports as $report) {
                LaporanKinerja::updateOrCreate(
                    [
                        'id_unit_kerja' => $report['id_unit_kerja'],
                        'id_periode'    => $report['id_periode'],
                        'bulan_laporan' => $report['bulan_laporan'],
                    ],
                    $report
                );
            }
        });
    }
}
