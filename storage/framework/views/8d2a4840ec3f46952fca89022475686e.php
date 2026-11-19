<div class="space-y-6">
    <?php
        $fmt = fn ($value) => 'Rp'.number_format((float) $value, 0, ',', '.');
        $fromLabel = $from ? \Carbon\CarbonImmutable::parse($from)->format('d M Y') : '-';
        $toLabel = $to ? \Carbon\CarbonImmutable::parse($to)->format('d M Y') : '-';
    ?>

    <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Laporan Shift</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Periode: <?php echo e($fromLabel); ?> – <?php echo e($toLabel); ?></p>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            <?php if (isset($component)) { $__componentOriginal0f75a9e682f4dfdf6a00b8cfac5a7028 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal0f75a9e682f4dfdf6a00b8cfac5a7028 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.common.date-range-picker','data' => ['preset' => $rangePreset,'from' => $from,'to' => $to,'wireFromModel' => 'from','wireToModel' => 'to','class' => 'flex flex-col gap-3 sm:flex-row sm:items-center','selectClass' => 'shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 sm:w-auto','inputClass' => 'h-11 w-full rounded-lg border border-gray-200 bg-white py-2.5 pl-[42px] pr-4 text-sm font-medium text-gray-700 shadow-theme-xs focus:outline-hidden focus:ring-0 focus-visible:outline-hidden dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('common.date-range-picker'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['preset' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($rangePreset),'from' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($from),'to' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($to),'wire-from-model' => 'from','wire-to-model' => 'to','class' => 'flex flex-col gap-3 sm:flex-row sm:items-center','select-class' => 'shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 sm:w-auto','input-class' => 'h-11 w-full rounded-lg border border-gray-200 bg-white py-2.5 pl-[42px] pr-4 text-sm font-medium text-gray-700 shadow-theme-xs focus:outline-hidden focus:ring-0 focus-visible:outline-hidden dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal0f75a9e682f4dfdf6a00b8cfac5a7028)): ?>
<?php $attributes = $__attributesOriginal0f75a9e682f4dfdf6a00b8cfac5a7028; ?>
<?php unset($__attributesOriginal0f75a9e682f4dfdf6a00b8cfac5a7028); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal0f75a9e682f4dfdf6a00b8cfac5a7028)): ?>
<?php $component = $__componentOriginal0f75a9e682f4dfdf6a00b8cfac5a7028; ?>
<?php unset($__componentOriginal0f75a9e682f4dfdf6a00b8cfac5a7028); ?>
<?php endif; ?>

            <select wire:model.live="employeeId" class="shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 sm:w-[200px]">
                <option value="">Semua karyawan</option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $employees; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $employee): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                    <option value="<?php echo e((int) $employee->id); ?>"><?php echo e($employee->name); ?></option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </select>

            <select wire:model.live="shiftId" class="shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 sm:w-[200px]">
                <option value="">Semua shift</option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $shifts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $shift): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                    <option value="<?php echo e((int) $shift->id); ?>"><?php echo e($shift->name); ?></option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </select>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4 md:gap-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 md:p-6 dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-theme-sm text-gray-500 dark:text-gray-400">Total Penjualan Lunas</p>
            <h4 class="mt-3 text-2xl font-bold text-gray-800 dark:text-white/90"><?php echo e($fmt($totals['total_sales'])); ?></h4>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400"><?php echo e(number_format($totals['transactions'], 0, ',', '.')); ?> transaksi · tunai <?php echo e($fmt($totals['cash_sales'])); ?></p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 md:p-6 dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-theme-sm text-gray-500 dark:text-gray-400">Jumlah Sesi</p>
            <h4 class="mt-3 text-2xl font-bold text-gray-800 dark:text-white/90"><?php echo e(number_format($totals['sessions'], 0, ',', '.')); ?></h4>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400"><?php echo e(number_format($totals['sessions_open'], 0, ',', '.')); ?> sesi masih terbuka</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 md:p-6 dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-theme-sm text-gray-500 dark:text-gray-400">Kas Seharusnya (sesi ditutup)</p>
            <h4 class="mt-3 text-2xl font-bold text-gray-800 dark:text-white/90"><?php echo e($fmt($totals['expected_cash'])); ?></h4>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Kas fisik tercatat <?php echo e($fmt($totals['closing_cash'])); ?></p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 md:p-6 dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-theme-sm text-gray-500 dark:text-gray-400">Total Selisih Kas</p>
            <h4 class="mt-3 text-2xl font-bold <?php echo e((int) $totals['variance'] === 0 ? 'text-success-600 dark:text-success-500' : 'text-error-600 dark:text-error-500'); ?>">
                <?php echo e($fmt($totals['variance'])); ?>

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
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                        <?php $summary = $summaries[(int) $row->id] ?? []; ?>
                        <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'report-row-'.e((int) $row->id).''; ?>wire:key="report-row-<?php echo e((int) $row->id); ?>">
                            <td class="px-5 py-4">
                                <p class="text-sm text-gray-800 dark:text-white/90"><?php echo e($row->business_date?->format('d/m/Y')); ?></p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    <?php echo e($row->shift?->name ?? 'Tanpa shift'); ?>

                                    <?php echo e($row->isOpen() ? '· masih terbuka' : ''); ?>

                                </p>
                            </td>
                            <td class="px-5 py-4 text-sm text-gray-800 dark:text-white/90"><?php echo e($row->user?->name ?? '-'); ?></td>
                            <td class="px-5 py-4 text-right text-sm text-gray-800 dark:text-white/90"><?php echo e(number_format((int) ($summary['transaction_count'] ?? 0), 0, ',', '.')); ?></td>
                            <td class="px-5 py-4 text-right text-sm text-gray-800 dark:text-white/90"><?php echo e($fmt($summary['cash_sales'] ?? 0)); ?></td>
                            <td class="px-5 py-4 text-right text-sm text-gray-800 dark:text-white/90"><?php echo e($fmt($summary['non_cash_sales'] ?? 0)); ?></td>
                            <td class="px-5 py-4 text-right text-sm text-gray-800 dark:text-white/90"><?php echo e($fmt($row->opening_cash)); ?></td>
                            <td class="px-5 py-4 text-right text-sm text-gray-800 dark:text-white/90"><?php echo e($fmt($summary['expected_cash'] ?? 0)); ?></td>
                            <td class="px-5 py-4 text-right text-sm text-gray-800 dark:text-white/90">
                                <?php echo e($row->closing_cash !== null ? $fmt($row->closing_cash) : '-'); ?>

                            </td>
                            <td class="px-5 py-4 text-right text-sm font-semibold <?php echo e((int) ($row->variance ?? 0) === 0 ? 'text-success-600 dark:text-success-500' : 'text-error-600 dark:text-error-500'); ?>">
                                <?php echo e($row->variance !== null ? $fmt($row->variance) : '-'); ?>

                            </td>
                        </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <?php if (isset($component)) { $__componentOriginal8333c7520247d01ca05cd625bf80e31f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8333c7520247d01ca05cd625bf80e31f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.common.empty-table-row','data' => ['colspan' => '9','message' => 'Belum ada sesi kas pada periode ini.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('common.empty-table-row'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['colspan' => '9','message' => 'Belum ada sesi kas pada periode ini.']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8333c7520247d01ca05cd625bf80e31f)): ?>
<?php $attributes = $__attributesOriginal8333c7520247d01ca05cd625bf80e31f; ?>
<?php unset($__attributesOriginal8333c7520247d01ca05cd625bf80e31f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8333c7520247d01ca05cd625bf80e31f)): ?>
<?php $component = $__componentOriginal8333c7520247d01ca05cd625bf80e31f; ?>
<?php unset($__componentOriginal8333c7520247d01ca05cd625bf80e31f); ?>
<?php endif; ?>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-200 px-5 py-4 dark:border-gray-800">
            <?php echo e($rows->links('livewire.pagination.admin')); ?>

        </div>
    </div>
</div>
<?php /**PATH D:\Satu-POS\resources\views/livewire/shifts/shift-report-page.blade.php ENDPATH**/ ?>