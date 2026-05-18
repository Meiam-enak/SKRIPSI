import os
import sys
import time

# 🔥 KODE SAKTI: Otomatis memindahkan terminal ke folder tempat file ini berada
# Ini mencegah Error "FileNotFound" untuk best.pt selamanya.
os.chdir(os.path.dirname(os.path.abspath(__file__)))

import cv2
import threading
import requests
import re
import numpy as np
import warnings
from ultralytics import YOLO
from deep_sort_realtime.deepsort_tracker import DeepSort
import torch 
import pandas as pd
from datetime import datetime

# MATIKAN PESAN MERAH
warnings.filterwarnings("ignore")

# ==========================================
# ⚙️ KONFIGURASI SISTEM (SUDAH DIUPDATE KE WEB!)
# ==========================================
class Config:
    # ✅ URL DIARAHKAN KE MULUT SERVER (API_INPUT.PHP)
    WEB_APP_URL = "http://ujiberkalabypassuppkb.com/api_input.php" 
    
    # ✅ PASSWORD RAHASIA AGAR TIDAK DIHACK (Sesuai dengan di cPanel)
    API_KEY = "BALONGGANDU_SECURE_2026"
    
    # ✅ TAMBAHAN: NAMA DEVICE AGAR MASUK KE FILTER "Kamera Simulasi Miniatur" DI WEB
    DEVICE_NAME = "Kamera Simulasi Miniatur"
    
    # 🔥 MENGGUNAKAN WEBCAM 🔥
    # Angka 0 untuk kamera bawaan laptop. Ganti 1 atau 2 jika pakai webcam USB.
    VIDEO_SOURCE = 0
    
    # 🔥 3 MODEL YOLO 🔥
    MODEL_VEHICLE = "MINIATUR_best.pt"  
    MODEL_PLAT    = "MINIATUR_best_plat.pt"     
    MODEL_CHAR    = "karakter_last.pt"     
    
    MODEL_SR_PATH = "ESPCN_x4.pb" # Opsional
    
    YOLO_SIZE = 640
    CONF_VEHICLE = 0.45
    CONF_PLAT    = 0.20 
    CONF_CHAR    = 0.35 
    
    LINE_POSITION_RATIO = 0.60  
    DEBUG_SAVE_IMAGES = True 
    
    FRAME_SKIP = 2 # 🔥 Mengembalikan FPS yang drop
    MAX_CANDIDATE_FRAMES = 3 # 🔥 ANTI-BLUR: Ambil 3 frame, pilih yang paling tajam

