<?php

namespace App\Services\Shifts;

use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;

class ShiftScheduleService
{
    /**
     * Jadwal dianggap bentrok bila rentang waktu shift baru beririsan dengan
     * jadwal karyawan yang sama pada hari itu (termasuk shift hari sebelumnya
     * yang melewati tengah malam).
     */
    public function hasConflict(User $user, Shift $shift, string $workDate, ?int $ignoreAssignmentId = null): bool
    {
        $previousDate = CarbonImmutable::parse($workDate)->subDay()->toDateString();

        $existing = ShiftAssignment::query()
            ->with('shift')
            ->where('user_id', $user->id)
            ->where(function ($query) use ($workDate, $previousDate) {
                $query->whereDate('work_date', $workDate)
                    ->orWhereDate('work_date', $previousDate);
            })
            ->when($ignoreAssignmentId, fn ($query) => $query->whereKeyNot($ignoreAssignmentId))
            ->get();

        if ($existing->isEmpty()) {
            return false;
        }

        $target = $this->timeRange($shift, $workDate);

        foreach ($existing as $assignment) {
            if (! $assignment->shift instanceof Shift) {
                continue;
            }

            $range = $this->timeRange($assignment->shift, $assignment->work_date->toDateString());

            if ($target[0] < $range[1] && $range[0] < $target[1]) {
                return true;
            }
        }

        return false;
    }

    public function assign(User $user, Shift $shift, string $workDate, ?User $createdBy = null, ?string $note = null): ShiftAssignment
    {
        $existing = ShiftAssignment::query()
            ->where('shift_id', $shift->id)
            ->where('user_id', $user->id)
            ->whereDate('work_date', $workDate)
            ->first();

        if ($existing) {
            return $existing;
        }

        return ShiftAssignment::query()->create([
            'shift_id' => $shift->id,
            'user_id' => $user->id,
            'work_date' => $workDate,
            'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
            'created_by_user_id' => $createdBy?->id,
        ]);
    }

    /**
     * @return array{0: int, 1: int} timestamp mulai dan selesai
     */
    private function timeRange(Shift $shift, string $workDate): array
    {
        $start = CarbonImmutable::parse($workDate.' '.$shift->startLabel());
        $end = CarbonImmutable::parse($workDate.' '.$shift->endLabel());

        if ($end <= $start) {
            $end = $end->addDay();
        }

        return [$start->getTimestamp(), $end->getTimestamp()];
    }
}
