<?php

namespace App\Http\Controllers;

use App\Mail\MemberVerificationMail;
use App\Models\DiningTable;
use App\Models\Member;
use App\Models\MemberRegion;
use App\Models\Region;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:members,email'],
            'phone' => ['required', 'string', 'max:30', 'unique:members,phone'],
            'province' => ['required', 'string', 'max:255'],
            'regency' => ['required', 'string', 'max:255'],
            'district' => ['required', 'string', 'max:255'],
        ]);

        if (! Region::isValidCombination($validated['province'], $validated['regency'], $validated['district'])) {
            return back()->withErrors(['district' => 'Wilayah tidak valid. Silakan pilih ulang.'])->withInput();
        }

        // Wilayah sales opsional: hanya ditautkan bila poligonnya sudah diimport.
        $territoryId = MemberRegion::query()
            ->where('province', $validated['province'])
            ->where('regency', $validated['regency'])
            ->where('district', $validated['district'])
            ->value('id');

        $member = Member::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $this->normalizePhone($validated['phone']),
            'member_region_id' => $territoryId,
            'province' => $validated['province'],
            'regency' => $validated['regency'],
            'district' => $validated['district'],
            'points' => 0,
            'verification_token' => Str::random(40),
            'email_verified_at' => null,
        ]);

        Mail::to($member->email)->queue(new MemberVerificationMail($member));

        return back()->with('success', 'Pendaftaran berhasil. Silakan cek email untuk verifikasi.');
    }

    public function verify(Request $request, string $token): View|RedirectResponse
    {
        $member = Member::where('verification_token', $token)->first();
        if (! $member) {
            return view('livewire.self-order.members.verified', ['member' => null, 'error' => 'Tautan verifikasi tidak valid atau telah digunakan.']);
        }

        $member->email_verified_at = now();
        $member->verification_token = null;
        $member->save();

        $tableCode = trim((string) $request->query('t', ''));
        $hasTableContext = is_numeric(session('dining_table_id')) || $tableCode !== '';

        if ($hasTableContext) {
            if (! is_numeric(session('dining_table_id')) && $tableCode !== '') {
                $table = $this->resolveTableFromQrInput($tableCode);
                if ($table) {
                    if (! is_array(session('cart_items'))) {
                        session(['cart_items' => []]);
                    }
                    if (! is_string(session('self_order_token')) || trim((string) session('self_order_token')) === '') {
                        session(['self_order_token' => Str::random(40)]);
                    }

                    $tableSessionMinutes = (int) config('self_order.table_session_minutes', 60);

                    session([
                        'dining_table_id' => (int) $table->id,
                        'table_session_started_at' => now()->timestamp,
                        'table_session_expires_at' => $tableSessionMinutes > 0 ? now()->addMinutes($tableSessionMinutes)->timestamp : 0,
                        'payment_token' => null,
                        'external_id' => null,
                        'has_unpaid_transaction' => false,
                    ]);
                }
            }

            if (is_numeric(session('dining_table_id'))) {
                session([
                    'customer_ready' => true,
                    'customer_type' => 'member',
                    'member_id' => (int) $member->id,
                    'name' => (string) $member->name,
                    'phone' => (string) ($member->phone ?? ''),
                    'email' => (string) ($member->email ?? ''),
                ]);

                return redirect()->route('self-order.home');
            }
        }

        return view('livewire.self-order.members.verified', ['member' => $member, 'error' => null]);
    }

    /**
     * Menerima nilai `t` berupa qr_value lama, URL QR bertanda tangan, atau kode QR saja.
     */
    protected function resolveTableFromQrInput(string $value): ?DiningTable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $direct = DiningTable::query()->where('qr_value', $value)->first();
        if ($direct) {
            return $direct;
        }

        $code = $value;
        if (str_contains($value, '/t/')) {
            $path = (string) parse_url($value, PHP_URL_PATH);
            $code = basename($path);
        } else {
            $code = explode('?', $value)[0];
        }

        $id = \decode_qr_code($code);

        return $id ? DiningTable::query()->find((int) $id) : null;
    }

    protected function normalizePhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }
        $p = preg_replace('/\\D+/', '', $phone);
        if (str_starts_with($p, '0')) {
            return '62'.substr($p, 1);
        }
        if (! str_starts_with($p, '62')) {
            return '62'.$p;
        }

        return $p;
    }
}
