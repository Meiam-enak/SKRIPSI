<?php
// Pengaturan Database (Jembatan Penghubung)
$host = "localhost"; 

$user = "ujiberka_verifikator"; // Contoh: u12345_admin
$pass = "verifikatorUPPKB123"; // Masukkan password yang Anda buat di Database Wizard
$db   = "ujiberka_kir";     // Contoh: u12345_uppkb

// Membuat koneksi ke database
$koneksi = mysqli_connect($host, $user, $pass, $db);

// Cek apakah jembatan berhasil tersambung
if (!$koneksi) {
    die("Gagal tersambung ke database: " . mysqli_connect_error());
}
?>