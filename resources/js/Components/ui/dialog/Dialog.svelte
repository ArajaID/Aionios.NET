<script>
    import { cn } from '@/lib/utils';
    import { X } from 'lucide-svelte';

    let {
        open = $bindable(false),
        title = '',
        description = '',
        class: className = '',
        maxWidth = 'max-w-lg',
        children,
        footer,
    } = $props();

    function close() {
        open = false;
    }

    function handleKeydown(e) {
        if (e.key === 'Escape') close();
    }
</script>

<svelte:window onkeydown={handleKeydown} />

{#if open}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 overflow-y-auto">
        <!-- Backdrop -->
        <button
            type="button"
            class="fixed inset-0 bg-stone-900/60 backdrop-blur-xs transition-opacity animate-in fade-in duration-200 cursor-default"
            onclick={close}
            aria-label="Close backdrop"
        ></button>

        <!-- Dialog Box -->
        <div
            class={cn(
                'relative z-50 w-full rounded-2xl border border-stone-200 bg-white text-stone-900 shadow-2xl transition-all duration-200 animate-in zoom-in-95',
                maxWidth,
                className
            )}
        >
            <div class="flex items-center justify-between border-b border-stone-200 px-6 py-4 bg-stone-50/80 rounded-t-2xl">
                <div>
                    {#if title}
                        <h2 class="text-base font-bold text-stone-900">{title}</h2>
                    {/if}
                    {#if description}
                        <p class="text-xs text-stone-500 mt-0.5">{description}</p>
                    {/if}
                </div>
                <button
                    type="button"
                    onclick={close}
                    class="rounded-lg p-1 text-stone-400 hover:bg-stone-200 hover:text-stone-700 transition-colors"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <div class="px-6 py-5 max-h-[75vh] overflow-y-auto">
                {#if children}
                    {@render children()}
                {/if}
            </div>

            {#if footer}
                <div class="flex items-center justify-end gap-3 border-t border-stone-200 px-6 py-4 bg-stone-50/80 rounded-b-2xl">
                    {@render footer()}
                </div>
            {/if}
        </div>
    </div>
{/if}
