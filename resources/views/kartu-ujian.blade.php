@php
    use App\Models\Setting;
    use Illuminate\Support\Facades\Storage;
    $namaSekolah = Setting::namaSekolah();
    $tahun = Setting::tahunPelajaran();
    $kepsek = Setting::kepalaSekolah();
    $judul = Setting::judulKartu();
    $logo = Setting::logoSekolah() ? Storage::disk('public')->url(Setting::logoSekolah()) : null;
    $logoBawah = Setting::logoBawah() ? Storage::disk('public')->url(Setting::logoBawah()) : null;
    $ttd = Setting::ttdGambar() ? Storage::disk('public')->url(Setting::ttdGambar()) : null;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kartu Ujian</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 16px; font-family: Arial, Helvetica, sans-serif; color: #111; background: #f3f4f6; }
        .toolbar { text-align: center; margin-bottom: 16px; }
        .toolbar button { padding: 8px 16px; border: 0; border-radius: 6px; background: #4f46e5; color: #fff; font-weight: 600; cursor: pointer; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .card { border: 2px solid #111; border-radius: 6px; padding: 12px 14px; background: #fff; page-break-inside: avoid; }
        .head { display: flex; align-items: center; gap: 10px; border-bottom: 2px solid #111; padding-bottom: 8px; }
        .head img { width: 54px; height: 54px; object-fit: contain; }
        .head .sek { text-align: center; flex: 1; }
        .head .sek .nm { font-size: 15px; font-weight: 800; letter-spacing: .5px; }
        .head .sek .tp { font-size: 11px; }
        .title { font-weight: 800; font-size: 14px; margin: 10px 0 8px; text-transform: uppercase; }
        .fields { font-size: 13px; padding-left: 8px; }
        .fields table { border-collapse: collapse; }
        .fields td { padding: 2px 0; vertical-align: top; }
        .fields td.lbl { width: 130px; }
        .ttd { margin-top: 6px; font-size: 12px; text-align: right; }
        .ttd .role { font-weight: 700; }
        .ttd img.sign { height: 56px; object-fit: contain; display: inline-block; margin: 2px 0; }
        .ttd .nm { margin-top: 4px; font-weight: 700; }
        .ttd .sp { height: 40px; }
        .foot { display: flex; align-items: flex-end; justify-content: space-between; margin-top: 4px; }
        .footlogo img { height: 34px; object-fit: contain; }
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none; }
            .grid { gap: 8px; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">🖨️ Cetak</button>
    </div>

    @if ($siswa->isEmpty())
        <p style="text-align:center">Tidak ada data siswa untuk dicetak.</p>
    @endif

    <div class="grid">
        @foreach ($siswa as $s)
            <div class="card">
                <div class="head">
                    @if ($logo)
                        <img src="{{ $logo }}" alt="Logo">
                    @endif
                    <div class="sek">
                        <div class="nm">{{ $namaSekolah }}</div>
                        <div class="tp">UJIAN SEKOLAH TP. {{ $tahun }}</div>
                    </div>
                    @if ($logo)
                        <span style="width:54px"></span>
                    @endif
                </div>

                <div class="title">{{ $judul }}</div>

                <div class="fields">
                    <table>
                        <tr><td class="lbl">NO UJIAN</td><td>: {{ $s->no_ujian ?? '-' }}</td></tr>
                        <tr><td class="lbl">NAMA</td><td>: {{ $s->name }}</td></tr>
                        <tr><td class="lbl">KELAS</td><td>: {{ $s->kelas ?? '-' }}</td></tr>
                        <tr><td class="lbl">PROGRAM STUDI</td><td>: {{ $s->program_studi ?? '-' }}</td></tr>
                    </table>
                </div>

                <div class="foot">
                    <div class="footlogo">
                        @if ($logoBawah)
                            <img src="{{ $logoBawah }}" alt="">
                        @endif
                    </div>
                    <div class="ttd">
                        <div class="role">Kepala Sekolah</div>
                        @if ($ttd)
                            <img class="sign" src="{{ $ttd }}" alt="Tanda tangan">
                        @else
                            <div class="sp"></div>
                        @endif
                        <div class="nm">{{ $kepsek ?: '________________' }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <script>
        window.addEventListener('load', () => setTimeout(() => window.print(), 400));
    </script>
</body>
</html>
