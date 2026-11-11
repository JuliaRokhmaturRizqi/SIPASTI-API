<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Kinerja - {{ $laporan->unitKerja->nama_unit ?? 'Unit Kerja' }}</title>
    <style>
        @page {
            margin: 2cm 2cm 2cm 2.5cm;
        }
        body {
            font-family: "Times New Roman", Times, Arial, serif;
            font-size: 11.5pt;
            line-height: 1.45;
            color: #111111;
            margin: 0;
            padding: 0;
        }
        /* Kop Surat Resmi Kampus */
        .kop-surat {
            width: 100%;
            border-bottom: 3px solid #000000;
            padding-bottom: 8px;
            margin-bottom: 3px;
            text-align: center;
        }
        .kop-garis-tipis {
            border-bottom: 1px solid #000000;
            margin-bottom: 20px;
        }
        .kop-yayasan {
            font-size: 11pt;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin: 0;
        }
        .kop-universitas {
            font-size: 16pt;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin: 2px 0;
            color: #0b4d26;
        }
        .kop-sistem {
            font-size: 10pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 2px 0;
        }
        .kop-alamat {
            font-size: 9pt;
            font-style: italic;
            color: #333333;
            margin: 3px 0 0 0;
        }

        /* Judul Dokumen */
        .judul-dokumen {
            text-align: center;
            margin-bottom: 18px;
        }
        .judul-dokumen h2 {
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
            text-decoration: underline;
            margin: 0 0 4px 0;
        }
        .judul-dokumen p {
            font-size: 10pt;
            margin: 0;
            color: #333333;
        }

        /* Tabel Identitas Unit */
        .tabel-identitas {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .tabel-identitas td {
            padding: 4px 6px;
            vertical-align: top;
            font-size: 11pt;
        }
        .tabel-identitas td.label {
            width: 28%;
            font-weight: bold;
        }
        .tabel-identitas td.colon {
            width: 3%;
            text-align: center;
        }
        .tabel-identitas td.value {
            width: 69%;
        }

        /* Tabel Isi Laporan */
        .tabel-isi {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        .tabel-isi th,
        .tabel-isi td {
            border: 1px solid #222222;
            padding: 8px 10px;
            vertical-align: top;
            text-align: left;
        }
        .tabel-isi th {
            background-color: #f0f4f1;
            font-weight: bold;
            width: 28%;
            font-size: 10.5pt;
        }
        .tabel-isi td {
            width: 72%;
            font-size: 11pt;
            white-space: pre-line;
            word-wrap: break-word;
        }
        .link-text {
            color: #0b5394;
            text-decoration: underline;
            word-break: break-all;
        }
        .badge-status {
            display: inline-block;
            padding: 2px 8px;
            font-size: 9.5pt;
            font-weight: bold;
            border: 1px solid #333333;
            border-radius: 3px;
            text-transform: uppercase;
        }

        /* Area Tanda Tangan */
        .ttd-wrapper {
            width: 100%;
            margin-top: 28px;
        }
        .ttd-box {
            width: 45%;
            margin-left: 55%;
            text-align: left;
        }
        .ttd-box p {
            margin: 2px 0;
        }
        .ttd-space {
            height: 65px;
        }
        .ttd-nama {
            font-weight: bold;
            text-decoration: underline;
        }

        /* Footer */
        .catatan-kaki {
            margin-top: 30px;
            border-top: 1px dashed #888888;
            padding-top: 6px;
            font-size: 8.5pt;
            color: #555555;
            font-style: italic;
        }
    </style>
</head>
<body>

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
        <p>Nomor Dokumen: LK/{{ strtoupper($laporan->unitKerja->kategori_unit ?? 'UNIT') }}/{{ $laporan->id }}/{{ date('Y') }}</p>
    </div>

    <!-- IDENTITAS PELAPORAN -->
    <table class="tabel-identitas">
        <tr>
            <td class="label">Nama Unit Kerja</td>
            <td class="colon">:</td>
            <td class="value"><strong>{{ $laporan->unitKerja->nama_unit ?? '-' }}</strong></td>
        </tr>
        <tr>
            <td class="label">Kategori Unit</td>
            <td class="colon">:</td>
            <td class="value">{{ strtoupper($laporan->unitKerja->kategori_unit ?? '-') }}</td>
        </tr>
        <tr>
            <td class="label">Periode Pelaporan</td>
            <td class="colon">:</td>
            <td class="value">{{ $laporan->periodeAkademik->nama_periode ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Nama Kegiatan</td>
            <td class="colon">:</td>
            <td class="value"><strong>{{ $laporan->nama_kegiatan }}</strong></td>
        </tr>
        <tr>
            <td class="label">Status Validasi</td>
            <td class="colon">:</td>
            <td class="value"><span class="badge-status">{{ $laporan->status }}</span></td>
        </tr>
    </table>

    <!-- RINCIAN PELAKSANAAN KEGIATAN -->
    <table class="tabel-isi">
        <tr>
            <th>1. Uraian Kegiatan</th>
            <td>{{ $laporan->uraian }}</td>
        </tr>
        <tr>
            <th>2. Kendala / Hambatan</th>
            <td>{{ $laporan->kendala ?: 'Tidak ada kendala yang dilaporkan.' }}</td>
        </tr>
        <tr>
            <th>3. Dukungan yang Dibutuhkan</th>
            <td>{{ $laporan->dukungan ?: '-' }}</td>
        </tr>
        <tr>
            <th>4. Rencana Tindak Lanjut</th>
            <td>{{ $laporan->tindak_lanjut ?: '-' }}</td>
        </tr>
        <tr>
            <th>5. Tautan Bukti Dukung</th>
            <td>
                @if(!empty($laporan->link_bukti))
                    <span class="link-text">{{ $laporan->link_bukti }}</span>
                @else
                    -
                @endif
            </td>
        </tr>
        @if(!empty($laporan->catatan_revisi))
        <tr>
            <th>6. Catatan Evaluasi / Revisi Admin</th>
            <td>{{ $laporan->catatan_revisi }}</td>
        </tr>
        @endif
    </table>

    <!-- AREA TANDA TANGAN -->
    <div class="ttd-wrapper">
        <div class="ttd-box">
            <p>OKU Timur, {{ $tanggalCetak ?? date('d F Y') }}</p>
            <p>Penanggung Jawab / Penandatangan,</p>
            <p><strong>{{ $laporan->unitKerja->nama_unit ?? 'Unit Kerja' }}</strong></p>
            <div class="ttd-space"></div>
            <p class="ttd-nama">{{ $laporan->nama_penandatangan }}</p>
        </div>
    </div>

    <div class="catatan-kaki">
        Dokumen ini dihasilkan secara otomatis melalui Sistem Informasi Pelaporan Terpadu (SIPASTI) Universitas Nurul Huda pada {{ date('d/m/Y H:i') }} WIB.
    </div>

</body>
</html>
