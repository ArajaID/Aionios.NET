<script>
    import { Link } from '@inertiajs/svelte';
    import { ChevronRight, Home } from 'lucide-svelte';
    import { cn } from '@/lib/utils';

    let { items = [], class: className = '' } = $props();
</script>

<nav aria-label="Breadcrumb" class={cn('flex items-center text-xs text-stone-500', className)}>
    <ol class="flex items-center gap-1.5 flex-wrap">
        <li>
            <Link href="/dashboard" class="flex items-center gap-1 hover:text-stone-900 transition-colors">
                <Home class="h-3.5 w-3.5" />
                <span>Dashboard</span>
            </Link>
        </li>

        {#each items as item, i}
            <li class="flex items-center gap-1.5">
                <ChevronRight class="h-3 w-3 text-stone-400" />
                {#if item.href && i < items.length - 1}
                    <Link href={item.href} class="hover:text-stone-900 transition-colors">
                        {item.label}
                    </Link>
                {:else}
                    <span class="font-bold text-stone-900">{item.label}</span>
                {/if}
            </li>
        {/each}
    </ol>
</nav>
