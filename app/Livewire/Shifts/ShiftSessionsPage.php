<?php

namespace App\Livewire\Shifts;

use App\Models\Shift;
use App\Models\ShiftCashMovement;
use App\Models\ShiftSession;
use App\Services\Shifts\ShiftSessionService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

class ShiftSessionsPage extends Component
{
    use WithPagination;

    public string $title = 'Sesi Kas Shift';

    public string $statusFilter = '';

    public bool $openModalOpen = false;

    public bool $closeModalOpen = false;

    public ?int $openShiftId = null;

    public string $openingCash = '0';

    public string $note = '';

    public ?int $closeSessionId = null;

    public string $closingCash = '0';

    public string $closingNote = '';

    public ?int $expectedPreview = null;

    public bool $movementModalOpen = false;

    public ?int $movementSessionId = null;

    public string $movementType = 'in';

    public string $movementAmount = '0';

    public string $movementCategory = '';

    public string $movementNote = '';

    public ?int $reversalMovementId = null;

    public string $reversalReason = '';

    public function mount(): void
    {
        $this->authorize('shifts.view');
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openSessionModal(): void
    {
        $this->authorize('shifts.sessions.open');

        $this->reset(['openShiftId', 'openingCash', 'note']);
        $this->openingCash = '0';
        $this->resetValidation();
        $this->openModalOpen = true;
    }

    public function closeSessionModal(): void
    {
        $this->openModalOpen = false;
        $this->resetValidation();
    }

    public function saveOpenSession(ShiftSessionService $sessionService): void
    {
        $this->authorize('shifts.sessions.open');

        $this->validate([
            'openShiftId' => ['nullable', 'integer', 'exists:shifts,id'],
            'openingCash' => ['required', 'numeric', 'min:0'],
        ]);

        $shift = $this->openShiftId ? Shift::query()->find($this->openShiftId) : null;

        try {
            $sessionService->open(
                auth()->user(),
                $shift,
                $this->toAmount($this->openingCash),
                $this->note !== '' ? $this->note : null,
            );
        } catch (RuntimeException $exception) {
            $this->addError('openingCash', $exception->getMessage());

            return;
        }

        $this->openModalOpen = false;
        $this->reset(['openShiftId', 'openingCash', 'note']);
        $this->resetValidation();
        $this->resetPage();

        $this->dispatch('toast', type: 'success', message: 'Sesi kas dibuka. Selamat bertransaksi.');
    }

    public function startClose(ShiftSessionService $sessionService): void
    {
        $session = ShiftSession::query()->findOrFail((int) $this->closeSessionId);

        $this->authorizeSession($session);

        if (! $session->isOpen()) {
            $this->dispatch('toast', type: 'error', message: 'Sesi kas ini sudah ditutup.');

            return;
        }

        $summary = $sessionService->cashSummary($session);

        $this->expectedPreview = $summary['expected_cash'];
        $this->closingCash = (string) $summary['expected_cash'];
        $this->closingNote = (string) ($session->note ?? '');
        $this->resetValidation();
        $this->closeModalOpen = true;
    }

    public function openCloseModal(int $sessionId): void
    {
        $this->closeSessionId = $sessionId;
        $this->startClose(app(ShiftSessionService::class));
    }

    public function cancelClose(): void
    {
        $this->closeModalOpen = false;
        $this->closeSessionId = null;
        $this->expectedPreview = null;
        $this->resetValidation();
    }

    public function saveCloseSession(ShiftSessionService $sessionService): void
    {
        $session = ShiftSession::query()->findOrFail((int) $this->closeSessionId);

        $this->authorizeSession($session);

        $this->validate([
            'closingCash' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $sessionService->close(
                $session,
                $this->toAmount($this->closingCash),
                auth()->user(),
                $this->closingNote,
            );
        } catch (RuntimeException $exception) {
            $this->addError('closingCash', $exception->getMessage());

            return;
        }

        $this->closeModalOpen = false;
        $this->closeSessionId = null;
        $this->expectedPreview = null;
        $this->resetValidation();

        $this->dispatch('toast', type: 'success', message: 'Sesi kas ditutup. Selisih kas sudah dicatat.');
    }

    public function openMovementModal(int $sessionId): void
    {
        $session = ShiftSession::query()->findOrFail($sessionId);

        $this->authorizeSession($session);

        $this->movementSessionId = (int) $session->id;
        $this->movementType = 'in';
        $this->movementAmount = '0';
        $this->movementCategory = '';
        $this->movementNote = '';
        $this->reversalMovementId = null;
        $this->reversalReason = '';
        $this->resetValidation();
        $this->movementModalOpen = true;
    }

    public function closeMovementModal(): void
    {
        $this->movementModalOpen = false;
        $this->movementSessionId = null;
        $this->reversalMovementId = null;
        $this->reversalReason = '';
        $this->resetValidation();
    }

    public function saveMovement(ShiftSessionService $sessionService): void
    {
        if (! $this->movementSessionId) {
            return;
        }

        $session = ShiftSession::query()->findOrFail((int) $this->movementSessionId);

        $this->authorizeSession($session);

        $this->validate([
            'movementType' => ['required', 'in:in,out'],
            'movementAmount' => ['required', 'numeric', 'min:1'],
            'movementCategory' => ['nullable', 'string', 'max:100'],
            'movementNote' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $sessionService->recordMovement(
                $session,
                $this->movementType,
                $this->toAmount($this->movementAmount),
                $this->movementCategory !== '' ? $this->movementCategory : null,
                $this->movementNote !== '' ? $this->movementNote : null,
                auth()->user(),
            );
        } catch (RuntimeException $exception) {
            $this->addError('movementAmount', $exception->getMessage());

            return;
        }

        $this->movementAmount = '0';
        $this->movementCategory = '';
        $this->movementNote = '';
        $this->resetValidation();

        $this->dispatch('toast', type: 'success', message: 'Gerakan kas dicatat.');
    }

    public function deleteMovement(int $movementId, ShiftSessionService $sessionService): void
    {
        [$movement, $session] = $this->findMovementSession($movementId);

        $this->authorizeSession($session);

        try {
            $sessionService->deleteMovement($movement);
        } catch (RuntimeException $exception) {
            $this->dispatch('toast', type: 'error', message: $exception->getMessage());

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Gerakan kas dihapus.');
    }

    public function startReversal(int $movementId): void
    {
        [$movement, $session] = $this->findMovementSession($movementId);

        $this->authorizeSession($session);

        if ($session->isOpen() || $movement->reversal_of_id !== null || $movement->isReversed()) {
            $this->dispatch('toast', type: 'error', message: 'Gerakan kas ini tidak bisa dibatalkan.');

            return;
        }

        $this->movementSessionId = (int) $session->id;
        $this->reversalMovementId = (int) $movement->id;
        $this->reversalReason = '';
        $this->resetValidation();
        $this->movementModalOpen = true;
    }

    public function cancelReversal(): void
    {
        $this->reversalMovementId = null;
        $this->reversalReason = '';
        $this->resetValidation();
    }

    public function confirmReversal(ShiftSessionService $sessionService): void
    {
        if (! $this->reversalMovementId) {
            return;
        }

        [$movement, $session] = $this->findMovementSession((int) $this->reversalMovementId);

        $this->authorizeSession($session);

        $this->validate([
            'reversalReason' => ['required', 'string', 'max:255'],
        ], attributes: ['reversalReason' => 'alasan pembatalan']);

        try {
            $sessionService->reverseMovement($movement, auth()->user(), $this->reversalReason);
        } catch (RuntimeException $exception) {
            $this->addError('reversalReason', $exception->getMessage());

            return;
        }

        $this->reversalMovementId = null;
        $this->reversalReason = '';
        $this->resetValidation();

        $this->dispatch('toast', type: 'success', message: 'Gerakan kas dibatalkan.');
    }

    public function render(ShiftSessionService $sessionService): View
    {
        $this->authorize('shifts.view');

        $sessions = ShiftSession::query()
            ->with(['shift', 'user', 'closedBy'])
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->orderByDesc('opened_at')
            ->paginate(25);

        $activeSession = $sessionService->activeFor(auth()->user());

        $summaries = $sessionService->reconciliations($sessions->getCollection());

        $movementSession = $this->movementSessionId
            ? ShiftSession::query()->with(['user', 'shift', 'cashMovements.createdBy', 'cashMovements.reversal'])->find($this->movementSessionId)
            : null;

        $movementMovements = $movementSession
            ? $movementSession->cashMovements->sortByDesc('created_at')->values()
            : collect();

        $reversalMovement = $this->reversalMovementId
            ? $movementMovements->firstWhere('id', $this->reversalMovementId)
            : null;

        return view('livewire.shifts.shift-sessions-page', [
            'sessions' => $sessions,
            'activeSession' => $activeSession,
            'activeSummary' => $activeSession ? $sessionService->cashSummary($activeSession) : null,
            'summaries' => $summaries,
            'shifts' => Shift::query()->active()->orderBy('sort_order')->orderBy('start_time')->get(),
            'requiredForPos' => $sessionService->requiredForPos(),
            'movementSession' => $movementSession,
            'movementMovements' => $movementMovements,
            'reversalMovement' => $reversalMovement,
        ])->layout('layouts.app', ['title' => $this->title]);
    }

    /**
     * Satu-satunya sumber aturan izin kas: sesi milik sendiri cukup
     * shifts.sessions.close, sesi kasir lain butuh shifts.sessions.close.any.
     * Dipakai untuk otorisasi aksi (tutup kas & gerakan kas) sekaligus
     * visibilitas tombolnya, supaya keduanya tidak bisa melenceng.
     */
    private function sessionPermission(ShiftSession $session): string
    {
        $userId = auth()->id();

        return $userId !== null && (int) $session->user_id === (int) $userId
            ? 'shifts.sessions.close'
            : 'shifts.sessions.close.any';
    }

    private function authorizeSession(ShiftSession $session): void
    {
        $this->authorize($this->sessionPermission($session));
    }

    public function canManageSession(ShiftSession $session): bool
    {
        return (bool) auth()->user()?->can($this->sessionPermission($session));
    }

    /** @return array{0: ShiftCashMovement, 1: ShiftSession} */
    private function findMovementSession(int $movementId): array
    {
        $movement = ShiftCashMovement::query()->findOrFail($movementId);

        return [$movement, ShiftSession::query()->findOrFail((int) $movement->shift_session_id)];
    }

    private function toAmount(string $value): int
    {
        return (int) preg_replace('/\D+/', '', $value);
    }
}
