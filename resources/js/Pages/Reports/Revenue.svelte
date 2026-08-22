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
    import { CreditCard, Printer, Sparkles, TrendingUp } from 'lucide-svelte';
    import { formatRupiah, formatDate } from '@/lib/utils';

    let {
        start_date = '',
        end_date = '',
        report = {
            total_gross: 0,
            total_mdr: 0,
            total_net: 0,
            manual_count: 0,
            manual_total: 0,
            qris_count: 0,
            qris_total: 0,
            payments: [],
        },
    } = $props();

    let startDate = $state(start_date || '');
    let endDate = $state(end_date || '');

    function handleFilter() {
        router.get('/reports/revenue', { start_date: startDate, end_date: endDate }, { preserveState: true });
    }
</script>

<AuthenticatedLayout
    title="Laporan Pendapatan & Rekonsiliasi MDR QRIS"
    breadcrumbs={[
        { label: 'Laporan', href: '/reports/revenue' },
        { label: 'Pendapatan & MDR' },
    ]}
>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 no-print">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-stone-900 flex items-center gap-2">
                    <CreditCard class="h-5 w-5 text-stone-800" />
                    Laporan Pendapatan & Rekonsiliasi MDR QRIS
                </h1>
                <p class="text-xs text-stone-500 mt-1">Audit rincian penerimaan kas, potongan fee MDR kanal QRIS, dan saldo settlement bersih.</p>
            </div>

            <Button variant="outline" size="sm" onclick={() => window.print()}>
                <Printer class="h-3.5 w-3.5 mr-1" />
                Cetak Laporan
            </Button>
        </div>

        <!-- Filter Bar -->
        <div class="rounded-xl border border-stone-200 bg-white p-4 no-print flex items-end gap-3">
            <div class="space-y-1">
                <label for="rev_start" class="text-xs font-semibold text-stone-700">Dari Tanggal</label>
                <Input id="rev_start" type="date" bind:value={startDate} />
            </div>
            <div class="space-y-1">
                <label for="rev_end" class="text-xs font-semibold text-stone-700">Sampai Tanggal</label>
                <Input id="rev_end" type="date" bind:value={endDate} />
            </div>
            <Button variant="default" size="sm" onclick={handleFilter}>
                Tampilkan Rekonsiliasi
            </Button>
        </div>

        <!-- KPI Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="p-4 rounded-xl border border-stone-200 bg-white shadow-xs">
                <span class="text-[11px] text-stone-500 uppercase font-semibold block">Total Penerimaan (Gross)</span>
                <span class="text-xl font-bold font-mono text-stone-900 mt-1 block">{formatRupiah(report.total_gross)}</span>
                <span class="text-[10px] text-stone-500 mt-1 block">{report.manual_count + report.qris_count} Transaksi Masuk</span>
            </div>

            <div class="p-4 rounded-xl border border-rose-200 bg-rose-50/80 shadow-xs">
                <span class="text-[11px] text-rose-700 uppercase font-bold block">Total Potongan MDR QRIS</span>
                <span class="text-xl font-bold font-mono text-rose-700 mt-1 block">-{formatRupiah(report.total_mdr)}</span>
                <span class="text-[10px] text-stone-500 mt-1 block">Beban biaya transaksi QRIS</span>
            </div>

            <div class="p-4 rounded-xl border border-emerald-200 bg-emerald-50/80 shadow-xs">
                <span class="text-[11px] text-emerald-700 uppercase font-bold block">Net Settlement Kas/Bank</span>
                <span class="text-xl font-bold font-mono text-emerald-700 mt-1 block">{formatRupiah(report.total_net)}</span>
                <span class="text-[10px] text-stone-500 mt-1 block">Dana riil masuk rekening</span>
            </div>
        </div>

        <!-- Payments Breakdown Table -->
        <Card>
            <CardHeader>
                <CardTitle>Rincian Transaksi Pembayaran & MDR</CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="border-b border-stone-200 text-stone-500 uppercase text-[10px] bg-stone-50">
                            <tr>
                                <th class="py-3 px-4">No. Bayar</th>
                                <th class="py-3 px-4">Tanggal</th>
                                <th class="py-3 px-4">Pelanggan</th>
                                <th class="py-3 px-4">Metode</th>
                                <th class="py-3 px-4">Rekening Tujuan</th>
                                <th class="py-3 px-4 text-right">Gross (Rp)</th>
                                <th class="py-3 px-4 text-right">MDR (Rp)</th>
                                <th class="py-3 px-4 text-right">Net Masuk (Rp)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            {#if report.payments.length === 0}
                                <tr>
                                    <td colspan="8" class="py-6 text-center text-stone-500">Tidak ada transaksi pembayaran pada rentang tanggal ini.</td>
                                </tr>
                            {:else}
                                {#each report.payments as pay}
                                    <tr class="hover:bg-stone-50">
                                        <td class="py-2.5 px-4 font-mono font-semibold text-stone-800">{pay.payment_number}</td>
                                        <td class="py-2.5 px-4 text-stone-500">{formatDate(pay.payment_date)}</td>
                                        <td class="py-2.5 px-4 font-medium text-stone-800">{pay.customer?.name}</td>
                                        <td class="py-2.5 px-4">
                                            <Badge variant={pay.payment_method === 'qris' ? 'purple' : 'default'}>
                                                {pay.payment_method?.toUpperCase()}
                                            </Badge>
                                        </td>
                                        <td class="py-2.5 px-4 text-stone-700">{pay.cash_bank_account?.name}</td>
                                        <td class="py-2.5 px-4 text-right font-mono text-stone-800">{formatRupiah(pay.gross_amount)}</td>
                                        <td class="py-2.5 px-4 text-right font-mono text-rose-700">
                                            {pay.mdr_fee > 0 ? `-${formatRupiah(pay.mdr_fee)} (${pay.mdr_percentage}%)` : '-'}
                                        </td>
                                        <td class="py-2.5 px-4 text-right font-mono font-bold text-emerald-700">{formatRupiah(pay.net_amount)}</td>
                                    </tr>
                                {/each}
                            {/if}
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</AuthenticatedLayout>
