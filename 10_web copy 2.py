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
    WEB_APP_URL = "***" 
    
    # ✅ PASSWORD AGAR TIDAK DIHACK (Sesuai dengan di cPanel)
    API_KEY = "***"
    
    # ✅ PATH VIDEO (Drive D: filenya ada)
    VIDEO_SOURCE = r"D:\hfh\UPPKB_BALONGGANDU-27.10.2025-15.34.00.mp4"
    
    # 🔥 3 MODEL YOLO 🔥
    MODEL_VEHICLE = "best.pt"  
    MODEL_PLAT    = "best_plat.pt"     
    MODEL_CHAR    = "karakter_last.pt"     
    MODEL_SR_PATH = "ESPCN_x4.pb" # Opsional
    
    YOLO_SIZE = 640
    CONF_VEHICLE = 0.45
    CONF_PLAT    = 0.20 
    CONF_CHAR    = 0.35 

    FRAME_SKIP = 2
    
    LINE_POSITION_RATIO = 0.60  
    DEBUG_SAVE_IMAGES = True 

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

        # --- TAMBAHAN UNTUK BAB 4: Variabel penyimpan FPS ---
        self.fps_list = []
        self.frame_count = 0
        # ---------------------------------------------------

        self.counter = {
        "Truck": 0,
        "Pickup": 0,
        "Car": 0,
        "Bus": 0
        }

    def increment_counter(self, label):
        if label in self.counter:
            self.counter[label] += 1

    # ==========================================
    # 🧠 ALGORITMA KOREKSI PLAT PINTAR 
    # ==========================================
    def format_and_correct_plate(self, raw_text):
        raw_text = raw_text.replace(" ", "").upper()
        
        if len(raw_text) < 3:
            return raw_text # Kembalikan apa adanya jika terlalu pendek

        best_score = -1
        best_split = None
        
        # Cari kombinasi pecahan terbaik (Prefix: 1-2 huruf, Angka: 1-4 angka, Suffix: 0-3 huruf)
        for p in [1, 2]:
            for n in [1, 2, 3, 4]:
                s_len = len(raw_text) - p - n
                if 0 <= s_len <= 3:
                    prefix = raw_text[0:p]
                    num = raw_text[p:p+n]
                    suffix = raw_text[p+n:]
                    
                    score = 0
                    score += sum(1 for c in prefix if c.isalpha())
                    score += sum(1 for c in num if c.isdigit())
                    score += sum(1 for c in suffix if c.isalpha())
                    
                    if score > best_score:
                        best_score = score
                        best_split = (prefix, num, suffix)
        
        if not best_split:
            return raw_text 
            
        def correct_to_letters(text):
            mapping = {'0': 'O', '1': 'I', '2': 'Z', '3': 'E', '4': 'A', '5': 'S', '6': 'G', '7': 'T', '8': 'B'}
            return "".join([mapping.get(c, c) for c in text])
            
        def correct_to_numbers(text):
            mapping = {'O': '0', 'Q': '0', 'D': '0', 'I': '1', 'L': '1', 'T': '1', 'J': '1', 'Z': '2', 'S': '5', 'B': '8', 'A': '4', 'G': '6', 'E': '3'}
            return "".join([mapping.get(c, c) for c in text])
        
        p, n, s = best_split
        clean_p = correct_to_letters(p)
        clean_n = correct_to_numbers(n)
        clean_s = correct_to_letters(s)
        
        if clean_s:
            return f"{clean_p} {clean_n} {clean_s}"
        return f"{clean_p} {clean_n}"

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
            
            # Kembali ke logika aslimu: filter center Y
            if abs(box_center_y - center_y_area) > (img_h * 0.35):
                continue 

            detections.append({'x1': x1, 'x2': x2, 'conf': conf, 'label': char_label, 'width': x2-x1})

        if not detections: return "TIDAK TERBACA", processed

        # Sort dan Hapus Overlap persis seperti aslimu
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

        # Ambil label mentah
        raw_chars = "".join([d['label'] for d in final_dets])
        
        # GUNAKAN KOREKSI PINTAR
        full_text = self.format_and_correct_plate(raw_chars)

        if Config.DEBUG_SAVE_IMAGES:
            debug_img = processed.copy()
            for d in final_dets:
                cv2.rectangle(debug_img, (d['x1'], 0), (d['x2'], img_h), (0,255,0), 1)
                cv2.putText(debug_img, d['label'], (d['x1'], 20), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (0,0,255), 2)
            cv2.imwrite(f"debug_plat/{track_id}_{full_text}.jpg", debug_img)

        return full_text, processed

    # 🧵 THREADING UNTUK MENGIRIM DATA KE WEB CPANEL ANDA
    def background_task(self, track_id, full_frame, car_box_coords, vehicle_label, violation_codes):
        try:
            clean_plat = "TIDAK TERBACA"
            plate_roi = None
            final_plate_img = None 
            
            # KEMBALI KE FULL FRAME KARENA DATASET DILATIH FULL FRAME
            results = self.model_plate(full_frame, conf=Config.CONF_PLAT, verbose=False)[0]
            cx1, cy1, cx2, cy2 = car_box_coords
            
            for box in results.boxes:
                px1, py1, px2, py2 = map(int, box.xyxy[0])
                p_center_x, p_center_y = (px1 + px2) // 2, (py1 + py2) // 2
                
                if (cx1 - 50 < p_center_x < cx2 + 50) and (cy1 - 50 < p_center_y < cy2 + 50):
                    plate_roi = full_frame[py1:py2, px1:px2]
                    
                    # Dapatkan plat yang sudah di format
                    clean_plat, final_plate_img = self.read_plate_with_yolo(plate_roi, track_id)
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
            margin_x = 250  # Jarak ke kiri dan kanan
            margin_y = 350  # Jarak ke atas dan bawah (diperbesar untuk atap truk)
            h_f, w_f = full_frame.shape[:2]

            v_y1 = max(0, cy1 - margin_y)
            v_y2 = min(h_f, cy2 + margin_y)
            v_x1 = max(0, cx1 - margin_x)
            v_x2 = min(w_f, cx2 + margin_x)

            vehicle_display = full_frame[v_y1:v_y2, v_x1:v_x2]
            
            # 1. Mengubah gambar OpenCV menjadi format file jpg (Bytes)
            encode_param = [int(cv2.IMWRITE_JPEG_QUALITY), 60]  # 0-100 (lebih kecil = lebih ringan)
            _, buf_v = cv2.imencode('.jpg', vehicle_display, encode_param)
            vehicle_bytes = buf_v.tobytes()
            
            plat_bytes = b""
            if final_plate_img is not None:
                _, buf_p = cv2.imencode('.jpg', final_plate_img, encode_param)
                plat_bytes = buf_p.tobytes()

            # 2. Menyiapkan Data Teks (Sesuai dengan yang ditangkap api_input.php)
            data_teks = {
                "api_key": Config.API_KEY,
                "plat_nomor": clean_plat,
                "waktu_melintas": datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            }

            # 3. Menyiapkan File Foto
            file_upload = {
                "foto_kendaraan": ("kendaraan.jpg", vehicle_bytes, "image/jpeg")
            }
            if final_plate_img is not None:
                file_upload["foto_plat"] = ("plat.jpg", plat_bytes, "image/jpeg")

            # 4. Tembak ke Website Anda!
            response = requests.post(Config.WEB_APP_URL, data=data_teks, files=file_upload, timeout=50)
            
            if response.status_code == 200:
                print(f"🚀 [UPLOAD KE WEB SUKSES] ID:{track_id} | Plat:{clean_plat}")
            else:
                print(f"⚠️ [WEB ERROR] Kode: {response.status_code}")

        except Exception as e:
            print(f"❌ Error Upload Thread (ID:{track_id}): {e}")

    # ==========================================
    # 👮‍♂️ MAIN PROCESS FRAME
    # ==========================================
    def process_frame(self, frame):
        # 1. AMBIL FOTO BERSIH DI AWAL (Tanpa kotak apapun!)
        clean_frame_for_upload = frame.copy()
        y_offset = 80
        for k, v in self.counter.items():
            cv2.putText(frame, f"{k}: {v}", (20, y_offset), 
                        cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255,255,255), 2)
            y_offset += 25

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

            if b >= line_y and track_id not in self.processed_ids:
                self.processed_ids.add(track_id)

                # 🔥 FILTER JENIS KENDARAAN
                if v_label in ["Truck", "Pickup"]:
                    full_frame_copy = frame.copy()
                    # 2. KIRIM FOTO BERSIH KE THREAD BUKAN FOTO YANG SEDANG DIGAMBAR
                    threading.Thread(
                        target=self.background_task,
                        args=(track_id, clean_frame_for_upload, [l, t, r, b], v_label, set(["287 (1)"])),
                        daemon=True
                    ).start()
                else:
                    # 🚗 Masuk traffic counting saja
                    self.increment_counter(v_label)
                cv2.rectangle(frame, (l, t), (r, b), (0, 255, 0), 2)
                cv2.putText(frame, f"ID:{track_id}", (l, t-5), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (0, 255, 0), 2)    
        cv2.line(frame, (0, line_y), (w_img, line_y), (0, 255, 255), 2)
        
        # MENGHITUNG FPS
        curr_time = time.time()
        exec_time = curr_time - self.prev_time
        self.prev_time = curr_time
        
        self.frame_count += 1
        if exec_time > 0:
            current_fps = 1.0 / exec_time
            self.fps = (self.fps * 0.9) + (current_fps * 0.1)
            
            if self.frame_count > 20:
                self.fps_list.append(current_fps)
        # ---------------------------------------------

        fps_color = (0, 255, 0) if self.fps > 20 else (0, 255, 255) if self.fps > 10 else (0, 0, 255)
        cv2.putText(frame, f"FPS: {int(self.fps)}", (20, 50), cv2.FONT_HERSHEY_SIMPLEX, 1.2, fps_color, 3)

        return frame

    def run(self):
        print(f"🎬 Membuka video dari: {Config.VIDEO_SOURCE}")
        cap = cv2.VideoCapture(Config.VIDEO_SOURCE)
        
        if not cap.isOpened():
            print("❌ GAGAL: Video tidak ditemukan atau format tidak didukung.")
            return

        cv2.namedWindow("UPPKB_SIMULTAN", cv2.WINDOW_NORMAL)

        while cap.isOpened():
            ret, frame = cap.read()
            if not ret: break
            
            processed = self.process_frame(frame)
            cv2.imshow("UPPKB_SIMULTAN", processed)
            
            if cv2.waitKey(1) == 27: break # Tekan ESC untuk keluar
            
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
