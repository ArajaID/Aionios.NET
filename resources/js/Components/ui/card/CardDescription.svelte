<script>
    import { cn } from '@/lib/utils';
    let { class: className = '', children, ...rest } = $props();
</script>

<p class={cn('text-xs text-stone-500', className)} {...rest}>
    {#if children}
        {@render children()}
    {/if}
</p>
