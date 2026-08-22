<script>
    import { cn } from '@/lib/utils';

    let {
        value,
        activeValue,
        class: className = '',
        children,
        ...rest
    } = $props();

    const isVisible = $derived(value === activeValue);
</script>

{#if isVisible}
    <div
        class={cn('mt-4 focus-visible:outline-none animate-in fade-in-50 duration-200', className)}
        {...rest}
    >
        {#if children}
            {@render children()}
        {/if}
    </div>
{/if}
