<?php

namespace Database\Seeders;

use App\Models\BahanKajian;
use App\Models\Course;
use App\Models\Cpl;
use App\Models\GraduateProfile;
use App\Models\Jurusan;
use App\Models\ProgramStudi;
use Illuminate\Database\Seeder;

/**
 * Kurikulum 2025 S1 Teknik Informatika Universitas Riau (KPT 5.0).
 *
 * Mengisi struktur OBE agar SESUAI dokumen kurikulum:
 *   - 13 CPL (CPL01..CPL13)
 *   - 4 Profil Lulusan (PL01..PL04) + pemetaan PL×CPL (Tabel 5)
 *   - 25 Bahan Kajian (BK01..BK25, Tabel 7)
 *   - 63 Mata Kuliah / 144 SKS / 8 semester (kode, SKS, semester)
 *   - Matriks evaluasi MK×CPL (Tabel 8)
 *
 * Catatan ketelitian:
 *   - Kode, SKS, semester, dan pemetaan MK×CPL diambil presis dari tabel dokumen.
 *   - Deskripsi CPL & BK adalah ringkasan substansi; ganti dengan teks verbatim
 *     dari PDF bila diperlukan keakuratan kata-per-kata.
 *   - Pemetaan PL×CPL mengikuti Tabel 5; PL03 tidak memiliki CPL pada tabel sumber.
 *   - Semua MK ditandai 'W' (wajib); tandai 'P' untuk MK pilihan/konsentrasi
 *     sesuai dokumen bila diperlukan.
 *
 * Jalankan: php artisan db:seed --class=KurikulumTif2025Seeder
 */
class KurikulumTif2025Seeder extends Seeder
{
    public function run(): void
    {
        $jurusan = Jurusan::firstOrCreate(
            ['nama_jurusan' => 'Teknik Informatika'],
            ['kode' => 'TI']
        );

        $prodi = ProgramStudi::firstOrCreate(
            ['kode' => 'S1-TI'],
            [
                'nama_prodi' => 'S1 Teknik Informatika',
                'jurusan_id' => $jurusan->id,
                'visi' => 'Menjadi program studi informatika unggul di tingkat nasional.',
            ]
        );

        $cplByCode = $this->seedCpl($prodi->id);
        $this->seedBahanKajian($prodi->id);
        $plByCode = $this->seedProfilLulusan($prodi->id);
        $this->seedProfilCplMatrix($plByCode, $cplByCode);
        $this->seedCourses($prodi->id, $jurusan->id, $cplByCode);

        $this->command?->info('✅ Kurikulum 2025 S1 Teknik Informatika berhasil di-seed.');
    }

    /**
     * @return array<string, Cpl>
     */
    private function seedCpl(int $prodiId): array
    {
        $cpls = [
            'CPL01' => 'Mampu menelaah dan mengintegrasikan nilai keislaman/kebangsaan serta menunjukkan sikap amanah, santun, dan bertanggung jawab.',
            'CPL02' => 'Bertakwa kepada Tuhan Yang Maha Esa serta menjunjung etika akademik dan integritas profesional.',
            'CPL03' => 'Menguasai konsep sistem komputer dan algoritma.',
            'CPL04' => 'Mampu menganalisis persoalan computing yang kompleks serta menerapkan manajemen proyek.',
            'CPL05' => 'Menguasai konsep teoritis informatika dan pengembangan multi-platform.',
            'CPL06' => 'Memiliki kompetensi teknologi serta kemampuan berpikir logis dan kritis.',
            'CPL07' => 'Mampu merancang dan menganalisis algoritma.',
            'CPL08' => 'Mampu merancang antarmuka pengguna dan aplikasi interaktif.',
            'CPL09' => 'Mampu mengembangkan sistem cerdas dan algoritma kompleks.',
            'CPL10' => 'Mampu merancang dan mengelola jaringan komputer.',
            'CPL11' => 'Mampu membangun solusi computing multi-platform.',
            'CPL12' => 'Mampu mengimplementasikan kebutuhan computing.',
            'CPL13' => 'Mampu menganalisis kebutuhan pengguna dan administrasi sistem.',
        ];

        $result = [];
        foreach ($cpls as $code => $description) {
            $result[$code] = Cpl::updateOrCreate(
                ['program_studi_id' => $prodiId, 'code' => $code],
                ['description' => $description, 'min_target' => 70],
            );
        }

        return $result;
    }

