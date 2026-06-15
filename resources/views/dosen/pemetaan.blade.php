<x-sidebar-layout :title="'Pemetaan CPL'" :header="'Pemetaan CPL ke Mata Kuliah'">

    <p class="text-muted small mb-3">
        Matriks Capaian Pembelajaran Lulusan (CPL) terhadap mata kuliah yang Anda ampu, berdasarkan CPMK yang terdaftar.
    </p>

    @if($courses->isEmpty())
        <div class="obe-card text-center py-5 text-muted">
            <em>Belum ada mata kuliah yang ditugaskan kepada Anda.</em>
        </div>
    @else
        <div class="obe-card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0" style="font-size:.8rem;">
                    <thead style="background:var(--obe-ink); color:#fff;">
                        <tr>
                            <th style="background:var(--obe-ink); color:#fff;">Kode</th>
                            <th style="background:var(--obe-ink); color:#fff;">Mata Kuliah</th>
                            @foreach($cpls as $cpl)
                                <th class="text-center" style="background:var(--obe-ink); color:#fff; min-width:56px;"
                                    title="{{ $cpl->description }}">{{ $cpl->code }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($courses as $course)
                            @php $set = $course->cpmks->pluck('cpl_id')->filter()->unique()->flip(); @endphp
                            <tr>
                                <td class="small" style="font-family:monospace;">{{ $course->code }}</td>
                                <td class="small fw-semibold">
                                    {{ $course->name }}
                                    <div class="text-muted" style="font-size:.7rem;">Sem {{ $course->semester }} · {{ $course->sks }} SKS</div>
                                </td>
                                @foreach($cpls as $cpl)
                                    <td class="text-center">
                                        @if($set->has($cpl->id))
                                            <span style="color:#16a34a; font-weight:700;">✓</span>
                                        @else
                                            <span class="text-muted">·</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-sidebar-layout>