# ==========================================
# 🚀 CORE CLASS: VEHICLE DETECTOR
# ==========================================
class VehicleDetector:
    def __init__(self):
        self.device = 'cuda' if torch.cuda.is_available() else 'cpu'
        print(f"⚡ Memulai Sistem pada: {self.device.upper()}")
        print("⏳ Memuat 3 Model YOLO dan DeepSORT...")

        self.model_vehicle = YOLO(Config.MODEL_VEHICLE)
        self.class_names = self.model_vehicle.names
        self.model_plate = YOLO(Config.MODEL_PLAT)
        self.model_char = YOLO(Config.MODEL_CHAR)
        self.char_names = self.model_char.names 

        # DeepSORT Tracker (Presisi Tinggi)
        self.tracker = DeepSort(
            max_age=30, n_init=2, nms_max_overlap=1.0, 
            embedder="mobilenet", half=(self.device == 'cuda')
        )
        self.processed_ids = set()
        
        # 🔥 ANTI-BLUR: Tempat menyimpan sementara gambar truk
        self.pending_tracks = {}

        self.sr = None
        if os.path.exists(Config.MODEL_SR_PATH):
            try:
                self.sr = cv2.dnn_superres.DnnSuperResImpl_create()
                self.sr.readModel(Config.MODEL_SR_PATH)
                self.sr.setModel("espcn", 4)
                print("✅ Super Resolution (ESPCN) Siap!")
            except: pass

        if Config.DEBUG_SAVE_IMAGES:
            os.makedirs("debug_plat", exist_ok=True)

        try:
            self.db_kir = pd.read_csv('Database_miniatur.csv')
            self.db_kir['Masa Berlaku Uji Berkala'] = pd.to_datetime(
                self.db_kir['Masa Berlaku Uji Berkala'], dayfirst=True
            )
            print("✅ Database CSV Berhasil Dimuat.")
        except:
            self.db_kir = None
            print("⚠️ Peringatan: Database_miniatur.csv Tidak Ditemukan.")
            
        self.prev_time = time.time()
        self.fps = 0.0

        # --- Variabel pencatat performa ---
        self.fps_list = []
        self.frame_count = 0

    def read_plate_with_yolo(self, plate_img, track_id):
        if plate_img is None or plate_img.size == 0: return "TIDAK TERBACA", plate_img

        h, w = plate_img.shape[:2]
        processed = plate_img
        
        if w < 200:
            if self.sr is not None:
                try: processed = self.sr.upsample(plate_img)
                except: processed = cv2.resize(plate_img, None, fx=2, fy=2)
            else:
                processed = cv2.resize(plate_img, None, fx=2, fy=2)
        
        try:
            lab = cv2.cvtColor(processed, cv2.COLOR_BGR2LAB)
            l, a, b = cv2.split(lab)
            clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8,8))
            cl = clahe.apply(l)
            processed = cv2.cvtColor(cv2.merge((cl,a,b)), cv2.COLOR_LAB2BGR)
        except: pass

        results = self.model_char(processed, conf=Config.CONF_CHAR, verbose=False)[0]
        
        detections = []
        img_h = processed.shape[0]
        center_y_area = img_h // 2  
        
        for box in results.boxes:
            x1, y1, x2, y2 = map(int, box.xyxy[0])
            conf = float(box.conf[0])
            cls_id = int(box.cls[0])
            char_label = self.char_names[cls_id]
            
            box_center_y = (y1 + y2) // 2
            if abs(box_center_y - center_y_area) > (img_h * 0.35):
                continue 

            detections.append({'x1': x1, 'x2': x2, 'conf': conf, 'label': char_label, 'width': x2-x1})

        if not detections: return "TIDAK TERBACA", processed

        detections.sort(key=lambda x: x['x1']) 
        final_dets = []
        
        if len(detections) > 0:
            final_dets.append(detections[0])
            for i in range(1, len(detections)):
                curr = detections[i]
                prev = final_dets[-1]
                overlap = max(0, min(prev['x2'], curr['x2']) - max(prev['x1'], curr['x1']))
                
                if overlap > (min(prev['width'], curr['width']) * 0.3):
                    if curr['conf'] > prev['conf']:
                        final_dets[-1] = curr 
                else:
                    final_dets.append(curr)

        raw_chars = [d['label'] for d in final_dets]
        total_char = len(raw_chars)
        fixed_chars = []

        for i, char in enumerate(raw_chars):
            corrected = char
            if i < 1: 
                corrected = self.fix_char(char, is_digit=False) 
            elif 1 <= i < total_char - 2:
                corrected = self.fix_char(char, is_digit=True) 
            elif i >= total_char - 2: 
                if char in ['0','1','2','3','4','5','6','7','8','9'] and i == total_char-1:
                     corrected = self.fix_char(char, is_digit=False) 
            fixed_chars.append(corrected)

        full_text = "".join(fixed_chars)

        if Config.DEBUG_SAVE_IMAGES:
            debug_img = processed.copy()
            for d in final_dets:
                cv2.rectangle(debug_img, (d['x1'], 0), (d['x2'], img_h), (0,255,0), 1)
                cv2.putText(debug_img, d['label'], (d['x1'], 20), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (0,0,255), 2)
            cv2.imwrite(f"debug_plat/{track_id}_smart_yolo.jpg", debug_img)

        return full_text, processed

    def fix_char(self, char, is_digit=True):
        if is_digit: 
            mapping = {'O': '0', 'D': '0', 'Q': '0', 'U': '0', 'I': '1', 'L': '1', 'T': '1', 'J': '1', 'Z': '2', 'S': '5', 'B': '8', 'A': '4', 'G': '6', 'E': '3'}
        else: 
            mapping = {'0': 'D', '1': 'I', '2': 'Z', '3': 'E', '4': 'A', '5': 'S', '6': 'G', '7': 'T', '8': 'B'}
        return mapping.get(char, char)

    # 🧵 THREADING UNTUK MENGIRIM DATA KE WEB CPANEL ANDA
    def background_task(self, track_id, full_frame, car_box_coords, vehicle_label, violation_codes, sharpness_score=0, cross_time_obj=None):
        try:
            clean_plat = "TIDAK TERBACA"
            plate_roi = None
            final_plate_img = None 
            
            results = self.model_plate(full_frame, conf=Config.CONF_PLAT, verbose=False)[0]
            cx1, cy1, cx2, cy2 = car_box_coords
            
            for box in results.boxes:
                px1, py1, px2, py2 = map(int, box.xyxy[0])
                p_center_x, p_center_y = (px1 + px2) // 2, (py1 + py2) // 2
                
                if (cx1 - 50 < p_center_x < cx2 + 50) and (cy1 - 50 < p_center_y < cy2 + 50):
                    plate_roi = full_frame[py1:py2, px1:px2]
                    raw_text, final_plate_img = self.read_plate_with_yolo(plate_roi, track_id)
                    
                    match = re.search(r'([A-Z]{1,2})([0-9]{1,4})([A-Z]{1,3})', raw_text)
                    if match:
                        clean_plat = f"{match.group(1)} {match.group(2)} {match.group(3)}"
                    else:
                        if len(raw_text) >= 3:
                            temp = re.sub(r'([A-Z])([0-9])', r'\1 \2', raw_text)
                            clean_plat = re.sub(r'([0-9])([A-Z])', r'\1 \2', temp)
                    break 

            if final_plate_img is None and plate_roi is not None:
                final_plate_img = plate_roi

            # LOGIKA PENGECEKAN CSV LOKAL TETAP DIBIARKAN AGAR TIDAK MERUSAK KODE (MESKIPUN WEB BISA CEK SENDIRI)
            violation_kir = set()
            data_uji_text = "TIDAK TERDAFTAR"
            status_uji_text = "ILEGAL?"

            if self.db_kir is not None and "TIDAK" not in clean_plat:
                pc = clean_plat.replace(" ", "")
                match = self.db_kir[self.db_kir['Nomor Kendaraan'].str.replace(" ", "").str.upper() == pc]
                
                if not match.empty:
                    row = match.iloc[0]
                    try:
                        tgl_berlaku = row['Masa Berlaku Uji Berkala']
                        status_uji_text = "TIDAK BERLAKU" if datetime.now() > tgl_berlaku else "BERLAKU"
                    except: status_uji_text = "ERROR TANGGAL"
                    
                    if status_uji_text == "TIDAK BERLAKU": 
                        violation_kir.add("286") 
                        violation_kir.add("288 (3)")

            violation_codes.update(violation_kir)
            
            # ==========================================
            # 🔥 BAGIAN PENGIRIMAN KE WEBSITE CPANEL 🔥
            # ==========================================
            # Margin diperlebar secara ekstrem agar bodi kendaraan utuh
            margin_x = 150  # Jarak ke kiri dan kanan
            margin_y = 250  # Jarak ke atas dan bawah (diperbesar untuk atap truk)
            h_f, w_f = full_frame.shape[:2]

            v_y1 = max(0, cy1 - margin_y)
            v_y2 = min(h_f, cy2 + margin_y)
            v_x1 = max(0, cx1 - margin_x)
            v_x2 = min(w_f, cx2 + margin_x)

            vehicle_display = full_frame[v_y1:v_y2, v_x1:v_x2]
            
            # 1. Mengubah gambar OpenCV menjadi format file jpg (Bytes)
            _, buf_v = cv2.imencode('.jpg', vehicle_display)
            vehicle_bytes = buf_v.tobytes()
            
            plat_bytes = b""
            if final_plate_img is not None:
                _, buf_p = cv2.imencode('.jpg', final_plate_img)
                plat_bytes = buf_p.tobytes()

            # 2. Menyiapkan Data Teks (Sesuai dengan yang ditangkap api_input.php)
            data_teks = {
                "api_key": Config.API_KEY,
                "plat_nomor": clean_plat,
                "waktu_melintas": cross_time_obj.strftime("%Y-%m-%d %H:%M:%S") if cross_time_obj else datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                "device": Config.DEVICE_NAME 
            }

            # 3. Menyiapkan File Foto
            file_upload = {
                "foto_kendaraan": ("kendaraan.jpg", vehicle_bytes, "image/jpeg")
            }
            if final_plate_img is not None:
                file_upload["foto_plat"] = ("plat.jpg", plat_bytes, "image/jpeg")

            # 4. Tembak ke Website Anda!
            response = requests.post(Config.WEB_APP_URL, data=data_teks, files=file_upload, timeout=15)
            
            if response.status_code == 200:
                # 🔥 MENGHITUNG WAKTU PEMROSESAN (LATENSI) 🔥
                if cross_time_obj:
                    upload_time_obj = datetime.now()
                    # %H:%M:%S.%f akan menghasilkan milidetik, kita potong 3 digit terakhir [:-3]
                    cross_time_str = cross_time_obj.strftime("%H:%M:%S.%f")[:-3]
                    upload_time_str = upload_time_obj.strftime("%H:%M:%S.%f")[:-3]
                    
                    delay_ms = (upload_time_obj - cross_time_obj).total_seconds() * 1000
                    
                    print(f"🚀 [UPLOAD KE WEB SUKSES] ID:{track_id} | Plat:{clean_plat} | Fokus: {sharpness_score:.0f}")
                    print(f"   ⏱️ Melintas: {cross_time_str} | Terupload: {upload_time_str} | Lama Proses: {delay_ms:.0f} ms\n")
                else:
                    print(f"🚀 [UPLOAD KE WEB SUKSES] ID:{track_id} | Plat:{clean_plat} | Device: {Config.DEVICE_NAME} | Fokus: {sharpness_score:.0f}")
            else:
                print(f"⚠️ [WEB ERROR] Kode: {response.status_code}")

        except Exception as e:
            print(f"❌ Error Upload Thread (ID:{track_id}): {e}")

    # ==========================================
    # 👮‍♂️ MAIN PROCESS FRAME
    # ==========================================
    def process_frame(self, frame):
        clean_frame_for_upload = frame.copy() # Ambil gambar bersih di awal
        
        h_img, w_img = frame.shape[:2]
        line_y = int(h_img * Config.LINE_POSITION_RATIO)
        
        results_v = self.model_vehicle(frame, imgsz=Config.YOLO_SIZE, conf=Config.CONF_VEHICLE, verbose=False)[0]

        detections = []         
        for box in results_v.boxes:
            x1, y1, x2, y2 = map(int, box.xyxy[0])
            conf = float(box.conf[0])
            label = self.class_names[int(box.cls[0])]

            if label in ["Truck", "Car", "Pickup", "Bus"]:
                w_box, h_box = x2 - x1, y2 - y1
                detections.append(([x1, y1, w_box, h_box], conf, label))

        tracks = self.tracker.update_tracks(detections, frame=frame)

        for track in tracks:
            if not track.is_confirmed(): continue
            track_id = track.track_id
            l, t, r, b = map(int, track.to_ltrb())
            v_label = track.get_det_class() or "Truck"

            l, t, r, b = max(0, l), max(0, t), min(w_img, r), min(h_img, b)
            cv2.rectangle(frame, (l, t), (r, b), (0, 255, 0), 2)
            cv2.putText(frame, f"ID:{track_id}", (l, t-5), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (0, 255, 0), 2)

            # 🔥 SISTEM PENGUMPULAN & PEMILIHAN FRAME TERTAJAM (ANTI-BLUR) 🔥
            if b >= line_y and track_id not in self.processed_ids:
                if v_label in ["Truck", "Pickup"]:
                    
                    crop = clean_frame_for_upload[t:b, l:r]
                    sharpness = 0
                    if crop.size > 0:
                        gray = cv2.cvtColor(crop, cv2.COLOR_BGR2GRAY)
                        sharpness = cv2.Laplacian(gray, cv2.CV_64F).var()

                    if track_id not in self.pending_tracks:
                        self.pending_tracks[track_id] = []
                        
                    self.pending_tracks[track_id].append({
                        'frame': clean_frame_for_upload.copy(), 
                        'box': [l, t, r, b], 
                        'label': v_label,
                        'sharpness': sharpness
                    })
                    
                    # Jika jumlah frame sudah cukup, pilih yang terbaik
                    if len(self.pending_tracks[track_id]) >= Config.MAX_CANDIDATE_FRAMES:
                        best_candidate = max(self.pending_tracks[track_id], key=lambda x: x['sharpness'])
                        
                        self.processed_ids.add(track_id) # Kunci agar tidak diproses lagi
                        
                        # TANGKAP WAKTU KETIKA MOBIL RESMI MELINTASI GARIS
                        cross_time_obj = datetime.now() 
                        
                        threading.Thread(
                            target=self.background_task,
                            args=(track_id, best_candidate['frame'], best_candidate['box'], best_candidate['label'], set(["287 (1)"]), best_candidate['sharpness'], cross_time_obj),
                            daemon=True
                        ).start()
                        
                        del self.pending_tracks[track_id] # Bersihkan memori
                else:
                    self.processed_ids.add(track_id)

        cv2.line(frame, (0, line_y), (w_img, line_y), (0, 255, 255), 2)
        
        # MENGHITUNG FPS
        curr_time = time.time()
        exec_time = curr_time - self.prev_time
        self.prev_time = curr_time
        
        self.frame_count += 1
        if exec_time > 0:
            current_fps = 1.0 / exec_time
            self.fps = (self.fps * 0.9) + (current_fps * 0.1)
            
            # Abaikan 20 frame pertama agar perhitungan rata-rata lebih akurat
            if self.frame_count > 20:
                self.fps_list.append(current_fps)

        fps_color = (0, 255, 0) if self.fps > 20 else (0, 255, 255) if self.fps > 10 else (0, 0, 255)
        cv2.putText(frame, f"FPS: {int(self.fps)}", (20, 50), cv2.FONT_HERSHEY_SIMPLEX, 1.2, fps_color, 3)

        return frame

    def run(self):
        print(f"🎬 Membuka sumber video: {Config.VIDEO_SOURCE} dengan kontrol kamera aktif...")
        
        # 🔥 WAJIB MENGGUNAKAN cv2.CAP_DSHOW AGAR FOKUS BISA DIATUR DI WINDOWS
        cap = cv2.VideoCapture(Config.VIDEO_SOURCE, cv2.CAP_DSHOW)
        
        if not cap.isOpened():
            print("❌ GAGAL: Webcam tidak ditemukan atau tidak mendapat izin.")
            return

        # ==========================================
        # 📸 PENGATURAN ANTI-LAG & FPS TINGGI
        # ==========================================
        cap.set(cv2.CAP_PROP_FOURCC, cv2.VideoWriter_fourcc('M', 'J', 'P', 'G'))
        cap.set(cv2.CAP_PROP_FRAME_WIDTH, 640)
        cap.set(cv2.CAP_PROP_FRAME_HEIGHT, 480)
        cap.set(cv2.CAP_PROP_FPS, 30)

        # ==========================================
        # 🎯 PENGATURAN FOKUS AWAL
        # ==========================================
        cap.set(cv2.CAP_PROP_AUTOFOCUS, 0) # Matikan auto-focus
        fokus_sekarang = 30
        cap.set(cv2.CAP_PROP_FOCUS, fokus_sekarang)

        # Membuka pop-up setting bawaan Windows jika butuh mengatur Exposure
        cap.set(cv2.CAP_PROP_SETTINGS, 1)

        cv2.namedWindow("UPPKB_SIMULTAN", cv2.WINDOW_NORMAL)
        
        print("\n" + "="*40)
        print("⚙️ KONTROL KAMERA AKTIF:")
        print("Tekan 'F' : Fokus Jarak Dekat (+)")
        print("Tekan 'G' : Fokus Jarak Jauh (-)")
        print("Tekan 'A' : Nyalakan/Matikan Auto-Focus")
        print("Tekan 'ESC': Keluar dari program")
        print("="*40 + "\n")

        autofocus_aktif = False
        frame_cnt_webcam = 0 # 🔥 Variabel untuk Frame Skip Webcam

        while cap.isOpened():
            ret, frame = cap.read()
            if not ret: break
            
            # 🔥 LOGIKA FRAME SKIP UNTUK MENAIKKAN FPS 🔥
            frame_cnt_webcam += 1
            if hasattr(Config, 'FRAME_SKIP') and frame_cnt_webcam % Config.FRAME_SKIP != 0: 
                continue

            processed = self.process_frame(frame)
            
            # Tampilkan informasi status fokus di bawah informasi FPS
            status_fokus = "AUTO" if autofocus_aktif else str(fokus_sekarang)
            cv2.putText(processed, f"Fokus Lensa: {status_fokus}", (20, 85), 
                        cv2.FONT_HERSHEY_SIMPLEX, 0.6, (0, 255, 255), 2)
            
            cv2.imshow("UPPKB_SIMULTAN", processed)
            
            # ==========================================
            # ⌨️ KONTROL KEYBOARD SECARA REAL-TIME
            # ==========================================
            key = cv2.waitKey(1) & 0xFF
            
            if key == 27: # Tekan ESC
                break
            elif key == ord('f') or key == ord('F'): 
                if not autofocus_aktif:
                    fokus_sekarang = min(255, fokus_sekarang + 5)
                    cap.set(cv2.CAP_PROP_FOCUS, fokus_sekarang)
                    print(f"🔍 Fokus digeser mendekat: {fokus_sekarang}")
            elif key == ord('g') or key == ord('G'): 
                if not autofocus_aktif:
                    fokus_sekarang = max(0, fokus_sekarang - 5)
                    cap.set(cv2.CAP_PROP_FOCUS, fokus_sekarang)
                    print(f"🔍 Fokus digeser menjauh: {fokus_sekarang}")
            elif key == ord('a') or key == ord('A'):
                autofocus_aktif = not autofocus_aktif
                cap.set(cv2.CAP_PROP_AUTOFOCUS, 1 if autofocus_aktif else 0)
                print(f"🤖 Auto-Focus: {'NYALA' if autofocus_aktif else 'MATI'}")
                if not autofocus_aktif:
                    # Kembalikan ke pengaturan nilai fokus manual saat dimatikan
                    cap.set(cv2.CAP_PROP_FOCUS, fokus_sekarang)
            
        cap.release()
        cv2.destroyAllWindows()
        print("✅ Program selesai.")

        # ====================================================
        # 📊 CETAK HASIL ANALISIS FPS UNTUK TABEL BAB 4
        # ====================================================
        if len(self.fps_list) > 0:
            fps_terendah = min(self.fps_list)
            fps_tertinggi = max(self.fps_list)
            fps_rata_rata = sum(self.fps_list) / len(self.fps_list)
            
            print("\n" + "="*50)
            print("=== HASIL ANALISIS FPS UNTUK TABEL 3.8 (BAB 4) ===")
            print("="*50)
            print(f"Total Frame Dianalisis : {len(self.fps_list)} frame")
            print(f"FPS Terendah           : {fps_terendah:.2f}")
            print(f"FPS Tertinggi          : {fps_tertinggi:.2f}")
            print(f"Rata-rata FPS          : {fps_rata_rata:.2f}")
            print("="*50 + "\n")
        else:
            print("⚠️ Tidak ada data FPS yang berhasil direkam.")

if __name__ == "__main__":
    app = VehicleDetector()
    app.run()