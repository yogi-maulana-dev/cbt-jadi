<?php

namespace App\Filament\Resources\SiswaResource\Pages;

use App\Filament\Resources\SiswaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSiswa extends CreateRecord
{
    protected static string $resource = SiswaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['role'] = 'siswa';

        // Email otomatis bila kosong; siswa mengisi email aktif saat login pertama.
        if (empty($data['email'])) {
            $data['email'] = ($data['no_ujian'] ?? 'siswa').'@ujian.local';
        }

        // Password awal = No Ujian bila kosong; wajib diganti saat login pertama.
        if (empty($data['password'])) {
            $data['password'] = $data['no_ujian'] ?? 'siswa';
        }
        $data['must_change_password'] = true;

        return $data;
    }
}
