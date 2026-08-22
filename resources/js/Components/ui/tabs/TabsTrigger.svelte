<script>
    import { cn } from '@/lib/utils';

    let {
        value,
        activeValue,
        onclick,
        class: className = '',
        children,
        ...rest
    } = $props();

    const isActive = $derived(value === activeValue);
</script>

<button
    type="button"
    {onclick}
    class={cn(
        'inline-flex items-center justify-center whitespace-nowrap rounded-lg px-3.5 py-1.5 text-xs font-semibold ring-offset-background transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 cursor-pointer gap-1.5',
        isActive
            ? 'bg-white text-stone-900 shadow-xs font-bold border border-stone-200/80'
            : 'text-stone-600 hover:text-stone-900 hover:bg-stone-100/60',
        className
    )}
    {...rest}
>
    {#if children}
        {@render children()}
    {/if}
</button>
