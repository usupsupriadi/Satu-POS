<?php

namespace App\Livewire\Shifts;

use App\Models\Shift;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ShiftMasterPage extends Component
{
    public string $title = 'Master Shift';

    public bool $modalOpen = false;

    public ?int $editingShiftId = null;

    public string $name = '';

    public string $startTime = '08:00';

    public string $endTime = '16:00';

    public bool $isActive = true;

    public int $sortOrder = 0;

    public function mount(): void
    {
        $this->authorize('shifts.manage');
    }

    public function openCreate(): void
    {
        $this->authorize('shifts.manage');

        $this->reset(['editingShiftId', 'name', 'startTime', 'endTime', 'isActive', 'sortOrder']);
        $this->startTime = '08:00';
        $this->endTime = '16:00';
        $this->isActive = true;
        $this->sortOrder = 0;

        $this->resetValidation();
        $this->modalOpen = true;
    }

    public function startEdit(int $shiftId): void
    {
        $this->authorize('shifts.manage');

        $shift = Shift::query()->findOrFail($shiftId);

        $this->editingShiftId = (int) $shift->id;
        $this->name = (string) $shift->name;
        $this->startTime = $shift->startLabel();
        $this->endTime = $shift->endLabel();
        $this->isActive = (bool) $shift->is_active;
        $this->sortOrder = (int) $shift->sort_order;

        $this->resetValidation();
        $this->modalOpen = true;
    }

    public function closeModal(): void
    {
        $this->modalOpen = false;
        $this->editingShiftId = null;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->authorize('shifts.manage');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:80'],
            'startTime' => ['required', 'date_format:H:i'],
            'endTime' => ['required', 'date_format:H:i'],
            'isActive' => ['boolean'],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $payload = [
            'name' => trim((string) $validated['name']),
            'start_time' => $validated['startTime'],
            'end_time' => $validated['endTime'],
            'is_active' => (bool) $validated['isActive'],
            'sort_order' => (int) ($validated['sortOrder'] ?? 0),
        ];

        if ($this->editingShiftId) {
            $shift = Shift::query()->findOrFail($this->editingShiftId);
            $shift->update($payload);
            $message = 'Shift berhasil diperbarui.';
        } else {
            Shift::query()->create($payload);
            $message = 'Shift berhasil ditambahkan.';
        }

        $this->closeModal();
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function toggleActive(int $shiftId): void
    {
        $this->authorize('shifts.manage');

        $shift = Shift::query()->findOrFail($shiftId);
        $shift->update(['is_active' => ! $shift->is_active]);

        $this->dispatch('toast', type: 'success', message: 'Status shift diperbarui.');
    }

    public function deleteShift(int $shiftId): void
    {
        $this->authorize('shifts.manage');

        $shift = Shift::query()->withCount(['assignments', 'sessions'])->findOrFail($shiftId);

        if ((int) $shift->assignments_count > 0 || (int) $shift->sessions_count > 0) {
            $this->dispatch('toast', type: 'error', message: 'Shift sudah dipakai pada jadwal/sesi kas. Nonaktifkan saja agar riwayat tetap utuh.');

            return;
        }

        $shift->delete();

        if ($this->editingShiftId === $shiftId) {
            $this->closeModal();
        }

        $this->dispatch('toast', type: 'success', message: 'Shift berhasil dihapus.');
    }

    public function render(): View
    {
        $this->authorize('shifts.manage');

        return view('livewire.shifts.shift-master-page', [
            'shifts' => Shift::query()
                ->withCount(['assignments', 'sessions'])
                ->orderBy('sort_order')
                ->orderBy('start_time')
                ->get(),
        ])->layout('layouts.app', ['title' => $this->title]);
    }
}
