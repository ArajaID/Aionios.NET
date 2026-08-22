<script>
    import { cn } from '@/lib/utils';

    let {
        title = '',
        value = '',
        subtitle = '',
        trend = null,
        trendType = 'positive',
        color = 'indigo',
        class: className = '',
        icon,
    } = $props();

    const colorAccents = {
        indigo: 'border-indigo-100 text-indigo-700 bg-indigo-50/80',
        emerald: 'border-emerald-100 text-emerald-700 bg-emerald-50/80',
        amber: 'border-amber-100 text-amber-800 bg-amber-50/80',
        rose: 'border-rose-100 text-rose-700 bg-rose-50/80',
        cyan: 'border-cyan-100 text-cyan-700 bg-cyan-50/80',
        purple: 'border-purple-100 text-purple-700 bg-purple-50/80',
        stone: 'border-stone-200 text-stone-700 bg-stone-100',
    };
</script>

<div
    class={cn(
        'relative overflow-hidden rounded-2xl border border-stone-200/90 bg-white p-5 shadow-xs transition-all hover:border-stone-300 hover:shadow-md',
        className
    )}
>
    <div class="relative z-10 flex items-start justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">{title}</p>
            <h4 class="mt-1.5 text-2xl font-black tracking-tight text-stone-900 font-mono">{value}</h4>

            {#if subtitle || trend}
                <div class="mt-2 flex items-center gap-2 text-xs">
                    {#if trend}
                        <span
                            class={cn(
                                'rounded-md px-1.5 py-0.5 font-bold',
                                trendType === 'positive'
                                    ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                    : trendType === 'negative'
                                    ? 'bg-rose-50 text-rose-700 border border-rose-200'
                                    : 'bg-stone-100 text-stone-700 border border-stone-200'
                            )}
                        >
                            {trend}
                        </span>
                    {/if}
                    {#if subtitle}
                        <span class="text-stone-500 font-medium">{subtitle}</span>
                    {/if}
                </div>
            {/if}
        </div>

        {#if icon}
            <div class={cn('flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border shadow-2xs', colorAccents[color] || colorAccents.stone)}>
                {@render icon()}
            </div>
        {/if}
    </div>
</div>
