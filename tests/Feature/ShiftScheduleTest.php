<?php

use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\Shifts\ShiftScheduleService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->service = app(ShiftScheduleService::class);

    $this->pagi = Shift::query()->create([
        'name' => 'Pagi',
        'start_time' => '08:00',
        'end_time' => '16:00',
        'is_active' => true,
        'sort_order' => 1,
    ]);
    $this->sore = Shift::query()->create([
        'name' => 'Sore',
        'start_time' => '16:00',
        'end_time' => '22:00',
        'is_active' => true,
        'sort_order' => 2,
    ]);
});

test('assigning the same shift twice keeps a single schedule row', function () {
    $user = User::factory()->create();

    $this->service->assign($user, $this->pagi, '2026-11-19');
    $this->service->assign($user, $this->pagi, '2026-11-19');

    expect(ShiftAssignment::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('back to back shifts do not conflict', function () {
    $user = User::factory()->create();

    $this->service->assign($user, $this->pagi, '2026-11-19');

    expect($this->service->hasConflict($user, $this->sore, '2026-11-19'))->toBeFalse();
});

test('overlapping shifts on the same day conflict', function () {
    $user = User::factory()->create();
    $overlap = Shift::query()->create([
        'name' => 'Siang',
        'start_time' => '15:00',
        'end_time' => '23:00',
        'is_active' => true,
        'sort_order' => 3,
    ]);

    $this->service->assign($user, $this->pagi, '2026-11-19');

    expect($this->service->hasConflict($user, $overlap, '2026-11-19'))->toBeTrue();
});

test('different employees may take the same shift', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();

    $this->service->assign($first, $this->pagi, '2026-11-19');

    expect($this->service->hasConflict($second, $this->pagi, '2026-11-19'))->toBeFalse();
});

test('cross midnight shift conflicts with an early morning shift the next day', function () {
    $user = User::factory()->create();
    $night = Shift::query()->create([
        'name' => 'Malam',
        'start_time' => '22:00',
        'end_time' => '06:00',
        'is_active' => true,
        'sort_order' => 4,
    ]);
    $early = Shift::query()->create([
        'name' => 'Dini Hari',
        'start_time' => '05:00',
        'end_time' => '09:00',
        'is_active' => true,
        'sort_order' => 5,
    ]);

    $this->service->assign($user, $night, '2026-11-18');

    expect($this->service->hasConflict($user, $this->pagi, '2026-11-19'))->toBeFalse()
        ->and($this->service->hasConflict($user, $early, '2026-11-19'))->toBeTrue();
});
