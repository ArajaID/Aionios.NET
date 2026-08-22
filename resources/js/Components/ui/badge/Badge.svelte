<script>
    import { cn } from '@/lib/utils';

    let {
        variant = 'default',
        class: className = '',
        children,
        ...rest
    } = $props();

    const variants = {
        default: 'bg-stone-100 text-stone-800 border-stone-300',
        primary: 'bg-stone-900 text-stone-50 border-stone-800',
        success: 'bg-emerald-50 text-emerald-700 border-emerald-200 font-medium',
        warning: 'bg-amber-50 text-amber-800 border-amber-200 font-medium',
        danger: 'bg-rose-50 text-rose-700 border-rose-200 font-medium',
        destructive: 'bg-rose-50 text-rose-700 border-rose-200 font-medium',
        outline: 'border-stone-300 text-stone-700 bg-white font-medium',
        purple: 'bg-purple-50 text-purple-700 border-purple-200 font-medium',
        cyan: 'bg-cyan-50 text-cyan-700 border-cyan-200 font-medium',
    };
</script>

<span
    class={cn(
        'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium transition-colors select-none shadow-2xs',
        variants[variant] || variants.default,
        className
    )}
    {...rest}
>
    {#if children}
        {@render children()}
    {/if}
</span>
