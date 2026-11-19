<?php

namespace App\Http\Controllers;

use App\Models\DiningTable;
use App\Services\SelfOrder\TableQrSigner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TableQrController extends Controller
{
    public function __invoke(Request $request, string $code): RedirectResponse
    {
        $id = \decode_qr_code($code);
        if (! $id) {
            return redirect()->route('self-order.invalid', ['reason' => 'qr_invalid']);
        }

        $table = DiningTable::query()->find((int) $id);
        if (! $table) {
            return redirect()->route('self-order.invalid', ['reason' => 'qr_invalid']);
        }

        $signer = app(TableQrSigner::class);
        $signatureQuery = $request->query('s', null);
        $expiresQuery = $request->query('e', null);
        $hasSignature = is_string($signatureQuery) && $signatureQuery !== '';

        if (! $hasSignature && ! $signer->isLegacyAllowed()) {
            return redirect()->route('self-order.invalid', ['reason' => 'qr_invalid']);
        }

        if ($hasSignature && ! $signer->isValid($code, $expiresQuery, $signatureQuery)) {
            $reason = $signer->isExpiredButAuthentic($code, $expiresQuery, $signatureQuery)
                ? 'qr_expired'
                : 'qr_invalid';

            return redirect()->route('self-order.invalid', ['reason' => $reason]);
        }

        if (auth()->check()) {
            return redirect()->route('pos.index', [
                'type' => 'dine_in',
                'table' => $table->id,
            ]);
        }

        $minutes = (int) config('self_order.table_session_minutes', 60);
        $now = now();

        session([
            'dining_table_id' => (int) $table->id,
            'table_session_started_at' => $now->timestamp,
            'table_session_expires_at' => $minutes > 0 ? $now->copy()->addMinutes($minutes)->timestamp : 0,
        ]);
        session([
            'self_order_token' => Str::random(40),
            'has_unpaid_transaction' => false,
            'cart_items' => [],
            'payment_token' => null,
            'external_id' => null,
        ]);
        session()->forget([
            'customer_ready',
            'customer_type',
            'member_id',
            'name',
            'phone',
            'email',
        ]);

        return redirect()->route('self-order.start');
    }
}
