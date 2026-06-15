# OBE App — Sistem Penilaian Outcome-Based Education

Aplikasi web manajemen kurikulum & penilaian berbasis **OBE (Outcome-Based Education)** untuk program studi.
Mengelola rantai **CPL → CPMK → Sub-CPMK (Indikator) → Komponen Penilaian → Nilai**, dengan banyak peran
(admin, kaprodi, dosen, mahasiswa, dll), transkrip OBE & konvensional, KHS, serta ekspor rekap nilai.

> Dokumen ini ditujukan sebagai konteks onboarding (termasuk untuk Claude Code di laptop lain).
> Aturan gaya kode & konvensi tambahan ada di **`CLAUDE.md`** (Laravel Boost guidelines) — baca itu juga.

---

## 1. Stack Teknologi

| Lapisan | Teknologi |
|---|---|
| Bahasa/Framework | PHP **8.4**, Laravel **12** (struktur ramping: tanpa `Http/Kernel.php` & `Console/Kernel.php`, semua di `bootstrap/app.php`) |
| Database | **MySQL** (XAMPP). Semua tabel domain ber-prefix **`obe_`** |
| Frontend | **Blade** + **Bootstrap 5.3** (via CDN) + **Alpine.js 3** (via CDN) + **DataTables/jQuery** (via CDN). CSS kustom kelas `obe-*` di `resources/css/app.css`, di-bundle **Vite**. *Catatan: Tailwind terpasang di config tapi UI nyata memakai Bootstrap.* |
| Ekspor | **PhpSpreadsheet** (rekap nilai .xlsx format SATU UNRI), **DomPDF** (transkrip & KHS) |
| Testing | **Dihapus** dari build akhir (lihat §10) |

---

## 2. Menjalankan di Laptop Baru

Prasyarat: XAMPP (Apache opsional, **MySQL wajib**), PHP 8.4, Composer, Node.js + npm.

```bash
# 1. Dependensi
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Database — buat skema di MySQL, lalu sesuaikan .env (lihat di bawah)
#    Default dev: DB_DATABASE=obe_app2, DB_USERNAME=root, DB_PASSWORD= (kosong)
php artisan migrate

# 4. (Opsional) data contoh kurikulum TIF 2025
php artisan db:seed --class=KurikulumTif2025Seeder

# 5. Build aset & jalankan
npm run build          # atau: npm run dev (mode watch)
php artisan serve      # http://127.0.0.1:8000
```

`.env` kunci:
```
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=obe_app2
DB_USERNAME=root
DB_PASSWORD=
```

CLI MySQL XAMPP (Windows): `c:\xampp\mysql\bin\mysql.exe -u root` (tanpa password).

---

## 3. Struktur Penting

```
app/
  Http/Controllers/        # per-role: Kaprodi, Dosen, Mahasiswa, Admin, Classroom, Cpmk, Assessment*, ...
    Concerns/BuildsGradeReport.php   # trait sumber-tunggal rekap nilai (dipakai semua role)
  Services/
    GradeService.php               # ENGINE perhitungan nilai (murni, tanpa Eloquent)
    CplAchievementService.php       # ketercapaian CPL per kelas/transkrip
  Models/                  # Cpmk, Indicator, Assessment, AssessmentScore, Classroom, Course, Cpl, User, ...
bootstrap/app.php          # registrasi middleware, routing, dll (Laravel 12)
routes/web.php             # semua route web (≈123 route), dikelompokkan per-role
resources/views/           # admin/ kaprodi/ dosen/ mahasiswa/ auth/ partials/ components/
database/migrations/       # skema; banyak migrasi ALTER + 1 migrasi rename ke prefix obe_
database/seeders/
```

---

## 4. Model Domain OBE (alur data)

```
Course (obe_mata_kuliah)
  └── Cpmk (obe_cpmk)                 percentage = bobot CPMK dalam MK (∑=100)
        └── Indicator (obe_indikator) percentage = bobot Sub-CPMK dalam CPMK (∑=100)
              └── Assessment (obe_komponen_penilaian)   ← PER-KELAS (kolom classroom_id)
                    └── AssessmentScore (obe_nilai_komponen)   nilai mentah mahasiswa 0–100

Cpl (obe_cpl) ⇄ Cpmk (cpl_id)         # pemetaan CPL dilakukan per-CPMK
Classroom (obe_kelas) ⇄ Cpmk          # pivot obe_kelas_cpmk_dosen (+lecturer_id per CPMK)
Classroom ⇄ User (mahasiswa)          # pivot obe_kelas_pengguna
```

**Kunci isolasi:** `Cpmk`/`Indicator` adalah **template milik mata kuliah** (dipakai bersama semua kelas yang
mengampu MK itu), tetapi **Komponen penilaian & nilai dipisah per kelas** lewat `obe_komponen_penilaian.classroom_id`.
Semua jalur baca **wajib** memfilter `classroom_id` agar nilai antar-kelas tidak tercampur.

---

## 5. Engine Penilaian (WAJIB lewat sini, jangan duplikasi)

- **`GradeService::aggregateStudent(array $cpmks)`** — engine murni. Aturan:
  - CPMK/Indikator/Komponen yang **belum dinilai dilewati (null), bukan 0**.
  - Bobot dinormalisasi ulang atas bagian yang sudah dinilai (penilaian parsial tetap masuk akal; kelas lengkap = weighted-sum biasa).
  - Jika ada CPMK yang **sudah dinilai tapi < ambang lulus (70)** → nilai akhir **E (0)**.
