<?php

namespace App\Http\Controllers;

use App\Models\Perangkat;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    private const KATEGORI = ['Hardware', 'Software', 'Periferal'];

    // Lebar maksimal gambar di PDF (px). Gambar diperkecil supaya PDF ringan.
    private const LEBAR_FOTO = 160;

    public function index()
    {
        $perangkat = Perangkat::orderBy('id')->get();

        return view('admin.admin', compact('perangkat'));
    }

    /**
     * Export PDF -> dibuka di browser (preview), TIDAK langsung download.
     */
    public function exportPdf()
    {
        set_time_limit(120);
        ini_set('memory_limit', '256M');

        $perangkat = Perangkat::orderBy('id')->get();

        return Pdf::loadView('admin.pdf', [
                'perangkat' => $perangkat,
                'fotos' => $this->ambilFoto($perangkat),
            ])
            ->setPaper('a4', 'landscape')
            ->stream('data-projects.pdf');
    }

    /**
     * Unduh semua gambar (dari link) lalu ubah jadi base64
     * supaya bisa ditanam di PDF tanpa mengaktifkan remote di dompdf.
     * Hasil: [id => 'data:image/jpeg;base64,...' atau null kalau gagal].
     */
    private function ambilFoto($perangkat): array
    {
        // Semua id diisi null dulu, jadi di view aman dipanggil $fotos[$id]
        $hasil = array_fill_keys($perangkat->pluck('id')->all(), null);

        $daftar = $perangkat->filter(fn ($p) => !empty($p->gambar));
        if ($daftar->isEmpty()) {
            return $hasil;
        }

        $respons = Http::pool(function (Pool $pool) use ($daftar) {
            foreach ($daftar as $p) {
                $pool->as((string) $p->id)
                    ->withoutVerifying() // atasi error SSL di Laragon (hapus kalau sudah di server)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36',
                        'Accept' => 'image/*,*/*;q=0.8',
                    ])
                    ->timeout(15)
                    ->get($p->gambar);
            }
        });

        foreach ($daftar as $p) {
            $r = $respons[(string) $p->id] ?? null;

            // Kalau koneksi gagal, hasilnya bisa berupa exception, bukan Response
            if (!$r instanceof Response || !$r->successful()) {
                continue;
            }

            $hasil[$p->id] = $this->gambarKeBase64($r->body());
        }

        return $hasil;
    }

    /**
     * Ubah isi gambar jadi data URI base64.
     * Kalau GD aktif: gambar diperkecil dan dijadikan JPEG (WebP ikut bisa tampil).
     * Kalau GD tidak aktif: pakai gambar asli (WebP tidak didukung dompdf).
     */
    private function gambarKeBase64(string $isi): ?string
    {
        $info = @getimagesizefromstring($isi);
        if (!$info) {
            return null;
        }

        $mime = $info['mime'];
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            return null;
        }

        if (function_exists('imagecreatefromstring')) {
            $asli = @imagecreatefromstring($isi);

            if ($asli) {
                $w = imagesx($asli);
                $h = imagesy($asli);
                $lebar = min($w, self::LEBAR_FOTO);
                $tinggi = (int) round($h * $lebar / $w);

                $baru = imagecreatetruecolor($lebar, $tinggi);
                imagefill($baru, 0, 0, imagecolorallocate($baru, 255, 255, 255)); // latar putih untuk PNG transparan
                imagecopyresampled($baru, $asli, 0, 0, 0, 0, $lebar, $tinggi, $w, $h);

                ob_start();
                imagejpeg($baru, null, 80);
                $jpeg = ob_get_clean();

                imagedestroy($asli);
                imagedestroy($baru);

                return 'data:image/jpeg;base64,' . base64_encode($jpeg);
            }
        }

        // Tanpa GD: WebP tidak bisa dipakai dompdf
        if ($mime === 'image/webp') {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($isi);
    }

    public function create()
    {
        return view('admin.form', [
            'perangkat' => new Perangkat(),
            'kategoriList' => self::KATEGORI,
        ]);
    }

    public function store(Request $request)
    {
        Perangkat::create($this->validasi($request));

        return redirect()->route('admin.index')->with('sukses', 'Data berhasil ditambahkan.');
    }

    public function edit(Perangkat $perangkat)
    {
        return view('admin.form', [
            'perangkat' => $perangkat,
            'kategoriList' => self::KATEGORI,
        ]);
    }

    public function update(Request $request, Perangkat $perangkat)
    {
        $perangkat->update($this->validasi($request));

        return redirect()->route('admin.index')->with('sukses', 'Data berhasil diubah.');
    }

    public function destroy(Perangkat $perangkat)
    {
        $nama = $perangkat->nama;
        $perangkat->delete();

        return redirect()->route('admin.index')->with('sukses', "Data \"{$nama}\" berhasil dihapus.");
    }

    private function validasi(Request $request): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'kategori' => ['required', 'in:' . implode(',', self::KATEGORI)],
            'deskripsi' => ['required', 'string'],
            'spesifikasi' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:255'],
            'gambar' => ['nullable', 'url', 'max:500'],
        ], [
            'required' => ':attribute wajib diisi.',
            'max' => ':attribute maksimal :max karakter.',
            'url' => ':attribute harus berupa link (diawali http:// atau https://).',
            'in' => ':attribute tidak valid.',
        ], [
            'nama' => 'Nama perangkat',
            'kategori' => 'Kategori',
            'deskripsi' => 'Deskripsi',
            'spesifikasi' => 'Spesifikasi',
            'status' => 'Status',
            'gambar' => 'Link gambar',
        ]);
    }
}