<?php
require 'koneksi.php';

// ==========================================
// KODE SAKTI: OTOMATIS MENAMBAH KOLOM DEVICE
// (Agar Anda tidak perlu repot buka phpMyAdmin)
// ==========================================
$cek_kolom = mysqli_query($koneksi, "SHOW COLUMNS FROM data_pelanggaran LIKE 'lokasi_kamera'");
if(mysqli_num_rows($cek_kolom) == 0) {
    // Jika belum ada, buatkan kolomnya dan jadikan semua data lama milik "Video CCTV Bypass"
    mysqli_query($koneksi, "ALTER TABLE data_pelanggaran ADD lokasi_kamera VARCHAR(100) DEFAULT 'Video CCTV Bypass'");
}

// ==========================================
// PENYARINGAN DEVICE & INTERVAL
// ==========================================
// 1. Filter Berdasarkan "Dunia" (Kamera yang dipilih)
$device = isset($_GET['device']) ? mysqli_real_escape_string($koneksi, $_GET['device']) : 'Video CCTV Bypass';
$where_sql = "lokasi_kamera = '$device'"; // Kunci pemisah 2 Dunia

// 2. Filter Berdasarkan Waktu
$interval = isset($_GET['interval']) ? $_GET['interval'] : '';

if ($interval == 'Jam') {
    if (!empty($_GET['tanggal'])) {
        $tgl = mysqli_real_escape_string($koneksi, $_GET['tanggal']);
        $where_sql .= " AND DATE(waktu_melintas) = '$tgl'";
    }
    if (!empty($_GET['jam_dari'])) {
        $jam_dari = mysqli_real_escape_string($koneksi, $_GET['jam_dari']);
        $where_sql .= " AND TIME(waktu_melintas) >= '$jam_dari:00'";
    }
    if (!empty($_GET['jam_sampai'])) {
        $jam_sampai = mysqli_real_escape_string($koneksi, $_GET['jam_sampai']);
        $where_sql .= " AND TIME(waktu_melintas) <= '$jam_sampai:59'";
    }
} elseif ($interval == 'Tanggal') {
    if (!empty($_GET['tgl_dari'])) {
        $tgl_dari = mysqli_real_escape_string($koneksi, $_GET['tgl_dari']);
        $where_sql .= " AND DATE(waktu_melintas) >= '$tgl_dari'";
    }
    if (!empty($_GET['tgl_sampai'])) {
        $tgl_sampai = mysqli_real_escape_string($koneksi, $_GET['tgl_sampai']);
        $where_sql .= " AND DATE(waktu_melintas) <= '$tgl_sampai'";
    }
} else {
    // DEFAULT JIKA INTERVAL KOSONG (Belum dipilih sama sekali) -> Tampilkan Hari Ini
    $tgl_hari_ini = date('Y-m-d');
    $where_sql .= " AND DATE(waktu_melintas) = '$tgl_hari_ini'";
}

// EKSEKUSI DATABASE
$query = mysqli_query($koneksi, "SELECT * FROM data_pelanggaran WHERE $where_sql ORDER BY id DESC");

if (mysqli_num_rows($query) == 0) {
    echo '<div class="col-12 text-center text-muted mt-5 w-100"><h6 style="font-size: 14px;">Tidak ada kendaraan pada device / rentang filter ini.</h6></div>';
    exit;
}

while ($row = mysqli_fetch_assoc($query)) {
    $badge_color = ($row['status_kir'] == 'BERLAKU') ? 'bg-success' : (($row['status_kir'] == 'TIDAK BERLAKU') ? 'bg-danger' : 'bg-warning text-dark');
    ?>
    
    <div class="kolom-5 card-antrean">
        <div class="card h-100 shadow-sm card-hover" 
             style="border: 1px solid #e2e8f0; border-radius: 4px; overflow: hidden; background-color: #ffffff; cursor: pointer; transition: 0.2s;"
             onclick="window.location.href='detail.php?id=<?= $row['id'] ?>'">
            
            <div style="position: relative; background-color: #2d3748; padding: 0;">
                <img src="uploads/<?= $row['file_foto_kendaraan'] ?>" 
                     style="height: 140px; width: 100%; object-fit: cover;" 
                     alt="Kendaraan">
                <div style="position: absolute; top: 10%; left: 15%; right: 15%; bottom: 15%; border: 1px solid red; pointer-events: none;"></div>
            </div>
            
            <div class="card-body d-flex flex-column" style="padding: 10px 12px;">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span style="font-size: 10px; color: #718096;"><?= date('d-m-Y H:i:s', strtotime($row['waktu_melintas'])) ?></span>
                    <span class="badge <?= $badge_color ?>" style="font-size: 9px; font-weight: bold; border-radius: 3px;"><?= $row['status_kir'] ?></span>
                </div>
                
                <div class="text-decoration-none text-dark">
                    <h6 class="mt-0 mb-2 fw-bold" style="font-size: 13px; color: #1a202c;"><?= $row['plat_nomor'] ?></h6>
                </div>
                
                <div class="d-flex justify-content-between align-items-end mt-auto pt-1">
                    <div class="form-check m-0" onclick="event.stopPropagation();">
                        <input class="form-check-input centang-pilih" type="checkbox" value="<?= $row['id'] ?>" id="pilih<?= $row['id'] ?>" style="cursor: pointer; width: 15px; height: 15px; border-color: #a0aec0;">
                        <label class="form-check-label" for="pilih<?= $row['id'] ?>" style="cursor: pointer; font-size: 11px; color: #4a5568; margin-left: 4px; margin-top: 1px;">Pilih</label>
                    </div>
                    
                    <img src="uploads/<?= $row['file_foto_plat'] ?>" 
                         style="height: 35px; max-width: 110px; width: auto; object-fit: contain; border-radius: 3px; border: 1px solid #cbd5e0; background-color: #f7fafc;" 
                         alt="Plat">
                </div>
            </div>
        </div>
    </div>
    
    <style>
        .card-hover:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
            border-color: #3182ce !important;
        }
    </style>
    <?php
}
?>