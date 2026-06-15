@php $route = request()->route()?->getName() ?? ''; @endphp
<div class="d-flex flex-wrap gap-2 mb-3 border-bottom pb-2">
    <a href="{{ route('kaprodi.laporan.index') }}"
       class="btn btn-sm {{ (str_starts_with($route, 'kaprodi.laporan.index') || str_starts_with($route, 'kaprodi.laporan.cpl')) ? 'btn-obe-red' : 'btn-obe-outline' }}">
        Laporan Ketercapaian CPL
    </a>
    <a href="{{ route('kaprodi.laporan.mahasiswa') }}"
       class="btn btn-sm {{ str_contains($route, 'laporan.mahasiswa') ? 'btn-obe-red' : 'btn-obe-outline' }}">
        Laporan Mahasiswa (CPL)
    </a>
</div>
