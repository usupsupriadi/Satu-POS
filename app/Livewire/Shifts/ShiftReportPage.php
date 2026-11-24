<?php

namespace App\Livewire\Shifts;

use App\Models\Shift;
use App\Models\ShiftSession;
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

        $rows = $this->baseQuery()
            ->with(['user', 'shift'])
            ->orderByDesc('opened_at')
            ->paginate(25);

        $summaries = $sessionService->reconciliations($rows->getCollection());

        // Total selalu mengikuti angka yang tampil di baris: sesi tertutup
        // memakai snapshot saat penutupan, sesi terbuka memakai hitungan live.
        $allRows = (clone $this->baseQuery())
            ->get(['id', 'status', 'opening_cash', 'expected_cash', 'closing_cash', 'variance', 'summary_snapshot']);
        $allSummaryMap = $sessionService->reconciliations($allRows);
        $allSummaries = collect($allSummaryMap);

        $totals = [
            'sessions' => $allSummaries->count(),
            'sessions_open' => $allRows->where('status', 'open')->count(),
            'transactions' => (int) $allSummaries->sum('transaction_count'),
            'total_sales' => (int) $allSummaries->sum('total_sales'),
            'cash_sales' => (int) $allSummaries->sum('cash_sales'),
            'expected_cash' => (int) $allSummaries->sum('expected_cash'),
            'closing_cash' => (int) $allSummaries->sum(fn (array $summary) => $summary['closing_cash'] ?? 0),
            'variance' => (int) $allSummaries->sum(fn (array $summary) => $summary['variance'] ?? 0),
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
