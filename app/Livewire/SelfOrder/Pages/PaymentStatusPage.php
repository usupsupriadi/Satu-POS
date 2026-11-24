<?php

namespace App\Livewire\SelfOrder\Pages;

use App\Models\Transaction;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Component;

class PaymentStatusPage extends Component
{
    public Transaction $transaction;

    public string $status = 'pending';

    /**
     * Halaman ini bisa dibuka hanya dengan kode transaksi (mis. redirect dari
     * Midtrans). Detail pesanan dan tautan struk hanya ditampilkan bila
     * permintaan datang dari perangkat pemesan (token/sesi) atau staf.
     */
    public bool $detailsVisible = false;

    public function getListeners(): array
    {
        if (! isset($this->transaction) || ! $this->detailsVisible) {
            return [];
        }

        $token = (string) ($this->transaction->self_order_token ?? '');

        if ($token === '') {
            return [];
        }

        return [
            "echo:self-order.{$token},.payment.updated" => 'handlePaymentUpdated',
        ];
    }

    public function mount(?string $code = null): void
    {
        $orderId = trim((string) request()->query('order_id', ''));

        $query = Transaction::query()
            ->where('channel', 'self_order')
            ->with(['transactionItems.product', 'transactionItems.variant', 'diningTable']);

        if ($orderId !== '') {
            $query->where('external_id', $orderId);
        } elseif ($code !== null && trim($code) !== '') {
            $query->where('code', trim($code));
        } else {
            abort(404);
        }

        $this->transaction = $query->firstOrFail();
        $this->detailsVisible = $this->canViewDetails();

        $this->status = (string) $this->transaction->payment_status;
        $this->syncSessionWithStatus();
    }

    private function canViewDetails(): bool
    {
        $trxToken = trim((string) ($this->transaction->self_order_token ?? ''));
        $requestToken = trim((string) request()->query('token', ''));
        $sessionToken = trim((string) session('self_order_token', ''));

        if ($trxToken !== '' && ($requestToken === $trxToken || $sessionToken === $trxToken)) {
            return true;
        }

        $sessionExternalId = trim((string) session('external_id', ''));
        if ($sessionExternalId !== '' && hash_equals($sessionExternalId, (string) $this->transaction->external_id)) {
            return true;
        }

        $user = auth()->user();

        return (bool) ($user && $user->can('transactions.details'));
    }

    public function handlePaymentUpdated(array $payload = []): void
    {
        $code = (string) ($payload['code'] ?? '');
        if ($code !== '' && $code !== (string) $this->transaction->code) {
            return;
        }

        $this->refreshFromDb();
    }

    private function refreshFromDb(): void
    {
        $this->transaction->refresh();
        $this->status = (string) $this->transaction->payment_status;
        $this->syncSessionWithStatus();
    }

    private function syncSessionWithStatus(): void
    {
        $status = strtolower((string) $this->status);

        if (in_array($status, ['paid', 'settled'], true)) {
            Session::forget(['external_id', 'has_unpaid_transaction', 'cart_items', 'payment_token', 'self_order_voucher_code', 'self_order_use_points', 'self_order_points_to_redeem']);
            Session::save();

            return;
        }

        if (in_array($status, ['failed', 'expired', 'canceled'], true)) {
            Session::forget(['external_id', 'has_unpaid_transaction', 'payment_token', 'self_order_use_points', 'self_order_points_to_redeem']);
            Session::save();
        }
    }

    #[Layout('layouts.self-order')]
    public function render(): View
    {
        return view('livewire.self-order.payment.status');
    }
}
