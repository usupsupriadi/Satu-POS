<?php

namespace App\Http\Controllers;

use App\Mail\MemberVerificationMail;
use App\Models\DiningTable;
use App\Models\Member;
use App\Models\MemberRegion;
use App\Models\Region;
use App\Services\SelfOrder\TableQrSigner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function register(Request $request): RedirectResponse
    {
        // Normalisasi dulu supaya unique:members,phone memeriksa format yang
        // benar-benar disimpan (0812... dan 62812... adalah orang yang sama).
        $request->merge([
            'phone' => $this->normalizePhone($request->input('phone')),
        ]);

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
     * Menerima nilai `t` berupa URL QR bertanda tangan, atau kode QR lama yang
     * hanya diizinkan saat SELF_ORDER_ALLOW_LEGACY_QR=true.
     *
     * Verifikasinya sama dengan TableQrController supaya TTL/tanda tangan QR
     * tidak bisa dilewati lewat tautan verifikasi email.
     */
    protected function resolveTableFromQrInput(string $value): ?DiningTable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $code = $value;
        $signature = null;
        $expires = null;

        $queryString = (string) parse_url($value, PHP_URL_QUERY);
        if ($queryString === '' && str_contains($value, '?')) {
            $queryString = explode('?', $value, 2)[1];
        }

        if ($queryString !== '') {
            $query = [];
            parse_str($queryString, $query);
            $signature = $query['s'] ?? null;
            $expires = $query['e'] ?? null;
        }

        if (str_contains($value, '/t/')) {
            $code = basename((string) parse_url($value, PHP_URL_PATH));
        } else {
            $code = explode('?', $value)[0];
        }

        $signer = app(TableQrSigner::class);
        $hasSignature = is_string($signature) && $signature !== '';

        if ($hasSignature) {
            // URL QR modern: HMAC + masa berlaku wajib valid.
            if (! $signer->isValid($code, $expires, $signature)) {
                return null;
            }

            $id = \decode_qr_code($code);

            return $id ? DiningTable::query()->find((int) $id) : null;
        }

        // Nilai lama tanpa tanda tangan hanya diterima bila persis sama dengan
        // qr_value yang tersimpan (tabel belum di-regenerate sejak fitur TTL).
        // Kode QR hasil decode yang tidak tersimpan tidak lagi diterima di
        // jalur ini, jadi ID meja tidak bisa ditebak dari foto/tebakan lama.
        $direct = DiningTable::query()->where('qr_value', $value)->first();
        if ($direct) {
            return $direct;
        }

        if (! $signer->isLegacyAllowed()) {
            return null;
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
