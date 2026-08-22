<script>
    import { router } from '@inertiajs/svelte';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
    import Button from '@/Components/ui/button/Button.svelte';
    import Input from '@/Components/ui/input/Input.svelte';
    import { TrendingUp, Printer } from 'lucide-svelte';
    import { formatRupiah, formatDate } from '@/lib/utils';

    let {
        start_date = '',
        end_date = '',
        report = {
            initial_capital: 0,
            capital_additions: 0,
            net_profit: 0,
            drawings: 0,
            ending_capital: 0,
        },
    } = $props();

    let startDate = $state(start_date || '');
    let endDate = $state(end_date || '');

    function handleFilter() {
        router.get('/reports/equity-changes', { start_date: startDate, end_date: endDate }, { preserveState: true });
    }
</script>

<AuthenticatedLayout
    title="Laporan Perubahan Modal (Equity Changes)"
    breadcrumbs={[
        { label: 'Laporan', href: '/reports/equity-changes' },
        { label: 'Perubahan Modal' },
    ]}
>
    <div class="max-w-4xl mx-auto space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 no-print">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-stone-900 flex items-center gap-2">
                    <TrendingUp class="h-5 w-5 text-stone-800" />
                    Laporan Perubahan Modal (Equity Statement)
                </h1>
                <p class="text-xs text-stone-500 mt-1">Pergerakan modal awal, penambahan investasi, laba bersih, dan prive pemilik.</p>
            </div>

            <Button variant="outline" size="sm" onclick={() => window.print()}>
                <Printer class="h-3.5 w-3.5 mr-1" />
                Cetak Laporan
            </Button>
        </div>

        <!-- Filter Bar -->
        <div class="rounded-xl border border-stone-200 bg-white p-4 no-print flex items-end gap-3">
            <div class="space-y-1">
                <label for="eq_start" class="text-xs font-semibold text-stone-700">Dari Tanggal</label>
                <Input id="eq_start" type="date" bind:value={startDate} />
            </div>
            <div class="space-y-1">
                <label for="eq_end" class="text-xs font-semibold text-stone-700">Sampai Tanggal</label>
                <Input id="eq_end" type="date" bind:value={endDate} />
            </div>
            <Button variant="default" size="sm" onclick={handleFilter}>
                Tampilkan Laporan
            </Button>
        </div>

        <!-- Document -->
        <div class="rounded-2xl border border-stone-200 bg-white p-8 shadow-2xl backdrop-blur-xl print:border-0 print:bg-white print:text-black print:p-0 space-y-6 text-xs">
            <div class="text-center border-b border-stone-200 pb-6 print:border-stone-300">
                <h2 class="text-lg font-bold text-stone-900 print:text-black">PT AIONIOS SOLUSI TELEMATIKA</h2>
                <h1 class="text-xl font-black text-stone-800 print:text-indigo-700 tracking-tight mt-1">LAPORAN PERUBAHAN MODAL</h1>
                <p class="text-xs text-stone-500 print:text-stone-600 mt-1 font-mono">
                    Periode: {formatDate(start_date)} s/d {formatDate(end_date)}
                </p>
            </div>

            <table class="w-full text-left">
                <tbody class="divide-y divide-stone-100">
                    <tr>
                        <td class="py-3 font-semibold text-stone-800">Saldo Modal Awal Periode</td>
                        <td class="py-3 text-right font-mono text-stone-900 font-bold">{formatRupiah(report.initial_capital)}</td>
                    </tr>
                    <tr>
                        <td class="py-2.5 pl-4 text-stone-700">Penambahan Modal Disetor (Investasi)</td>
                        <td class="py-2.5 text-right font-mono text-emerald-700">+{formatRupiah(report.capital_additions)}</td>
                    </tr>
                    <tr>
                        <td class="py-2.5 pl-4 text-stone-700">Laba Bersih Operasional Periode Ini</td>
                        <td class="py-2.5 text-right font-mono {report.net_profit >= 0 ? 'text-emerald-700' : 'text-rose-700'}">
                            {report.net_profit >= 0 ? `+${formatRupiah(report.net_profit)}` : `(${formatRupiah(Math.abs(report.net_profit))})`}
                        </td>
                    </tr>
                    <tr>
                        <td class="py-2.5 pl-4 text-stone-700">Prive (Penarikan Pribadi Pemilik)</td>
                        <td class="py-2.5 text-right font-mono text-rose-700">-{formatRupiah(report.drawings)}</td>
                    </tr>
                </tbody>
                <tfoot class="border-t-2 border-stone-300 font-bold text-sm">
                    <tr>
                        <td class="py-4 text-stone-900">SALDO MODAL AKHIR PERIODE</td>
                        <td class="py-4 text-right font-mono text-stone-800 font-black">{formatRupiah(report.ending_capital)}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</AuthenticatedLayout>
