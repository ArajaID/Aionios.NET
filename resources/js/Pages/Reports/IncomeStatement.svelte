<script>
    import { router } from '@inertiajs/svelte';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
    import Button from '@/Components/ui/button/Button.svelte';
    import Input from '@/Components/ui/input/Input.svelte';
    import Card from '@/Components/ui/card/Card.svelte';
    import CardHeader from '@/Components/ui/card/CardHeader.svelte';
    import CardTitle from '@/Components/ui/card/CardTitle.svelte';
    import CardContent from '@/Components/ui/card/CardContent.svelte';
    import Badge from '@/Components/ui/badge/Badge.svelte';
    import { PieChart, Printer, TrendingUp, TrendingDown, DollarSign } from 'lucide-svelte';
    import { formatRupiah, formatDate } from '@/lib/utils';

    let {
        start_date = '',
        end_date = '',
        report = { revenues: [], total_revenue: 0, expenses: [], total_expense: 0, net_profit: 0 },
    } = $props();

    let startDate = $state(start_date || '');
    let endDate = $state(end_date || '');

    function handleFilter() {
        router.get('/reports/income-statement', { start_date: startDate, end_date: endDate }, { preserveState: true });
    }
</script>

<AuthenticatedLayout
    title="Laporan Laba Rugi (Income Statement)"
    breadcrumbs={[
        { label: 'Laporan', href: '/reports/income-statement' },
        { label: 'Laba Rugi' },
    ]}
>
    <div class="max-w-4xl mx-auto space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 no-print">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-stone-900 flex items-center gap-2">
                    <PieChart class="h-5 w-5 text-stone-800" />
                    Laporan Laba Rugi (Income Statement)
                </h1>
                <p class="text-xs text-stone-500 mt-1">Akumulasi pendapatan dan beban operasional pada rentang waktu yang ditentukan.</p>
            </div>

            <Button variant="outline" size="sm" onclick={() => window.print()}>
                <Printer class="h-3.5 w-3.5 mr-1" />
                Cetak / Simpan PDF
            </Button>
        </div>

        <!-- Filter Bar (no-print) -->
        <div class="rounded-xl border border-stone-200 bg-white p-4 no-print">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                <div class="space-y-1">
                    <label for="is_start" class="text-xs font-semibold text-stone-700">Dari Tanggal</label>
                    <Input id="is_start" type="date" bind:value={startDate} />
                </div>
                <div class="space-y-1">
                    <label for="is_end" class="text-xs font-semibold text-stone-700">Sampai Tanggal</label>
                    <Input id="is_end" type="date" bind:value={endDate} />
                </div>
                <Button variant="default" size="sm" onclick={handleFilter}>
                    Tampilkan Laporan
                </Button>
            </div>
        </div>

        <!-- Financial Statement Document -->
        <div class="rounded-2xl border border-stone-200 bg-white p-8 shadow-2xl backdrop-blur-xl print:border-0 print:bg-white print:text-black print:p-0">
            <!-- Header -->
            <div class="text-center border-b border-stone-200 pb-6 print:border-stone-300">
                <h2 class="text-lg font-bold text-stone-900 print:text-black">PT AIONIOS SOLUSI TELEMATIKA</h2>
                <h1 class="text-xl font-black text-stone-800 print:text-indigo-700 tracking-tight mt-1">LAPORAN LABA RUGI</h1>
                <p class="text-xs text-stone-500 print:text-stone-600 mt-1 font-mono">
                    Periode: {formatDate(start_date)} s/d {formatDate(end_date)}
                </p>
            </div>

            <!-- Statement Content -->
            <div class="py-6 space-y-6 text-xs">
                <!-- 1. PENDAPATAN (REVENUE) -->
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-700 print:text-emerald-700 border-b border-stone-200 print:border-stone-300 pb-2 flex justify-between">
                        <span>I. PENDAPATAN USAHA (REVENUE)</span>
                    </h3>

                    <table class="w-full mt-2 text-left">
                        <tbody class="divide-y divide-stone-100 print:divide-stone-200">
                            {#each report.revenues as rev}
                                <tr>
                                    <td class="py-2 text-stone-700 print:text-stone-800 pl-4">
                                        <span class="font-mono text-stone-500 mr-2">{rev.code}</span>
                                        {rev.name}
                                    </td>
                                    <td class="py-2 text-right font-mono font-medium text-stone-900 print:text-black">
                                        {formatRupiah(rev.amount)}
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                        <tfoot class="border-t border-stone-300 font-bold">
                            <tr>
                                <td class="py-2.5 text-stone-800 print:text-black pl-4">TOTAL PENDAPATAN USAHA</td>
                                <td class="py-2.5 text-right font-mono text-emerald-700 print:text-emerald-700 text-sm">
                                    {formatRupiah(report.total_revenue)}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- 2. BEBAN OPERASIONAL (EXPENSES) -->
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-rose-700 print:text-rose-700 border-b border-stone-200 print:border-stone-300 pb-2 flex justify-between">
                        <span>II. BEBAN & PENGELUARAN OPERASIONAL (EXPENSES)</span>
                    </h3>

                    <table class="w-full mt-2 text-left">
                        <tbody class="divide-y divide-stone-100 print:divide-stone-200">
                            {#each report.expenses as exp}
                                <tr>
                                    <td class="py-2 text-stone-700 print:text-stone-800 pl-4">
                                        <span class="font-mono text-stone-500 mr-2">{exp.code}</span>
                                        {exp.name}
                                    </td>
                                    <td class="py-2 text-right font-mono font-medium text-stone-900 print:text-black">
                                        {formatRupiah(exp.amount)}
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                        <tfoot class="border-t border-stone-300 font-bold">
                            <tr>
                                <td class="py-2.5 text-stone-800 print:text-black pl-4">TOTAL BEBAN OPERASIONAL</td>
                                <td class="py-2.5 text-right font-mono text-rose-700 print:text-rose-700 text-sm">
                                    ({formatRupiah(report.total_expense)})
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- 3. LABA / RUGI BERSIH -->
                <div class="p-4 rounded-xl border-2 border-indigo-500/40 bg-stone-50 print:bg-stone-100 print:border-black flex items-center justify-between">
                    <div>
                        <span class="text-xs uppercase font-extrabold text-stone-900 print:text-black block tracking-wider">
                            LABA / (RUGI) BERSIH OPERASIONAL
                        </span>
                        <span class="text-[11px] text-stone-500 print:text-stone-600">Total Pendapatan - Total Beban</span>
                    </div>

                    <span class="text-xl font-black font-mono {report.net_profit >= 0 ? 'text-emerald-700 print:text-emerald-700' : 'text-rose-700 print:text-rose-700'}">
                        {formatRupiah(report.net_profit)}
                    </span>
                </div>
            </div>

            <!-- Signatures in print -->
            <div class="hidden print:grid grid-cols-2 gap-8 pt-12 text-center text-xs">
                <div>
                    <p class="text-stone-600">Disiapkan Oleh,</p>
                    <div class="h-16"></div>
                    <p class="font-bold underline">Admin Keuangan</p>
                </div>
                <div>
                    <p class="text-stone-600">Disetujui Oleh,</p>
                    <div class="h-16"></div>
                    <p class="font-bold underline">Direktur / Owner</p>
                </div>
            </div>
        </div>
    </div>
</AuthenticatedLayout>