- **`GradeService::fromCpmkCollection($cpmks, $scoreMap, $studentId, ?$classroomId)`** — adapter Eloquent→array; `$classroomId` memfilter komponen per kelas.
- **`GradeService::distributeAutoWeights($n, $remaining)`** — pembagian bobot otomatis dibagi rata.
- Konversi nilai → huruf/mutu mengikuti **SATU UNRI** (`toHuruf`, `toMutu`, `toKonvensional`).
- **Trait `Concerns\BuildsGradeReport`** (`buildRows`, `mergeCpmkPresentation`, `buildScoreMap`, `aggregateClassroom`, `transcriptRow`) — dipakai Dosen/Kaprodi/Mahasiswa. **Semua perubahan logika nilai lewat trait/Service ini.**

---

## 6. Peran (Roles)

`admin`, `admin_jurusan`/`kajur`, `kaprodi`, `dosen`, `dekan`, `wakil_dekan`, `mahasiswa`.
Multi-tenant per **jurusan/prodi** — data difilter sesuai jurusan/prodi user (cegah IDOR/kebocoran lintas prodi).
Alur ringkas:

- **Kaprodi**: kelola Profil Lulusan, CPL, Mata Kuliah, **CPMK + bobot + Sub-CPMK**, pemetaan **CPL×CPMK**, kelola/arsip kelas, laporan nilai.
- **Dosen**: lihat kelas yang diampu, buat **Komponen Penilaian** per Sub-CPMK (per kelas), **input nilai** mahasiswa, Laporan Nilai, ekspor Excel.
- **Mahasiswa**: gabung kelas (enrollment code), lihat **Rincian Penilaian** per kelas, Transkrip OBE/Konvensional, KHS.

---

## 7. Auto-Arsip Kelas

`Classroom::autoArchiveExpired()` (dipanggil di `ClassroomController::index`, di-cache 1×/hari) hanya mengarsip
kelas yang **periode label DAN periode saat dibuat** sudah lebih lama dari periode aktif sekarang
(`max(periodRank(label), periodRank(created_at)) < periodRank(currentPeriod)`).
Tujuannya: kelas baru di periode berjalan **tidak** ikut terarsip; kelas semester depan juga aman.
Periode dihitung dari tanggal via `Classroom::periodForDate()` + setting `period_*_start/end` (format MM-DD).
Arsip **manual** mengisi `kaprodi_snapshot`; arsip **otomatis** meninggalkannya `NULL` (penanda pembeda).

---

## 8. Catatan / Gotchas (penting bagi pengembang & Claude)

1. **Bukan repo git.** Tidak ada riwayat/undo — hati-hati saat menghapus/overwrite.
2. **Kolom bobot bisa "hilang" di DB yang di-restore dari dump lama.** `obe_cpmk.percentage`,
   `obe_indikator.percentage`, `obe_cpmk.meeting_start/meeting_end` pernah hilang walau migrasinya tercatat
   "sudah jalan" → seluruh bobot jadi 0 → total nilai tidak muncul. Dipulihkan migrasi idempotent
   `2026_06_07_*_restore_percentage_and_meetings_on_obe_cpmk_indikator` (guard `Schema::hasColumn` + backfill).
   **Pelajaran:** kalau total nilai tidak muncul, cek dulu kolom DB benar-benar ADA — jangan percaya tabel `migrations`.
3. **Dua sistem penilaian.** Yang AKTIF: `Cpmk→Indicator→Assessment(obe_komponen_penilaian)→AssessmentScore`.
   Sistem `ClassroomCpmk*` (obe_kelas_cpmk*) sebagian ORPHAN/setengah jadi — tabelnya kosong; sebagian route
   `dosen.classroom-cpmks.*` masih ada. Jangan campur saat menambah fitur nilai.
4. **Frontend via CDN**, bukan npm — Bootstrap/Alpine/DataTables di `components/sidebar-layout.blade.php` &
   `guest-layout.blade.php`. Perubahan Blade langsung tampak setelah refresh; perubahan `resources/css|js` perlu `npm run build`/`npm run dev`.
5. **Konvensi Laravel Boost** (lihat `CLAUDE.md`): pakai `php artisan make:*`, Form Request untuk validasi,
   `Model::query()` bukan `DB::`, named routes, jalankan `vendor/bin/pint` sebelum selesai.

---

## 9. Perintah Berguna

```bash
php artisan migrate                 # jalankan migrasi pending
php artisan migrate:status          # cek migrasi
php artisan route:list              # daftar route
php artisan tinker                  # REPL debug (atau MCP 'tinker'/'database-query')
vendor/bin/pint                     # format kode (WAJIB sebelum finalisasi)
npm run build                       # bundle aset produksi
```

---

## 10. Testing

File pengujian otomatis (`tests/`, `phpunit.xml`) **telah dihapus** untuk build produk akhir, beserta satu file
controller mati (`AssessmentController.php` — duplikat tak terpakai). Dependensi dev pest/phpunit masih ada di
`composer.json`; jika ingin menulis ulang test: `php artisan make:test --pest {Nama}` lalu kembalikan `phpunit.xml`.

---

## 11. Lisensi

Framework Laravel berlisensi [MIT](https://opensource.org/licenses/MIT). Kode aplikasi OBE ini milik tim pengembang proyek.
