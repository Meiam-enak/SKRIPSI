<?php
require 'koneksi.php';

$id = isset($_GET['id']) ? $_GET['id'] : 0;

// ==========================================
// FITUR KOREKSI PLAT JIKA AI SALAH BACA
// ==========================================
if (isset($_POST['koreksi_plat'])) {
    $plat_baru = strtoupper(mysqli_real_escape_string($koneksi, trim($_POST['plat_baru'])));
    
    // Cari plat yang sudah dikoreksi ke Buku Induk (Excel)
    $cek_master = mysqli_query($koneksi, "SELECT * FROM master_kendaraan WHERE plat_nomor = '$plat_baru'");
    
    if (mysqli_num_rows($cek_master) > 0) {
        $master = mysqli_fetch_assoc($cek_master);
        $nama = $master['nama_pemilik'];
        $wa = $master['nomor_wa'];
        $masa = $master['masa_berlaku_kir'];
        
        // Hitung ulang status KIR-nya
        $tanggal_hari_ini = date('Y-m-d');
        if ($tanggal_hari_ini > $masa) {
            $status = "TIDAK BERLAKU";
        } else {
            $status = "BERLAKU";
        }
    } else {
        // Jika plat yang diketik petugas juga tidak ada di database
        $nama = "KENDARAAN ASING (TIDAK TERDAFTAR)";
        $wa = "-";
        $masa = "0000-00-00";
        $status = "DATA TIDAK DITEMUKAN";
    }
    
    // Update data di tabel pelanggaran dengan data yang benar
    mysqli_query($koneksi, "UPDATE data_pelanggaran SET 
        plat_nomor='$plat_baru', nama_pemilik='$nama', nomor_wa='$wa', 
        masa_berlaku_kir='$masa', status_kir='$status' WHERE id='$id'");
        
    // Refresh halaman otomatis agar data baru muncul
    header("Location: detail.php?id=$id");
    exit;
}
// ==========================================

// Mengambil data untuk ditampilkan
$query = mysqli_query($koneksi, "
    SELECT dp.*, mk.nomor_uji, mk.jenis_kendaraan, mk.merek_tipe 
    FROM data_pelanggaran dp 
    LEFT JOIN master_kendaraan mk ON dp.plat_nomor = mk.plat_nomor 
    WHERE dp.id = '$id'
");

if (mysqli_num_rows($query) == 0) {
    die("Data tidak ditemukan di database antrean!");
}
$data = mysqli_fetch_assoc($query);

// Menyiapkan Nomor WA
$nomor_wa = $data['nomor_wa'];
if (substr($nomor_wa, 0, 1) == '0') {
    $nomor_wa_format = '62' . substr($nomor_wa, 1);
} else {
    $nomor_wa_format = $nomor_wa;
}

// LOGIKA PENGECEKAN PELANGGARAN
// Jika status bukan BERLAKU, berarti ada pelanggaran dokumen (mati KIR/tidak terdaftar)
$is_melanggar = ($data['status_kir'] !== 'BERLAKU');

// MEMBUAT LINK SURAT OTOMATIS (Sesuai IP/Domain web saat ini)
$host = $_SERVER['HTTP_HOST']; 
$path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$link_surat = "http://" . $host . $path . "/cetak_surat.php?id=" . $id;

// MENYIAPKAN PESAN TEKS WHATSAPP YANG LEBIH FORMAL DAN RAPI (DENGAN PENYESUAIAN SPASI)
$pesan_wa = "🚨 *PEMBERITAHUAN RESMI UPPKB* 🚨\n\n"
          . "Yth. Bapak/Ibu *" . $data['nama_pemilik'] . "*,\n"
          . "Kami menginformasikan bahwa kendaraan Anda terdeteksi melintas di Jl. Raya Jatisari dan tidak melakukan penimbangan di UPPKB Balonggandu.\n\n"
          . "Berikut rincian data kendaraan Anda:\n"
          . "*Plat Nomor* : " . $data['plat_nomor'] . "\n"
          . "*Jenis* : " . ($data['jenis_kendaraan'] ? $data['jenis_kendaraan'] : '-') . "\n"
          . "*Merek/Tipe* : " . ($data['merek_tipe'] ? $data['merek_tipe'] : '-') . "\n"
          . "*Status KIR* : *" . $data['status_kir'] . "*\n\n";

if ($is_melanggar) {
    // Jika KIR Mati / Ada Pelanggaran
    $pesan_wa .= "Berdasarkan hasil verifikasi sistem kami, terdapat indikasi *Pelanggaran Dokumen* terkait masa berlaku KIR kendaraan Anda.\n\n"
              . "*Surat Bukti Pelanggaran*\n"
              . "Silakan klik tautan berwarna biru di bawah ini untuk melihat dan mengunduh detail surat bukti pelanggaran Anda:\n"
              . $link_surat . "\n\n"
              . "Mohon segera melakukan tindak lanjut perpanjangan KIR. Apabila Anda sudah melakukan perpanjangan, mohon abaikan pesan ini.\n\n";
} else {
    // Jika KIR Hidup / Tidak Melanggar
    $pesan_wa .= "Terima kasih telah mematuhi aturan dokumen kelengkapan jalan (KIR Anda Valid). Tetap utamakan keselamatan dalam berkendara.\n\n";
}

$pesan_wa .= "Terima kasih atas perhatian dan kerjasamanya.\n"
          . "Salam,\n*Petugas UPPKB Balonggandu*";

// Encode pesan agar aman dikirim via JavaScript
$pesan_wa_js = json_encode($pesan_wa);

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Verifikasi - UPPKB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* CSS DARI INDEX.PHP UNTUK NAVBAR */
        .navbar-custom { background-color: #2b6cb0; padding: 4px 15px; }
        .nav-link { color: #d9e2ec !important; font-size: 13px; margin-right: 15px; padding: 12px 0; }
        .nav-link.active-menu { color: #ffffff !important; font-weight: bold; border-bottom: 3px solid #63b3ed; }
        
        /* CSS UNTUK KONTEN DETAIL */
        body { background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; }
        .table-verifikasi td, .table-verifikasi th { padding: 4px 8px; border: 1px solid #e2e8f0; font-size: 11px; }
        .table-verifikasi th { background-color: #f8fafc; font-weight: 500; color: #64748b; text-align: left; width: 20%;}
        .table-verifikasi td { color: #1e293b; width: 30%;}
        .table-verifikasi tr:nth-child(even) td { background-color: #f1f5f9; }
        .table-verifikasi tr:nth-child(odd) td { background-color: #ffffff; }
        .data-label { font-weight: 500; color: #64748b; font-size: 11px; }
        
        /* Toggle Switch Custom */
        .toggle-checkbox:checked { right: 0; border-color: #22c55e; }
        .toggle-checkbox:checked + .toggle-label { background-color: #22c55e; }
        .toggle-checkbox { right: 16px; transition: all 0.3s; }
        .toggle-label { transition: all 0.3s; }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-custom shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="#" style="text-decoration: none;">
                <img src="logo_kemenhub.png" style="height: 32px; margin-right: 6px;" alt="Kemenhub" 
                     onerror="this.src='https://upload.wikimedia.org/wikipedia/commons/thumb/8/8a/Logo_of_the_Ministry_of_Transportation_of_the_Republic_of_Indonesia.svg/512px-Logo_of_the_Ministry_of_Transportation_of_the_Republic_of_Indonesia.svg.png'">
                
                <img src="logo_instansi.png" style="height: 32px; margin-right: 12px;" alt="Instansi" 
                     onerror="this.src='https://via.placeholder.com/32x32.png?text=🛡️'">
                
                <span style="font-family: 'Arial Black', Impact, sans-serif; font-size: 22px; color: #82caff; font-weight: 900; letter-spacing: 1px;">JTO</span>
                
                <span style="font-family: 'Brush Script MT', 'Lucida Handwriting', cursive; font-size: 26px; color: #ffffff; font-style: italic; margin-left: 4px;">Verifikator</span>
            </a>

            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 mt-1 ms-3">
                    <li class="nav-item"><a class="nav-link" href="#">Beranda</a></li>
                    <li class="nav-item"><a class="nav-link active-menu" href="index.php">Verifikasi</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">WIM</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Pelanggaran</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Arsip Data</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Laporan</a></li>
                </ul>
                <div class="text-white d-flex align-items-center">
                <span style="font-size: 14px; margin-right: 10px;">👤 Admin</span>
                <a href="logout.php" class="btn btn-danger btn-sm py-0 px-2" style="font-size: 11px;">Keluar</a>
            </div>
        </div>
    </nav>

    <div class="max-w-[1300px] mx-auto mt-4 mb-8 bg-white shadow-sm border border-slate-200">
        <div class="bg-white p-3 flex justify-between items-center border-b border-slate-200 px-5">
            <h1 class="text-base font-semibold text-slate-800">Verifikasi Data</h1>
            <a href="index.php?kembali=1" class="text-slate-500 hover:text-red-500 font-bold text-xl px-2">&times;</a>
        </div>

        <div class="p-6 grid grid-cols-1 lg:grid-cols-[45%_55%] gap-8 bg-slate-50">
            <div>
                <div class="mb-4 bg-black relative rounded overflow-hidden shadow-inner flex items-center justify-center" style="height: 400px;">
                    <div class="absolute border-2 border-red-600 top-1/4 left-[15%] w-[40%] h-[40%] z-10"></div>
                    <img src="uploads/<?= $data['file_foto_kendaraan'] ?>" class="w-full h-full object-cover opacity-90" alt="Foto Kendaraan AI">
                </div>

                <div class="grid grid-cols-2 gap-4 mt-6">
                    <div>
                        <h4 class="font-semibold text-slate-400 mb-2 text-xs uppercase">PELANGGARAN</h4>
                        <ul class="text-xs text-slate-600 space-y-1 list-none">
                            <?php if ($is_melanggar): ?>
                                <li>- DOKUMEN</li>
                            <?php else: ?>
                                <li class="text-green-600 italic">Tidak ada pelanggaran</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div>
                        <h4 class="font-semibold text-slate-400 mb-2 text-xs uppercase">PASAL</h4>
                        <ul class="text-xs text-slate-600 space-y-1 list-none">
                            <?php if ($is_melanggar): ?>
                                <li>- Pasal 288 ayat (3) Jo Pasal 106 ayat (5) huruf c</li>
                            <?php else: ?>
                                <li>-</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="bg-white p-5 rounded-md shadow-sm border border-slate-200">
                <h3 class="font-medium text-slate-500 mb-2 text-xs">Foto Plat Kendaraan</h3>
                
                <div class="mb-4 w-48 h-16 bg-gray-900 border border-slate-400 rounded overflow-hidden">
                    <img src="uploads/<?= $data['file_foto_plat'] ?>" class="w-full h-full object-cover" alt="Plat Nomor AI">
                </div>

                <form method="POST" class="mb-4 relative">
                    <div class="flex gap-2 w-full">
                        <div class="relative w-full">
                            <span class="text-red-500 absolute -top-2 left-2 text-[10px] font-bold">* No Kendaraan</span>
                            <input type="text" name="plat_baru" value="<?= $data['plat_nomor'] ?>" class="w-full bg-white border border-slate-300 rounded px-3 py-1.5 text-sm text-slate-800 uppercase focus:outline-none focus:border-[#2B6CB0]">
                        </div>
                        <button type="submit" name="koreksi_plat" class="bg-[#2B6CB0] hover:bg-blue-800 text-white text-xs px-4 py-1.5 rounded flex items-center gap-2 whitespace-nowrap">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/></svg>
                            Cari Data Blue
                        </button>
                    </div>
                </form>

                <table class="w-full table-verifikasi mb-5">
                    <tbody>
                        <tr>
                            <th>No Kendaraan</th><td><?= $data['plat_nomor'] ?></td>
                            <th>No Uji</th><td><?= $data['nomor_uji'] ?? '-' ?></td>
                        </tr>
                        <tr>
                            <th>Pemilik</th><td><?= $data['nama_pemilik'] ?></td>
                            <th>Alamat</th><td>-</td>
                        </tr>
                        <tr>
                            <th>Masa Berlaku</th><td class="<?= $is_melanggar ? 'text-red-600 font-bold' : '' ?>"><?= ($data['masa_berlaku_kir'] != '0000-00-00') ? date('d-m-Y', strtotime($data['masa_berlaku_kir'])) : '-' ?></td>
                            <th>JBI</th><td>- Kg</td>
                        </tr>
                        <tr>
                            <th>Jenis Kendaraan</th><td><?= $data['jenis_kendaraan'] ?? '-' ?></td>
                            <th>Berat Timbang</th><td>- Kg</td>
                        </tr>
                        <tr>
                            <th>Sumbu</th><td>-</td>
                            <th>Berat Lebih</th><td>- Kg</td>
                        </tr>
                        <tr>
                            <th>Kepemilikan</th><td>PERSEORANGAN</td>
                            <th>Persentase</th><td>- %</td>
                        </tr>
                    </tbody>
                </table>

                <h4 class="font-medium text-slate-500 mb-2 text-xs">Dimensi</h4>
                <table class="w-full table-verifikasi text-center mb-6">
                    <thead>
                        <tr>
                            <th></th>
                            <th class="text-center font-normal">Uji</th>
                            <th class="text-center font-normal">Ukur</th>
                            <th class="text-center font-normal">Toleransi</th>
                            <th class="text-center font-normal">Lebih</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><th class="text-left font-normal">Panjang</th><td>- <span class="text-[10px]">mm</span></td><td>- <span class="text-[10px]">mm</span></td><td>- <span class="text-[10px]">mm</span></td><td>0 <span class="text-[10px]">mm</span></td></tr>
                        <tr><th class="text-left font-normal">Lebar</th><td>- <span class="text-[10px]">mm</span></td><td>- <span class="text-[10px]">mm</span></td><td>- <span class="text-[10px]">mm</span></td><td>0 <span class="text-[10px]">mm</span></td></tr>
                        <tr><th class="text-left font-normal">Tinggi</th><td>- <span class="text-[10px]">mm</span></td><td>- <span class="text-[10px]">mm</span></td><td>- <span class="text-[10px]">mm</span></td><td>0 <span class="text-[10px]">mm</span></td></tr>
                        <tr><th class="text-left font-normal">FOH</th><td>- <span class="text-[10px]">mm</span></td><td>- <span class="text-[10px]">mm</span></td><td>- <span class="text-[10px]">mm</span></td><td>0 <span class="text-[10px]">mm</span></td></tr>
                        <tr><th class="text-left font-normal">ROH</th><td>- <span class="text-[10px]">mm</span></td><td>- <span class="text-[10px]">mm</span></td><td>- <span class="text-[10px]">mm</span></td><td>0 <span class="text-[10px]">mm</span></td></tr>
                    </tbody>
                </table>

                <div class="space-y-4 text-xs">
                    <div class="flex items-center gap-3">
                        <label class="data-label text-slate-600">Melanggar ?</label>
                        <div class="relative inline-block w-10 h-5 align-middle select-none">
                            <input type="checkbox" id="toggle" class="toggle-checkbox absolute block w-5 h-5 rounded-full bg-white border-4 appearance-none cursor-pointer <?= $is_melanggar ? 'border-green-500' : 'border-gray-300' ?>" <?= $is_melanggar ? 'checked' : '' ?> disabled/>
                            <label for="toggle" class="toggle-label block overflow-hidden h-5 rounded-full cursor-pointer <?= $is_melanggar ? 'bg-green-500' : 'bg-gray-300' ?>"></label>
                        </div>
                    </div>

                    <?php if ($is_melanggar): ?>
                    <div class="relative mt-3">
                        <label class="data-label absolute -top-2 left-2 px-1 bg-white z-10 text-[10px]"><span class="text-red-500">*</span> Jenis Pelanggaran</label>
                        <div class="border border-slate-300 rounded p-2 flex flex-wrap gap-1 bg-white mt-2">
                            <span class="bg-slate-200 text-slate-700 px-2 py-0.5 rounded flex items-center gap-1 text-[11px]">DOKUMEN <span class="text-slate-400 cursor-pointer hover:text-red-500">×</span></span>
                        </div>
                    </div>

                    <div class="relative mt-3">
                        <label class="data-label absolute -top-2 left-2 px-1 bg-white z-10 text-[10px]"><span class="text-red-500">*</span> Pasal</label>
                        <div class="border border-slate-300 rounded p-2 flex flex-wrap gap-1 bg-white mt-2">
                            <span class="bg-slate-200 text-slate-700 px-2 py-0.5 rounded flex items-center gap-1 text-[11px]">Pasal 288 ayat (3) Jo Pasal 106 ayat (5) huruf c <span class="text-slate-400 cursor-pointer hover:text-red-500">×</span></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="mt-6 flex justify-end">
                    <button onclick="bukaPopup()" class="bg-[#2B6CB0] hover:bg-blue-800 text-white py-1.5 px-6 rounded font-bold text-xs transition duration-300 flex items-center gap-2 shadow-md">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16"><path d="M2 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4.207a1 1 0 0 0-.293-.707l-2.5-2.5A1 1 0 0 0 10.5 1H2zm13 10a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h8.086L15 4.914V11zM3 4h4v3H3V4zm6 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/></svg>
                        SIMPAN
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="popupModal" class="hidden fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-8 max-w-lg w-full text-center text-gray-800 shadow-2xl">
            <h2 class="text-2xl font-bold mb-4">Bukti Pelanggaran</h2>
            <p class="mb-8 text-gray-600">Apakah anda akan mencetak data pelanggaran (PDF) ?</p>
            
            <div class="flex justify-center items-center gap-4">
                <button onclick="tutupPopup()" class="border border-gray-300 hover:bg-gray-100 text-gray-700 px-6 py-2 rounded font-bold">
                    TIDAK
                </button>
                <button onclick="kirimWAGateway()" class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded font-bold flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
                    KIRIM WA
                </button>
                <button onclick="downloadBukti()" class="bg-yellow-500 hover:bg-yellow-600 text-white px-6 py-2 rounded font-bold">
                    YA
                </button>
            </div>
            <p id="statusWA" class="mt-4 text-sm font-bold hidden"></p>
        </div>
    </div>

    <script>
        function bukaPopup() { document.getElementById('popupModal').classList.remove('hidden'); }
        function tutupPopup() { 
            document.getElementById('popupModal').classList.add('hidden'); 
            document.getElementById('statusWA').classList.add('hidden');
        }
        function downloadBukti() {
            window.open('cetak_surat.php?id=<?= $data['id'] ?>', '_blank');
            tutupPopup();
        }

        async function kirimWAGateway() {
            const btnKirim = document.querySelector('button[onclick="kirimWAGateway()"]');
            const statusText = document.getElementById('statusWA');
            
            // Kunci tombol agar tidak di-spam klik
            btnKirim.disabled = true;
            btnKirim.classList.add('opacity-50', 'cursor-not-allowed');
            
            statusText.classList.remove('hidden');
            statusText.className = "mt-4 text-sm font-bold text-blue-600";
            statusText.innerText = "⏳ Mencatat waktu & mengirim pesan...";

            const formData = new FormData();
            formData.append("nomor_wa", "<?= $nomor_wa_format ?>");
            formData.append("pesan_teks", <?= $pesan_wa_js ?>);
            formData.append("id_pelanggaran", "<?= $id ?>"); // Untuk relasi log

            try {
                // Panggil file PHP internal kita, BUKAN langsung ke Fonnte
                const response = await fetch("api_kirim_wa.php", {
                    method: "POST",
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.status === "success") {
                    statusText.className = "mt-4 text-sm font-bold text-green-600";
                    statusText.innerHTML = `✅ Pesan Terkirim!<br>
                        <span class="text-xs text-gray-500">Waktu Server (T0): ${result.waktu_klik}</span><br>
                        <span class="text-xs text-gray-500 font-mono">Epoch T0: ${result.epoch_t0}</span>
                    `;
                    
                } else {
                    statusText.className = "mt-4 text-sm font-bold text-red-600";
                    statusText.innerText = "❌ Gagal terkirim: " + result.message;
                }
            } catch (error) {
                statusText.className = "mt-4 text-sm font-bold text-red-600";
                statusText.innerText = "❌ Terjadi kesalahan server/jaringan.";
            } finally {
                // Buka kembali tombol
                btnKirim.disabled = false;
                btnKirim.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        }
    </script>
</body>
</html>