<?php
session_start();
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JTO Verifikator - E-Bypass Balonggandu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* PERUBAHAN 1: Flexbox pada Body agar responsif mengisi layar penuh */
        body { background-color: #f4f6f9; color: #333; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; overflow: hidden; display: flex; flex-direction: column; height: 100vh; }
        
        /* NAVBAR */
        .navbar-custom { background-color: #2b6cb0; padding: 4px 15px; }
        .nav-link { color: #d9e2ec !important; font-size: 13px; margin-right: 15px; padding: 12px 0; }
        .nav-link.active-menu { color: #ffffff !important; font-weight: bold; border-bottom: 3px solid #63b3ed; }
        
        /* PERUBAHAN 2: Container Utama fleksibel */
        .main-container { flex-grow: 1; display: flex; flex-direction: column; overflow: hidden; padding-bottom: 10px; }

        /* PANEL FILTER SUPER RAPAT */
        .filter-panel { background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 4px; padding: 12px 16px; margin-bottom: 12px; }
        .form-label { font-size: 11px; color: #718096; margin-bottom: 2px; }
        .text-danger { font-weight: bold; color: #e53e3e !important; }
        
        /* KOTAK INPUT DEMPET */
        .form-control, .form-select { font-size: 12px; border-radius: 4px; border: 1px solid #cbd5e0; height: 30px; padding: 2px 8px; }
        .form-control:focus, .form-select:focus { border-color: #3182ce; box-shadow: 0 0 0 1px #3182ce; }
        
        /* HEADER FILTER BISA DIKLIK */
        .filter-header { font-size: 13px; font-weight: bold; color: #4a5568; margin-top: 10px; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #edf2f7; padding-bottom: 8px; cursor: pointer; user-select: none; transition: 0.2s; }
        .filter-header:hover { color: #2b6cb0; }
        
        /* TOMBOL-TOMBOL BAWAH */
        .btn-custom { font-size: 12px; padding: 4px 10px; border-radius: 4px; font-weight: 500; display: inline-flex; align-items: center; gap: 5px; height: 30px;}
        .btn-get { background-color: #38a169; color: white; border: none; }
        .btn-get:hover { background-color: #2f855a; color: white; }
        .btn-filter { background-color: #3182ce; color: white; border: none; }
        .btn-hapus { background-color: #e53e3e; color: white; border: none; }
        .btn-verifikasi { background-color: #ed8936; color: white; border: none; }
        
        /* KUNCI GRID 5 KOLOM */
        .antrean-row { display: flex; flex-wrap: wrap; margin-right: -6px; margin-left: -6px; }
        .kolom-5 { width: 20%; padding-right: 6px; padding-left: 6px; margin-bottom: 12px; }
        
        @media (max-width: 1200px) { .kolom-5 { width: 25%; } }
        @media (max-width: 992px) { .kolom-5 { width: 33.33%; } }
        @media (max-width: 768px) { .kolom-5 { width: 50%; } }

        /* PERUBAHAN 3: SCROLLBAR AJAIB (Otomatis melar kalau filter dilipat) */
        .scroll-area {
            flex-grow: 1; /* Mengambil seluruh sisa ruang layar di bawah filter */
            overflow-y: auto;
            overflow-x: hidden;
            padding-right: 5px;
        }
        .scroll-area::-webkit-scrollbar { width: 6px; }
        .scroll-area::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
        .scroll-area::-webkit-scrollbar-thumb { background: #cbd5e0; border-radius: 4px; }
        .scroll-area::-webkit-scrollbar-thumb:hover { background: #a0aec0; }
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

    <div class="container-fluid mt-3 main-container">
        <h5 class="fw-bold mb-2" style="font-size: 16px; color: #1a202c;">Data Capture</h5>
        
        <div class="filter-panel shadow-sm">
            <form id="formFilter">
                <div class="row gx-2 mb-1">
                    <div class="col-md-6">
                        <label class="form-label"><span class="text-danger">*</span> Shift</label>
                        <select class="form-select" id="f_shift">
                            <option selected>SHIFT 1</option>
                            <option>SHIFT 2</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><span class="text-danger">*</span> Regu</label>
                        <select class="form-select" id="f_regu">
                            <option selected>REGU A</option>
                            <option>REGU B</option>
                            <option>REGU C</option>
                        </select>
                    </div>
                </div>

                <div class="filter-header" data-bs-toggle="collapse" data-bs-target="#areaFilterBody" title="Klik untuk melipat/memperluas filter">
                    <div class="d-flex align-items-center">
                        <svg width="14" height="14" fill="currentColor" class="me-2" viewBox="0 0 16 16">
                          <path d="M1.5 1.5A.5.5 0 0 1 2 1h12a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.128.334L10 8.692V13.5a.5.5 0 0 1-.342.474l-3 1A.5.5 0 0 1 6 14.5V8.692L1.628 3.834A.5.5 0 0 1 1.5 3.5v-2z"/>
                        </svg>
                        Filter Data
                    </div>
                    <span id="ikon-collapse" style="font-size: 10px;">▼</span>
                </div>

                <div class="collapse show" id="areaFilterBody">
                    <div class="row gx-2 mb-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">Device</label>
                            <select class="form-select" id="f_device">
                                <option value="Video CCTV Bypass" selected>Video CCTV Bypass</option>
                                <option value="Kamera Simulasi Miniatur">Kamera Simulasi Miniatur</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Interval</label>
                            <select class="form-select" id="f_interval">
                                <option value="" selected disabled hidden>Pilih Interval...</option>
                                <option value="Jam">Jam</option>
                                <option value="Tanggal">Tanggal</option>
                            </select>
                        </div>
                        <div class="col-md-4 pb-1">
                            <div class="form-check d-flex align-items-center m-0" style="padding-left: 20px;">
                                <input class="form-check-input m-0 me-2" type="checkbox" id="reloadOtomatis" style="width:14px; height:14px; cursor: pointer;">
                                <label class="form-check-label text-muted" for="reloadOtomatis" style="font-size: 12px; cursor: pointer; padding-top:2px;">
                                    Reload Data Otomatis
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="row gx-2 mb-3 d-none" id="blok_jam">
                        <div class="col-md-4">
                            <label class="form-label">Tanggal</label>
                            <input type="date" class="form-control" id="f_tanggal" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Jam Dari</label>
                            <input type="time" class="form-control" id="f_jam_dari" value="00:00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Jam Sampai</label>
                            <input type="time" class="form-control" id="f_jam_sampai" value="23:59">
                        </div>
                    </div>

                    <div class="row gx-2 mb-3 d-none" id="blok_tanggal">
                        <div class="col-md-6">
                            <label class="form-label">Tanggal Dari</label>
                            <input type="date" class="form-control" id="f_tgl_dari" value="<?= date('Y-m-d', strtotime('-7 days')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tanggal Sampai</label>
                            <input type="date" class="form-control" id="f_tgl_sampai" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-1">
                        <div class="d-flex align-items-center gap-3">
                            <span style="font-size: 11px; color: #4a5568;" id="teksJumlah">Jumlah Data WIM Di Antrian : 0</span>
                            <button type="button" class="btn btn-custom btn-get" onclick="loadAntrean()">
                                <svg width="12" height="12" fill="currentColor" viewBox="0 0 16 16"><path d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/><path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/></svg> Get Data Antrian
                            </button>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-custom btn-filter" onclick="loadAntrean()">
                                <svg width="12" height="12" fill="currentColor" viewBox="0 0 16 16"><path d="M1.5 1.5A.5.5 0 0 1 2 1h12a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.128.334L10 8.692V13.5a.5.5 0 0 1-.342.474l-3 1A.5.5 0 0 1 6 14.5V8.692L1.628 3.834A.5.5 0 0 1 1.5 3.5v-2z"/></svg> Filter
                            </button>
                            <button type="button" class="btn btn-custom btn-hapus" onclick="aksiMassal('hapus')">
                                <svg width="12" height="12" fill="currentColor" viewBox="0 0 16 16"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/><path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/></svg> Hapus
                            </button>
                            <button type="button" class="btn btn-custom btn-verifikasi" onclick="aksiMassal('verifikasi')">
                                <svg width="12" height="12" fill="currentColor" viewBox="0 0 16 16"><path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/><path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"/></svg> Verifikasi
                            </button>
                        </div>
                    </div>
                </div> 
            </form>
        </div>

        <div class="scroll-area">
            <div class="antrean-row" id="antrean-container">
                <div class="col-12 text-center text-muted mt-5 w-100">
                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div> Mengambil data...
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const areaFilterBody = document.getElementById('areaFilterBody');
        const ikonCollapse = document.getElementById('ikon-collapse');
        const scrollArea = document.querySelector('.scroll-area');

        areaFilterBody.addEventListener('hide.bs.collapse', () => { ikonCollapse.innerText = '◀'; });
        areaFilterBody.addEventListener('show.bs.collapse', () => { ikonCollapse.innerText = '▼'; });

        document.getElementById('f_interval').addEventListener('change', function() {
            if (this.value === 'Jam') {
                document.getElementById('blok_jam').classList.remove('d-none'); document.getElementById('blok_tanggal').classList.add('d-none');
            } else if (this.value === 'Tanggal') {
                document.getElementById('blok_jam').classList.add('d-none'); document.getElementById('blok_tanggal').classList.remove('d-none');
            } else {
                document.getElementById('blok_jam').classList.add('d-none'); document.getElementById('blok_tanggal').classList.add('d-none');
            }
        });

        document.getElementById('f_device').addEventListener('change', loadAntrean);

        let reloadInterval;

        // MENYIMPAN FILTER
        function simpanFilterKeMemori() {
            sessionStorage.setItem('f_shift', document.getElementById('f_shift').value);
            sessionStorage.setItem('f_regu', document.getElementById('f_regu').value);
            sessionStorage.setItem('f_device', document.getElementById('f_device').value);
            sessionStorage.setItem('f_interval', document.getElementById('f_interval').value);
            sessionStorage.setItem('f_tanggal', document.getElementById('f_tanggal').value);
            sessionStorage.setItem('f_jam_dari', document.getElementById('f_jam_dari').value);
            sessionStorage.setItem('f_jam_sampai', document.getElementById('f_jam_sampai').value);
            sessionStorage.setItem('f_tgl_dari', document.getElementById('f_tgl_dari').value);
            sessionStorage.setItem('f_tgl_sampai', document.getElementById('f_tgl_sampai').value);
        }

        // MENGEMBALIKAN FILTER
        function pulihkanFilterDariMemori() {
            if (sessionStorage.getItem('f_device')) { 
                document.getElementById('f_shift').value = sessionStorage.getItem('f_shift');
                document.getElementById('f_regu').value = sessionStorage.getItem('f_regu');
                document.getElementById('f_device').value = sessionStorage.getItem('f_device');
                
                const interval = sessionStorage.getItem('f_interval');
                if (interval) {
                    document.getElementById('f_interval').value = interval;
                    document.getElementById('f_interval').dispatchEvent(new Event('change'));
                }

                document.getElementById('f_tanggal').value = sessionStorage.getItem('f_tanggal');
                document.getElementById('f_jam_dari').value = sessionStorage.getItem('f_jam_dari');
                document.getElementById('f_jam_sampai').value = sessionStorage.getItem('f_jam_sampai');
                document.getElementById('f_tgl_dari').value = sessionStorage.getItem('f_tgl_dari');
                document.getElementById('f_tgl_sampai').value = sessionStorage.getItem('f_tgl_sampai');
            }
        }

        // SIMPAN SCROLL
        scrollArea.addEventListener('scroll', function() {
            sessionStorage.setItem('posisiScrollAntrean', this.scrollTop);
        });

        function loadAntrean() {
            simpanFilterKeMemori(); 

            const device = document.getElementById('f_device').value; 
            const interval = document.getElementById('f_interval').value;
            
            let url = `get_antrean.php?device=${encodeURIComponent(device)}&interval=${interval}`;
            
            if (interval === 'Jam') {
                url += `&tanggal=${document.getElementById('f_tanggal').value}&jam_dari=${document.getElementById('f_jam_dari').value}&jam_sampai=${document.getElementById('f_jam_sampai').value}`;
            } else if (interval === 'Tanggal') {
                url += `&tgl_dari=${document.getElementById('f_tgl_dari').value}&tgl_sampai=${document.getElementById('f_tgl_sampai').value}`;
            }

            fetch(url)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('antrean-container').innerHTML = html;
                    const jumlah = document.querySelectorAll('.card-antrean').length;
                    document.getElementById('teksJumlah').innerText = `Jumlah Data WIM Di Antrian : ${jumlah}`;

                    // KEMBALIKAN POSISI SCROLL JIKA KITA DARI HALAMAN DETAIL
                    const urlParams = new URLSearchParams(window.location.search);
                    if (urlParams.get('kembali') === '1') {
                        const posisiTerakhir = sessionStorage.getItem('posisiScrollAntrean');
                        if (posisiTerakhir) {
                            setTimeout(() => {
                                scrollArea.scrollTop = posisiTerakhir;
                            }, 50);
                        }
                    }
                })
                .catch(error => console.error('Gagal:', error));
        }

        document.getElementById('reloadOtomatis').addEventListener('change', function() {
            if (this.checked) {
                loadAntrean(); 
                reloadInterval = setInterval(loadAntrean, 3000);
            } else {
                clearInterval(reloadInterval);
            }
        });

        function aksiMassal(jenisAksi) {
            const kotakDicentang = document.querySelectorAll('.centang-pilih:checked');
            let ids = [];
            kotakDicentang.forEach(box => ids.push(box.value));

            if (ids.length === 0) { alert('⚠️ Silakan centang minimal 1 kendaraan terlebih dahulu!'); return; }

            let pesan = jenisAksi === 'hapus' ? 'menghapus' : 'memverifikasi';
            if (confirm(`Yakin ingin ${pesan} ${ids.length} kendaraan yang dipilih?`)) {
                const formData = new URLSearchParams();
                formData.append('action', jenisAksi); formData.append('ids', JSON.stringify(ids));
                fetch('aksi_massal.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: formData.toString() })
                .then(res => res.text())
                .then(jawaban => { alert(jawaban); loadAntrean(); });
            }
        }

        // LOGIKA UTAMA SAAT HALAMAN DIBUKA
        const urlParams = new URLSearchParams(window.location.search);
        // Jika dari halaman detail (ada kembali=1 di URL), pulihkan filter dari memori
        if (urlParams.get('kembali') === '1') {
            pulihkanFilterDariMemori();
            
            // Opsional: Bersihkan URL agar terlihat rapi tanpa me-reload (Menghilangkan ?kembali=1)
            window.history.replaceState({}, document.title, window.location.pathname);
        }
        
        // Load data dari MySQL (Akan langsung menarik plat terbaru)
        loadAntrean(); 
    </script>
</body>
</html>