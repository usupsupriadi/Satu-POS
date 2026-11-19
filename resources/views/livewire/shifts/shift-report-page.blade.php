<div class="space-y-6">
    @php
        $fmt = fn ($value) => 'Rp'.number_format((float) $value, 0, ',', '.');
        $fromLabel = $from ? \Carbon\CarbonImmutable::parse($from)->format('d M Y') : '-';
        $toLabel = $to ? \Carbon\CarbonImmutable::parse($to)->format('d M Y') : '-';
    @endphp

    <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Laporan Shift</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Periode: {{ $fromLabel }} – {{ $toLabel }}</p>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            <x-common.date-range-picker
                :preset="$rangePreset"
                :from="$from"
                :to="$to"
                wire-from-model="from"
                wire-to-model="to"
                class="flex flex-col gap-3 sm:flex-row sm:items-center"
                select-class="shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 sm:w-auto"
                input-class="h-11 w-full rounded-lg border border-gray-200 bg-white py-2.5 pl-[42px] pr-4 text-sm font-medium text-gray-700 shadow-theme-xs focus:outline-hidden focus:ring-0 focus-visible:outline-hidden dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
            />

            <select wire:model.live="employeeId" class="shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 sm:w-[200px]">
                <option value="">Semua karyawan</option>
                @foreach ($employees as $employee)
                    <option value="{{ (int) $employee->id }}">{{ $employee->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="shiftId" class="shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 sm:w-[200px]">
                <option value="">Semua shift</option>
                @foreach ($shifts as $shift)
                    <option value="{{ (int) $shift->id }}">{{ $shift->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4 md:gap-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 md:p-6 dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-theme-sm text-gray-500 dark:text-gray-400">Total Penjualan Lunas</p>
            <h4 class="mt-3 text-2xl font-bold text-gray-800 dark:text-white/90">{{ $fmt($totals['total_sales']) }}</h4>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ number_format($totals['transactions'], 0, ',', '.') }} transaksi · tunai {{ $fmt($totals['cash_sales']) }}</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 md:p-6 dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-theme-sm text-gray-500 dark:text-gray-400">Jumlah Sesi</p>
            <h4 class="mt-3 text-2xl font-bold text-gray-800 dark:text-white/90">{{ number_format($totals['sessions'], 0, ',', '.') }}</h4>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ number_format($totals['sessions_open'], 0, ',', '.') }} sesi masih terbuka</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 md:p-6 dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-theme-sm text-gray-500 dark:text-gray-400">Kas Seharusnya (sesi ditutup)</p>
            <h4 class="mt-3 text-2xl font-bold text-gray-800 dark:text-white/90">{{ $fmt($totals['expected_cash']) }}</h4>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Kas fisik tercatat {{ $fmt($totals['closing_cash']) }}</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 md:p-6 dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-theme-sm text-gray-500 dark:text-gray-400">Total Selisih Kas</p>
            <h4 class="mt-3 text-2xl font-bold {{ (int) $totals['variance'] === 0 ? 'text-success-600 dark:text-success-500' : 'text-error-600 dark:text-error-500' }}">
                {{ $fmt($totals['variance']) }}
            </h4>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Positif berarti kas lebih, negatif berarti kurang.</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="custom-scrollbar overflow-x-auto">
            <table class="w-full table-auto">
                <thead>
                    <tr class="border-b border-gray-200 dark:divide-gray-800 dark:border-gray-800">
                        <th class="px-5 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Tanggal / Shift</th>
                        <th class="px-5 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Kasir</th>
                        <th class="px-5 py-4 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Transaksi</th>
                        <th class="px-5 py-4 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Tunai</th>
                        <th class="px-5 py-4 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Non-tunai</th>
                        <th class="px-5 py-4 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Kas Awal</th>
                        <th class="px-5 py-4 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Kas Seharusnya</th>
                        <th class="px-5 py-4 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Kas Fisik</th>
                        <th class="px-5 py-4 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Selisih</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($rows as $row)
                        @php $summary = $summaries[(int) $row->id] ?? []; @endphp
                        <tr wire:key="report-row-{{ (int) $row->id }}">
                            <td class="px-5 py-4">
                                <p class="text-sm text-gray-800 dark:text-white/90">{{ $row->business_date?->format('d/m/Y') }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $row->shift?->name ?? 'Tanpa shift' }}
                                    {{ $row->isOpen() ? '· masih terbuka' : '' }}
                                </p>
                            </td>
                            <td class="px-5 py-4 text-sm text-gray-800 dark:text-white/90">{{ $row->user?->name ?? '-' }}</td>
                            <td class="px-5 py-4 text-right text-sm text-gray-800 dark:text-white/90">{{ number_format((int) ($summary['transaction_count'] ?? 0), 0, ',', '.') }}</td>
                            <td class="px-5 py-4 text-right text-sm text-gray-800 dark:text-white/90">{{ $fmt($summary['cash_sales'] ?? 0) }}</td>
                            <td class="px-5 py-4 text-right text-sm text-gray-800 dark:text-white/90">{{ $fmt($summary['non_cash_sales'] ?? 0) }}</td>
                            <td class="px-5 py-4 text-right text-sm text-gray-800 dark:text-white/90">{{ $fmt($row->opening_cash) }}</td>
                            <td class="px-5 py-4 text-right text-sm text-gray-800 dark:text-white/90">{{ $fmt($summary['expected_cash'] ?? 0) }}</td>
                            <td class="px-5 py-4 text-right text-sm text-gray-800 dark:text-white/90">
                                {{ $row->closing_cash !== null ? $fmt($row->closing_cash) : '-' }}
                            </td>
                            <td class="px-5 py-4 text-right text-sm font-semibold {{ (int) ($row->variance ?? 0) === 0 ? 'text-success-600 dark:text-success-500' : 'text-error-600 dark:text-error-500' }}">
                                {{ $row->variance !== null ? $fmt($row->variance) : '-' }}
                            </td>
                        </tr>
                    @empty
                        <x-common.empty-table-row colspan="9" message="Belum ada sesi kas pada periode ini." />
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-200 px-5 py-4 dark:border-gray-800">
            {{ $rows->links('livewire.pagination.admin') }}
        </div>
    </div>
</div>
