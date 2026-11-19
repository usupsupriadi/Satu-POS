<?php

namespace App\Livewire\Shifts;

use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\Shifts\ShiftScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ShiftSchedulePage extends Component
{
    public string $title = 'Jadwal Shift';

    public string $weekStart = '';

    public bool $assignModalOpen = false;

    public ?int $assignUserId = null;

    public ?int $assignShiftId = null;

    public string $assignDate = '';

    public string $assignNote = '';

    public ?int $bulkUserId = null;

    public ?int $bulkShiftId = null;

    public function mount(): void
    {
        $this->authorize('shifts.view');

        $this->weekStart = CarbonImmutable::now()->startOfWeek(CarbonImmutable::MONDAY)->toDateString();
    }

    public function previousWeek(): void
    {
        $this->weekStart = $this->weekStartDate()->subWeek()->toDateString();
    }

    public function nextWeek(): void
    {
        $this->weekStart = $this->weekStartDate()->addWeek()->toDateString();
    }

    public function currentWeek(): void
    {
        $this->weekStart = CarbonImmutable::now()->startOfWeek(CarbonImmutable::MONDAY)->toDateString();
    }

    public function openAssign(?string $date = null): void
    {
        $this->authorize('shifts.manage');

        $this->reset(['assignUserId', 'assignShiftId', 'assignNote']);
        $this->assignDate = $date ?: $this->weekStartDate()->toDateString();
        $this->resetValidation();
        $this->assignModalOpen = true;
    }

    public function closeAssign(): void
    {
        $this->assignModalOpen = false;
        $this->resetValidation();
    }

    public function saveAssignment(ShiftScheduleService $scheduleService): void
    {
        $this->authorize('shifts.manage');

        $validated = $this->validate([
            'assignUserId' => ['required', 'integer', 'exists:users,id'],
            'assignShiftId' => ['required', 'integer', 'exists:shifts,id'],
            'assignDate' => ['required', 'date_format:Y-m-d'],
            'assignNote' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::query()->findOrFail((int) $validated['assignUserId']);
        $shift = Shift::query()->findOrFail((int) $validated['assignShiftId']);

        if ($scheduleService->hasConflict($user, $shift, (string) $validated['assignDate'])) {
            $this->addError('assignShiftId', 'Karyawan ini sudah punya shift lain pada rentang waktu tersebut.');

            return;
        }

        $existing = ShiftAssignment::query()
            ->where('user_id', $user->id)
            ->where('shift_id', $shift->id)
            ->whereDate('work_date', (string) $validated['assignDate'])
            ->exists();

        $scheduleService->assign($user, $shift, (string) $validated['assignDate'], auth()->user(), $validated['assignNote'] ?? null);

        $this->assignModalOpen = false;
        $this->resetValidation();
        $this->dispatch(
            'toast',
            type: 'success',
            message: $existing ? 'Jadwal tersebut sudah ada.' : 'Jadwal shift berhasil disimpan.'
        );
    }

    public function removeAssignment(int $assignmentId): void
    {
        $this->authorize('shifts.manage');

        ShiftAssignment::query()->whereKey($assignmentId)->delete();

        $this->dispatch('toast', type: 'success', message: 'Jadwal shift dihapus.');
    }

    public function applyToWeek(ShiftScheduleService $scheduleService): void
    {
        $this->authorize('shifts.manage');

        $validated = $this->validate([
            'bulkUserId' => ['required', 'integer', 'exists:users,id'],
            'bulkShiftId' => ['required', 'integer', 'exists:shifts,id'],
        ]);

        $user = User::query()->findOrFail((int) $validated['bulkUserId']);
        $shift = Shift::query()->findOrFail((int) $validated['bulkShiftId']);

        $created = 0;
        $skipped = 0;

        foreach (range(0, 6) as $offset) {
            $date = $this->weekStartDate()->addDays($offset)->toDateString();

            if ($scheduleService->hasConflict($user, $shift, $date)) {
                $skipped++;

                continue;
            }

            $assignment = $scheduleService->assign($user, $shift, $date, auth()->user());

            if ($assignment->wasRecentlyCreated) {
                $created++;
            } else {
                $skipped++;
            }
        }

        $this->dispatch(
            'toast',
            type: $created > 0 ? 'success' : 'error',
            message: $created > 0
                ? "{$created} jadwal ditambahkan, {$skipped} dilewati."
                : 'Tidak ada jadwal baru yang ditambahkan.'
        );
    }

    public function render(): View
    {
        $this->authorize('shifts.view');

        $start = $this->weekStartDate();
        $end = $start->addDays(6);

        $assignments = ShiftAssignment::query()
            ->with(['shift', 'user'])
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy(fn (ShiftAssignment $assignment) => $assignment->work_date->toDateString());

        $days = [];
        foreach (range(0, 6) as $offset) {
            $day = $start->addDays($offset);
            $key = $day->toDateString();

            $days[] = [
                'date' => $key,
                'label' => $day->translatedFormat('D, d M'),
                'is_today' => $day->isToday(),
                'assignments' => ($assignments->get($key) ?? collect())->sortBy(fn (ShiftAssignment $assignment) => $assignment->shift?->startLabel()),
            ];
        }

        return view('livewire.shifts.shift-schedule-page', [
            'days' => $days,
            'weekLabel' => $start->translatedFormat('d M').' – '.$end->translatedFormat('d M Y'),
            'employees' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'shifts' => Shift::query()->active()->orderBy('sort_order')->orderBy('start_time')->get(),
        ])->layout('layouts.app', ['title' => $this->title]);
    }

    private function weekStartDate(): CarbonImmutable
    {
        $parsed = $this->weekStart !== '' ? CarbonImmutable::parse($this->weekStart) : CarbonImmutable::now();

        return $parsed->startOfWeek(CarbonImmutable::MONDAY)->startOfDay();
    }
}
