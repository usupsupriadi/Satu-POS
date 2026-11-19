<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Master Shift</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Definisikan jam shift toko. Shift yang melewati tengah malam didukung (mis. 22:00 - 06:00).</p>
        </div>
        @can('shifts.manage')
            <button type="button" wire:click="openCreate" class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex h-11 items-center justify-center rounded-lg px-4 text-sm font-semibold text-white transition">
                Tambah Shift
            </button>
        @endcan
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="custom-scrollbar overflow-x-auto">
            <table class="w-full table-auto">
                <thead>
                    <tr class="border-b border-gray-200 dark:divide-gray-800 dark:border-gray-800">
                        <th class="px-5 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Nama Shift</th>
                        <th class="px-5 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Jam</th>
                        <th class="px-5 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Jadwal</th>
                        <th class="px-5 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Sesi Kas</th>
                        <th class="px-5 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Status</th>
                        <th class="px-5 py-4 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($shifts as $shift)
                        <tr wire:key="shift-row-{{ (int) $shift->id }}">
                            <td class="px-5 py-4">
                                <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $shift->name }}</p>
                                @if ($shift->crossesMidnight())
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Melewati tengah malam</p>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-sm text-gray-800 dark:text-white/90">
                                {{ $shift->timeRangeLabel() }}
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ intdiv($shift->durationMinutes(), 60) }} jam {{ $shift->durationMinutes() % 60 }} menit</p>
                            </td>
                            <td class="px-5 py-4 text-sm text-gray-800 dark:text-white/90">{{ (int) $shift->assignments_count }}</td>
                            <td class="px-5 py-4 text-sm text-gray-800 dark:text-white/90">{{ (int) $shift->sessions_count }}</td>
                            <td class="px-5 py-4">
                                <span class="rounded-full px-2 py-0.5 text-theme-xs font-medium {{ $shift->is_active ? 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500' : 'bg-gray-50 text-gray-600 dark:bg-gray-500/15 dark:text-gray-400' }}">
                                    {{ $shift->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    @can('shifts.manage')
                                        <button type="button" wire:click="startEdit({{ (int) $shift->id }})" class="shadow-theme-xs inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                                            Ubah
                                        </button>
                                        <button type="button" wire:click="toggleActive({{ (int) $shift->id }})" class="shadow-theme-xs inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                                            {{ $shift->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                        <button type="button" x-on:click.prevent="$dispatch('confirm', { message: 'Hapus shift ini?', method: 'deleteShift', args: [{{ (int) $shift->id }}] })" class="shadow-theme-xs inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                                            Hapus
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-common.empty-table-row colspan="6" message="Belum ada shift. Tambahkan shift pertama." />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($modalOpen)
        <div class="fixed inset-0 z-[100000] flex items-center justify-center p-4" aria-modal="true" role="dialog">
            <div class="absolute inset-0 bg-black/50" wire:click="closeModal"></div>
            <div class="relative w-full max-w-xl overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <div>
                        <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">{{ $editingShiftId ? 'Ubah Shift' : 'Tambah Shift' }}</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Jam selesai lebih kecil dari jam mulai berarti shift lintas tengah malam.</p>
                    </div>
                    <button type="button" wire:click="closeModal" class="text-sm font-medium text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200">
                        Tutup
                    </button>
                </div>

                <form wire:submit="save" autocomplete="off" class="space-y-4 p-5">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Nama Shift</label>
                        <input wire:model.live="name" type="text" placeholder="Contoh: Pagi" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        <x-common.input-error for="name" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Jam Mulai</label>
                            <input wire:model.live="startTime" type="time" class="shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400" />
                            <x-common.input-error for="startTime" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Jam Selesai</label>
                            <input wire:model.live="endTime" type="time" class="shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400" />
                            <x-common.input-error for="endTime" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Urutan Tampil</label>
                            <input wire:model.live="sortOrder" type="number" min="0" class="shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400" />
                            <x-common.input-error for="sortOrder" />
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input wire:model.live="isActive" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/20 dark:border-gray-700" />
                                Aktif
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-4 dark:border-gray-800">
                        <button type="button" wire:click="closeModal" class="shadow-theme-xs inline-flex h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.03]">
                            Batal
                        </button>
                        <button type="submit" class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex h-11 items-center justify-center rounded-lg px-4 text-sm font-semibold text-white transition">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
