<script>
    import { cn } from '@/lib/utils';
    let { class: className = '', children, ...rest } = $props();
</script>

<h3 class={cn('text-base font-bold leading-tight tracking-tight text-stone-900', className)} {...rest}>
    {#if children}
        {@render children()}
    {/if}
</h3>
