<?php
// 1. Panggil koneksi database
require 'koneksi.php';

// Set header agar merespons dengan format JSON (Format standar AI/API)
header('Content-Type: application/json');

// 2. CEK KEAMANAN (API KEY)
// Ini agar tidak ada hacker yang bisa mengirim data palsu ke web Anda
$api_key_server = "***";
$api_key_client = isset($_POST['api_key']) ? $_POST['api_key'] : '';

if ($api_key_client !== $api_key_server) {
    echo json_encode(["status" => "error", "pesan" => "Akses Ditolak! API Key salah atau tidak ada."]);
    exit;
}

// 3. AMBIL DATA TEKS DARI PYTHON
$plat_nomor = isset($_POST['plat_nomor']) ? $_POST['plat_nomor'] : '';
$waktu_melintas = isset($_POST['waktu_melintas']) ? $_POST['waktu_melintas'] : date('Y-m-d H:i:s');

// 👇 INI YANG KITA PERBAIKI: Menangkap label 'device' dari Python 👇
$lokasi_kamera = isset($_POST['device']) ? $_POST['device'] : 'Video CCTV Bypass';

if (empty($plat_nomor)) {
    echo json_encode(["status" => "error", "pesan" => "Plat nomor kosong!"]);
    exit;
}

// Bersihkan spasi berlebih pada plat nomor
$plat_bersih = mysqli_real_escape_string($koneksi, trim($plat_nomor));

// 4. PROSES UPLOAD FOTO (KENDARAAN & PLAT)
$folder_uploads = "uploads/";
$nama_file_kendaraan = "";
$nama_file_plat = "";

// Fungsi untuk menyimpan foto ke folder uploads
function uploadFoto($file_input_name, $prefix) {
    global $folder_uploads;
    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] == 0) {
        $ext = pathinfo($_FILES[$file_input_name]['name'], PATHINFO_EXTENSION);
        // Bikin nama file unik: kendaraan_16123456_89.jpg
        $nama_baru = $prefix . "_" . time() . "_" . rand(10,99) . "." . $ext;
        move_uploaded_file($_FILES[$file_input_name]['tmp_name'], $folder_uploads . $nama_baru);
        return $nama_baru;
    }
    return "";
}

$nama_file_kendaraan = uploadFoto('foto_kendaraan', 'kendaraan');
$nama_file_plat = uploadFoto('foto_plat', 'plat');

// 5. PENGECEKAN CERDAS KE BUKU INDUK (EXCEL YANG TADI DIMASUKKAN)
$query_cek = mysqli_query($koneksi, "SELECT * FROM master_kendaraan WHERE plat_nomor = '$plat_bersih'");

if (mysqli_num_rows($query_cek) > 0) {
    // SKENARIO A: KENDARAAN DITEMUKAN DI DATABASE
    $data_master = mysqli_fetch_assoc($query_cek);
    $nama_pemilik = $data_master['nama_pemilik'];
    $nomor_wa = $data_master['nomor_wa'];
    $masa_berlaku_kir = $data_master['masa_berlaku_kir'];

    // Hitung status KIR (Bandingkan masa berlaku dengan tanggal hari ini)
    $tanggal_hari_ini = date('Y-m-d');
    if ($tanggal_hari_ini > $masa_berlaku_kir) {
        $status_kir = "TIDAK BERLAKU";
    } else {
        $status_kir = "BERLAKU";
    }

} else {
    // SKENARIO B: KENDARAAN ASING / TIDAK TERDAFTAR (Mencegah Error)
    $nama_pemilik = "KENDARAAN ASING (TIDAK TERDAFTAR)";
    $nomor_wa = "-";
    // Tanggal default yang valid agar database tidak error
    $masa_berlaku_kir = "1999-01-01"; 
    $status_kir = "DATA TIDAK DITEMUKAN";
}

// 6. SIMPAN SEMUANYA KE TABEL PELANGGARAN
// Menyimpan '$lokasi_kamera' yang sudah ditangkap dengan benar dari Python
$query_insert = "INSERT INTO data_pelanggaran 
                (waktu_melintas, plat_nomor, nama_pemilik, nomor_wa, masa_berlaku_kir, status_kir, file_foto_kendaraan, file_foto_plat, status_tindakan, lokasi_kamera) 
                VALUES 
                ('$waktu_melintas', '$plat_bersih', '$nama_pemilik', '$nomor_wa', '$masa_berlaku_kir', '$status_kir', '$nama_file_kendaraan', '$nama_file_plat', 'BELUM DIPROSES', '$lokasi_kamera')";

if (mysqli_query($koneksi, $query_insert)) {
    echo json_encode(["status" => "sukses", "pesan" => "Data Pelat $plat_bersih berhasil diamankan di Server!"]);
} else {
    echo json_encode(["status" => "error", "pesan" => "Gagal menyimpan data: " . mysqli_error($koneksi)]);
}
?>
