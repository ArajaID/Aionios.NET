<script>
    import { router } from '@inertiajs/svelte';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
    import Button from '@/Components/ui/button/Button.svelte';
    import Input from '@/Components/ui/input/Input.svelte';
    import Badge from '@/Components/ui/badge/Badge.svelte';
    import { Scale, Printer, CheckCircle2, AlertTriangle } from 'lucide-svelte';
    import { formatRupiah, formatDate } from '@/lib/utils';

    let {
        as_of_date = '',
        report = { assets: [], total_assets: 0, liabilities: [], total_liabilities: 0, equity: [], total_equity: 0, total_liabilities_equity: 0, is_balanced: true },
    } = $props();

    let asOfDate = $state(as_of_date || '');

    function handleFilter() {
        router.get('/reports/balance-sheet', { as_of_date: asOfDate }, { preserveState: true });
    }
</script>

<AuthenticatedLayout
    title="Neraca Keuangan (Balance Sheet)"
    breadcrumbs={[
        { label: 'Laporan', href: '/reports/balance-sheet' },
        { label: 'Neraca Keuangan' },
    ]}
>
    <div class="max-w-5xl mx-auto space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 no-print">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-stone-900 flex items-center gap-2">
                    <Scale class="h-5 w-5 text-stone-800" />
                    Laporan Neraca Keuangan (Balance Sheet)
                </h1>
                <p class="text-xs text-stone-500 mt-1">Posisi aset (aktiva), kewajiban (hutang), dan ekuitas (modal) perusahaan.</p>
            </div>

            <Button variant="outline" size="sm" onclick={() => window.print()}>
                <Printer class="h-3.5 w-3.5 mr-1" />
                Cetak / Simpan PDF
            </Button>
        </div>

        <!-- Filter Bar -->
        <div class="rounded-xl border border-stone-200 bg-white p-4 no-print flex items-end gap-3">
            <div class="space-y-1">
                <label for="bs_date" class="text-xs font-semibold text-stone-700">Posisi Per Tanggal (Cut-off)</label>
                <Input id="bs_date" type="date" bind:value={asOfDate} />
            </div>
            <Button variant="default" size="sm" onclick={handleFilter}>
                Tampilkan Neraca
            </Button>
        </div>

        <!-- Balance Sheet Document -->
        <div class="rounded-2xl border border-stone-200 bg-white p-8 shadow-2xl backdrop-blur-xl print:border-0 print:bg-white print:text-black print:p-0 space-y-6">
            <!-- Header -->
            <div class="text-center border-b border-stone-200 pb-6 print:border-stone-300">
                <h2 class="text-lg font-bold text-stone-900 print:text-black">PT AIONIOS SOLUSI TELEMATIKA</h2>
                <h1 class="text-xl font-black text-stone-800 print:text-indigo-700 tracking-tight mt-1">NERACA KEUANGAN (BALANCE SHEET)</h1>
                <p class="text-xs text-stone-500 print:text-stone-600 mt-1 font-mono">
                    Per Tanggal: {formatDate(as_of_date)}
                </p>
            </div>

            <!-- Two-Column Financial Layout (Assets vs Liabilities & Equity) -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 text-xs">
                <!-- LEFT: ASSETS (ASET) -->
                <div class="space-y-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-stone-800 print:text-indigo-700 border-b border-stone-200 print:border-stone-300 pb-2">
                        I. ASET / AKTIVA (ASSETS)
                    </h3>

                    <table class="w-full text-left">
                        <tbody class="divide-y divide-stone-100 print:divide-stone-200">
                            {#each report.assets as ast}
                                <tr>
                                    <td class="py-2 text-stone-700 print:text-stone-800">
                                        <span class="font-mono text-stone-500 mr-2">{ast.code}</span>
                                        {ast.name}
                                    </td>
                                    <td class="py-2 text-right font-mono font-medium text-stone-900 print:text-black">
                                        {formatRupiah(ast.amount)}
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                        <tfoot class="border-t-2 border-stone-300 font-bold">
                            <tr>
                                <td class="py-3 text-stone-900 print:text-black">TOTAL ASET</td>
                                <td class="py-3 text-right font-mono text-stone-800 print:text-indigo-700 text-sm">
                                    {formatRupiah(report.total_assets)}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- RIGHT: LIABILITIES & EQUITY -->
                <div class="space-y-6">
                    <!-- Kewajiban -->
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-amber-800 print:text-amber-700 border-b border-stone-200 print:border-stone-300 pb-2">
                            II. KEWAJIBAN / HUTANG (LIABILITIES)
                        </h3>

                        <table class="w-full text-left mt-2">
                            <tbody class="divide-y divide-stone-100 print:divide-stone-200">
                                {#each report.liabilities as lib}
                                    <tr>
                                        <td class="py-2 text-stone-700 print:text-stone-800">
                                            <span class="font-mono text-stone-500 mr-2">{lib.code}</span>
                                            {lib.name}
                                        </td>
                                        <td class="py-2 text-right font-mono font-medium text-stone-900 print:text-black">
                                            {formatRupiah(lib.amount)}
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                            <tfoot class="border-t border-stone-300 font-bold">
                                <tr>
                                    <td class="py-2.5 text-stone-800 print:text-black">TOTAL KEWAJIBAN</td>
                                    <td class="py-2.5 text-right font-mono text-amber-800 print:text-amber-700">
                                        {formatRupiah(report.total_liabilities)}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Ekuitas -->
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-purple-700 print:text-purple-700 border-b border-stone-200 print:border-stone-300 pb-2">
                            III. EKUITAS / MODAL (EQUITY)
                        </h3>

                        <table class="w-full text-left mt-2">
                            <tbody class="divide-y divide-stone-100 print:divide-stone-200">
                                {#each report.equity as eq}
                                    <tr>
                                        <td class="py-2 text-stone-700 print:text-stone-800">
                                            <span class="font-mono text-stone-500 mr-2">{eq.code}</span>
                                            {eq.name}
                                        </td>
                                        <td class="py-2 text-right font-mono font-medium text-stone-900 print:text-black">
                                            {formatRupiah(eq.amount)}
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                            <tfoot class="border-t border-stone-300 font-bold">
                                <tr>
                                    <td class="py-2.5 text-stone-800 print:text-black">TOTAL EKUITAS</td>
                                    <td class="py-2.5 text-right font-mono text-purple-700 print:text-purple-700">
                                        {formatRupiah(report.total_equity)}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Total Kewajiban & Ekuitas -->
                    <div class="border-t-2 border-stone-300 pt-3 flex justify-between font-bold text-xs">
                        <span class="text-stone-900 print:text-black">TOTAL KEWAJIBAN & EKUITAS</span>
                        <span class="font-mono text-stone-800 print:text-indigo-700 text-sm">
                            {formatRupiah(report.total_liabilities_equity)}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Balance Verification Banner -->
            <div class="mt-8 p-4 rounded-xl border border-stone-200 bg-stone-50 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    {#if report.is_balanced}
                        <CheckCircle2 class="h-5 w-5 text-emerald-700" />
                        <span class="text-xs font-bold text-emerald-700">
                            NERACA SEIMBANG: ASET ({formatRupiah(report.total_assets)}) = KEWAJIBAN & EKUITAS ({formatRupiah(report.total_liabilities_equity)})
                        </span>
                    {:else}
                        <AlertTriangle class="h-5 w-5 text-rose-700" />
                        <span class="text-xs font-bold text-rose-700">
                            NERACA BELUM SEIMBANG (SELISIH: {formatRupiah(Math.abs(report.total_assets - report.total_liabilities_equity))})
                        </span>
                    {/if}
                </div>
            </div>
        </div>
    </div>
</AuthenticatedLayout>
