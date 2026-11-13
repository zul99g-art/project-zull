<?php

namespace App\Http\Controllers;

class AboutController extends Controller
{
    public function index()
    {
        $mahasiswa = [
            'nama' => 'Nuzul',
            'status' => 'Mahasiswa Aktif',
            'nim' => '251011700332',
            'email' => 'zul99g@gmail.com',
            'prodi' => 'Sistem Informasi',
        ];

        return view('mahasiswa', compact('mahasiswa'));
    }
}