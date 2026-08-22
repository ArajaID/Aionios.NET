<script>
    import { cn } from '@/lib/utils';
    let { class: className = '', children, ...rest } = $props();
</script>

<div
    class={cn(
        'rounded-xl border border-stone-200/90 bg-white text-stone-900 shadow-sm transition-all',
        className
    )}
    {...rest}
>
    {#if children}
        {@render children()}
    {/if}
</div>
