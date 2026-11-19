<?php

namespace App\Livewire\SelfOrder\Pages;

use Livewire\Attributes\Layout;
use Livewire\Component;

class InvalidPage extends Component
{
    public string $reason = 'qr_invalid';

    public function mount(): void
    {
        $reason = (string) request()->query('reason', 'qr_invalid');
        $this->reason = in_array($reason, ['qr_invalid', 'qr_expired', 'session_expired'], true)
            ? $reason
            : 'qr_invalid';
    }

    #[Layout('layouts.self-order')]
    public function render()
    {
        $minutes = (int) config('self_order.table_session_minutes', 60);

        return view('livewire.self-order.invalid', [
            'title' => match ($this->reason) {
                'qr_expired' => 'QR meja sudah kedaluwarsa',
                'session_expired' => 'Sesi meja berakhir',
                default => 'QR Code tidak valid',
            },
            'message' => match ($this->reason) {
                'qr_expired' => 'QR ini sudah lewat masa berlaku. Silakan scan QR terbaru yang tersedia di meja Anda, atau minta bantuan kasir.',
                'session_expired' => $minutes > 0
                    ? 'Sesi pemesanan Anda sudah melewati batas '.$minutes.' menit. Scan ulang QR di meja untuk melanjutkan pesanan.'
                    : 'Sesi pemesanan Anda sudah berakhir. Scan ulang QR di meja untuk melanjutkan pesanan.',
                default => 'Untuk mulai self-order, silakan scan QR meja yang tersedia di meja Anda.',
            },
            'tips' => match ($this->reason) {
                'qr_expired' => [
                    'Scan QR terbaru yang ditempel di meja.',
                    'QR lama tidak bisa dipakai lagi.',
                    'Jika belum ada QR baru, minta bantuan kasir.',
                ],
                'session_expired' => [
                    'Scan ulang QR meja untuk memulai sesi baru.',
                    'Pesanan yang belum dibayar akan dibatalkan otomatis.',
                    'Hubungi kasir bila pesanan Anda sudah terlanjur dibuat.',
                ],
                default => [
                    'Pastikan QR yang dipindai adalah QR meja.',
                    'Coba scan ulang dengan pencahayaan cukup.',
                    'Jika masih gagal, minta bantuan kasir untuk QR meja yang benar.',
                ],
            },
        ]);
    }
}
