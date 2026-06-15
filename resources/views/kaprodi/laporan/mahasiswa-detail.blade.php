<x-sidebar-layout :title="'Detail Mahasiswa'" :header="'Detail Mahasiswa: ' . $student->name">

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <p class="text-muted small mb-0">
            <span class="fw-semibold" style="color:var(--obe-ink);">{{ $student->name }}</span>
            — <span style="font-family:monospace;">{{ $student->profilMahasiswa->nim ?? $student->identity }}</span>
        </p>
        <a href="{{ route('kaprodi.laporan.mahasiswa') }}" class="btn btn-obe-outline btn-sm">&larr; Kembali</a>
    </div>

    {{-- Daftar kelas yang diikuti --}}
    <div class="obe-card p-0 overflow-hidden mb-3">
        <div class="px-3 py-3 border-bottom" style="background:var(--obe-bg);">
            <h2 class="obe-card__title mb-0">Nilai per Kelas</h2>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:.82rem;">
                <thead style="background:#f8fafc;">
                    <tr style="color:#64748b; font-weight:600; font-size:.74rem; text-transform:uppercase;">
                        <th class="text-center" style="width:40px;">No</th>
                        <th>Mata Kuliah</th>
                        <th class="text-center" style="width:80px;">Nilai</th>
                        <th class="text-center" style="width:70px;">Mutu</th>
                        <th class="text-center" style="width:70px;">Grade</th>
                        <th class="text-center" style="width:90px;">Detail</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($classrooms as $i => $classroom)
                        @php $res = $classroomResults[$classroom->id] ?? null; @endphp
                        <tr>
                            <td class="text-center text-muted small">{{ $i + 1 }}</td>
                            <td class="small fw-semibold">
                                {{ $classroom->course->name ?? '—' }}
                                <div class="text-muted" style="font-size:.7rem;">{{ $classroom->course->code ?? '' }} · {{ $classroom->name }}</div>
                            </td>
                            <td class="text-center">{{ $res ? ($res['anyFailed'] ? '0' : number_format($res['finalScore'] ?? 0, 1)) : '—' }}</td>
                            <td class="text-center fw-bold">{{ $res ? number_format($res['finalMutu'], 2) : '—' }}</td>
                            <td class="text-center fw-bold">{{ $res['finalGrade'] ?? '—' }}</td>
                            <td class="text-center">
                                <a href="{{ route('kaprodi.laporan.mahasiswa.kelas', [$student, $classroom]) }}" class="btn btn-sm btn-obe-outline">Rincian</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4"><em>Mahasiswa belum mengikuti kelas apa pun.</em></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Ringkasan CPL --}}
    <div class="obe-card p-0 overflow-hidden">
        <div class="px-3 py-3 border-bottom" style="background:var(--obe-bg);">
            <h2 class="obe-card__title mb-0">Ringkasan Ketercapaian CPL</h2>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0" style="font-size:.82rem;">
                <thead style="background:var(--obe-ink); color:#fff;">
                    <tr>
                        <th class="text-center" style="background:var(--obe-ink); color:#fff; width:110px;">Kode CPL</th>
                        <th style="background:var(--obe-ink); color:#fff;">Pernyataan</th>
                        <th class="text-center" style="background:var(--obe-ink); color:#fff; width:110px;">Min.</th>
                        <th class="text-center" style="background:var(--obe-ink); color:#fff; width:130px;">Ketercapaian</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cpls as $cpl)
                        @php $val = $cplSummary[$cpl->id] ?? null; $min = $cpl->min_target ?? 70; @endphp
                        <tr>
                            <td class="text-center"><span class="badge bg-light text-dark border" style="font-family:monospace;">{{ $cpl->code }}</span></td>
                            <td class="small">{{ $cpl->description }}</td>
                            <td class="text-center small">{{ rtrim(rtrim(number_format($min, 2, '.', ''), '0'), '.') }}%</td>
                            <td class="text-center fw-bold" style="color:{{ $val === null ? '#6b7280' : ($val >= $min ? '#16a34a' : 'var(--obe-red)') }};">
                                {{ $val === null ? '—' : number_format($val, 1) . '%' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-sidebar-layout>
