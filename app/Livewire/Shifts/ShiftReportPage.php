<?php

namespace App\Livewire\Shifts;

use App\Models\Shift;
use App\Models\ShiftSession;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Shifts\ShiftSessionService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class ShiftReportPage extends Component
{
    use WithPagination;

    public string $title = 'Laporan Shift';

    public ?string $from = null;

    public ?string $to = null;

    public string $rangePreset = '30d';

    public ?int $employeeId = null;

    public ?int $shiftId = null;

    public function mount(): void
    {
        $this->authorize('shifts.reports.view');

        $this->setRange('30d');
    }

    public function updatedFrom(): void
    {
        $this->resetPage();
    }

    public function updatedTo(): void
    {
        $this->resetPage();
    }

    public function updatedEmployeeId(): void
    {
        $this->resetPage();
    }

    public function updatedShiftId(): void
    {
        $this->resetPage();
    }

    public function setTransactionsRange(?string $from, ?string $to): void
    {
        if (! $from || ! $to) {
            return;
        }

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $this->from = $from;
        $this->to = $to;
        $this->rangePreset = 'custom';
        $this->resetPage();
    }

    public function setRange(string $preset): void
    {
        $today = CarbonImmutable::now();

        if ($preset === 'today') {
            $from = $today;
            $to = $today;
        } elseif ($preset === '7d') {
            $from = $today->subDays(6);
            $to = $today;
        } elseif ($preset === '30d') {
            $from = $today->subDays(29);
            $to = $today;
        } else {
            return;
        }

        $this->from = $from->format('Y-m-d');
        $this->to = $to->format('Y-m-d');
        $this->rangePreset = $preset;
        $this->resetPage();
    }

    public function render(ShiftSessionService $sessionService): View
    {
        $this->authorize('shifts.reports.view');

        $rows = $this->baseQuery()->orderByDesc('opened_at')->paginate(25);

        $summaries = [];
        foreach ($rows as $row) {
            $summaries[(int) $row->id] = $sessionService->reconciliation($row);
        }

        $transactions = Transaction::query()
            ->whereIn('shift_session_id', (clone $this->baseQuery())->select('id'))
            ->where('payment_status', 'paid');

        // Total kas mengikuti angka yang tampil di baris: sesi tertutup memakai
        // nilai tersimpan, sesi terbuka memakai hitungan live.
        $openSessions = (clone $this->baseQuery())
            ->where('status', 'open')
            ->get(['id', 'status', 'opening_cash', 'expected_cash']);

        $openExpectedCash = $openSessions->reduce(
            fn (int $carry, ShiftSession $session) => $carry + $sessionService->reconciliation($session)['expected_cash'],
            0,
        );

        $totals = [
            'sessions' => (int) (clone $this->baseQuery())->count(),
            'sessions_open' => $openSessions->count(),
            'transactions' => (int) (clone $transactions)->count(),
            'total_sales' => (int) (clone $transactions)->sum('total'),
            'cash_sales' => (int) (clone $transactions)->where('payment_method', 'cash')->sum('total'),
            'expected_cash' => (int) (clone $this->baseQuery())->where('status', 'closed')->sum('expected_cash') + $openExpectedCash,
            'closing_cash' => (int) (clone $this->baseQuery())->sum('closing_cash'),
            'variance' => (int) (clone $this->baseQuery())->sum('variance'),
        ];

        return view('livewire.shifts.shift-report-page', [
            'rows' => $rows,
            'summaries' => $summaries,
            'totals' => $totals,
            'employees' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'shifts' => Shift::query()->orderBy('sort_order')->orderBy('start_time')->get(),
        ])->layout('layouts.app', ['title' => $this->title]);
    }

    protected function baseQuery(): Builder
    {
        return ShiftSession::query()
            ->when($this->from, fn (Builder $query) => $query->whereDate('business_date', '>=', (string) $this->from))
            ->when($this->to, fn (Builder $query) => $query->whereDate('business_date', '<=', (string) $this->to))
            ->when($this->employeeId, fn (Builder $query) => $query->where('user_id', (int) $this->employeeId))
            ->when($this->shiftId, fn (Builder $query) => $query->where('shift_id', (int) $this->shiftId));
    }
}
