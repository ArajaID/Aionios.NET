<script>
    import { cn } from '@/lib/utils';
    let { class: className = '', children, ...rest } = $props();
</script>

<th class={cn('h-10 px-4 text-left align-middle font-semibold text-xs text-stone-600 bg-stone-50/80 [&:has([role=checkbox])]:pr-0', className)} {...rest}>
    {#if children}
        {@render children()}
    {/if}
</th>
