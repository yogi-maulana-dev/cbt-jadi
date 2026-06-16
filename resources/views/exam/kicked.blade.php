<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
</head>
<body style="margin:0;font-family:figtree,system-ui,sans-serif;background:#111827;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem;">
    <div style="width:100%;max-width:30rem;background:#fff;border-radius:1rem;padding:2.25rem;text-align:center;box-shadow:0 25px 60px rgba(0,0,0,.4);">
        <div style="margin:0 auto 1.25rem;width:4.5rem;height:4.5rem;border-radius:9999px;background:#fef3c7;display:flex;align-items:center;justify-content:center;font-size:2.25rem;">⚠️</div>
        <h1 style="margin:0 0 .65rem;font-size:1.35rem;font-weight:600;color:#111827;">{{ $title }}</h1>
        <p style="margin:0 0 1.75rem;font-size:.97rem;line-height:1.65;color:#4b5563;white-space:pre-line;">{{ $message }}</p>
        <a href="{{ route('exam.index') }}"
           style="display:inline-block;width:100%;box-sizing:border-box;padding:.75rem 1rem;border-radius:.6rem;background:#d97706;color:#fff;font-weight:600;text-decoration:none;">
            Kembali ke Daftar Ujian
        </a>
    </div>
</body>
</html>
