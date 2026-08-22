<script>
    import { cn } from '@/lib/utils';

    let {
        variant = 'default',
        size = 'default',
        class: className = '',
        type = 'button',
        disabled = false,
        onclick,
        children,
        ...rest
    } = $props();

    const variants = {
        default: 'bg-stone-900 text-stone-50 font-semibold hover:bg-stone-800 shadow-sm active:scale-[0.98]',
        primary: 'bg-stone-900 text-stone-50 font-semibold hover:bg-stone-800 shadow-sm active:scale-[0.98]',
        secondary: 'bg-stone-100 text-stone-800 hover:bg-stone-200 border border-stone-200 active:scale-[0.98]',
        destructive: 'bg-rose-600 text-white hover:bg-rose-700 shadow-sm active:scale-[0.98]',
        outline: 'border border-stone-300 bg-white text-stone-800 hover:bg-stone-100 hover:text-stone-900 shadow-xs active:scale-[0.98]',
        ghost: 'text-stone-700 hover:bg-stone-100 hover:text-stone-900',
        link: 'text-stone-900 underline-offset-4 hover:underline',
        success: 'bg-emerald-600 text-white hover:bg-emerald-700 shadow-sm active:scale-[0.98]',
    };

    const sizes = {
        default: 'h-9 px-4 py-2 text-sm',
        sm: 'h-8 rounded-lg px-3 text-xs',
        lg: 'h-10 rounded-lg px-6 text-sm',
        icon: 'h-9 w-9 p-0 flex items-center justify-center',
    };
</script>

<button
    {type}
    {disabled}
    {onclick}
    class={cn(
        'inline-flex items-center justify-center whitespace-nowrap rounded-lg font-medium transition-all duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-stone-400 disabled:pointer-events-none disabled:opacity-50 cursor-pointer gap-2 select-none',
        variants[variant] || variants.default,
        sizes[size] || sizes.default,
        className
    )}
    {...rest}
>
    {#if children}
        {@render children()}
    {/if}
</button>
