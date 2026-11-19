<?php
    $toc = (array) ($article['toc'] ?? []);
    $html = (string) ($article['html'] ?? '');
    $isMissing = (bool) ($article['missing'] ?? false);
?>

<style>
    .guide-content { color: #374151; font-size: 0.9375rem; line-height: 1.75; }
    .guide-content > *:first-child { margin-top: 0; }
    .guide-content > *:last-child { margin-bottom: 0; }
    .guide-content h2 { margin: 1.75rem 0 0.75rem; font-size: 1.125rem; font-weight: 700; color: #1f2937; scroll-margin-top: 96px; }
    .guide-content h3 { margin: 1.5rem 0 0.5rem; font-size: 1rem; font-weight: 700; color: #1f2937; scroll-margin-top: 96px; }
    .guide-content p { margin: 0.75rem 0; }
    .guide-content ul, .guide-content ol { margin: 0.75rem 0; padding-left: 1.25rem; }
    .guide-content ul { list-style: disc; }
    .guide-content ol { list-style: decimal; }
    .guide-content li { margin: 0.35rem 0; }
    .guide-content li[data-check] { list-style: none; position: relative; padding-left: 1.5rem; }
    .guide-content li[data-check]::before { content: '☐'; position: absolute; left: 0; color: #9ca3af; }
    .guide-content li[data-check="1"]::before { content: '☑'; color: #16a34a; }
    .guide-content a { color: #465fff; text-decoration: underline; }
    .guide-content strong { color: #1f2937; font-weight: 700; }
    .guide-content blockquote { margin: 1rem 0; padding: 0.75rem 1rem; border-left: 3px solid #465fff; background: #f9fafb; border-radius: 0.5rem; }
    .guide-content code { background: #f3f4f6; border-radius: 0.25rem; padding: 0.1rem 0.35rem; font-size: 0.85em; }
    .guide-content pre { margin: 1rem 0; padding: 1rem; background: #f3f4f6; border-radius: 0.75rem; overflow-x: auto; }
    .guide-content pre code { background: transparent; padding: 0; }
    .guide-content hr { margin: 1.5rem 0; border-color: #e5e7eb; }
    .guide-content table { display: block; width: 100%; margin: 1rem 0; overflow-x: auto; border-collapse: collapse; }
    .guide-content th, .guide-content td { border: 1px solid #e5e7eb; padding: 0.5rem 0.75rem; text-align: left; font-size: 0.875rem; }
    .guide-content th { background: #f9fafb; font-weight: 600; }

    .dark .guide-content { color: #d1d5db; }
    .dark .guide-content h2, .dark .guide-content h3, .dark .guide-content strong { color: #f3f4f6; }
    .dark .guide-content blockquote { background: rgba(255, 255, 255, 0.03); }
    .dark .guide-content code, .dark .guide-content pre { background: #111827; color: #e5e7eb; }
    .dark .guide-content hr { border-color: #1f2937; }
    .dark .guide-content th, .dark .guide-content td { border-color: #1f2937; }
    .dark .guide-content th { background: rgba(255, 255, 255, 0.03); }
</style>

<div class="space-y-4">
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <a href="<?php echo e(route('guides.index')); ?>" wire:navigate class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                Buku Panduan
            </a>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($article['category_label'])): ?>
                <span class="inline-flex w-fit items-center rounded-full border border-gray-200 bg-white px-3 py-1 text-xs font-semibold text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                    <?php echo e((string) $article['category_label']); ?>

                </span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <h1 class="mt-3 text-xl font-bold text-gray-800 dark:text-white/90"><?php echo e((string) ($article['title'] ?? '')); ?></h1>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($article['summary'])): ?>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400"><?php echo e((string) $article['summary']); ?></p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
        <div class="lg:col-span-3">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isMissing || $html === ''): ?>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-6 text-sm text-gray-600 dark:border-gray-800 dark:bg-gray-900/40 dark:text-gray-300">
                        Isi artikel ini belum tersedia. Hubungi admin untuk melengkapi konten panduan.
                    </div>
                <?php else: ?>
                    <div class="guide-content">
                        <?php echo $html; ?>

                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($prev || $next): ?>
                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($prev): ?>
                            <a href="<?php echo e(route('guides.show', ['slug' => (string) $prev['slug']])); ?>" wire:navigate class="block rounded-xl border border-gray-200 bg-white p-4 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900/30 dark:hover:bg-white/[0.03]">
                                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Sebelumnya</div>
                                <div class="mt-1 text-sm font-semibold text-gray-800 dark:text-white/90"><?php echo e((string) $prev['title']); ?></div>
                            </a>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($next): ?>
                            <a href="<?php echo e(route('guides.show', ['slug' => (string) $next['slug']])); ?>" wire:navigate class="block rounded-xl border border-gray-200 bg-white p-4 text-right hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900/30 dark:hover:bg-white/[0.03]">
                                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Berikutnya</div>
                                <div class="mt-1 text-sm font-semibold text-gray-800 dark:text-white/90"><?php echo e((string) $next['title']); ?></div>
                            </a>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <div class="space-y-4">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($toc) > 0): ?>
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="text-sm font-semibold text-gray-800 dark:text-white/90">Daftar isi</div>
                    <nav class="mt-3 space-y-1.5">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $toc; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                            <a href="#<?php echo e((string) $item['id']); ?>" class="<?php echo e((int) ($item['level'] ?? 2) === 3 ? 'pl-6 text-xs' : 'text-sm'); ?> block text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200">
                                <?php echo e((string) $item['title']); ?>

                            </a>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </nav>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($related)): ?>
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="text-sm font-semibold text-gray-800 dark:text-white/90">Artikel terkait</div>
                    <div class="mt-3 space-y-2">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $related; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                            <a href="<?php echo e(route('guides.show', ['slug' => (string) $item['slug']])); ?>" wire:navigate class="block rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-800 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900/30 dark:text-white/90 dark:hover:bg-white/[0.03]">
                                <?php echo e((string) $item['title']); ?>

                            </a>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
</div>
<?php /**PATH D:\Satu-POS\resources\views/livewire/guides/guide-show-page.blade.php ENDPATH**/ ?>