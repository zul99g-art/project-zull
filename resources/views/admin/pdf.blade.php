<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Data Projects</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #212529; }
        h2 { margin: 0 0 4px; }
        p { margin: 0 0 12px; color: #6c757d; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #adb5bd; padding: 6px 8px; text-align: left; vertical-align: middle; }
        img.foto { width: 60px; height: 45px; }
        th { background: #e9ecef; }
    </style>
</head>
<body>
    <h2>Data Projects (VERSI GAMBAR)</h2>
    <p>Dicetak pada {{ now()->format('d/m/Y H:i') }}</p>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Gambar</th>
                <th>Judul</th>
                <th>Kategori</th>
                <th>Deskripsi</th>
                <th>Teknologi</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($perangkat as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td style="text-align:center">
                        @if (!empty($fotos[$item->id]))
                            <img class="foto" src="{{ $fotos[$item->id] }}" alt="">
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $item->nama }}</td>
                    <td>{{ $item->kategori }}</td>
                    <td>{{ $item->deskripsi }}</td>
                    <td>{{ $item->spesifikasi }}</td>
                    <td>{{ $item->status }}</td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center">Belum ada data.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>