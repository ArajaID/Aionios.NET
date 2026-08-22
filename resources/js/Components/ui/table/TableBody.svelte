<script>
    import { cn } from '@/lib/utils';
    let { class: className = '', children, ...rest } = $props();
</script>

<tbody class={cn('divide-y divide-stone-800/60 font-normal', className)} {...rest}>
    {#if children}
        {@render children()}
    {/if}
</tbody>
