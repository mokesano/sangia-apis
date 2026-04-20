<?php
declare(strict_types=1);

namespace Sangia\Gateway;

class AuthKeyValidator
{
    /**
     * Memvalidasi API Key yang dikirim pengguna.
     * Di versi produksi, fungsi ini bisa menembak ke database atau API Internal Developers Sangia.
     */
    public static function isValid(?string $apiKey): bool
    {
        if (empty($apiKey)) {
            return false;
        }

        // Contoh validasi dasar: panjang karakter API key minimal 16 karakter
        // Anda bisa menggantinya dengan logika validasi JWT atau Database Query
        if (strlen($apiKey) < 16) {
            return false;
        }

        // Jika lolos semua pengecekan
        return true;
    }

    /**
     * Memotong eksekusi dan mengembalikan pesan error 401 Unauthorized
     */
    public static function reject(): void
    {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error',
            'code' => 401,
            'message' => 'Unauthorized: API Key tidak valid atau tidak ditemukan.',
            'action_required' => 'Dapatkan API Key di https://developers.sangia.org'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit; // Hentikan PHP sepenuhnya, core mesin tidak akan disentuh!
    }
}