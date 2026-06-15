<x-sidebar-layout :title="'Riwayat Kelas'" :header="'Riwayat Kelas'">

    <div class="obe-card">
        <h2 class="obe-card__title mb-1">Kelas Semester Lalu</h2>
        <p class="text-muted small mb-3">Kelas yang sudah diarsipkan dari semester sebelumnya.</p>

        @forelse($classrooms as $c)
            <div class="border rounded p-3 mb-2" style="--bs-border-color:var(--obe-line);">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="flex-grow-1">
                        <h4 class="h6 fw-bold mb-1">{{ $c->course->name ?? '—' }}</h4>
                        <p class="text-muted small mb-1">{{ $c->course->code ?? '' }} — {{ $c->name }}</p>
                        <div class="d-flex flex-wrap gap-3 small text-muted">
                            <span><strong>SKS:</strong> {{ $c->course->sks ?? '-' }}</span>
                            <span><strong>Semester:</strong> {{ $c->semester }}</span>
                            <span><strong>{{ ucfirst($c->period_type) }}</strong> {{ $c->academic_year }}</span>
                        </div>
                    </div>
                    <div class="d-flex flex-column align-items-end gap-2 flex-shrink-0">
                        <span class="badge bg-light text-muted border">Arsip</span>
                        <a href="{{ route('mahasiswa.classrooms.show', $c) }}" class="btn btn-obe-red btn-sm">
                            Lihat Detail
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-4 text-muted">
                <p class="mb-1 fst-italic">Belum ada riwayat kelas.</p>
                <small>Kelas yang diarsipkan akan muncul di sini.</small>
            </div>
        @endforelse
    </div>
</x-sidebar-layout>
