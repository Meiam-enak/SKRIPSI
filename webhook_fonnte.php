<?php
require 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

// 1. Menerima laporan dari Fonnte
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// 2. Jika pesan berstatus 'delivered' (sampai di HP)
if (isset($data['status']) && $data['status'] === 'delivered') {
    
    // T1: Waktu pesan sampai di HP dengan presisi milidetik
    $time_end = microtime(true); 
    $waktu_diterima = date('Y-m-d H:i:s') . sprintf('.%03d', ($time_end - floor($time_end)) * 1000);
    
    $nomor_wa = $data['target']; // Nomor WA tujuan
    
    // 3. Ambil waktu klik (T0) dari database untuk nomor ini yang belum diupdate
    $query = mysqli_query($koneksi, "SELECT id, waktu_klik FROM log_latensi_wa WHERE nomor_wa = '$nomor_wa' AND latensi_total IS NULL ORDER BY id DESC LIMIT 1");
    
    if (mysqli_num_rows($query) > 0) {
        $row = mysqli_fetch_assoc($query);
        $id_log = $row['id'];
        
        // Ekstrak timestamp T0 menjadi milidetik murni
        $t0_ms = strtotime($row['waktu_klik']) + (float)substr($row['waktu_klik'], 20);
        
        // 4. Hitung Latensi End-to-End Otomatis!
        $latensi_detik = round($time_end - $t0_ms, 2);
        
        // 5. Update Database
        mysqli_query($koneksi, "UPDATE log_latensi_wa SET waktu_diterima = '$waktu_diterima', latensi_total = '$latensi_detik' WHERE id = '$id_log'");
    }
}
?>