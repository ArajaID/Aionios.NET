<script>
    import { cn, formatRupiah } from '@/lib/utils';
    import { TrendingUp } from 'lucide-svelte';

    let {
        data = [], // [{ period: 'Mar 2026', revenue: 1500000 }, ...]
        title = 'Tren Pendapatan & Pertumbuhan Penjualan',
        height = 200,
        class: className = '',
    } = $props();

    const maxVal = $derived(Math.max(...data.map((d) => Number(d.revenue) || 0), 1000000));
    const totalRev = $derived(data.reduce((sum, d) => sum + (Number(d.revenue) || 0), 0));
    const avgRev = $derived(data.length > 0 ? Math.round(totalRev / data.length) : 0);
</script>

<div class={cn('w-full rounded-2xl border border-stone-200 bg-white p-6 shadow-xs', className)}>
    <!-- Header with clean summary badge -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-6 border-b border-stone-100 pb-4">
        <div>
            <h4 class="text-base font-bold text-stone-900 tracking-tight">{title}</h4>
            <p class="text-xs text-stone-500 mt-0.5">Analisis histori penerimaan kas 6 bulan terakhir</p>
        </div>

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-stone-100 text-stone-800 border border-stone-200 shadow-2xs">
                <TrendingUp class="h-3.5 w-3.5 text-stone-700" />
                Rata-rata: {formatRupiah(avgRev)}/bln
            </span>
        </div>
    </div>

    <!-- Chart Canvas with background guide lines and tracks -->
    <div class="relative flex items-end gap-3 sm:gap-6 pt-4" style="height: {height}px;">
        <!-- Dotted background grid lines -->
        <div class="pointer-events-none absolute inset-x-0 top-0 border-b border-dashed border-stone-200/80"></div>
        <div class="pointer-events-none absolute inset-x-0 top-1/2 border-b border-dashed border-stone-200/60"></div>
        <div class="pointer-events-none absolute inset-x-0 bottom-7 border-b border-stone-200"></div>

        {#each data as item}
            {@const heightPercent = Math.max(10, Math.round(((Number(item.revenue) || 0) / maxVal) * 100))}
            <div class="group relative flex flex-1 flex-col items-center h-full justify-end z-10">
                <!-- Tooltip hover -->
                <div
                    class="pointer-events-none absolute -top-10 z-30 hidden rounded-lg bg-stone-900 px-3 py-1.5 text-xs font-bold text-stone-50 shadow-xl group-hover:flex items-center gap-1.5 whitespace-nowrap animate-in fade-in zoom-in-95 font-mono"
                >
                    <span>{formatRupiah(item.revenue)}</span>
                </div>

                <!-- Column Container with light track -->
                <div class="relative w-full max-w-[54px] h-[calc(100%-28px)] rounded-xl bg-stone-100 flex items-end p-1 transition-all group-hover:bg-stone-200/60">
                    <!-- Value Bar -->
                    <div
                        class="w-full rounded-lg bg-gradient-to-t from-stone-900 via-stone-800 to-stone-700 shadow-xs transition-all duration-300 group-hover:from-stone-950 group-hover:to-stone-800 group-hover:shadow-md"
                        style="height: {heightPercent}%;"
                    ></div>
                </div>

                <!-- Month Label -->
                <span class="mt-2 text-xs font-bold text-stone-600 truncate max-w-full text-center group-hover:text-stone-900 transition-colors">
                    {item.period}
                </span>
            </div>
        {/each}
    </div>
</div>
