<script>
    import { Link } from '@inertiajs/svelte';
    import { cn } from '@/lib/utils';
    import { ChevronLeft, ChevronRight } from 'lucide-svelte';

    let { links = [], class: className = '' } = $props();

    function isPrevious(label) {
        return label.includes('Previous') || label.includes('&laquo;') || label.includes('«');
    }

    function isNext(label) {
        return label.includes('Next') || label.includes('&raquo;') || label.includes('»');
    }
</script>

{#if links && links.length > 3}
    <div class={cn('flex flex-wrap items-center justify-between gap-2 pt-4', className)}>
        <p class="text-xs text-stone-500">
            Menampilkan data paginasi sistem
        </p>

        <div class="flex items-center gap-1">
            {#each links as link, i}
                {#if link.url}
                    <Link
                        href={link.url}
                        class={cn(
                            'inline-flex h-8 min-w-8 items-center justify-center rounded-lg px-2.5 text-xs font-medium transition-colors select-none shadow-2xs gap-1',
                            link.active
                                ? 'bg-stone-900 text-stone-50 font-bold'
                                : 'text-stone-700 hover:bg-stone-100 hover:text-stone-900 border border-stone-300 bg-white'
                        )}
                    >
                        {#if isPrevious(link.label)}
                            <ChevronLeft class="h-3.5 w-3.5" />
                            <span>Sebelumnya</span>
                        {:else if isNext(link.label)}
                            <span>Berikutnya</span>
                            <ChevronRight class="h-3.5 w-3.5" />
                        {:else}
                            <span>{link.label}</span>
                        {/if}
                    </Link>
                {:else}
                    <span
                        class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg px-2.5 text-xs text-stone-300 border border-stone-200 bg-stone-50 select-none cursor-not-allowed gap-1"
                    >
                        {#if isPrevious(link.label)}
                            <ChevronLeft class="h-3.5 w-3.5" />
                            <span>Sebelumnya</span>
                        {:else if isNext(link.label)}
                            <span>Berikutnya</span>
                            <ChevronRight class="h-3.5 w-3.5" />
                        {:else}
                            <span>{link.label}</span>
                        {/if}
                    </span>
                {/if}
            {/each}
        </div>
    </div>
{/if}
