<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Format Laporan Pembahasan Kendala Dukungan Tindak Lanjut - {{ $laporan->unitKerja->nama_unit ?? 'Unit Kerja' }}</title>
    <style>
        @page {
            margin: 1.2cm 1.8cm 2.2cm 1.8cm;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            line-height: 1.38;
            color: #111111;
            margin: 0;
            padding: 0;
        }

        /* Kop Surat Dinamis / Fallback Statis */
        .kop-surat {
            width: 100%;
            text-align: center;
            margin-bottom: 10px;
            page-break-inside: avoid;
        }
        .kop-surat-img {
            width: 100%;
            height: auto;
            max-height: 130px;
            display: block;
            margin: 0 auto 4px auto;
        }
        .kop-surat-static {
            width: 100%;
            border-bottom: 3px solid #111111;
            padding-bottom: 6px;
            margin-bottom: 2px;
            text-align: center;
        }
        .kop-garis-tipis {
            border-bottom: 1px solid #111111;
            margin-bottom: 10px;
        }
        .kop-yayasan {
            font-size: 10pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0;
        }
        .kop-universitas {
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #2F4F4F;
            margin: 2px 0;
        }
        .kop-sistem {
            font-size: 9pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 2px 0;
        }
        .kop-alamat {
            font-size: 8.5pt;
            font-style: italic;
            color: #333333;
            margin: 2px 0 0 0;
        }

        /* Judul Utama di Bagian Paling Atas (Tengah) */
        .doc-header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            page-break-inside: avoid;
        }
        .doc-header-table td {
            text-align: center;
            border: none;
            padding: 0;
        }
        .doc-main-title {
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #111111;
            margin: 0 0 6px 0;
        }
        .doc-title-rule {
            border-bottom: 2px solid #2F4F4F;
            height: 2px;
            width: 100%;
        }

        /* Blok Bagian (Wrapper agar judul & konten tidak mudah terpisah) */
        .section-block {
            margin-top: 12px;
            margin-bottom: 8px;
        }

        /* Sub-Judul Bagian (A, B, C, D, E, F, G, H) */
        .section-heading {
            font-size: 10.5pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #111111;
            margin: 0 0 3px 0;
            padding: 0;
            page-break-after: avoid;
        }

        /* Deskripsi Sub-Judul */
        .section-desc {
            font-size: 9.5pt;
            color: #333333;
            margin: 0 0 6px 0;
            padding: 0;
            page-break-after: avoid;
        }

        /* Semua Tabel Umum (DOMPDF Strict) */
        table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: avoid;
        }
        tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }
        thead {
            display: table-header-group;
        }

        /* A. Tabel Identitas Laporan (2 Kolom Statis) */
        .table-identitas {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
            page-break-inside: avoid;
        }
        .table-identitas td {
            border: 1px solid #333333;
            padding: 5px 8px;
            vertical-align: top;
            font-size: 10pt;
        }
        .table-identitas td.col-label {
            width: 30%;
            font-weight: bold;
            background-color: #F4F6F5;
            color: #111111;
        }
        .table-identitas td.col-value {
            width: 70%;
            color: #111111;
        }

        /* B. Tabel Kotak Ringkasan Pelaksanaan */
        .table-ringkasan {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
            page-break-inside: avoid;
        }
        .table-ringkasan td {
            border: 1px solid #333333;
            padding: 7px 9px;
            vertical-align: top;
            font-size: 10pt;
            min-height: 45px;
            white-space: pre-line;
        }

        /* C, D, E, F. Tabel Data Dinamis dengan Header Hijau Gelap & Teks Putih */
        .table-matrix {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin-bottom: 4px;
            page-break-inside: avoid;
        }
        .table-matrix th {
            background-color: #2F4F4F;
            color: #FFFFFF;
            font-weight: bold;
            font-size: 9.5pt;
            text-align: center;
            vertical-align: middle;
            border: 1px solid #2F4F4F;
            padding: 6px 6px;
            word-wrap: break-word;
        }
        .table-matrix td {
            border: 1px solid #333333;
            padding: 5px 6px;
            vertical-align: top;
            text-align: left;
            font-size: 9.5pt;
            word-wrap: break-word;
            word-break: break-word;
            color: #111111;
        }
        .table-matrix td.col-no {
            width: 5%;
            text-align: center;
        }
        .link-bukti {
            color: #0B5394;
            text-decoration: underline;
            word-break: break-all;
        }

        /* Catatan Kecil di Bawah Tabel C */
        .note-small {
            font-size: 8.5pt;
            font-style: italic;
            color: #444444;
            margin: 2px 0 6px 0;
        }

        /* G. Tabel Statis 2 Baris Kesimpulan & Rekomendasi */
        .table-kesimpulan {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
            page-break-inside: avoid;
        }
        .table-kesimpulan td {
            border: 1px solid #333333;
            padding: 6px 8px;
            vertical-align: top;
            font-size: 10pt;
        }
        .table-kesimpulan td.col-label {
            width: 25%;
            font-weight: bold;
            background-color: #F4F6F5;
            color: #111111;
        }
        .table-kesimpulan td.col-value {
            width: 75%;
            white-space: pre-line;
            color: #111111;
        }

        /* H. Tabel Pengesahan Tanpa Border (Split 2 Kolom Kiri - Kanan) */
        .pengesahan-intro {
            font-size: 10pt;
            margin: 0 0 8px 0;
            color: #111111;
        }
        .table-pengesahan {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            margin-bottom: 10px;
            page-break-inside: avoid;
        }
        .table-pengesahan td {
            width: 50%;
            border: none;
            padding: 4px 12px;
            vertical-align: top;
            text-align: left;
            font-size: 10pt;
        }
        .ttd-heading {
            margin: 0 0 2px 0;
        }
        .ttd-role {
            font-weight: bold;
            margin: 0 0 6px 0;
        }
        .ttd-box {
            height: 65px;
            margin: 4px 0;
        }
        .ttd-img {
            max-height: 60px;
            max-width: 170px;
        }
        .ttd-name {
            font-weight: bold;
            text-decoration: underline;
            margin: 4px 0 2px 0;
        }
        .ttd-date {
            font-size: 9.5pt;
            margin: 0;
            color: #222222;
        }

        /* Petunjuk Bagian Paling Bawah */
        .petunjuk-bottom {
            margin-top: 8px;
            padding: 6px 8px;
            background-color: #F4F6F5;
            border-left: 3px solid #2F4F4F;
            font-size: 8.5pt;
            font-style: italic;
            color: #333333;
            page-break-inside: avoid;
        }

        /* Footer Halaman (Kiri: Teks, Kanan: Nomor Halaman) */
        .page-footer {
            position: fixed;
            bottom: -1.3cm;
            left: 0;
            right: 0;
            height: 0.9cm;
            border-top: 1px solid #888888;
            padding-top: 4px;
            font-size: 8.5pt;
            color: #444444;
        }
        .page-footer-table {
            width: 100%;
            border-collapse: collapse;
        }
        .page-footer-table td {
            border: none;
            padding: 0;
            font-size: 8.5pt;
            color: #444444;
        }
        .page-number:before {
            content: "Halaman " counter(page);
        }
    </style>
