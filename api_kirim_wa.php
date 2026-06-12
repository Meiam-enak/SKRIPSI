<?php
require 'koneksi.php';
date_default_timezone_set('Asia/Jakarta'); // WAJIB agar waktu sama dengan HP

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. MENCATAT WAKTU MULAI (T0) - Resolusi Milidetik
    $time_start = microtime(true);
    $timestamp_t0 = date('Y-m-d H:i:s') . sprintf('.%03d', ($time_start - floor($time_start)) * 1000);
// --- TAMBAHKAN INI: Nilai Absolut untuk Kalkulasi Latensi ---
    $epoch_t0 = round($time_start * 1000);

    $nomor_wa = $_POST['nomor_wa'];
    $pesan = $_POST['pesan_teks'];
    $plat_nomor = isset($_POST['plat_nomor']) ? $_POST['plat_nomor'] : 'TIDAK_DIKETAHUI'; 
    
    // TOKEN FONNTE AMAN DI SINI
    $tokenFonnte = "***"; 

    // 2. EKSEKUSI API FONNTE
    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://api.fonnte.com/send',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      
      // Heuristik Timeout (Memberi toleransi pada jaringan shared hosting)
      CURLOPT_CONNECTTIMEOUT => 10, 
      CURLOPT_TIMEOUT => 15,        
      
      CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_SSL_VERIFYHOST => 0,

      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => array('target' => $nomor_wa, 'message' => $pesan),
      CURLOPT_HTTPHEADER => array("Authorization: $tokenFonnte"),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    // 🛡️ REFACTOR: Pesan Error disesuaikan dengan konfigurasi 10 detik
    if ($err) {
        $pesan_gagal = (strpos($err, 'timed out') !== false) 
            ? "Koneksi ke Gateway WA terputus (Timeout jaringan > 10 detik)." 
            : "Gangguan infrastruktur jaringan: " . $err;
            
        echo json_encode(["status" => "error", "message" => $pesan_gagal]);
        exit;
    }

    $res_data = json_decode($response, true);

    // 3. MENCATAT WAKTU API SELESAI & SIMPAN DATABASE
    $time_end = microtime(true);
    $latensi_internal_ms = round(($time_end - $time_start) * 1000, 2);

    if (isset($res_data['status']) && $res_data['status'] == true) {
        
        // 🛡️ REFACTOR: Ubah PENDING menjadi SENT_TO_GATEWAY karena T1 dihitung terpisah di Edge (MacroDroid)
        $query_log = "INSERT INTO log_latensi_wa (plat_nomor, nomor_wa, waktu_klik, status_pesan) 
                      VALUES ('$plat_nomor', '$nomor_wa', '$timestamp_t0', 'SENT_TO_GATEWAY')";
        
        $simpan_db = mysqli_query($koneksi, $query_log);

        if ($simpan_db) {
            echo json_encode([
                "status" => "success", 
                "waktu_klik" => $timestamp_t0,
                "epoch_t0" => $epoch_t0, // Kirimkan angka murni ke UI/Python
                "latensi_internal" => $latensi_internal_ms
            ]);
        } else {
            echo json_encode([
                "status" => "error", 
                "message" => "WA Terkirim, tapi gagal simpan DB: " . mysqli_error($koneksi)
            ]);
        }
        
    } else {
        echo json_encode(["status" => "error", "message" => $res_data['reason'] ?? 'Gagal koneksi Fonnte']);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid Request"]);
}
?>
