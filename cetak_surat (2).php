<?php
require 'koneksi.php';

$id = isset($_GET['id']) ? $_GET['id'] : 0;
$query = mysqli_query($koneksi, "
    SELECT dp.*, mk.nomor_uji, mk.jenis_kendaraan, mk.merek_tipe 
    FROM data_pelanggaran dp 
    LEFT JOIN master_kendaraan mk ON dp.plat_nomor = mk.plat_nomor 
    WHERE dp.id = '$id'
");

if (mysqli_num_rows($query) == 0) {
    die("Data tidak ditemukan!");
}
$data = mysqli_fetch_assoc($query);

// Memecah Tanggal dan Jam dari waktu_melintas
$waktu_parts = explode(' ', $data['waktu_melintas']);
$tanggal_melintas_raw = isset($waktu_parts[0]) ? $waktu_parts[0] : date('Y-m-d');
$tanggal = isset($waktu_parts[0]) ? date('d-m-Y', strtotime($waktu_parts[0])) : '-';
$jam = isset($waktu_parts[1]) ? date('H:i:s', strtotime($waktu_parts[1])) : '-';

// Menentukan Masa Berlaku untuk Tampilan Text
$teks_masa_berlaku = '-';
if (!empty($data['masa_berlaku_kir']) && $data['masa_berlaku_kir'] != '0000-00-00') {
    $teks_masa_berlaku = date('d-m-Y', strtotime($data['masa_berlaku_kir']));
}

// LOGIKA PENGECEKAN KEDALUWARSA KIR
$is_expired = false;
if (!empty($data['masa_berlaku_kir']) && strtotime($data['masa_berlaku_kir'])) {
    $tgl_berlaku = strtotime($data['masa_berlaku_kir']);
    $tgl_melintas = strtotime($tanggal_melintas_raw);

    // Jika tanggal masa berlaku LEBIH KECIL dari tanggal melintas, berarti MATI/KEDALUWARSA
    if ($tgl_berlaku < $tgl_melintas) {
        $is_expired = true;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bukti Pelanggaran - <?= $data['plat_nomor'] ?></title>
    <style>
        /* Reset dan Pengaturan Kertas */
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            background-color: #525659; /* Warna background browser PDF */
            margin: 0;
            padding: 20px;
        }
        .kertas-print {
            background-color: white;
            width: 210mm;
            min-height: 297mm;
            margin: auto;
            padding: 10mm 15mm;
            box-sizing: border-box;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
        }
        
        /* Kop Surat */
        .kop-surat {
            display: flex;
            align-items: center;
            border-bottom: 2px solid black;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .kop-logo {
            width: 70px;
            margin-right: 15px;
        }
        .kop-teks {
            flex-grow: 1;
        }
        .kop-teks h2, .kop-teks h3, .kop-teks p { margin: 0; line-height: 1.2; }
        .kop-teks h2 { font-size: 12pt; font-weight: bold; }
        .kop-teks h3 { font-size: 11pt; font-weight: normal; }
        .kop-teks p { font-size: 9pt; }

        /* Judul Surat */
        .judul-surat {
            text-align: center;
            font-weight: bold;
            font-size: 12pt;
            text-decoration: underline;
            margin-bottom: 15px;
        }

        /* Tabel Data 2 Kolom */
        .grid-data {
            display: grid;
            grid-template-columns: 50% 50%;
            margin-bottom: 20px;
            font-size: 10pt;
        }
        .grid-data table { width: 100%; border-collapse: collapse; }
        .grid-data td { padding: 2px 0; vertical-align: top; }
        .grid-data td.label { width: 120px; }
        .grid-data td.titikdua { width: 10px; }

        /* Area Foto */
        .foto-area { margin-bottom: 20px; }
        .foto-title { font-weight: bold; font-size: 10pt; margin-bottom: 5px; }
        .foto-plat { height: 40px; border: 1px solid #ccc; display: block; margin-bottom: 15px; }
        
        .foto-kendaraan-container {
            position: relative;
            width: 100%;
            border: 1px solid #ccc;
        }
        .foto-kendaraan {
            width: 100%;
            max-height: 350px;
            object-fit: cover;
            display: block;
        }
        .timestamp-bar {
            display: flex;
            justify-content: space-between;
            font-size: 9pt;
            padding: 2px 5px;
            color: black;
            margin-top: 2px;
        }

        /* Bagian Pelanggaran & TTD */
        .pelanggaran-area {
            text-align: center;
            font-size: 10pt;
            margin-top: 10px;
            min-height: 80px; /* Supaya layout tidak naik-turun saat teks hilang */
        }
        .pelanggaran-area h4 { margin: 5px 0; font-size: 10pt; font-weight: bold; }
        .pelanggaran-area p { margin: 0 0 15px 0; }
        
        .ttd-area {
            text-align: center;
            font-weight: bold;
            font-size: 10pt;
            margin-top: 20px;
        }
        .stamp-placeholder {
            width: 200px; /* Lebar area stempel + ttd disesuaikan */
            height: auto;
            margin: 5px auto;
        }
        .stamp-placeholder img {
            width: 100%;
            height: auto;
            display: block;
        }

        /* Mode Cetak (Print) */
        @media print {
            body { background-color: white; padding: 0; }
            .kertas-print { box-shadow: none; width: 100%; margin: 0; padding: 0; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="kertas-print">
        <div class="kop-surat">
            <img src="logo_kemenhub.png" class="kop-logo" alt="Logo Kemenhub">
            <div class="kop-teks">
                <h2>KEMENTERIAN PERHUBUNGAN</h2>
                <h2>DIREKTORAT JENDERAL PERHUBUNGAN DARAT</h2>
                <h2>BPTD KELAS I JAWA BARAT</h2>
                <h2>SATUAN PELAYANAN UPPKB BALONGGANDU</h2>
                <p>Jl. Raya Jatisari No.4, Balonggandu, Kec. Jatisari, Kabupaten Karawang, Jawa Barat 41374</p>
            </div>
        </div>

        <div class="judul-surat">BUKTI PELANGGARAN</div>

        <div class="grid-data">
            <div>
                <table>
                    <tr><td class="label">Tanggal Verifikasi</td><td class="titikdua">:</td><td><?= $tanggal ?></td></tr>
                    <tr><td class="label">No Kendaraan</td><td class="titikdua">:</td><td><b><?= $data['plat_nomor'] ?></b></td></tr>
                    <tr><td class="label">Pemilik</td><td class="titikdua">:</td><td><?= $data['nama_pemilik'] ?? '-' ?></td></tr>
                    <tr><td class="label">Alamat</td><td class="titikdua">:</td><td>-</td></tr>
                    <tr><td class="label">Masa Berlaku Uji</td><td class="titikdua">:</td><td><?= $teks_masa_berlaku ?></td></tr>
                </table>
            </div>
            <div>
                <table>
                    <tr><td class="label">Jam Verifikasi</td><td class="titikdua">:</td><td><?= $jam ?></td></tr>
                    <tr><td class="label">No Uji</td><td class="titikdua">:</td><td><?= $data['nomor_uji'] ?? '-' ?></td></tr>
                    <tr><td class="label">JBI</td><td class="titikdua">:</td><td>0 Kg</td></tr>
                    <tr><td class="label">Berat Timbang</td><td class="titikdua">:</td><td>0 Kg</td></tr>
                    <tr><td class="label">Berat Lebih</td><td class="titikdua">:</td><td>0 %</td></tr>
                </table>
            </div>
        </div>

        <div class="foto-area">
            <div class="foto-title">PHOTO PLAT KENDARAAN :</div>
            <img src="uploads/<?= $data['file_foto_plat'] ?>" class="foto-plat" alt="Plat Nomor">
            
            <div class="foto-kendaraan-container">
                <img src="uploads/<?= $data['file_foto_kendaraan'] ?>" class="foto-kendaraan" alt="Foto Kendaraan">
                <div class="timestamp-bar">
                    <span><?= $tanggal ?></span>
                    <span><?= $jam ?></span>
                </div>
            </div>
        </div>

        <div class="pelanggaran-area">
            <h4>PELANGGARAN :</h4>
            
            <?php if ($is_expired): ?>
                <h4>DOKUMEN</h4>
                <h4>PASAL :</h4>
                <p>Pasal 288 ayat (3) Jo Pasal 106 ayat (5) huruf c</p>
            <?php else: ?>
                <p>-</p>
            <?php endif; ?>
        </div>

        <div class="ttd-area">
            <div>WASATPEL</div>
            <div class="stamp-placeholder">
                <img src="stempel_replika.png" alt="Stempel dan Tanda Tangan Wasatpel">
            </div>
        </div>

    </div>

</body>
</html>