<div class="space-y-6">
    <?php
        $fmt = fn ($value) => 'Rp'.number_format((float) $value, 0, ',', '.');
    ?>

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Sesi Kas Shift</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Buka kas dengan kas awal, tutup kas dengan kas fisik yang dihitung.</p>
        </div>
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('shifts.sessions.open')): ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $activeSession): ?>
                <button type="button" wire:click="openSessionModal" class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex h-11 items-center justify-center rounded-lg px-4 text-sm font-semibold text-white transition">
                    Buka Kas
                </button>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($requiredForPos): ?>
        <div class="rounded-lg border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
            POS mewajibkan sesi kas terbuka. Kasir tanpa sesi tidak bisa menyimpan pesanan atau checkout.
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeSession): ?>
        <div class="rounded-2xl border border-brand-200 bg-brand-50 p-5 dark:border-brand-500/30 dark:bg-brand-500/10">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-brand-700 dark:text-brand-300">Sesi kas Anda sedang terbuka</p>
                    <p class="text-xs text-brand-700/80 dark:text-brand-300/80">
                        <?php echo e($activeSession->shift?->name ?? 'Tanpa shift'); ?>

                        · <?php echo e($activeSession->business_date?->format('d M Y')); ?>

                        · dibuka <?php echo e($activeSession->opened_at?->format('H:i')); ?>

                    </p>
                    <p class="mt-1 text-xs text-brand-700/80 dark:text-brand-300/80">
                        Kas awal <?php echo e($fmt($activeSession->opening_cash)); ?>

                        · Kas seharusnya <?php echo e($fmt($activeSummary['expected_cash'] ?? 0)); ?>

                        · <?php echo e((int) ($activeSummary['transaction_count'] ?? 0)); ?> transaksi lunas
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((int) ($activeSummary['cash_movement_count'] ?? 0) > 0): ?>
                            · Kas masuk <?php echo e($fmt($activeSummary['cash_movements_in'] ?? 0)); ?> / keluar <?php echo e($fmt($activeSummary['cash_movements_out'] ?? 0)); ?>

                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </p>
                </div>
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->any(['shifts.sessions.close', 'shifts.sessions.close.any'])): ?>
                    <button type="button" wire:click="openMovementModal(<?php echo e((int) $activeSession->id); ?>)" class="shadow-theme-xs inline-flex h-11 items-center justify-center rounded-lg border border-brand-300 bg-white px-4 text-sm font-semibold text-brand-700 hover:bg-brand-50 dark:border-brand-500/40 dark:bg-gray-900 dark:text-brand-300">
                        Gerakan Kas
                    </button>
                    <button type="button" wire:click="openCloseModal(<?php echo e((int) $activeSession->id); ?>)" class="shadow-theme-xs inline-flex h-11 items-center justify-center rounded-lg border border-brand-300 bg-white px-4 text-sm font-semibold text-brand-700 hover:bg-brand-50 dark:border-brand-500/40 dark:bg-gray-900 dark:text-brand-300">
                        Tutup Kas
                    </button>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Riwayat Sesi</h3>
        <select wire:model.live="statusFilter" class="shadow-theme-xs h-10 rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
            <option value="">Semua status</option>
            <option value="open">Terbuka</option>
            <option value="closed">Ditutup</option>
        </select>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="custom-scrollbar overflow-x-auto">
            <table class="w-full table-auto">
                <thead>
                    <tr class="border-b border-gray-200 dark:divide-gray-800 dark:border-gray-800">
                        <th class="px-5 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Tanggal Bisnis</th>
                        <th class="px-5 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Kasir</th>
                        <th class="px-5 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Shift</th>
                        <th class="px-5 py-4 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Kas Awal</th>
                        <th class="px-5 py-4 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Kas Seharusnya</th>
                        <th class="px-5 py-4 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Kas Fisik</th>
                        <th class="px-5 py-4 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Selisih</th>
                        <th class="px-5 py-4 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Status</th>
                        <th class="px-5 py-4 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $sessions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $session): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                        <?php $summary = $summaries[(int) $session->id] ?? null; ?>
                        <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'session-row-'.e((int) $session->id).''; ?>wire:key="session-row-<?php echo e((int) $session->id); ?>">
                            <td class="px-5 py-4">
                                <p class="text-sm text-gray-800 dark:text-white/90"><?php echo e($session->business_date?->format('d/m/Y')); ?></p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Buka <?php echo e($session->opened_at?->format('H:i')); ?>

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($session->closed_at): ?>
                                        · Tutup <?php echo e($session->closed_at->format('H:i')); ?>

                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </p>
                            </td>
                            <td class="px-5 py-4 text-sm text-gray-800 dark:text-white/90"><?php echo e($session->user?->name ?? '-'); ?></td>
                            <td class="px-5 py-4">
                                <p class="text-sm text-gray-800 dark:text-white/90"><?php echo e($session->shift?->name ?? '-'); ?></p>
                                <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($session->shift?->timeRangeLabel()); ?></p>
                            </td>
                            <td class="px-5 py-4 text-right text-sm text-gray-800 dark:text-white/90"><?php echo e($fmt($session->opening_cash)); ?></td>
                            <td class="px-5 py-4 text-right text-sm text-gray-800 dark:text-white/90"><?php echo e($fmt($summary['expected_cash'] ?? 0)); ?></td>
                            <td class="px-5 py-4 text-right text-sm text-gray-800 dark:text-white/90">
                                <?php echo e($session->closing_cash !== null ? $fmt($session->closing_cash) : '-'); ?>

                            </td>
                            <td class="px-5 py-4 text-right text-sm font-semibold <?php echo e((int) ($session->variance ?? 0) === 0 ? 'text-success-600 dark:text-success-500' : 'text-error-600 dark:text-error-500'); ?>">
                                <?php echo e($session->variance !== null ? $fmt($session->variance) : '-'); ?>

                            </td>
                            <td class="px-5 py-4">
                                <span class="rounded-full px-2 py-0.5 text-theme-xs font-medium <?php echo e($session->isOpen() ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400' : 'bg-gray-50 text-gray-600 dark:bg-gray-500/15 dark:text-gray-400'); ?>">
                                    <?php echo e($session->isOpen() ? 'Terbuka' : 'Ditutup'); ?>

                                </span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($session->isOpen()): ?>
                                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->any(['shifts.sessions.close', 'shifts.sessions.close.any'])): ?>
                                        <button type="button" wire:click="openMovementModal(<?php echo e((int) $session->id); ?>)" class="shadow-theme-xs inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                                            Kas
                                        </button>
                                        <button type="button" wire:click="openCloseModal(<?php echo e((int) $session->id); ?>)" class="shadow-theme-xs inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                                            Tutup Kas
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400 dark:text-gray-500"><?php echo e($session->closedBy?->name ?? '-'); ?></span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                        </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <?php if (isset($component)) { $__componentOriginal8333c7520247d01ca05cd625bf80e31f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8333c7520247d01ca05cd625bf80e31f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.common.empty-table-row','data' => ['colspan' => '9','message' => 'Belum ada sesi kas.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('common.empty-table-row'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['colspan' => '9','message' => 'Belum ada sesi kas.']); ?>
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
            <?php echo e($sessions->links('livewire.pagination.admin')); ?>

        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($openModalOpen): ?>
        <div class="fixed inset-0 z-[100000] flex items-center justify-center p-4" aria-modal="true" role="dialog">
            <div class="absolute inset-0 bg-black/50" wire:click="closeSessionModal"></div>
            <div class="relative w-full max-w-lg overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Buka Kas</h3>
                    <button type="button" wire:click="closeSessionModal" class="text-sm font-medium text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200">Tutup</button>
                </div>

                <form wire:submit="saveOpenSession" autocomplete="off" class="space-y-4 p-5">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Shift (opsional)</label>
                        <select wire:model.live="openShiftId" class="shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                            <option value="">Tanpa shift</option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $shifts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $shift): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                <option value="<?php echo e((int) $shift->id); ?>"><?php echo e($shift->label()); ?></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                        <?php if (isset($component)) { $__componentOriginalee90cf1aab8b8cee8674701eaf7a143f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.common.input-error','data' => ['for' => 'openShiftId']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('common.input-error'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['for' => 'openShiftId']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f)): ?>
