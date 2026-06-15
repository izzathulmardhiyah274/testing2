<x-sidebar-layout :title="'Bobot CPMK — ' . $cpl->code" :header="'Laporan Ketercapaian CPL'">

    @include('kaprodi.laporan._subnav')

    <a href="{{ route('kaprodi.laporan.index') }}" class="btn btn-sm btn-obe-outline mb-3">&larr; Kembali ke daftar CPL</a>

    @if(session('success'))
        <div class="alert alert-success py-2 small">{{ session('success') }}</div>
    @endif

    <div class="obe-card mb-3">
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge" style="background:var(--obe-red); color:#fff;">{{ $cpl->code }}</span>
            <span class="text-muted small">Min. ketercapaian: {{ rtrim(rtrim(number_format((float) $cpl->min_target, 2), '0'), '.') }}%</span>
        </div>
        <p class="mb-0 small">{{ $cpl->description }}</p>
    </div>

    <div class="obe-card p-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <div>
                <h2 class="obe-card__title mb-1">CPMK Pendukung CPL Ini</h2>
                <small class="text-muted">Bobot menentukan kontribusi tiap CPMK terhadap ketercapaian CPL. Kosongkan untuk dibagi rata otomatis.</small>
            </div>
            <span class="badge" id="weightTotalBadge" style="background:#fee2e2; color:#b91c1c;">Total: 0%</span>
        </div>

        @if($cpmks->isEmpty())
            <div class="text-center text-muted py-5"><em>Belum ada CPMK yang menunjuk CPL ini.</em></div>
        @else
            <form method="POST" action="{{ route('kaprodi.laporan.cpl.weights', $cpl) }}" id="weightForm">
                @csrf
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:110px;">CPMK</th>
                                <th>Mata Kuliah</th>
                                <th>Pernyataan CPMK</th>
                                <th class="text-center" style="width:160px;">Bobot ke CPL (%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cpmks as $cpmk)
                                <tr>
                                    <td class="fw-semibold">{{ $cpmk->code }}</td>
                                    <td class="small">
                                        @if($cpmk->course)
                                            <div class="fw-semibold">{{ $cpmk->course->name }}</div>
                                            <div class="text-muted" style="font-size:.72rem;">{{ $cpmk->course->code }} · {{ $cpmk->course->sks }} SKS</div>
                                        @else
                                            <span class="text-muted fst-italic">—</span>
                                        @endif
                                    </td>
                                    <td class="small">{{ \Illuminate\Support\Str::limit($cpmk->description, 140) }}</td>
                                    <td class="text-center">
                                        <input type="number" step="0.01" min="0" max="100"
                                               name="weights[{{ $cpmk->id }}]"
                                               class="form-control form-control-sm text-center weight-input"
                                               value="{{ $cpmk->cpl_weight !== null ? rtrim(rtrim(number_format((float) $cpmk->cpl_weight, 2), '0'), '.') : '' }}"
                                               placeholder="auto: {{ number_format($effectiveWeights[$cpmk->id] ?? 0, 2) }}">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3">
                    <small class="text-muted">Total bobot manual tidak boleh melebihi 100%. Baris kosong otomatis berbagi sisa secara merata.</small>
                    <button type="submit" class="btn btn-obe-red">Simpan Bobot</button>
                </div>
            </form>
        @endif
    </div>

    <script>
        (function () {
            const inputs = Array.from(document.querySelectorAll('.weight-input'));
            const badge  = document.getElementById('weightTotalBadge');
            if (!badge) return;

            function recalc() {
                const total = inputs.reduce((s, i) => s + (i.value === '' ? 0 : Number(i.value) || 0), 0);
                const filled = inputs.filter(i => i.value !== '').length;
                badge.textContent = 'Total manual: ' + total.toFixed(2) + '%' + (filled < inputs.length ? ' (+ ' + (inputs.length - filled) + ' otomatis)' : '');
                const ok = total <= 100.01;
                badge.style.background = ok ? '#dcfce7' : '#fee2e2';
                badge.style.color = ok ? '#166534' : '#b91c1c';
            }

            inputs.forEach(i => i.addEventListener('input', recalc));
            recalc();

            document.getElementById('weightForm')?.addEventListener('submit', function (e) {
                const total = inputs.reduce((s, i) => s + (i.value === '' ? 0 : Number(i.value) || 0), 0);
                if (total > 100.01) {
                    e.preventDefault();
                    alert('Total bobot manual melebihi 100% (saat ini ' + total.toFixed(2) + '%). Kurangi dulu.');
                }
            });
        })();
    </script>

</x-sidebar-layout>
