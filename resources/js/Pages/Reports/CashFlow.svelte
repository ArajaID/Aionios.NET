<script>
    import { router } from '@inertiajs/svelte';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
    import Button from '@/Components/ui/button/Button.svelte';
    import Input from '@/Components/ui/input/Input.svelte';
    import { DollarSign, Printer } from 'lucide-svelte';
    import { formatRupiah, formatDate } from '@/lib/utils';

    let {
        start_date = '',
        end_date = '',
        report = {
            operating_inflows: [],
            total_operating_inflows: 0,
            operating_outflows: [],
            total_operating_outflows: 0,
            net_operating_cash_flow: 0,
            financing_inflows: [],
            total_financing_inflows: 0,
            financing_outflows: [],
            total_financing_outflows: 0,
            net_financing_cash_flow: 0,
            net_cash_change: 0,
            opening_cash: 0,
            closing_cash: 0,
        },
    } = $props();

    let startDate = $state(start_date || '');
    let endDate = $state(end_date || '');

    function handleFilter() {
        router.get('/reports/cash-flow', { start_date: startDate, end_date: endDate }, { preserveState: true });
    }
</script>

<AuthenticatedLayout
    title="Laporan Arus Kas (Cash Flow)"
    breadcrumbs={[
        { label: 'Laporan', href: '/reports/cash-flow' },
        { label: 'Arus Kas' },
    ]}
>
    <div class="max-w-4xl mx-auto space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 no-print">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-stone-900 flex items-center gap-2">
                    <DollarSign class="h-5 w-5 text-stone-800" />
                    Laporan Arus Kas (Cash Flow Statement)
                </h1>
                <p class="text-xs text-stone-500 mt-1">Aliran kas masuk dan keluar dari aktivitas operasional dan pendanaan/modal.</p>
            </div>

            <Button variant="outline" size="sm" onclick={() => window.print()}>
                <Printer class="h-3.5 w-3.5 mr-1" />
                Cetak Laporan
            </Button>
        </div>

        <!-- Filter Bar -->
        <div class="rounded-xl border border-stone-200 bg-white p-4 no-print flex items-end gap-3">
            <div class="space-y-1">
                <label for="cf_start" class="text-xs font-semibold text-stone-700">Dari Tanggal</label>
                <Input id="cf_start" type="date" bind:value={startDate} />
            </div>
            <div class="space-y-1">
                <label for="cf_end" class="text-xs font-semibold text-stone-700">Sampai Tanggal</label>
                <Input id="cf_end" type="date" bind:value={endDate} />
            </div>
            <Button variant="default" size="sm" onclick={handleFilter}>
                Tampilkan Arus Kas
            </Button>
        </div>

        <!-- Statement Document -->
        <div class="rounded-2xl border border-stone-200 bg-white p-8 shadow-2xl backdrop-blur-xl print:border-0 print:bg-white print:text-black print:p-0 space-y-6 text-xs">
            <div class="text-center border-b border-stone-200 pb-6 print:border-stone-300">
                <h2 class="text-lg font-bold text-stone-900 print:text-black">PT AIONIOS SOLUSI TELEMATIKA</h2>
                <h1 class="text-xl font-black text-stone-800 print:text-indigo-700 tracking-tight mt-1">LAPORAN ARUS KAS (METODE LANGSUNG)</h1>
                <p class="text-xs text-stone-500 print:text-stone-600 mt-1 font-mono">
                    Periode: {formatDate(start_date)} s/d {formatDate(end_date)}
                </p>
            </div>

            <!-- 1. OPERATING ACTIVITIES -->
            <div class="space-y-3">
                <h3 class="font-bold text-xs uppercase text-stone-800 border-b border-stone-200 pb-1.5">
                    I. ARUS KAS DARI AKTIVITAS OPERASIONAL
                </h3>

                <table class="w-full text-left">
                    <tbody class="divide-y divide-stone-100">
                        <tr>
                            <td class="py-2 pl-4 text-stone-700">Penerimaan Kas dari Pelanggan & Jasa</td>
                            <td class="py-2 text-right font-mono text-emerald-700">{formatRupiah(report.total_operating_inflows)}</td>
                        </tr>
                        <tr>
                            <td class="py-2 pl-4 text-stone-700">Pengeluaran Kas untuk Operasional & Upstream</td>
                            <td class="py-2 text-right font-mono text-rose-700">({formatRupiah(report.total_operating_outflows)})</td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t border-stone-300 font-bold">
                        <tr>
                            <td class="py-2 pl-4 text-stone-900">Arus Kas Bersih dari Aktivitas Operasional</td>
                            <td class="py-2 text-right font-mono {report.net_operating_cash_flow >= 0 ? 'text-emerald-700' : 'text-rose-700'}">
                                {formatRupiah(report.net_operating_cash_flow)}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- 2. FINANCING ACTIVITIES -->
            <div class="space-y-3">
                <h3 class="font-bold text-xs uppercase text-purple-700 border-b border-stone-200 pb-1.5">
                    II. ARUS KAS DARI AKTIVITAS PENDANAAN / MODAL
                </h3>

                <table class="w-full text-left">
                    <tbody class="divide-y divide-stone-100">
                        <tr>
                            <td class="py-2 pl-4 text-stone-700">Penerimaan Setoran & Tambahan Modal</td>
                            <td class="py-2 text-right font-mono text-emerald-700">{formatRupiah(report.total_financing_inflows)}</td>
                        </tr>
                        <tr>
                            <td class="py-2 pl-4 text-stone-700">Penarikan Modal / Prive Pemilik</td>
                            <td class="py-2 text-right font-mono text-rose-700">({formatRupiah(report.total_financing_outflows)})</td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t border-stone-300 font-bold">
                        <tr>
                            <td class="py-2 pl-4 text-stone-900">Arus Kas Bersih dari Aktivitas Pendanaan</td>
                            <td class="py-2 text-right font-mono text-purple-700">
                                {formatRupiah(report.net_financing_cash_flow)}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- 3. SUMMARY -->
            <div class="border-t-2 border-stone-300 pt-4 space-y-2">
                <div class="flex justify-between py-1 text-stone-700">
                    <span>Kenaikan / (Penurunan) Kas Bersih:</span>
                    <span class="font-mono font-bold {report.net_cash_change >= 0 ? 'text-emerald-700' : 'text-rose-700'}">
                        {formatRupiah(report.net_cash_change)}
                    </span>
                </div>
                <div class="flex justify-between py-1 text-stone-500">
                    <span>Saldo Kas & Bank Awal Periode:</span>
                    <span class="font-mono font-bold text-stone-800">{formatRupiah(report.opening_cash)}</span>
                </div>
                <div class="flex justify-between py-2 text-sm font-black border-t border-stone-200 text-stone-900">
                    <span>SALDO KAS & BANK AKHIR PERIODE:</span>
                    <span class="font-mono text-stone-800">{formatRupiah(report.closing_cash)}</span>
                </div>
            </div>
        </div>
    </div>
</AuthenticatedLayout>
