<x-sidebar-layout :title="'Laporan Nilai'" :header="'Laporan Nilai'">

    @include('kaprodi.laporan._subnav')

    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mb-3 gap-2">
        <p class="text-muted small mb-0">
            Pilih CPL untuk melihat CPMK pendukungnya dan mengatur
            <span class="fw-semibold" style="color:var(--obe-red);">bobot kontribusi tiap CPMK terhadap CPL</span>.
        </p>
    </div>

    @if(session('success'))
        <div class="alert alert-success py-2 small">{{ session('success') }}</div>
    @endif

    <div class="obe-card p-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 obe-dt"
                   data-no-sort="0,3"
                   data-page-length="50">
                <thead>
                    <tr>
                        <th class="text-center" style="width:90px;">Kode</th>
                        <th>Pernyataan CPL</th>
                        <th class="text-center" style="width:110px;">Jumlah CPMK</th>
                        <th class="text-center" style="width:100px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cpls as $cpl)
                        <tr>
                            <td class="text-center fw-bold">{{ $cpl->code }}</td>
                            <td class="small">{{ \Illuminate\Support\Str::limit($cpl->description, 180) }}</td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border">{{ $cpl->cpmks_count }} CPMK</span>
                            </td>
                            <td class="text-center">
                                <a href="{{ route('kaprodi.laporan.cpl.show', $cpl) }}" class="btn btn-sm btn-obe-red" title="Lihat & atur bobot">Lihat</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-5"><em>Belum ada CPL.</em></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</x-sidebar-layout>
