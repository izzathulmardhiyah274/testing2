<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Transkrip OBE — {{ $user->identity }}</title>
    <style>
        * { font-family: "Times New Roman", Times, serif; }
        @page { margin: 28px 34px; }
        body { font-size: 10px; color: #000; margin: 0; }

        .header { width: 100%; border-bottom: 2px solid #000; padding-bottom: 6px; margin-bottom: 12px; }
        .header td { vertical-align: middle; }
        .header .logo { width: 70px; }
        .header .logo img { width: 62px; height: auto; }
        .header .title-pt { font-size: 22px; font-weight: bold; letter-spacing: .5px; }
        .header .title-fak { font-size: 13px; letter-spacing: 2px; }

        .doc-title { text-align: center; margin: 4px 0 2px; }
        .doc-title .t1 { font-size: 14px; font-weight: bold; letter-spacing: .5px; }
        .doc-title .t2 { font-size: 11px; font-weight: bold; letter-spacing: 3px; margin-top: 2px; }

        table.identity { width: 100%; margin: 12px 0 4px; }
        table.identity td { font-size: 10px; padding: 1px 2px; vertical-align: top; }
        table.identity .lbl { width: 150px; }
        table.identity .sep { width: 8px; }

        table.grades { width: 100%; border-collapse: collapse; }
        table.grades th, table.grades td { border: 1px solid #000; padding: 2px 4px; font-size: 9px; }
        table.grades th { text-align: center; font-weight: bold; }
        td.c, th.c { text-align: center; }
        .grades-wrap { width: 100%; }
        .grades-wrap > tbody > tr > td { vertical-align: top; width: 50%; }
        .grades-wrap > tbody > tr > td:first-child { padding-right: 6px; }

        .section-title { font-size: 11px; font-weight: bold; margin: 12px 0 4px; }

        table.cpl { width: 100%; border-collapse: collapse; }
        table.cpl th, table.cpl td { border: 1px solid #000; padding: 2px 5px; font-size: 9px; }
        table.cpl th { text-align: center; font-weight: bold; }
        .ok { color: #15803d; font-weight: bold; }
        .no { color: #b91c1c; font-weight: bold; }
        .note { font-size: 8px; color: #555; margin-top: 3px; }

        .skripsi { margin: 10px 0 4px; font-size: 10px; }

        .footer-wrap { width: 100%; margin-top: 6px; }
        .footer-wrap > tbody > tr > td { vertical-align: top; }

        table.scale { border-collapse: collapse; }
        table.scale th, table.scale td { border: 1px solid #000; padding: 2px 8px; font-size: 9px; }
        table.scale th { font-weight: bold; text-align: center; }
        table.scale td.c { text-align: center; }

        table.summary td { font-size: 10px; padding: 2px 2px; }
        table.summary .lbl { width: 165px; }

        .ttd { font-size: 10px; }
        .ttd .name { font-weight: bold; text-decoration: underline; padding-top: 52px; }
    </style>
</head>
<body>

    {{-- ── Kop surat ── --}}
    <table class="header">
        <tr>
            <td class="logo">
                @if(!empty($logoData))
                    <img src="{{ $logoData }}" alt="Logo">
                @endif
            </td>
            <td>
                <div class="title-pt">{{ $namaPerguruanTinggi }}</div>
                <div class="title-fak">{{ $namaFakultas }}</div>
            </td>
        </tr>
    </table>

    <div class="doc-title">
        <div class="t1">DAFTAR PRESTASI AKADEMIK MAHASISWA</div>
        <div class="t2">SEMENTARA</div>
    </div>

    {{-- ── Identitas ── --}}
    <table class="identity">
        <tr>
            <td class="lbl">Nama</td><td class="sep">:</td><td>{{ $user->name }}</td>
            <td class="lbl">Program Pendidikan Tinggi</td><td class="sep">:</td><td>{{ $programPendidikan }}</td>
        </tr>
        <tr>
            <td class="lbl">NIM</td><td class="sep">:</td><td>{{ $user->identity }}</td>
            <td class="lbl">Program Studi</td><td class="sep">:</td><td>{{ $namaProdi }}</td>
        </tr>
    </table>

    {{-- ── Tabel nilai (dua kolom berdampingan) ── --}}
    @php
        $rows = collect($transcriptRows)->values();
        $half = (int) ceil($rows->count() / 2);
        $leftRows = $rows->take($half)->values();
        $rightRows = $rows->slice($half)->values();
    @endphp
    <table class="grades-wrap">
        <tr>
            <td>
                <table class="grades">
                    <thead>
                        <tr>
                            <th class="c" style="width:24px;">No</th>
                            <th style="width:62px;">Kode Mata Kuliah</th>
                            <th>Nama Mata Kuliah</th>
                            <th class="c" style="width:34px;">Kredit</th>
                            <th class="c" style="width:34px;">Nilai</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($leftRows as $i => $row)
                            <tr>
                                <td class="c">{{ $i + 1 }}</td>
                                <td>{{ $row['course']->code }}</td>
                                <td>{{ $row['course']->name }}</td>
                                <td class="c">{{ $row['course']->sks ?? '-' }}</td>
                                <td class="c">{{ $row['any_failed'] ? 'E' : $row['final_grade'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
            <td>
                <table class="grades">
                    <thead>
                        <tr>
                            <th class="c" style="width:24px;">No</th>
                            <th style="width:62px;">Kode Mata Kuliah</th>
                            <th>Nama Mata Kuliah</th>
                            <th class="c" style="width:34px;">Kredit</th>
                            <th class="c" style="width:34px;">Nilai</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rightRows as $i => $row)
                            <tr>
                                <td class="c">{{ $half + $i + 1 }}</td>
                                <td>{{ $row['course']->code }}</td>
                                <td>{{ $row['course']->name }}</td>
                                <td class="c">{{ $row['course']->sks ?? '-' }}</td>
                                <td class="c">{{ $row['any_failed'] ? 'E' : $row['final_grade'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="c" style="color:#666;">—</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <div class="skripsi">Judul Skripsi :</div>

    {{-- ── Ketercapaian CPL (khusus transkrip OBE) ── --}}
    <div class="section-title">Ketercapaian Capaian Pembelajaran Lulusan (CPL)</div>
    <table class="cpl">
        <thead>
            <tr>
                <th class="c" style="width:50px;">Kode</th>
                <th>Pernyataan CPL</th>
                <th class="c" style="width:48px;">CPMK</th>
                <th class="c" style="width:40px;">Min.</th>
                <th class="c" style="width:52px;">Capaian</th>
                <th class="c" style="width:60px;">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($cplAchievement as $row)
                @php
                    $avg = $row['average'];
                    $min = $row['min_target'] ?? 70;
                    $lulus = $avg !== null && $avg >= $min;
                @endphp
                <tr>
                    <td class="c">{{ $row['cpl']->code }}</td>
                    <td>{{ $row['cpl']->description ?? '—' }}</td>
                    <td class="c">{{ $row['taken_count'] ?? 0 }} / {{ $row['support_count'] ?? 0 }}</td>
                    <td class="c">{{ rtrim(rtrim(number_format($min, 2, '.', ''), '0'), '.') }}%</td>
                    <td class="c">{{ $avg === null ? '—' : number_format($avg, 1).'%' }}</td>
                    <td class="c">
                        @if($avg === null)—@elseif($lulus)<span class="ok">Tercapai</span>@else<span class="no">Belum</span>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="c">Belum ada data ketercapaian CPL.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="note">Tiap CPL didistribusikan rata 100% ke seluruh CPMK pendukungnya (CPL dengan N CPMK pendukung, bobot tiap CPMK = 100/N%).</div>

    {{-- ── Keterangan + rekap ── --}}
    <table class="footer-wrap">
        <tr>
            <td style="width:54%;">
                <div style="font-size:10px; margin-bottom:3px;">Keterangan :</div>
                <table class="scale">
                    <thead>
                        <tr>
                            <th>Nilai Mutu</th>
                            <th>Angka mutu</th>
                            <th>Sebutan mutu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td class="c">A</td><td class="c">4</td><td rowspan="2" class="c">Sangat Baik</td></tr>
                        <tr><td class="c">A-</td><td class="c">3.75</td></tr>
                        <tr><td class="c">B+</td><td class="c">3.5</td><td rowspan="3" class="c">Baik</td></tr>
                        <tr><td class="c">B</td><td class="c">3</td></tr>
                        <tr><td class="c">B-</td><td class="c">2.75</td></tr>
                        <tr><td class="c">C+</td><td class="c">2.5</td><td rowspan="2" class="c">Cukup</td></tr>
                        <tr><td class="c">C</td><td class="c">2</td></tr>
                        <tr><td class="c">D</td><td class="c">1</td><td class="c">Kurang</td></tr>
                    </tbody>
                </table>
            </td>
            <td style="width:46%; vertical-align:top;">
                <table class="summary">
                    <tr><td class="lbl">Nilai Mutu Kumulatif</td><td>= {{ number_format($nilaiMutuKumulatif, 2) }}</td></tr>
                    <tr><td class="lbl">Kredit Kumulatif</td><td>= {{ $totalSks }}</td></tr>
                    <tr><td class="lbl">Indeks Prestasi Kumulatif</td><td>= {{ number_format($ipk, 2) }}</td></tr>
                    <tr><td class="lbl">Predikat Lulus</td><td>= {{ $predikat }}</td></tr>
                </table>

                <table class="ttd" style="margin-top:26px; width:100%;">
                    <tr><td>{{ $kotaTandaTangan }}, {{ $tanggalCetak }}</td></tr>
                    <tr><td>Wakil Dekan Bidang Akademis,</td></tr>
                    <tr><td class="name">{{ $wakilDekan }}</td></tr>
                    <tr><td>NIP. {{ $nipWakilDekan }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

</body>
</html>