<?php $attributes = $__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f; ?>
<?php unset($__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalee90cf1aab8b8cee8674701eaf7a143f)): ?>
<?php $component = $__componentOriginalee90cf1aab8b8cee8674701eaf7a143f; ?>
<?php unset($__componentOriginalee90cf1aab8b8cee8674701eaf7a143f); ?>
<?php endif; ?>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Kas Awal (Rp)</label>
                        <input wire:model.live="openingCash" type="text" inputmode="numeric" class="shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400" />
                        <?php if (isset($component)) { $__componentOriginalee90cf1aab8b8cee8674701eaf7a143f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.common.input-error','data' => ['for' => 'openingCash']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('common.input-error'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['for' => 'openingCash']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f)): ?>
<?php $attributes = $__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f; ?>
<?php unset($__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalee90cf1aab8b8cee8674701eaf7a143f)): ?>
<?php $component = $__componentOriginalee90cf1aab8b8cee8674701eaf7a143f; ?>
<?php unset($__componentOriginalee90cf1aab8b8cee8674701eaf7a143f); ?>
<?php endif; ?>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Catatan (opsional)</label>
                        <input wire:model.live="note" type="text" maxlength="255" class="shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400" />
                        <?php if (isset($component)) { $__componentOriginalee90cf1aab8b8cee8674701eaf7a143f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.common.input-error','data' => ['for' => 'note']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('common.input-error'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['for' => 'note']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f)): ?>
<?php $attributes = $__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f; ?>
<?php unset($__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalee90cf1aab8b8cee8674701eaf7a143f)): ?>
<?php $component = $__componentOriginalee90cf1aab8b8cee8674701eaf7a143f; ?>
<?php unset($__componentOriginalee90cf1aab8b8cee8674701eaf7a143f); ?>
<?php endif; ?>
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-4 dark:border-gray-800">
                        <button type="button" wire:click="closeSessionModal" class="shadow-theme-xs inline-flex h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.03]">Batal</button>
                        <button type="submit" class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex h-11 items-center justify-center rounded-lg px-4 text-sm font-semibold text-white transition">Buka Kas</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($closeModalOpen): ?>
        <div class="fixed inset-0 z-[100000] flex items-center justify-center p-4" aria-modal="true" role="dialog">
            <div class="absolute inset-0 bg-black/50" wire:click="cancelClose"></div>
            <div class="relative w-full max-w-lg overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Tutup Kas</h3>
                    <button type="button" wire:click="cancelClose" class="text-sm font-medium text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200">Tutup</button>
                </div>

                <form wire:submit="saveCloseSession" autocomplete="off" class="space-y-4 p-5">
                    <div class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-700 dark:bg-white/[0.03] dark:text-gray-300">
                        Kas seharusnya: <span class="font-semibold"><?php echo e($fmt((int) ($expectedPreview ?? 0))); ?></span>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Hitung uang fisik di laci, lalu masukkan jumlahnya. Selisih dihitung otomatis.</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Kas Fisik (Rp)</label>
                        <input wire:model.live="closingCash" type="text" inputmode="numeric" class="shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400" />
                        <?php if (isset($component)) { $__componentOriginalee90cf1aab8b8cee8674701eaf7a143f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.common.input-error','data' => ['for' => 'closingCash']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('common.input-error'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['for' => 'closingCash']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f)): ?>
<?php $attributes = $__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f; ?>
<?php unset($__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalee90cf1aab8b8cee8674701eaf7a143f)): ?>
<?php $component = $__componentOriginalee90cf1aab8b8cee8674701eaf7a143f; ?>
<?php unset($__componentOriginalee90cf1aab8b8cee8674701eaf7a143f); ?>
<?php endif; ?>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Catatan (opsional)</label>
                        <input wire:model.live="closingNote" type="text" maxlength="255" class="shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400" />
                        <?php if (isset($component)) { $__componentOriginalee90cf1aab8b8cee8674701eaf7a143f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.common.input-error','data' => ['for' => 'closingNote']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('common.input-error'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['for' => 'closingNote']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f)): ?>
<?php $attributes = $__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f; ?>
<?php unset($__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalee90cf1aab8b8cee8674701eaf7a143f)): ?>
<?php $component = $__componentOriginalee90cf1aab8b8cee8674701eaf7a143f; ?>
<?php unset($__componentOriginalee90cf1aab8b8cee8674701eaf7a143f); ?>
<?php endif; ?>
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-4 dark:border-gray-800">
                        <button type="button" wire:click="cancelClose" class="shadow-theme-xs inline-flex h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.03]">Batal</button>
                        <button type="submit" class="bg-error-500 hover:bg-error-600 inline-flex h-11 items-center justify-center rounded-lg px-4 text-sm font-semibold text-white transition">Tutup Kas</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($movementModalOpen): ?>
        <div class="fixed inset-0 z-[100000] flex items-center justify-center p-4" aria-modal="true" role="dialog">
            <div class="absolute inset-0 bg-black/50" wire:click="closeMovementModal"></div>
            <div class="relative w-full max-w-2xl overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <div>
                        <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Gerakan Kas</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            <?php echo e($movementSession?->user?->name ?? '-'); ?>

                            · <?php echo e($movementSession?->shift?->name ?? 'Tanpa shift'); ?>

                            · Kas awal <?php echo e($fmt((int) ($movementSession->opening_cash ?? 0))); ?>

                        </p>
                    </div>
                    <button type="button" wire:click="closeMovementModal" class="text-sm font-medium text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200">Tutup</button>
                </div>

                <div class="space-y-4 p-5">
                    <form wire:submit="saveMovement" autocomplete="off" class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Jenis</label>
                            <select wire:model.live="movementType" class="shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                                <option value="in">Setoran (masuk)</option>
                                <option value="out">Ambil (keluar)</option>
                            </select>
                            <?php if (isset($component)) { $__componentOriginalee90cf1aab8b8cee8674701eaf7a143f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.common.input-error','data' => ['for' => 'movementType']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('common.input-error'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['for' => 'movementType']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f)): ?>
<?php $attributes = $__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f; ?>
<?php unset($__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalee90cf1aab8b8cee8674701eaf7a143f)): ?>
<?php $component = $__componentOriginalee90cf1aab8b8cee8674701eaf7a143f; ?>
<?php unset($__componentOriginalee90cf1aab8b8cee8674701eaf7a143f); ?>
<?php endif; ?>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Nilai (Rp)</label>
                            <input wire:model.live="movementAmount" type="text" inputmode="numeric" class="shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400" />
                            <?php if (isset($component)) { $__componentOriginalee90cf1aab8b8cee8674701eaf7a143f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.common.input-error','data' => ['for' => 'movementAmount']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('common.input-error'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['for' => 'movementAmount']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f)): ?>
<?php $attributes = $__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f; ?>
<?php unset($__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalee90cf1aab8b8cee8674701eaf7a143f)): ?>
<?php $component = $__componentOriginalee90cf1aab8b8cee8674701eaf7a143f; ?>
<?php unset($__componentOriginalee90cf1aab8b8cee8674701eaf7a143f); ?>
<?php endif; ?>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Kategori</label>
                            <input wire:model.live="movementCategory" type="text" maxlength="100" placeholder="Contoh: Beli galon" class="shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400" />
                            <?php if (isset($component)) { $__componentOriginalee90cf1aab8b8cee8674701eaf7a143f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.common.input-error','data' => ['for' => 'movementCategory']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('common.input-error'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['for' => 'movementCategory']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f)): ?>
<?php $attributes = $__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f; ?>
<?php unset($__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalee90cf1aab8b8cee8674701eaf7a143f)): ?>
<?php $component = $__componentOriginalee90cf1aab8b8cee8674701eaf7a143f; ?>
<?php unset($__componentOriginalee90cf1aab8b8cee8674701eaf7a143f); ?>
<?php endif; ?>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Catatan</label>
                            <input wire:model.live="movementNote" type="text" maxlength="255" class="shadow-theme-xs h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400" />
                            <?php if (isset($component)) { $__componentOriginalee90cf1aab8b8cee8674701eaf7a143f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.common.input-error','data' => ['for' => 'movementNote']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('common.input-error'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['for' => 'movementNote']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f)): ?>
<?php $attributes = $__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f; ?>
<?php unset($__attributesOriginalee90cf1aab8b8cee8674701eaf7a143f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalee90cf1aab8b8cee8674701eaf7a143f)): ?>
<?php $component = $__componentOriginalee90cf1aab8b8cee8674701eaf7a143f; ?>
<?php unset($__componentOriginalee90cf1aab8b8cee8674701eaf7a143f); ?>
<?php endif; ?>
                        </div>
                        <div class="sm:col-span-4 flex items-center justify-end gap-2">
                            <button type="submit" class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex h-11 items-center justify-center rounded-lg px-4 text-sm font-semibold text-white transition">Catat</button>
                        </div>
                    </form>

                    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                        <table class="w-full table-auto">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-800">
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Waktu</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Jenis</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Nilai</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Kategori</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Catatan</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $movementMovements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $movement): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                    <tr>
                                        <td class="px-4 py-2 text-xs text-gray-500 dark:text-gray-400"><?php echo e($movement->created_at?->format('H:i')); ?></td>
                                        <td class="px-4 py-2 text-sm <?php echo e($movement->isIn() ? 'text-success-600' : 'text-error-600'); ?>">
                                            <?php echo e($movement->isIn() ? 'Masuk' : 'Keluar'); ?>

                                        </td>
                                        <td class="px-4 py-2 text-right text-sm font-semibold text-gray-800 dark:text-white/90"><?php echo e($fmt($movement->amount)); ?></td>
                                        <td class="px-4 py-2 text-sm text-gray-800 dark:text-white/90"><?php echo e((string) ($movement->category ?? '-')); ?></td>
                                        <td class="px-4 py-2 text-sm text-gray-800 dark:text-white/90"><?php echo e((string) ($movement->note ?? '-')); ?></td>
                                        <td class="px-4 py-2 text-right">
                                            <button type="button" wire:click="deleteMovement(<?php echo e((int) $movement->id); ?>)" class="text-xs text-error-600 hover:text-error-700">Hapus</button>
                                        </td>
                                    </tr>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    <tr>
                                        <td colspan="6" class="px-4 py-4 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada gerakan kas.</td>
                                    </tr>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH D:\Satu-POS\resources\views/livewire/shifts/shift-sessions-page.blade.php ENDPATH**/ ?>