<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Kinerja - {{ $laporan->unitKerja->nama_unit ?? 'Unit Kerja' }}</title>
    <style>
        @page {
            margin: 1.8cm 1.8cm 1.8cm 2cm;
        }
        body {
            font-family: "Times New Roman", Times, Arial, serif;
            font-size: 10.5pt;
            line-height: 1.4;
            color: #111111;
            margin: 0;
            padding: 0;
        }

        /* Kop Surat Resmi Kampus */
        .kop-surat {
            width: 100%;
            border-bottom: 3px solid #000000;
            padding-bottom: 6px;
            margin-bottom: 2px;
            text-align: center;
        }
        .kop-garis-tipis {
            border-bottom: 1px solid #000000;
            margin-bottom: 14px;
        }
        .kop-yayasan {
            font-size: 10.5pt;
            font-weight: bold;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            margin: 0;
        }
        .kop-universitas {
            font-size: 15pt;
            font-weight: bold;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            margin: 2px 0;
            color: #0b4d26;
        }
        .kop-sistem {
            font-size: 9.5pt;
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

        /* Judul Dokumen */
        .judul-dokumen {
            text-align: center;
            margin-bottom: 14px;
        }
        .judul-dokumen h2 {
            font-size: 12.5pt;
            font-weight: bold;
            text-transform: uppercase;
            text-decoration: underline;
            margin: 0 0 3px 0;
        }
        .judul-dokumen p {
            font-size: 9.5pt;
            margin: 0;
            color: #333333;
        }

        /* Heading Bagian (A, C, D, E, F, G, H) */
        .section-title {
            font-size: 10.5pt;
            font-weight: bold;
            text-transform: uppercase;
            background-color: #e8efe9;
            border: 1px solid #222222;
            padding: 5px 8px;
            margin-top: 12px;
            margin-bottom: 6px;
        }

        /* Tabel Identitas */
        .table-identitas {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .table-identitas td {
            border: 1px solid #222222;
            padding: 5px 8px;
            vertical-align: top;
            font-size: 10pt;
        }
        .table-identitas td.label {
            width: 28%;
            font-weight: bold;
            background-color: #f7f9f7;
        }
        .table-identitas td.value {
            width: 72%;
        }

        /* Tabel Data Dinamis */
        .table-data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .table-data th,
        .table-data td {
            border: 1px solid #222222;
            padding: 5px 7px;
            vertical-align: top;
            text-align: left;
            font-size: 9.5pt;
            word-wrap: break-word;
        }
        .table-data th {
            background-color: #f0f4f1;
            font-weight: bold;
            text-align: center;
        }
        .table-data td.col-no {
            width: 5%;
            text-align: center;
        }
        .link-text {
            color: #0b5394;
            text-decoration: underline;
            word-break: break-all;
        }

        /* Kotak Kesimpulan & Rekomendasi */
        .box-narasi {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .box-narasi th,
        .box-narasi td {
            border: 1px solid #222222;
            padding: 6px 8px;
            vertical-align: top;
            text-align: left;
            font-size: 10pt;
        }
        .box-narasi th {
            width: 25%;
            background-color: #f7f9f7;
            font-weight: bold;
        }
        .box-narasi td {
            width: 75%;
            white-space: pre-line;
        }

        /* Tabel Pengesahan (Split Kiri - Kanan) */
        .table-pengesahan {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }
        .table-pengesahan td {
            width: 50%;
            vertical-align: top;
            text-align: center;
            padding: 6px 10px;
            font-size: 10pt;
            border: none;
        }
        .ttd-space {
            height: 65px;
        }
        .ttd-img-wrapper {
            height: 65px;
            margin: 4px auto;
            text-align: center;
        }
        .ttd-img {
            max-height: 60px;
            max-width: 165px;
        }
        .ttd-nama {
            font-weight: bold;
            text-decoration: underline;
            margin-top: 4px;
        }

        /* Footer */
        .catatan-kaki {
            margin-top: 20px;
            border-top: 1px dashed #888888;
            padding-top: 5px;
            font-size: 8pt;
            color: #555555;
            font-style: italic;
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

        $kategoriNama    = $laporan->unitKerja->kategori->nama_kategori ?? $laporan->unitKerja->kategori_unit ?? 'UNIT';
        $namaUnit        = $laporan->unitKerja->nama_unit ?? ($identitas['nama_unit'] ?? '-');
        $namaPeriode     = $laporan->periodeAkademik->nama_periode ?? ($identitas['periode'] ?? '-');
        $bulanLaporan    = $laporan->bulan_laporan ?? ($identitas['bulan'] ?? '-');
        $penanggungJawab = $identitas['penanggung_jawab'] ?? ($identitas['kepala_unit'] ?? ($laporan->nama_penandatangan ?? '-'));
        $namaPenyusun    = $identitas['penyusun'] ?? ($identitas['nama_penyusun'] ?? '-');
    @endphp

    <!-- KOP SURAT RESMI -->
    <div class="kop-surat">
        <p class="kop-yayasan">Yayasan Pondok Pesantren Nurul Huda Sukaraja</p>
        <p class="kop-universitas">Universitas Nurul Huda (UNUHA)</p>
        <p class="kop-sistem">Sistem Informasi Pelaporan Akademik &amp; Kinerja Terpadu (SIPASTI)</p>
        <p class="kop-alamat">Jl. Kotabaru Sukaraja, Buay Madang, OKU Timur, Sumatera Selatan 32161 | Website: www.unuha.ac.id</p>
    </div>
    <div class="kop-garis-tipis"></div>

    <!-- JUDUL DOKUMEN -->
    <div class="judul-dokumen">
        <h2>Laporan Kinerja Unit Kerja</h2>
        <p>Nomor Dokumen: LK/{{ strtoupper($kategoriNama) }}/{{ $laporan->id }}/{{ date('Y') }}</p>
    </div>

    <!-- A. IDENTITAS LAPORAN -->
    <div class="section-title">A. Identitas Laporan</div>
    <table class="table-identitas">
        <tr>
            <td class="label">Nama Unit Kerja</td>
            <td class="value"><strong>{{ $namaUnit }}</strong></td>
        </tr>
        <tr>
            <td class="label">Periode Pelaporan</td>
            <td class="value">{{ $namaPeriode }}</td>
        </tr>
        <tr>
            <td class="label">Bulan</td>
            <td class="value">{{ $bulanLaporan }}</td>
        </tr>
        <tr>
            <td class="label">Penanggung Jawab</td>
            <td class="value">{{ $penanggungJawab }}</td>
        </tr>
        <tr>
            <td class="label">Penyusun</td>
            <td class="value">{{ $namaPenyusun }}</td>
        </tr>
    </table>

    <!-- C. PEMBAHASAN LAPORAN -->
    <div class="section-title">C. Pembahasan Laporan</div>
    <table class="table-data">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 25%;">Aspek / Indikator</th>
                <th style="width: 22%;">Realisasi</th>
                <th style="width: 28%;">Analisis</th>
                <th style="width: 20%;">Link Bukti</th>
            </tr>
        </thead>
        <tbody>
            @forelse($pembahasanList as $index => $item)
                <tr>
                    <td class="col-no">{{ $index + 1 }}</td>
                    <td>{{ $item['aspek'] ?? ($item['indikator'] ?? ($item['aspek_indikator'] ?? '-')) }}</td>
                    <td>{{ $item['realisasi'] ?? '-' }}</td>
                    <td>{{ $item['analisis'] ?? '-' }}</td>
                    <td>
                        @if(!empty($item['link_bukti']))
                            <span class="link-text">{{ $item['link_bukti'] }}</span>
                        @else
                            -
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center;">{{ $laporan->uraian ?? 'Tidak ada data pembahasan.' }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- D. KENDALA DAN PERMASALAHAN -->
    <div class="section-title">D. Kendala dan Permasalahan</div>
    <table class="table-data">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 25%;">Kendala</th>
                <th style="width: 23%;">Penyebab</th>
                <th style="width: 23%;">Dampak</th>
                <th style="width: 24%;">Upaya</th>
            </tr>
        </thead>
        <tbody>
            @forelse($kendalaList as $index => $item)
                <tr>
                    <td class="col-no">{{ $index + 1 }}</td>
                    <td>{{ $item['kendala'] ?? '-' }}</td>
                    <td>{{ $item['penyebab'] ?? '-' }}</td>
                    <td>{{ $item['dampak'] ?? '-' }}</td>
                    <td>{{ $item['upaya'] ?? ($item['solusi'] ?? '-') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center;">{{ $laporan->kendala ?? 'Tidak ada kendala yang dilaporkan.' }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- E. DUKUNGAN YANG DIBUTUHKAN -->
    <div class="section-title">E. Dukungan yang Dibutuhkan</div>
    <table class="table-data">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 35%;">Jenis Dukungan</th>
                <th style="width: 35%;">Bentuk</th>
                <th style="width: 25%;">Pihak</th>
            </tr>
        </thead>
        <tbody>
            @forelse($dukunganList as $index => $item)
                <tr>
                    <td class="col-no">{{ $index + 1 }}</td>
                    <td>{{ $item['jenis_dukungan'] ?? ($item['jenis'] ?? '-') }}</td>
                    <td>{{ $item['bentuk'] ?? ($item['bentuk_dukungan'] ?? '-') }}</td>
                    <td>{{ $item['pihak'] ?? ($item['pemberi'] ?? '-') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align: center;">{{ $laporan->dukungan ?? 'Tidak ada dukungan khusus yang diajukan.' }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- F. RENCANA TINDAK LANJUT -->
    <div class="section-title">F. Rencana Tindak Lanjut</div>
    <table class="table-data">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 30%;">Tindak Lanjut</th>
                <th style="width: 22%;">Penanggung Jawab</th>
                <th style="width: 20%;">Target Waktu</th>
                <th style="width: 23%;">Indikator</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tindakLanjutList as $index => $item)
                <tr>
                    <td class="col-no">{{ $index + 1 }}</td>
                    <td>{{ $item['tindak_lanjut'] ?? ($item['rencana'] ?? '-') }}</td>
                    <td>{{ $item['penanggung_jawab'] ?? ($item['pic'] ?? '-') }}</td>
                    <td>{{ $item['target_waktu'] ?? ($item['waktu'] ?? '-') }}</td>
                    <td>{{ $item['indikator'] ?? ($item['output'] ?? '-') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center;">{{ $laporan->tindak_lanjut ?? 'Tidak ada rencana tindak lanjut.' }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- G. KESIMPULAN DAN REKOMENDASI -->
    <div class="section-title">G. Kesimpulan dan Rekomendasi</div>
    <table class="box-narasi">
        <tr>
            <th>Kesimpulan</th>
            <td>{{ $laporan->kesimpulan ?: '-' }}</td>
        </tr>
        <tr>
            <th>Rekomendasi</th>
            <td>{{ $laporan->rekomendasi ?: '-' }}</td>
        </tr>
    </table>

    <!-- H. PENGESAHAN (TANDA TANGAN DUA KOLOM: KIRI & KANAN) -->
    <div class="section-title">H. Pengesahan</div>
    <table class="table-pengesahan">
        <tr>
            <!-- Kolom Kiri: Pimpinan / Kepala Unit -->
            <td>
                <p>&nbsp;</p>
                <p>Mengetahui,</p>
                <p><strong>Pimpinan / Kepala {{ $namaUnit }}</strong></p>

                @if(!empty($laporan->ttd_pimpinan_path))
                    <div class="ttd-img-wrapper">
                        <img src="{{ !empty($ttdPimpinanBase64) ? $ttdPimpinanBase64 : public_path('storage/' . $laporan->ttd_pimpinan_path) }}" alt="TTD Pimpinan" class="ttd-img">
                    </div>
                @else
                    <div class="ttd-space"></div>
                @endif

                <p class="ttd-nama">{{ $penanggungJawab }}</p>
            </td>

            <!-- Kolom Kanan: Penyusun -->
            <td>
                <p>OKU Timur, {{ $tanggalCetak ?? ($laporan->created_at ? $laporan->created_at->format('d F Y') : date('d F Y')) }}</p>
                <p>Disusun oleh,</p>
                <p><strong>Penanggung Jawab / Penyusun</strong></p>

                @if(!empty($laporan->ttd_penyusun_path))
                    <div class="ttd-img-wrapper">
                        <img src="{{ !empty($ttdPenyusunBase64) ? $ttdPenyusunBase64 : public_path('storage/' . $laporan->ttd_penyusun_path) }}" alt="TTD Penyusun" class="ttd-img">
                    </div>
                @else
                    <div class="ttd-space"></div>
                @endif

                <p class="ttd-nama">{{ $namaPenyusun !== '-' ? $namaPenyusun : $penanggungJawab }}</p>
            </td>
        </tr>
    </table>

    <div class="catatan-kaki">
        Dokumen ini dihasilkan secara otomatis melalui Sistem Informasi Pelaporan Terpadu (SIPASTI) Universitas Nurul Huda pada {{ date('d/m/Y H:i') }} WIB.
    </div>

</body>
</html>
