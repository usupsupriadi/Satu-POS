<?php

use App\Models\DiningTable;
use App\Services\SelfOrder\TableQrSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeTable(string $number = 'A1'): DiningTable
{
    return DiningTable::query()->create([
        'table_number' => $number,
        'image' => null,
        'qr_value' => 'TBL-'.$number,
    ]);
}

function qrCodeFromTable(DiningTable $table): string
{
    $path = (string) parse_url((string) $table->qr_value, PHP_URL_PATH);

    return basename($path);
}

it('generates a signed table qr url', function () {
    $table = makeTable();
    $table->refresh();

    expect((string) $table->qr_value)->toContain('/t/')
        ->toContain('?e=')
        ->toContain('&s=');

    $signer = app(TableQrSigner::class);
    $code = qrCodeFromTable($table);
    parse_str((string) parse_url((string) $table->qr_value, PHP_URL_QUERY), $query);

    expect($signer->isValid($code, $query['e'] ?? null, $query['s'] ?? null))->toBeTrue();
});

it('accepts a valid signed qr and starts a time limited table session', function () {
    $table = makeTable();
    $table->refresh();

    $this->get((string) $table->qr_value)
        ->assertRedirect(route('self-order.start'))
        ->assertSessionHas('dining_table_id', $table->id)
        ->assertSessionHas('table_session_expires_at', fn ($value) => (int) $value > now()->timestamp);
});

it('rejects an expired signed qr', function () {
    $table = makeTable();
    $code = qrCodeFromTable($table);
    $url = app(TableQrSigner::class)->url($code, now()->subMinute()->timestamp);

    $this->get($url)
        ->assertRedirect(route('self-order.invalid', ['reason' => 'qr_expired']))
        ->assertSessionMissing('dining_table_id');
});

it('rejects a signed qr with a tampered signature', function () {
    $table = makeTable();
    $table->refresh();

    $url = (string) $table->qr_value;
    $tampered = substr($url, 0, -1).(str_ends_with($url, 'a') ? 'b' : 'a');

    $this->get($tampered)->assertRedirect(route('self-order.invalid', ['reason' => 'qr_invalid']));
});

it('rejects an unsigned legacy qr by default', function () {
    $table = makeTable();
    $code = qrCodeFromTable($table);

    $this->get('/t/'.$code)
        ->assertRedirect(route('self-order.invalid', ['reason' => 'qr_invalid']))
        ->assertSessionMissing('dining_table_id');
});

it('allows an unsigned legacy qr during the transition window', function () {
    config(['self_order.allow_legacy_unsigned_qr' => true]);

    $table = makeTable();
    $code = qrCodeFromTable($table);

    $this->get('/t/'.$code)->assertRedirect(route('self-order.start'));
});

it('blocks ordering once the table session has expired', function () {
    $table = makeTable();

    $this->withSession([
        'dining_table_id' => $table->id,
        'table_session_started_at' => now()->subMinutes(90)->timestamp,
        'table_session_expires_at' => now()->subMinute()->timestamp,
        'customer_ready' => true,
        'customer_type' => 'guest',
        'name' => 'Tamu',
        'phone' => '628111',
        'cart_items' => [],
    ])
        ->get(route('self-order.start'))
        ->assertRedirect(route('self-order.invalid', ['reason' => 'session_expired']))
        ->assertSessionMissing('dining_table_id');
});

it('explains why the table session ended', function () {
    $this->get('/order/invalid?reason=session_expired')
        ->assertOk()
        ->assertSee('Sesi meja berakhir')
        ->assertSee('Scan ulang QR di meja');
});

it('explains why a table qr is rejected', function () {
    $this->get('/order/invalid?reason=qr_expired')
        ->assertOk()
        ->assertSee('QR meja sudah kedaluwarsa');

    $this->get('/order/invalid?reason=qr_invalid')
        ->assertOk()
        ->assertSee('QR Code tidak valid');
});

it('allows ordering inside the table session window', function () {
    $table = makeTable();

    $this->withSession([
        'dining_table_id' => $table->id,
        'table_session_started_at' => now()->timestamp,
        'table_session_expires_at' => now()->addMinutes(30)->timestamp,
        'customer_ready' => true,
        'customer_type' => 'guest',
        'name' => 'Tamu',
        'phone' => '628111',
        'cart_items' => [],
    ])
        ->get(route('self-order.start'))
        ->assertOk();
});
