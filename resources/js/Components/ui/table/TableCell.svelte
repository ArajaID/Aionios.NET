<script>
    import { cn } from '@/lib/utils';
    let { class: className = '', children, ...rest } = $props();
</script>

<td class={cn('p-4 align-middle [&:has([role=checkbox])]:pr-0 text-stone-800', className)} {...rest}>
    {#if children}
        {@render children()}
    {/if}
</td>
