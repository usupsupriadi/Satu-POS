<?php

namespace App\Http\Middleware;

use App\Models\DiningTable;
use Closure;
use Illuminate\Http\Request;

class CheckTableNumber
{
    public function handle(Request $request, Closure $next)
    {
        $tableId = $request->session()->get('dining_table_id');

        if ($tableId && DiningTable::query()->whereKey($tableId)->exists()) {
            if (! $this->tableSessionIsActive($request)) {
                $this->forgetTableSession($request);

                return redirect()->route('self-order.invalid', ['reason' => 'session_expired']);
            }

            if (
                ! $request->session()->get('customer_ready')
                && ! $request->is('order/start')
                && ! $request->is('order/verify-email')
            ) {
                return redirect()->route('self-order.start');
            }

            return $next($request);
        }

        $this->forgetTableSession($request);

        return redirect()->route('self-order.invalid', ['reason' => 'qr_invalid']);
    }

    /**
     * Sesi meja dibatasi waktu supaya QR yang difoto tidak bisa dipakai
     * memesan dari luar kedai dalam waktu lama.
     */
    private function tableSessionIsActive(Request $request): bool
    {
        $minutes = (int) config('self_order.table_session_minutes', 60);
        if ($minutes <= 0) {
            return true;
        }

        $expiresAt = (int) $request->session()->get('table_session_expires_at', 0);

        if ($expiresAt <= 0) {
            // Sesi lama dari sebelum fitur ini aktif: beri satu masa berlaku penuh.
            $request->session()->put('table_session_expires_at', now()->addMinutes($minutes)->timestamp);
            $request->session()->save();

            return true;
        }

        return now()->timestamp <= $expiresAt;
    }

    private function forgetTableSession(Request $request): void
    {
        $request->session()->forget([
            'dining_table_id',
            'table_session_started_at',
            'table_session_expires_at',
            'customer_ready',
            'customer_type',
            'member_id',
            'name',
            'phone',
            'email',
        ]);
    }
}