</head>
<body>

    @php
        $identitas = is_array($laporan->identitas_laporan)
            ? $laporan->identitas_laporan
            : (is_string($laporan->identitas_laporan) ? (json_decode($laporan->identitas_laporan, true) ?? []) : []);

        $pembahasanList = is_array($laporan->pembahasan_json)
            ? $laporan->pembahasan_json
            : (is_string($laporan->pembahasan_json) ? (json_decode($laporan->pembahasan_json, true) ?? []) : []);

        $kendalaList = is_array($laporan->kendala_json)
            ? $laporan->kendala_json
            : (is_string($laporan->kendala_json) ? (json_decode($laporan->kendala_json, true) ?? []) : []);

        $dukunganList = is_array($laporan->dukungan_json)
            ? $laporan->dukungan_json
            : (is_string($laporan->dukungan_json) ? (json_decode($laporan->dukungan_json, true) ?? []) : []);

        $tindakLanjutList = is_array($laporan->tindak_lanjut_json)
            ? $laporan->tindak_lanjut_json
            : (is_string($laporan->tindak_lanjut_json) ? (json_decode($laporan->tindak_lanjut_json, true) ?? []) : []);

        // Data Identitas Laporan
        $namaUnit = $identitas['nama_unit']
            ?? ($identitas['unit_bagian']
            ?? ($laporan->unitKerja->nama_unit ?? '-'));

        $namaKegiatan = $identitas['nama_kegiatan']
            ?? ($identitas['nama_program']
            ?? ($identitas['kegiatan']
            ?? ($laporan->nama_kegiatan
            ?? ($pembahasanList[0]['program_kerja'] ?? ($pembahasanList[0]['aspek'] ?? '-')))));

        $namaPeriode = $identitas['periode_laporan']
            ?? ($identitas['periode']
            ?? ($laporan->periodeAkademik->nama_periode ?? '-'));
        $bulanLaporan = $laporan->bulan_laporan ?? ($identitas['bulan'] ?? null);
        $periodeTampil = $bulanLaporan && !str_contains(strtolower($namaPeriode), strtolower($bulanLaporan))
            ? $bulanLaporan . ' (' . $namaPeriode . ')'
            : $namaPeriode;

        $tanggalPenyusunan = $identitas['tanggal_penyusunan']
            ?? ($identitas['tanggal']
            ?? ($tanggalCetak ?? ($laporan->created_at ? $laporan->created_at->format('d/m/Y') : date('d/m/Y'))));

        $penanggungJawab = $identitas['penanggung_jawab']
            ?? ($identitas['kepala_unit']
            ?? ($identitas['nama_pimpinan']
            ?? ($laporan->nama_penandatangan ?? '-')));

        $penyusunLaporan = $identitas['penyusun_laporan']
            ?? ($identitas['penyusun']
            ?? ($identitas['nama_penyusun'] ?? '-'));

        // Ringkasan Pelaksanaan (Bagian B)
        $ringkasanPelaksanaan = $laporan->ringkasan_pelaksanaan
            ?? ($identitas['ringkasan_pelaksanaan']
            ?? ($identitas['ringkasan']
            ?? ($laporan->uraian
            ?? ($pembahasanList[0]['uraian'] ?? '-'))));

        // Tanggal Pengesahan
        $tanggalPengesahanPimpinan = $identitas['tanggal_pengesahan_pimpinan']
            ?? ($identitas['tanggal_pimpinan']
            ?? ($laporan->status_laporan === 'menunggu_admin' || $laporan->status_laporan === 'selesai'
                ? ($laporan->updated_at ? $laporan->updated_at->format('d/m/Y') : $tanggalPenyusunan)
                : '........................................'));

        $tanggalPengesahanPenyusun = $identitas['tanggal_pengesahan_penyusun']
            ?? ($identitas['tanggal_penyusun']
            ?? $tanggalPenyusunan);
    @endphp

    <!-- FOOTER TETAP (KIRI: NAMA FORMAT, KANAN: NOMOR HALAMAN) -->
    <div class="page-footer">
        <table class="page-footer-table">
            <tr>
                <td style="text-align: left; width: 70%;">Format Laporan Evaluasi dan Tindak Lanjut</td>
                <td style="text-align: right; width: 30%;"><span class="page-number"></span></td>
            </tr>
        </table>
    </div>

    <!-- KOP SURAT DINAMIS (GAMBAR UPLOAD) ATAU STATIS (FALLBACK) -->
    <div class="kop-surat">
        @if(!empty($laporan->kop_surat_path) || !empty($kopSuratBase64))
            <img
                src="{{ !empty($kopSuratBase64) ? $kopSuratBase64 : public_path('storage/' . $laporan->kop_surat_path) }}"
                alt="Kop Surat"
                class="kop-surat-img"
            >
        @else
            <div class="kop-surat-static">
                <p class="kop-yayasan">Yayasan Pondok Pesantren Nurul Huda Sukaraja</p>
                <p class="kop-universitas">Universitas Nurul Huda (UNUHA)</p>
                <p class="kop-sistem">Sistem Informasi Pelaporan Akademik &amp; Kinerja Terpadu (SIPASTI)</p>
                <p class="kop-alamat">Jl. Kotabaru Sukaraja, Buay Madang, OKU Timur, Sumatera Selatan 32161 | Website: www.unuha.ac.id</p>
            </div>
            <div class="kop-garis-tipis"></div>
        @endif
    </div>

    <!-- JUDUL ATAS -->
    <table class="doc-header-table">
        <tr>
            <td>
                <div class="doc-main-title">FORMAT LAPORAN PEMBAHASAN KENDALA DUKUNGAN TINDAK LANJUT</div>
                <div class="doc-title-rule"></div>
            </td>
        </tr>
    </table>

    <!-- A. IDENTITAS LAPORAN -->
    <div class="section-block">
        <div class="section-heading">A. IDENTITAS LAPORAN</div>
        <table class="table-identitas">
            <tr>
                <td class="col-label">Nama unit/bagian</td>
                <td class="col-value">{{ $namaUnit }}</td>
            </tr>
            <tr>
                <td class="col-label">Nama kegiatan/program</td>
                <td class="col-value">{{ $namaKegiatan }}</td>
            </tr>
            <tr>
                <td class="col-label">Periode laporan</td>
                <td class="col-value">{{ $periodeTampil }}</td>
            </tr>
            <tr>
                <td class="col-label">Tanggal penyusunan</td>
                <td class="col-value">{{ $tanggalPenyusunan }}</td>
            </tr>
            <tr>
                <td class="col-label">Penanggung jawab</td>
                <td class="col-value">{{ $penanggungJawab }}</td>
            </tr>
            <tr>
                <td class="col-label">Penyusun laporan</td>
                <td class="col-value">{{ $penyusunLaporan }}</td>
            </tr>
        </table>
    </div>

    <!-- B. RINGKASAN PELAKSANAAN -->
    <div class="section-block">
        <div class="section-heading">B. RINGKASAN PELAKSANAAN</div>
        <div class="section-desc">Gambaran singkat tujuan, waktu dan tempat, pihak yang terlibat, serta capaian utama.</div>
        <table class="table-ringkasan">
            <tr>
                <td>{{ $ringkasanPelaksanaan ?: '-' }}</td>
            </tr>
        </table>
    </div>

    <!-- C. PEMBAHASAN LAPORAN -->
    <div class="section-block">
        <div class="section-heading">C. PEMBAHASAN LAPORAN</div>
        <div class="section-desc">Uraikan realisasi kegiatan berdasarkan rencana, indikator capaian, hasil yang diperoleh, analisis pelaksanaan, serta tautan bukti dukung.</div>
        <table class="table-matrix">
            <thead>
                <tr>
                    <th style="width: 5%;">No</th>
                    <th style="width: 23%;">Aspek/indikator</th>
                    <th style="width: 25%;">Realisasi dan hasil</th>
                    <th style="width: 25%;">Analisis/catatan</th>
                    <th style="width: 22%;">Link bukti dukung (laporan/dokumen)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pembahasanList as $index => $item)
                    @php
                        $aspek = $item['aspek_indikator']
                            ?? ($item['aspek']
                            ?? ($item['indikator']
                            ?? ($item['program_kerja'] ?? '-')));

                        $realisasi = $item['realisasi_dan_hasil']
                            ?? ($item['realisasi']
                            ?? ($item['capaian']
                            ?? ($item['uraian'] ?? '-')));

                        $analisis = $item['analisis_catatan']
                            ?? ($item['analisis']
                            ?? ($item['catatan']
                            ?? ($item['status'] ?? '-')));

                        $bukti = $item['link_bukti']
                            ?? ($item['bukti']
                            ?? ($item['tautan'] ?? null));
                    @endphp
                    <tr>
                        <td class="col-no">{{ $index + 1 }}</td>
                        <td>{{ $aspek }}</td>
                        <td>{{ $realisasi }}</td>
                        <td>{{ $analisis }}</td>
                        <td>
                            @if(!empty($bukti))
                                <span class="link-bukti">{{ $bukti }}</span>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="col-no">1</td>
                        <td>{{ $laporan->nama_kegiatan ?? '-' }}</td>
                        <td>{{ $laporan->uraian ?? '-' }}</td>
                        <td>-</td>
                        <td>
                            @if(!empty($laporan->link_bukti))
                                <span class="link-bukti">{{ $laporan->link_bukti }}</span>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="note-small">Catatan: tautan dapat berupa URL Google Drive, OneDrive, situs resmi, atau penyimpanan dokumen internal.</div>
    </div>

    <!-- D. KENDALA DAN PERMASALAHAN -->
    <div class="section-block">
        <div class="section-heading">D. KENDALA DAN PERMASALAHAN</div>
        <div class="section-desc">Catat kendala, penyebab utama, dampak terhadap pelaksanaan kegiatan, serta upaya sementara yang telah dilakukan.</div>
        <table class="table-matrix">
            <thead>
                <tr>
                    <th style="width: 5%;">No</th>
                    <th style="width: 25%;">Kendala/permasalahan</th>
                    <th style="width: 23%;">Penyebab</th>
                    <th style="width: 23%;">Dampak</th>
                    <th style="width: 24%;">Upaya sementara</th>
                </tr>
            </thead>
            <tbody>
                @forelse($kendalaList as $index => $item)
                    @php
                        $kendalaItem = $item['kendala_permasalahan']
                            ?? ($item['kendala']
                            ?? ($item['uraian']
                            ?? ($item['kategori'] ?? '-')));

                        $penyebabItem = $item['penyebab'] ?? '-';
                        $dampakItem   = $item['dampak'] ?? '-';
                        $upayaItem    = $item['upaya_sementara']
                            ?? ($item['upaya']
                            ?? ($item['solusi'] ?? '-'));
                    @endphp
                    <tr>
                        <td class="col-no">{{ $index + 1 }}</td>
                        <td>{{ $kendalaItem }}</td>
                        <td>{{ $penyebabItem }}</td>
                        <td>{{ $dampakItem }}</td>
                        <td>{{ $upayaItem }}</td>
                    </tr>
                @empty
                    <tr>
                        <td class="col-no">1</td>
                        <td>{{ $laporan->kendala ?? '-' }}</td>
                        <td>-</td>
                        <td>-</td>
                        <td>-</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- E. DUKUNGAN YANG DIBUTUHKAN -->
    <div class="section-block">
        <div class="section-heading">E. DUKUNGAN YANG DIBUTUHKAN</div>
        <div class="section-desc">Jelaskan dukungan yang diperlukan, bentuk kebutuhan spesifik, pihak yang diharapkan membantu, dan tingkat prioritasnya.</div>
        <table class="table-matrix">
            <thead>
                <tr>
                    <th style="width: 5%;">No</th>
                    <th style="width: 23%;">Jenis dukungan</th>
                    <th style="width: 30%;">Bentuk/kebutuhan dukungan</th>
                    <th style="width: 24%;">Pihak yang diharapkan</th>
                    <th style="width: 18%;">Prioritas</th>
                </tr>
            </thead>
            <tbody>
                @forelse($dukunganList as $index => $item)
                    @php
                        $jenisDukungan = $item['jenis_dukungan']
                            ?? ($item['jenis']
                            ?? ($item['kategori'] ?? '-'));

                        $bentukDukungan = $item['bentuk_dukungan']
                            ?? ($item['bentuk']
                            ?? ($item['kebutuhan'] ?? '-'));

                        $pihakDiharapkan = $item['pihak_yang_diharapkan']
                            ?? ($item['pihak']
                            ?? ($item['pemberi']
                            ?? ($item['alasan'] ?? '-')));

                        $prioritasDukungan = $item['prioritas'] ?? '-';
                    @endphp
                    <tr>
                        <td class="col-no">{{ $index + 1 }}</td>
                        <td>{{ $jenisDukungan }}</td>
                        <td>{{ $bentukDukungan }}</td>
                        <td>{{ $pihakDiharapkan }}</td>
                        <td style="text-align: center;">{{ $prioritasDukungan }}</td>
                    </tr>
                @empty
                    <tr>
                        <td class="col-no">1</td>
                        <td>{{ $laporan->dukungan ?? '-' }}</td>
                        <td>-</td>
                        <td>-</td>
                        <td style="text-align: center;">-</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- F. RENCANA TINDAK LANJUT -->
    <div class="section-block">
        <div class="section-heading">F. RENCANA TINDAK LANJUT</div>
        <div class="section-desc">Rumuskan tindakan konkret/solusi yang akan ditempuh, penanggung jawab, target waktu penyelesaian, serta indikator keberhasilannya.</div>
        <table class="table-matrix">
            <thead>
                <tr>
                    <th style="width: 5%;">No</th>
                    <th style="width: 30%;">Tindak lanjut/solusi</th>
                    <th style="width: 22%;">Penanggung jawab</th>
                    <th style="width: 18%;">Target waktu</th>
                    <th style="width: 25%;">Indikator keberhasilan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tindakLanjutList as $index => $item)
                    @php
                        $tindakLanjutItem = $item['tindak_lanjut_solusi']
                            ?? ($item['tindak_lanjut']
                            ?? ($item['solusi']
                            ?? ($item['rencana'] ?? '-')));

                        $pjItem = $item['penanggung_jawab']
                            ?? ($item['pic'] ?? '-');

                        $targetWaktuItem = $item['target_waktu']
                            ?? ($item['waktu'] ?? '-');

                        $indikatorItem = $item['indikator_keberhasilan']
                            ?? ($item['indikator']
                            ?? ($item['output'] ?? '-'));
                    @endphp
                    <tr>
                        <td class="col-no">{{ $index + 1 }}</td>
                        <td>{{ $tindakLanjutItem }}</td>
                        <td>{{ $pjItem }}</td>
                        <td style="text-align: center;">{{ $targetWaktuItem }}</td>
                        <td>{{ $indikatorItem }}</td>
                    </tr>
                @empty
                    <tr>
                        <td class="col-no">1</td>
                        <td>{{ $laporan->tindak_lanjut ?? '-' }}</td>
                        <td>-</td>
                        <td style="text-align: center;">-</td>
                        <td>-</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- G. KESIMPULAN DAN REKOMENDASI -->
    <div class="section-block">
        <div class="section-heading">G. KESIMPULAN DAN REKOMENDASI</div>
        <table class="table-kesimpulan">
            <tr>
                <td class="col-label">Kesimpulan</td>
                <td class="col-value">{{ $laporan->kesimpulan ?: ($identitas['kesimpulan'] ?? '-') }}</td>
            </tr>
            <tr>
                <td class="col-label">Rekomendasi</td>
                <td class="col-value">{{ $laporan->rekomendasi ?: ($identitas['rekomendasi'] ?? '-') }}</td>
            </tr>
        </table>
    </div>

    <!-- H. PENGESAHAN -->
    <div class="section-block">
        <div class="section-heading">H. PENGESAHAN</div>
        <p class="pengesahan-intro">Demikian laporan ini disusun sebagai bahan evaluasi dan tindak lanjut pelaksanaan kegiatan.</p>

        <table class="table-pengesahan">
            <tr>
                <!-- Kolom Kiri: Mengetahui, Pimpinan/Kepala Unit -->
                <td>
                    <p class="ttd-heading">Mengetahui,</p>
                    <p class="ttd-role">Pimpinan/Kepala Unit</p>

                    <div class="ttd-box">
                        @if(!empty($laporan->ttd_pimpinan_path))
                            <img src="{{ !empty($ttdPimpinanBase64) ? $ttdPimpinanBase64 : public_path('storage/' . $laporan->ttd_pimpinan_path) }}" alt="TTD Pimpinan" class="ttd-img">
                        @endif
                    </div>

                    <p class="ttd-name">{{ $penanggungJawab !== '-' ? $penanggungJawab : '................................................' }}</p>
                    <p class="ttd-date">Tanggal: {{ $tanggalPengesahanPimpinan }}</p>
                </td>

                <!-- Kolom Kanan: Disusun oleh, Penanggung Jawab -->
                <td>
                    <p class="ttd-heading">Disusun oleh,</p>
                    <p class="ttd-role">Penanggung Jawab</p>

                    <div class="ttd-box">
                        @if(!empty($laporan->ttd_penyusun_path))
                            <img src="{{ !empty($ttdPenyusunBase64) ? $ttdPenyusunBase64 : public_path('storage/' . $laporan->ttd_penyusun_path) }}" alt="TTD Penyusun" class="ttd-img">
                        @endif
                    </div>

                    <p class="ttd-name">{{ $penyusunLaporan !== '-' ? $penyusunLaporan : ($penanggungJawab !== '-' ? $penanggungJawab : '................................................') }}</p>
                    <p class="ttd-date">Tanggal: {{ $tanggalPengesahanPenyusun }}</p>
                </td>
            </tr>
        </table>

        <div class="petunjuk-bottom">
            Petunjuk: gunakan data faktual dan terukur, lampirkan tautan bukti dukung yang dapat diakses, serta pastikan laporan telah disahkan sebelum diarsipkan.
        </div>
    </div>

</body>
</html>
