<script>
    import { cn } from '@/lib/utils';

    let {
        type = 'text',
        value = $bindable(''),
        class: className = '',
        placeholder = '',
        disabled = false,
        required = false,
        id,
        name,
        oninput,
        onchange,
        ...rest
    } = $props();
</script>

<input
    {type}
    {id}
    {name}
    {placeholder}
    {disabled}
    {required}
    bind:value
    {oninput}
    {onchange}
    class={cn(
        'flex h-9 w-full rounded-lg border border-stone-300 bg-white px-3 py-1 text-sm text-stone-900 placeholder:text-stone-400 shadow-xs transition-all focus-visible:outline-none focus-visible:border-stone-700 focus-visible:ring-1 focus-visible:ring-stone-400/30 disabled:cursor-not-allowed disabled:bg-stone-100 disabled:opacity-60',
        className
    )}
    {...rest}
/>
