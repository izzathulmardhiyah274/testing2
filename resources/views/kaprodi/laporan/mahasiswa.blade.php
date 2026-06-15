<x-sidebar-layout :title="'Laporan Mahasiswa'" :header="'Laporan Mahasiswa (Ketercapaian CPL)'">

    @include('kaprodi.laporan._subnav')

    <div class="obe-card mb-3">
        <form method="GET" action="{{ route('kaprodi.laporan.mahasiswa') }}" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-uppercase" style="font-size:.7rem;">Angkatan</label>
                <select name="angkatan" onchange="this.form.submit()" class="form-select form-select-sm">
                    <option value="">Semua Angkatan</option>
                    @foreach($angkatanList as $a)
                        <option value="{{ $a }}" {{ (string) $filterAngkatan === (string) $a ? 'selected' : '' }}>{{ $a }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <a href="{{ route('kaprodi.laporan.mahasiswa') }}" class="btn btn-obe-outline btn-sm w-100">Reset Filter</a>
            </div>
        </form>
    </div>

    <div class="obe-card p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0" style="font-size:.78rem;">
                <thead style="background:var(--obe-ink); color:#fff;">
                    <tr>
                        <th class="text-center" style="background:var(--obe-ink); color:#fff; width:40px;">No</th>
                        <th style="background:var(--obe-ink); color:#fff;">NIM</th>
                        <th style="background:var(--obe-ink); color:#fff;">Nama</th>
                        @foreach($cpls as $cpl)
                            <th class="text-center" style="background:var(--obe-ink); color:#fff; min-width:60px;" title="{{ $cpl->description }}">{{ $cpl->code }}</th>
                        @endforeach
                        <th class="text-center" style="background:var(--obe-ink); color:#fff; width:80px;">Detail</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($allStudents as $i => $student)
                        <tr>
                            <td class="text-center text-muted small">{{ $i + 1 }}</td>
                            <td class="small" style="font-family:monospace;">{{ $student->profilMahasiswa->nim ?? $student->identity }}</td>
                            <td class="fw-semibold small">{{ $student->name }}</td>
                            @foreach($cpls as $cpl)
                                @php $val = $studentCplMap[$student->id][$cpl->id] ?? null; @endphp
                                <td class="text-center fw-bold"
                                    style="color:{{ $val === null ? '#9ca3af' : ($val >= ($cpl->min_target ?? 70) ? '#16a34a' : 'var(--obe-red)') }};">
                                    {{ $val === null ? '—' : number_format($val, 1) }}
                                </td>
                            @endforeach
                            <td class="text-center">
                                <a href="{{ route('kaprodi.laporan.mahasiswa.show', $student) }}" class="btn btn-sm btn-obe-red">Lihat</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ 4 + $cpls->count() }}" class="text-center text-muted py-5"><em>Belum ada mahasiswa pada filter ini.</em></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-sidebar-layout>
