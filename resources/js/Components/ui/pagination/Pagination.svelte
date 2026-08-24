<script>
    import { Link } from '@inertiajs/svelte';
    import { cn } from '@/lib/utils';

    let { links = [], class: className = '' } = $props();

    function cleanLabel(label, index, total) {
        if (!label && label !== 0) return '';
        const strLabel = String(label);

        if (index === 0) {
            return 'Sebelumnya';
        }
        if (index === total - 1) {
            return 'Berikutnya';
        }

        return strLabel
            .replace(/&laquo;/gi, '')
            .replace(/&raquo;/gi, '')
            .replace(/&hellip;/gi, '...')
            .replace(/«/g, '')
            .replace(/»/g, '')
            .replace(/previous/gi, 'Sebelumnya')
            .replace(/next/gi, 'Berikutnya')
            .trim();
    }
</script>

{#if links && links.length > 3}
    <div class={cn('flex flex-wrap items-center justify-between gap-2 pt-4', className)}>
        <p class="text-xs text-stone-500">
            Menampilkan data paginasi
        </p>

        <div class="flex items-center gap-1">
            {#each links as link, i}
                {@const labelText = cleanLabel(link.label, i, links.length)}
                {#if link.url}
                    <Link
                        href={link.url}
                        class={cn(
                            'inline-flex h-8 min-w-8 items-center justify-center rounded-lg px-3 text-xs font-medium transition-colors select-none shadow-2xs',
                            link.active
                                ? 'bg-stone-900 text-stone-50 font-bold'
                                : 'text-stone-700 hover:bg-stone-100 hover:text-stone-900 border border-stone-300 bg-white'
                        )}
                    >
                        {labelText}
                    </Link>
                {:else}
                    <span
                        class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg px-3 text-xs text-stone-400 border border-stone-200 bg-stone-50 select-none cursor-not-allowed"
                    >
                        {labelText}
                    </span>
                {/if}
            {/each}
        </div>
    </div>
{/if}
