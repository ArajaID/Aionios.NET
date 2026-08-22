<script>
    import { cn } from '@/lib/utils';
    import { Info, AlertTriangle, CheckCircle2, XCircle } from 'lucide-svelte';

    let {
        variant = 'info',
        title = '',
        class: className = '',
        children,
        ...rest
    } = $props();

    const variants = {
        info: 'border-blue-200 bg-blue-50/80 text-blue-900',
        warning: 'border-amber-200 bg-amber-50/80 text-amber-900',
        success: 'border-emerald-200 bg-emerald-50/80 text-emerald-900',
        destructive: 'border-rose-200 bg-rose-50/80 text-rose-900',
    };
</script>

<div
    role="alert"
    class={cn(
        'relative w-full rounded-xl border p-4 flex gap-3 text-sm transition-all shadow-xs',
        variants[variant] || variants.info,
        className
    )}
    {...rest}
>
    <div class="shrink-0 mt-0.5">
        {#if variant === 'info'}
            <Info class="h-4 w-4 text-blue-600" />
        {:else if variant === 'warning'}
            <AlertTriangle class="h-4 w-4 text-amber-600" />
        {:else if variant === 'success'}
            <CheckCircle2 class="h-4 w-4 text-emerald-600" />
        {:else if variant === 'destructive'}
            <XCircle class="h-4 w-4 text-rose-600" />
        {/if}
    </div>
    <div class="flex-1">
        {#if title}
            <h5 class="font-bold leading-tight mb-1">{title}</h5>
        {/if}
        <div class="text-xs leading-relaxed opacity-95">
            {#if children}
                {@render children()}
            {/if}
        </div>
    </div>
</div>
