<?php

use App\Livewire\Shifts\ShiftMasterPage;
use App\Livewire\Shifts\ShiftReportPage;
use App\Livewire\Shifts\ShiftSchedulePage;
use App\Livewire\Shifts\ShiftSessionsPage;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('shift pages deny access without shift permissions', function () {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('waiter');

    Livewire::actingAs($user)->test(ShiftMasterPage::class)->assertStatus(403);
    Livewire::actingAs($user)->test(ShiftSchedulePage::class)->assertStatus(403);
    Livewire::actingAs($user)->test(ShiftSessionsPage::class)->assertStatus(403);
    Livewire::actingAs($user)->test(ShiftReportPage::class)->assertStatus(403);
});

test('cashier can open a cash session page but cannot manage master shifts', function () {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('cashier');

    Livewire::actingAs($user)->test(ShiftSessionsPage::class)->assertOk();
    Livewire::actingAs($user)->test(ShiftSchedulePage::class)->assertOk();
    Livewire::actingAs($user)->test(ShiftMasterPage::class)->assertStatus(403);
    Livewire::actingAs($user)->test(ShiftReportPage::class)->assertStatus(403);
});

test('owner can access every shift page', function () {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('owner');

    Livewire::actingAs($user)->test(ShiftMasterPage::class)->assertOk();
    Livewire::actingAs($user)->test(ShiftSchedulePage::class)->assertOk();
    Livewire::actingAs($user)->test(ShiftSessionsPage::class)->assertOk();
    Livewire::actingAs($user)->test(ShiftReportPage::class)->assertOk();
});

test('shift routes are protected by permission middleware', function () {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('waiter');

    $this->actingAs($user)->get(route('shifts.sessions'))->assertForbidden();
    $this->actingAs($user)->get(route('shifts.reports'))->assertForbidden();
});