    private function seedBahanKajian(int $prodiId): void
    {
        $bks = [
            'BK01' => 'Masalah Sosial & Praktik Profesional',
            'BK02' => 'Keamanan & Manajemen',
            'BK03' => 'Manajemen Proyek',
            'BK04' => 'Desain Pengalaman Pengguna',
            'BK05' => 'Isu Keamanan & Prinsip Dasar',
            'BK06' => 'Data & Manajemen Informasi',
            'BK07' => 'Komputasi Paralel & Terdistribusi',
            'BK08' => 'Jaringan Komputer',
            'BK09' => 'Teknologi Keamanan',
            'BK10' => 'Desain Perangkat Lunak',
            'BK11' => 'Sistem Operasi',
            'BK12' => 'Struktur Data, Algoritma & Kompleksitas',
            'BK13' => 'Bahasa Pemrograman',
            'BK14' => 'Prinsip Dasar Pemrograman',
            'BK15' => 'Fundamental Sistem Komputer',
            'BK16' => 'Arsitektur & Organisasi Komputer',
            'BK17' => 'Grafis & Visualisasi',
            'BK18' => 'Sistem Cerdas',
            'BK19' => 'Pengembangan Berbasis Platform',
            'BK20' => 'Komputasi Sains',
            'BK21' => 'Interaksi Manusia & Komputer',
            'BK22' => 'Fundamental Pengembangan Perangkat Lunak',
            'BK23' => 'Desain & Analisis Sistem',
            'BK24' => 'Pengembangan Diri (SN Dikti)',
            'BK25' => 'Metodologi Penelitian (Wajib Umum)',
        ];

        foreach ($bks as $code => $name) {
            BahanKajian::updateOrCreate(
                ['program_studi_id' => $prodiId, 'code' => $code],
                ['name' => $name],
            );
        }
    }

    /**
     * @return array<int, GraduateProfile> keyed by nomor PL (1..4)
     */
    private function seedProfilLulusan(int $prodiId): array
    {
        $profiles = [
            1 => ['name' => 'Artificial Intelligence Engineer', 'description' => 'Merancang, membangun, mengimplementasikan, dan memelihara sistem kecerdasan buatan.'],
            2 => ['name' => 'System Analyst', 'description' => 'Menganalisis kebutuhan industri dan merancang solusi sistem informasi yang efisien.'],
            3 => ['name' => 'IT Mobility dan Internet of Things', 'description' => 'Merancang aplikasi mobile dan perangkat IoT untuk solusi cerdas.'],
            4 => ['name' => 'Programmer dan Software Developer', 'description' => 'Merancang dan mengembangkan perangkat lunak sesuai kebutuhan pengguna.'],
        ];

        $result = [];
        foreach ($profiles as $no => $data) {
            $result[$no] = GraduateProfile::updateOrCreate(
                ['program_studi_id' => $prodiId, 'name' => $data['name']],
                ['description' => $data['description']],
            );
        }

        return $result;
    }

    /**
     * Pemetaan PL × CPL (Tabel 5).
     *
     * @param  array<int, GraduateProfile>  $plByCode
     * @param  array<string, Cpl>  $cplByCode
     */
    private function seedProfilCplMatrix(array $plByCode, array $cplByCode): void
    {
        $matrix = [
            1 => [3, 4, 5, 6, 9, 10, 13],
            2 => [2, 7, 11, 12],
            3 => [],
            4 => [1, 8],
        ];

        foreach ($matrix as $plNo => $cplNumbers) {
            $cplIds = collect($cplNumbers)
                ->map(fn (int $n): int => $cplByCode[sprintf('CPL%02d', $n)]->id)
                ->all();

            $plByCode[$plNo]->cpls()->sync($cplIds);
        }
    }

