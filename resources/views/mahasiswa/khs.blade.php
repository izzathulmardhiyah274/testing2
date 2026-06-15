<x-sidebar-layout :title="'Kartu Hasil Studi'" :header="'Kartu Hasil Studi (KHS)'">

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div class="text-muted small">
            <span class="fw-semibold" style="color:var(--obe-ink);">{{ $user->name }}</span>
            — <span style="font-family:monospace;">{{ $user->identity }}</span><br>
            {{ $namaProdi }} · Angkatan {{ $angkatan }}
        </div>
    </div>

    {{-- Pemilih semester --}}
    @if(count($semesterList))
        <div class="d-flex flex-wrap gap-2 mb-3">
            @foreach($semesterList as $s)
                <a href="{{ route('mahasiswa.khs', ['period_type' => $s['period_type'], 'academic_year' => $s['academic_year']]) }}"
                   class="btn btn-sm {{ ($s['period_type'] === $activePeriodType && $s['academic_year'] === $activeAcademicYear) ? 'btn-obe-red' : 'btn-obe-outline' }}">
                    {{ ucfirst($s['period_type']) }} {{ $s['academic_year'] }}
                </a>
            @endforeach
        </div>
    @endif

    {{-- Ringkasan --}}
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="obe-stat-card"><div class="obe-stat-card__label">Total SKS</div><div class="obe-stat-card__value">{{ $totalSks }}</div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="obe-stat-card">
                <div class="obe-stat-card__label">IPS</div>
                <div class="obe-stat-card__value" style="color:{{ $ips >= 3.0 ? '#16a34a' : ($ips >= 2.0 ? '#d97706' : 'var(--obe-red)') }};">{{ number_format($ips, 2) }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="obe-stat-card">
                <div class="obe-stat-card__label">IPK</div>
                <div class="obe-stat-card__value" style="color:{{ $ipk >= 3.0 ? '#16a34a' : ($ipk >= 2.0 ? '#d97706' : 'var(--obe-red)') }};">{{ number_format($ipk, 2) }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="obe-stat-card"><div class="obe-stat-card__label">Maks. SKS Berikutnya</div><div class="obe-stat-card__value">{{ $maxSksBerikutnya }}</div></div>
        </div>
    </div>

    <div class="obe-card p-0 overflow-hidden">
        <div class="px-3 py-3 border-bottom" style="background:var(--obe-bg);">
            <h2 class="obe-card__title mb-1">Nilai Semester {{ ucfirst($activePeriodType ?? '-') }} {{ $activeAcademicYear ?? '' }}</h2>
            <small class="text-muted">KE = banyaknya pengambilan mata kuliah s.d. semester ini.</small>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:.82rem;">
                <thead style="background:#f8fafc;">
                    <tr style="color:#64748b; font-weight:600; font-size:.74rem; text-transform:uppercase; letter-spacing:.04em;">
                        <th class="text-center" style="width:44px;">No</th>
                        <th style="width:120px;">Kode MK</th>
                        <th>Mata Kuliah</th>
                        <th class="text-center" style="width:55px;">SKS</th>
                        <th class="text-center" style="width:50px;">KE</th>
                        <th class="text-center" style="width:70px;">Nilai</th>
                        <th class="text-center" style="width:70px;">Mutu</th>
                        <th class="text-center" style="width:70px;">Grade</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($khsRows as $i => $row)
                        @php
                            $grade = $row['final_grade'];
                            $isGood = in_array($grade, ['A','A-','B+','B','B-']);
                            $badgeBg = $isGood ? '#dcfce7' : ($grade === 'E' ? '#fee2e2' : '#fef3c7');
                            $badgeColor = $isGood ? '#15803d' : ($grade === 'E' ? '#b91c1c' : '#a16207');
                        @endphp
                        <tr>
                            <td class="text-center text-muted">{{ $i + 1 }}</td>
                            <td><span class="badge" style="background:#f1f5f9; color:#475569; font-family:monospace; font-weight:600;">{{ $row['course']->code }}</span></td>
                            <td class="fw-semibold">{{ $row['course']->name }}<div class="text-muted" style="font-size:.7rem;">{{ $row['classroom']->name }}</div></td>
                            <td class="text-center fw-bold">{{ $row['course']->sks ?? '-' }}</td>
                            <td class="text-center">{{ $row['ke'] }}</td>
                            <td class="text-center">{{ $row['any_failed'] ? '0' : number_format($row['final_score'], 1) }}</td>
                            <td class="text-center fw-bold">{{ number_format($row['final_mutu'], 2) }}</td>
                            <td class="text-center">
                                <span class="d-inline-flex align-items-center justify-content-center fw-bold"
                                      style="min-width:34px; height:30px; padding:0 .5rem; border-radius:6px; background:{{ $badgeBg }}; color:{{ $badgeColor }};">
                                    {{ $grade }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4"><em>Belum ada nilai pada semester ini.</em></td></tr>
                    @endforelse
                </tbody>
                @if(count($khsRows))
                    <tfoot>
                        <tr style="background:var(--obe-bg);">
                            <td colspan="3" class="text-end fw-bold">Total SKS Semester</td>
                            <td class="text-center fw-bold">{{ $totalSks }}</td>
                            <td colspan="2" class="text-end fw-semibold text-muted small">IPS</td>
                            <td colspan="2" class="text-center fw-bold" style="color:var(--obe-red);">{{ number_format($ips, 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</x-sidebar-layout>
