<div class="space-y-6">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Jadwal Shift</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Periode: {{ $weekLabel }}</p>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="inline-flex items-center gap-2">
                <button type="button" wire:click="previousWeek" class="shadow-theme-xs inline-flex h-10 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">&larr;</button>
                <button type="button" wire:click="currentWeek" class="shadow-theme-xs inline-flex h-10 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">Minggu Ini</button>
                <button type="button" wire:click="nextWeek" class="shadow-theme-xs inline-flex h-10 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">&rarr;</button>
            </div>

            @can('shifts.manage')
                <button type="button" wire:click="openAssign" class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex h-10 items-center justify-center rounded-lg px-4 text-sm font-semibold text-white transition">
                    Tambah Jadwal
                </button>
            @endcan
        </div>
    </div>

    @can('shifts.manage')
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-sm font-semibold text-gray-800 dark:text-white/90">Terapkan ke Seminggu</p>
            <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">Isi jadwal yang sama untuk satu karyawan pada 7 hari di minggu ini. Jadwal yang bentrok otomatis dilewati.</p>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <select wire:model.live="bulkUserId" class="shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                    <option value="">Pilih karyawan</option>
                    @foreach ($employees as $employee)
                        <option value="{{ (int) $employee->id }}">{{ $employee->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="bulkShiftId" class="shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                    <option value="">Pilih shift</option>
                    @foreach ($shifts as $shift)
                        <option value="{{ (int) $shift->id }}">{{ $shift->label() }}</option>
                    @endforeach
                </select>
                <button type="button" wire:click="applyToWeek" class="shadow-theme-xs inline-flex h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.03]">
                    Terapkan
                </button>
            </div>
            <x-common.input-error for="bulkUserId" />
            <x-common.input-error for="bulkShiftId" />
        </div>
    @endcan

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach ($days as $day)
            <div wire:key="day-{{ $day['date'] }}" class="rounded-2xl border {{ $day['is_today'] ? 'border-brand-400 dark:border-brand-500' : 'border-gray-200 dark:border-gray-800' }} bg-white p-4 dark:bg-white/[0.03]">
                <div class="mb-3 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ $day['label'] }}</p>
                        @if ($day['is_today'])
                            <p class="text-xs text-brand-600 dark:text-brand-400">Hari ini</p>
                        @endif
                    </div>
                    @can('shifts.manage')
                        <button type="button" wire:click="openAssign('{{ $day['date'] }}')" class="text-xs font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400">+ Jadwal</button>
                    @endcan
                </div>

                <div class="space-y-2">
                    @forelse ($day['assignments'] as $assignment)
                        <div wire:key="assignment-{{ (int) $assignment->id }}" class="rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-700">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $assignment->user?->name ?? '-' }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $assignment->shift?->name ?? '-' }} · {{ $assignment->shift?->timeRangeLabel() }}
                                    </p>
                                    @if ($assignment->note)
                                        <p class="text-xs text-gray-400 dark:text-gray-500">{{ $assignment->note }}</p>
                                    @endif
                                </div>
                                @can('shifts.manage')
                                    <button type="button" wire:click="removeAssignment({{ (int) $assignment->id }})" class="text-xs font-medium text-error-600 hover:text-error-700">Hapus</button>
                                @endcan
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 dark:text-gray-500">Belum ada jadwal.</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>

    @if ($assignModalOpen)
        <div class="fixed inset-0 z-[100000] flex items-center justify-center p-4" aria-modal="true" role="dialog">
            <div class="absolute inset-0 bg-black/50" wire:click="closeAssign"></div>
            <div class="relative w-full max-w-xl overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Tambah Jadwal Shift</h3>
                    <button type="button" wire:click="closeAssign" class="text-sm font-medium text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200">Tutup</button>
                </div>

                <form wire:submit="saveAssignment" autocomplete="off" class="space-y-4 p-5">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Karyawan</label>
                        <select wire:model.live="assignUserId" class="shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                            <option value="">Pilih karyawan</option>
                            @foreach ($employees as $employee)
                                <option value="{{ (int) $employee->id }}">{{ $employee->name }}</option>
                            @endforeach
                        </select>
                        <x-common.input-error for="assignUserId" />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Shift</label>
                        <select wire:model.live="assignShiftId" class="shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                            <option value="">Pilih shift</option>
                            @foreach ($shifts as $shift)
                                <option value="{{ (int) $shift->id }}">{{ $shift->label() }}</option>
                            @endforeach
                        </select>
                        <x-common.input-error for="assignShiftId" />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Tanggal</label>
                        <input wire:model.live="assignDate" type="date" class="shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400" />
                        <x-common.input-error for="assignDate" />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Catatan (opsional)</label>
                        <input wire:model.live="assignNote" type="text" maxlength="255" class="shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400" />
                        <x-common.input-error for="assignNote" />
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-4 dark:border-gray-800">
                        <button type="button" wire:click="closeAssign" class="shadow-theme-xs inline-flex h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.03]">Batal</button>
                        <button type="submit" class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex h-11 items-center justify-center rounded-lg px-4 text-sm font-semibold text-white transition">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
