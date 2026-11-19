<?php

namespace App\Services\SelfOrder;

use Illuminate\Support\Carbon;

/**
 * Membuat dan memverifikasi link QR meja yang bertanda tangan (HMAC) dan
 * memiliki masa berlaku, sehingga foto QR tidak bisa dipakai selamanya.
 */
class TableQrSigner
{
    public function url(string $code, ?int $expiresAt = null): string
    {
        $expiresAt ??= $this->defaultExpiryTimestamp();

        return rtrim((string) config('app.url'), '/')
            .'/t/'.$code
            .'?e='.$expiresAt
            .'&s='.$this->signature($code, $expiresAt);
    }

    public function defaultExpiryTimestamp(): int
    {
        $days = max(1, (int) config('self_order.qr_ttl_days', 365));

        return Carbon::now()->addDays($days)->timestamp;
    }

    public function signature(string $code, int $expiresAt): string
    {
        return hash_hmac('sha256', $code.'|'.$expiresAt, (string) config('app.key'));
    }

    public function isValid(string $code, mixed $expires, mixed $signature): bool
    {
        $expires = is_string($expires) || is_int($expires) ? (string) $expires : '';
        $signature = is_string($signature) ? $signature : '';

        if ($expires === '' || ! ctype_digit($expires) || $signature === '') {
            return false;
        }

        $expiresAt = (int) $expires;
        if ($expiresAt <= Carbon::now()->timestamp) {
            return false;
        }

        return hash_equals($this->signature($code, $expiresAt), $signature);
    }

    /**
     * Tanda tangan asli tetapi sudah lewat masa berlaku.
     */
    public function isExpiredButAuthentic(string $code, mixed $expires, mixed $signature): bool
    {
        $expires = is_string($expires) || is_int($expires) ? (string) $expires : '';
        $signature = is_string($signature) ? $signature : '';

        if ($expires === '' || ! ctype_digit($expires) || $signature === '') {
            return false;
        }

        $expiresAt = (int) $expires;
        if ($expiresAt > Carbon::now()->timestamp) {
            return false;
        }

        return hash_equals($this->signature($code, $expiresAt), $signature);
    }

    public function isLegacyAllowed(): bool
    {
        return (bool) config('self_order.allow_legacy_unsigned_qr', false);
    }
}
