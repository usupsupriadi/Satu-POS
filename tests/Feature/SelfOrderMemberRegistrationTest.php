<?php

use App\Livewire\SelfOrder\Pages\StartPage;
use App\Mail\MemberVerificationMail;
use App\Models\DiningTable;
use App\Models\Member;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('lists all reference provinces, not only sales territory provinces', function () {
    expect(Region::query()->count())->toBeGreaterThan(7000);

    Livewire::test(StartPage::class)
        ->call('setTab', 'register')
        ->assertSee('Aceh')
        ->assertSee('Papua')
        ->assertSet('provinces', fn ($provinces) => in_array('Aceh', $provinces, true)
            && in_array('Lampung', $provinces, true)
            && in_array('Papua', $provinces, true))
        ->set('register_province', 'Aceh')
        ->assertSet('regencies', fn ($regencies) => in_array('Kota Banda Aceh', $regencies, true))
        ->set('register_regency', 'Kota Banda Aceh')
        ->assertSet('districts', fn ($districts) => in_array('Baiturrahman', $districts, true));
});

it('registers a member from a region without a sales territory polygon', function () {
    Mail::fake();

    $table = DiningTable::query()->create([
        'table_number' => 'A1',
        'image' => null,
        'qr_value' => 'TBL-A1',
    ]);

    session(['dining_table_id' => $table->id]);

    Livewire::test(StartPage::class)
        ->call('setTab', 'register')
        ->set('register_name', 'Member Aceh')
        ->set('register_email', 'aceh@example.com')
        ->set('register_phone', '081234567890')
        ->set('register_province', 'Aceh')
        ->set('register_regency', 'Kota Banda Aceh')
        ->set('register_district', 'Baiturrahman')
        ->call('registerMember')
        ->assertHasNoErrors()
        ->assertRedirect(route('self-order.member.verify-email'));

    $member = Member::query()->where('email', 'aceh@example.com')->firstOrFail();

    expect($member->province)->toBe('Aceh');
    expect($member->regency)->toBe('Kota Banda Aceh');
    expect($member->district)->toBe('Baiturrahman');
    expect($member->phone)->toBe('6281234567890');
    expect($member->member_region_id)->toBeNull();

    Mail::assertQueued(MemberVerificationMail::class);
});

it('rejects a region combination that is not in the reference data', function () {
    Mail::fake();

    $table = DiningTable::query()->create([
        'table_number' => 'A1',
        'image' => null,
        'qr_value' => 'TBL-A1',
    ]);

    session(['dining_table_id' => $table->id]);

    Livewire::test(StartPage::class)
        ->call('setTab', 'register')
        ->set('register_name', 'Member Palsu')
        ->set('register_email', 'palsu@example.com')
        ->set('register_phone', '081234567891')
        ->set('register_province', 'Aceh')
        ->set('register_regency', 'Kota Banda Aceh')
        ->set('register_district', 'Kecamatan Karangan')
        ->call('registerMember')
        ->assertHasErrors(['register_district']);

    expect(Member::query()->where('email', 'palsu@example.com')->exists())->toBeFalse();
});
