<script>
    import { cn } from '@/lib/utils';

    let {
        value = $bindable(''),
        class: className = '',
        disabled = false,
        required = false,
        id,
        name,
        onchange,
        children,
        ...rest
    } = $props();
</script>

<select
    {id}
    {name}
    {disabled}
    {required}
    bind:value
    {onchange}
    class={cn(
        'flex h-9 w-full rounded-lg border border-stone-300 bg-white px-3 py-1 text-sm text-stone-900 shadow-xs transition-all focus-visible:outline-none focus-visible:border-stone-700 focus-visible:ring-1 focus-visible:ring-stone-400/30 disabled:cursor-not-allowed disabled:bg-stone-100 disabled:opacity-60 cursor-pointer',
        className
    )}
    {...rest}
>
    {#if children}
        {@render children()}
    {/if}
</select>
