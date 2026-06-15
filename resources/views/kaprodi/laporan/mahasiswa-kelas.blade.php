<x-sidebar-layout :title="'Rincian Nilai'" :header="'Rincian Nilai: ' . $student->name">

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <p class="text-muted small mb-0">
            <span class="fw-semibold" style="color:var(--obe-ink);">{{ $student->name }}</span>
            — <span style="font-family:monospace;">{{ $student->profilMahasiswa->nim ?? $student->identity }}</span><br>
            {{ $course?->code }} {{ $course?->name }} · {{ $classroom->name }}
        </p>
        <a href="{{ route('kaprodi.laporan.mahasiswa.show', $student) }}" class="btn btn-obe-outline btn-sm">&larr; Kembali</a>
    </div>

    {{-- Ringkasan nilai akhir --}}
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="obe-stat-card"><div class="obe-stat-card__label">Nilai Akhir</div>
                <div class="obe-stat-card__value" style="color:{{ $anyFailed ? 'var(--obe-red)' : 'inherit' }};">{{ $anyFailed ? '0' : number_format($finalScore ?? 0, 1) }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="obe-stat-card"><div class="obe-stat-card__label">Grade</div><div class="obe-stat-card__value">{{ $finalGrade }}</div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="obe-stat-card"><div class="obe-stat-card__label">Mutu</div><div class="obe-stat-card__value">{{ number_format($finalMutu ?? 0, 2) }}</div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="obe-stat-card"><div class="obe-stat-card__label">Status</div>
                <div class="obe-stat-card__value" style="font-size:1rem; color:{{ $anyFailed ? 'var(--obe-red)' : '#16a34a' }};">{{ $anyFailed ? 'Ada CPMK Gagal' : ($complete ? 'Lengkap' : 'Sebagian') }}</div>
            </div>
        </div>
    </div>

    @forelse($cpmkResults as $cpmk)
        <div class="obe-card p-0 overflow-hidden mb-3">
            <div class="px-3 py-2 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2" style="background:var(--obe-bg);">
                <div>
                    <span class="fw-bold" style="font-family:monospace; color:var(--obe-red);">{{ $cpmk['code'] }}</span>
                    @if($cpmk['cpl'])<span class="badge bg-light text-dark border ms-1">{{ $cpmk['cpl']->code }}</span>@endif
                    <span class="text-muted small ms-1">Bobot {{ $cpmk['weight'] }}%</span>
                </div>
                <div class="small">
                    Total CPMK:
                    <span class="fw-bold" style="color:{{ ($cpmk['total'] ?? 0) >= 70 ? '#16a34a' : 'var(--obe-red)' }};">
                        {{ $cpmk['total'] !== null ? number_format($cpmk['total'], 1) : '—' }}
                    </span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0" style="font-size:.78rem;">
                    <thead style="background:#374151; color:#d1d5db;">
                        <tr>
                            <th style="background:#374151; color:#d1d5db;">Indikator</th>
                            <th style="background:#374151; color:#d1d5db;">Komponen</th>
                            <th class="text-center" style="background:#374151; color:#d1d5db; width:70px;">Bobot</th>
                            <th class="text-center" style="background:#374151; color:#d1d5db; width:70px;">Nilai</th>
                            <th class="text-center" style="background:#374151; color:#fbbf24; width:80px;">Total Ind.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cpmk['indicators'] as $ind)
                            @php $rows = max(count($ind['components']), 1); @endphp
                            @if(count($ind['components']))
                                @foreach($ind['components'] as $k => $comp)
                                    <tr>
                                        @if($k === 0)
                                            <td rowspan="{{ $rows }}" class="small">{{ $ind['description'] }}<div class="text-muted" style="font-size:.7rem;">{{ $ind['weight'] }}%</div></td>
                                        @endif
                                        <td class="small">{{ $comp['name'] }}</td>
                                        <td class="text-center small">{{ $comp['weight'] }}%</td>
                                        <td class="text-center small">{{ $comp['raw'] !== null ? number_format($comp['raw'], 1) : '—' }}</td>
                                        @if($k === 0)
                                            <td rowspan="{{ $rows }}" class="text-center fw-bold">{{ $ind['total'] !== null ? number_format($ind['total'], 1) : '—' }}</td>
                                        @endif
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td class="small">{{ $ind['description'] }}<div class="text-muted" style="font-size:.7rem;">{{ $ind['weight'] }}%</div></td>
                                    <td colspan="3" class="text-center text-muted small"><em>Belum ada komponen penilaian.</em></td>
                                    <td class="text-center">—</td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="5" class="text-center text-muted small py-3"><em>Belum ada indikator.</em></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="obe-card text-center py-5 text-muted"><em>Belum ada CPMK untuk mata kuliah ini.</em></div>
    @endforelse
</x-sidebar-layout>
