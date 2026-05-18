<?php
require 'koneksi.php';

$action = isset($_POST['action']) ? $_POST['action'] : '';
$ids = json_decode(isset($_POST['ids']) ? $_POST['ids'] : '[]');

if (empty($ids)) {
    die("Tidak ada data yang diproses.");
}

// Mengamankan ID agar menjadi angka (mencegah SQL Injection)
$ids_aman = implode(",", array_map('intval', $ids));

if ($action == 'hapus') {
    // Jalankan perintah hapus
    mysqli_query($koneksi, "DELETE FROM data_pelanggaran WHERE id IN ($ids_aman)");
    echo "✅ Berhasil menghapus " . count($ids) . " kendaraan dari antrean!";

} elseif ($action == 'verifikasi') {
    // Simulasi Verifikasi: Mengubah status tindakan
    mysqli_query($koneksi, "UPDATE data_pelanggaran SET status_tindakan='SUDAH DIPROSES' WHERE id IN ($ids_aman)");
    echo "✅ Berhasil memverifikasi " . count($ids) . " kendaraan terpilih!";
}
?>