    /**
     * Seed 63 mata kuliah + matriks MK×CPL (Tabel 8).
     *
     * @param  array<string, Cpl>  $cplByCode
     */
    private function seedCourses(int $prodiId, int $jurusanId, array $cplByCode): void
    {
        foreach ($this->courseData() as $row) {
            [$code, $name, $sks, $semester, $cplNumbers] = $row;

            $course = Course::updateOrCreate(
                ['program_studi_id' => $prodiId, 'code' => $code],
                [
                    'jurusan_id' => $jurusanId,
                    'name' => $name,
                    'sks' => $sks,
                    'semester' => $semester,
                    'wajib_pilihan' => 'W',
                ],
            );

            $cplIds = collect($cplNumbers)
                ->map(fn (int $n): int => $cplByCode[sprintf('CPL%02d', $n)]->id)
                ->all();

            $course->cpls()->sync($cplIds);
        }
    }

    /**
     * Data MK: [kode, nama, sks, semester, [nomor CPL...]].
     *
     * @return array<int, array{0:string,1:string,2:int,3:int,4:array<int,int>}>
     */
    private function courseData(): array
    {
        return [
            // ── Semester I (20 SKS) ──
            ['UNR00101001', 'Literasi Digital', 1, 1, [1]],
            ['UNR00101002', 'Bahasa Inggris', 1, 1, [1]],
            ['UNR00101003', 'Budaya Melayu', 2, 1, [1]],
            ['MWU00101001', 'Pendidikan Agama', 2, 1, [1, 2]],
            ['MWU00101007', 'Pendidikan Kewarganegaraan', 2, 1, [1, 2]],
            ['TIK07111001', 'Pengenalan Pemrograman', 3, 1, [2, 3, 7]],
            ['TIK07111002', 'Kalkulus', 3, 1, [2, 5, 6]],
            ['TIK07111003', 'Statistika', 3, 1, [2, 4, 6]],
            ['TIK07111004', 'Logika Matematika', 3, 1, [2, 4, 6]],

            // ── Semester II (20 SKS) ──
            ['TIK07122001', 'Algoritma Pemrograman', 2, 2, [2, 3, 7]],
            ['TIK07122002', 'Praktikum Algoritma Pemrograman', 1, 2, [2, 7]],
            ['MWU00101006', 'Pendidikan Pancasila', 2, 2, [1, 2]],
            ['UNR00101004', 'Ilmu Lingkungan & Mitigasi Bencana', 2, 2, [1, 2]],
            ['UNR00101005', 'Kewirausahaan', 2, 2, [1, 2]],
            ['MWU00101008', 'Bahasa Indonesia', 2, 2, [1, 2]],
            ['TIK07122003', 'Organisasi & Arsitektur Komputer', 3, 2, [2, 3, 6]],
            ['TIK07122004', 'Aljabar Linier', 3, 2, [2, 4, 6]],
            ['TIK07122005', 'Struktur Data', 2, 2, [2, 3, 7]],
            ['TIK07122006', 'Praktikum Struktur Data', 1, 2, [2, 7]],

            // ── Semester III (20 SKS) ──
            ['TIK07113001', 'Basis Data', 2, 3, [3, 13]],
            ['TIK07113002', 'Praktikum Basis Data', 1, 3, [2, 13]],
            ['TIK07113003', 'Jaringan Komputer', 2, 3, [4, 10]],
            ['TIK07113004', 'Praktikum Jaringan Komputer', 1, 3, [2, 10]],
            ['TIK07113005', 'Matematika Diskrit', 3, 3, [2, 5, 6]],
            ['TIK07113006', 'Sistem Operasi', 2, 3, [5, 11]],
            ['TIK07113007', 'Kompleksitas Algoritma', 3, 3, [3, 7, 12]],
            ['TIK07113008', 'Desain & Pemrograman Web', 2, 3, [5, 8]],
            ['TIK07113009', 'Praktikum Pemrograman Web', 1, 3, [2, 8]],
            ['TIK07113010', 'Pemrograman Berorientasi Objek', 2, 3, [3, 7]],
            ['TIK07113011', 'Praktikum Pemrograman Berorientasi Objek', 1, 3, [2, 7]],

            // ── Semester IV (22 SKS) ──
            ['TIK07124001', 'Pemrograman Mobile', 2, 4, [5, 8]],
            ['TIK07124002', 'Praktikum Pemrograman Mobile', 1, 4, [2, 8]],
            ['TIK07124003', 'Animasi & Pemodelan 3D', 2, 4, [5, 8]],
            ['TIK07124004', 'Praktikum Animasi & Pemodelan 3D', 1, 4, [2, 8]],
            ['TIK07124005', 'Rekayasa Perangkat Lunak', 2, 4, [2, 4, 13]],
            ['TIK07124006', 'Kecerdasan Buatan', 2, 4, [3, 9, 12]],
            ['TIK07124007', 'Praktikum Kecerdasan Buatan', 1, 4, [9, 12]],
            ['TIK07124008', 'Interaksi Manusia & Komputer', 2, 4, [5, 8]],
            ['TIK07124009', 'Pengolahan Citra Digital', 2, 4, [3, 7]],
            ['TIK07124010', 'Praktikum Pengolahan Citra Digital', 1, 4, [2, 7]],
            ['TIK07124011', 'Pemrograman Berbasis Platform', 3, 4, [2, 5, 11]],
            ['TIK07124012', 'Pembelajaran Mesin', 3, 4, [2, 3, 9]],

            // ── Semester V (23 SKS) ──
            ['TIK07115001', 'Keamanan Data & Informasi', 3, 5, [2, 4, 10]],
            ['TIK07115002', 'Visi Komputer', 3, 5, [2, 3, 9]],
            ['TIK07115003', 'Analisis & Desain Perangkat Lunak', 3, 5, [2, 4, 13]],
            ['TIK07115004', 'Manajemen Proyek Teknologi Informasi', 2, 5, [2, 4]],
            ['TIK07115005', 'Komputasi Paralel & Terdistribusi', 3, 5, [2, 4, 10]],
            ['TIK07115006', 'Kerja Praktek/Magang', 3, 5, [2, 6, 13]],
            ['TIK07115007', 'Internet of Things', 3, 5, [2, 3, 11]],
            ['TIK07115008', 'Ilmu Data', 3, 5, [2, 4, 9]],

            // ── Semester VI (23 SKS) ──
            ['TIK07126001', 'Pemrosesan Bahasa Alami', 3, 6, [4, 6, 9]],
            ['TIK07126002', 'Metodologi Penelitian', 2, 6, [2, 4, 6]],
            ['TIK07126003', 'Pengembangan AR & VR', 3, 6, [2, 5, 8]],
            ['TIK07126004', 'Komputasi Awan', 3, 6, [2, 4, 11]],
            ['TIK07126005', 'Big Data', 3, 6, [2, 4, 12]],
            ['TIK07126006', 'Antar Muka & Pengalaman Pengguna', 3, 6, [2, 5, 8]],
            ['TIK07126007', 'Sistem Informasi Geografis', 3, 6, [2, 4, 6]],
            ['TIK07126008', 'Robotika', 3, 6, [2, 4, 9]],

            // ── Semester VII (10 SKS) ──
            ['TIK07117001', 'Etika & Profesi', 2, 7, [1, 2]],
            ['TIK07117002', 'Proyek Perangkat Lunak (Capstone)', 3, 7, [2, 4, 11]],
            ['UNR00101006', 'Kuliah Kerja Nyata (KUKERTA)', 4, 7, [1, 2, 5]],
            ['TIK07117003', 'Seminar Proposal', 1, 7, [2, 5]],

            // ── Semester VIII (6 SKS) ──
            ['TIK07128001', 'Tugas Akhir', 6, 8, [1, 2, 5, 6, 11]],
        ];
    }
}
