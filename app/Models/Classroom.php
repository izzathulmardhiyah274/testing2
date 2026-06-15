<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Classroom extends Model
{
    protected $table = 'obe_kelas';

    protected $fillable = [
        'name',
        'semester',
        'academic_year',
        'period_type',
        'lecturer_id',
        'course_id',
        'enrollment_code',
        'is_archived',
        'kaprodi_snapshot',
        'archived_at',
        'satu_unri_bobot',
    ];

    protected $casts = [
        'is_archived' => 'boolean',
        'archived_at' => 'datetime',
        'satu_unri_bobot' => 'array',
    ];

    /* ── Relationships ─────────────────────────────────── */

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function students()
    {
        return $this->belongsToMany(User::class, 'obe_kelas_pengguna', 'classroom_id', 'user_id');
    }

    public function lecturer()
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    public function cpmks()
    {
        return $this->belongsToMany(Cpmk::class, 'obe_kelas_cpmk_dosen')
            ->withPivot('lecturer_id')
            ->withTimestamps();
    }

    public function classroomCpmks()
    {
        return $this->hasMany(ClassroomCpmk::class);
    }

    public function cpmkLecturers()
    {
        return $this->belongsToMany(User::class, 'obe_kelas_cpmk_dosen', 'classroom_id', 'lecturer_id')
            ->distinct();
    }

    /* ── Helpers ───────────────────────────────────────── */

    /**
     * Apakah dosen ini mengajar kelas tersebut?
     * True bila dia PIC utama kelas (kolom lecturer_id) ATAU ditugaskan pada
     * salah satu CPMK kelas (pivot obe_kelas_cpmk_dosen.lecturer_id).
     * Dipakai untuk otorisasi endpoint dosen agar tidak terjadi akses lintas kelas.
     */
    public function isTaughtBy(User $user): bool
    {
        if ((int) $this->lecturer_id === (int) $user->id) {
            return true;
        }

        return $this->cpmks()
            ->wherePivot('lecturer_id', $user->id)
            ->exists();
    }

    /**
     * Label periode (mis. "Ganjil 2024/2025")
     */
    public function getPeriodLabelAttribute(): string
    {
        $period = $this->period_type ? ucfirst($this->period_type) : '-';
        $year = $this->academic_year ?? '-';

        return "{$period} {$year}";
    }

    /**
     * Periode akademik aktif berdasarkan tanggal hari ini.
     */
    public static function currentPeriod(): array
    {
        return self::periodForDate(now());
    }

    /**
     * Hitung academic_year & period_type untuk satu tanggal, berbasis rentang
     * yang dapat dikonfigurasi via obe_pengaturan: period_ganjil_start,
     * period_ganjil_end, period_genap_start, period_genap_end (format MM-DD).
     */
    public static function periodForDate(\Carbon\Carbon $date): array
    {
        $year = (int) $date->year;
        $mmdd = $date->format('m-d');

        $get = function (string $key, string $default): string {
            $row = Setting::where('key', $key)->first();

            return $row?->value ?: $default;
        };

        $ganjilStart = $get('period_ganjil_start', '08-01');
        $ganjilEnd = $get('period_ganjil_end', '01-31');
        $genapStart = $get('period_genap_start', '02-01');
        $genapEnd = $get('period_genap_end', '07-31');

        // Genap: rentang dalam tahun yang sama (start <= today <= end).
        if ($mmdd >= $genapStart && $mmdd <= $genapEnd) {
            return [
                'period_type' => 'genap',
                'academic_year' => ($year - 1).'/'.$year,
            ];
        }

        // Ganjil: rentang lintas tahun (start..12-31) atau (01-01..end).
        if ($mmdd >= $ganjilStart) {
            return [
                'period_type' => 'ganjil',
                'academic_year' => $year.'/'.($year + 1),
            ];
        }

        if ($mmdd <= $ganjilEnd) {
            return [
                'period_type' => 'ganjil',
                'academic_year' => ($year - 1).'/'.$year,
            ];
        }

        // Fallback: gap antar rentang — jatuhkan ke ganjil tahun berjalan.
        return [
            'period_type' => 'ganjil',
            'academic_year' => $year.'/'.($year + 1),
        ];
    }

    /**
     * Urutan kronologis sebuah periode sebagai bilangan yang dapat dibandingkan.
     * Ganjil mendahului Genap pada tahun ajaran yang sama.
     * Contoh: ganjil 2025/2026 → 20251, genap 2025/2026 → 20252, ganjil 2026/2027 → 20261.
     *
     * @param  array{period_type: string|null, academic_year: string|null}  $period
     */
    public static function periodRank(array $period): int
    {
        $startYear = (int) substr((string) ($period['academic_year'] ?? '0'), 0, 4);
        $offset = ($period['period_type'] ?? 'ganjil') === 'genap' ? 2 : 1;

        return $startYear * 10 + $offset;
    }

    /**
     * Urutan kronologis periode kelas ini (dari label period_type/academic_year).
     */
    public function periodRankValue(): int
    {
        return self::periodRank([
            'period_type' => $this->period_type,
            'academic_year' => $this->academic_year,
        ]);
    }

    /**
     * Arsipkan otomatis kelas dari semester yang sudah benar-benar lewat.
     *
     * Sebuah kelas dianggap kedaluwarsa hanya bila BAIK periode labelnya MAUPUN
     * periode saat ia dibuat sudah lebih lama dari periode aktif sekarang. Dengan
     * begitu kelas yang baru dibuat pada jendela periode berjalan tidak ikut
     * terarsip (walau labelnya berbeda dari hitungan tanggal), dan kelas yang
     * disiapkan untuk semester mendatang juga tidak terarsip lebih awal.
     * Dijalankan sekali per hari (cache).
     */
    public static function autoArchiveExpired(): int
    {
        $cacheKey = 'classrooms.auto_archived_at';
        $today = now()->toDateString();

        if (\Illuminate\Support\Facades\Cache::get($cacheKey) === $today) {
            return 0;
        }

        $currentRank = self::periodRank(self::currentPeriod());

        $expiredIds = self::where('is_archived', false)
            ->get(['id', 'period_type', 'academic_year', 'created_at'])
            ->filter(function (self $classroom) use ($currentRank): bool {
                $labelRank = $classroom->periodRankValue();
                $createdRank = $classroom->created_at
                    ? self::periodRank(self::periodForDate($classroom->created_at))
                    : $labelRank;

                return max($labelRank, $createdRank) < $currentRank;
            })
            ->pluck('id');

        if ($expiredIds->isEmpty()) {
            \Illuminate\Support\Facades\Cache::put($cacheKey, $today, now()->endOfDay());

            return 0;
        }

        $count = self::whereIn('id', $expiredIds)->update([
            'is_archived' => true,
            'archived_at' => now(),
        ]);

        \Illuminate\Support\Facades\Cache::put($cacheKey, $today, now()->endOfDay());

        return (int) $count;
    }
}
