<script>
    import { Link } from '@inertiajs/svelte';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
    import MetricCard from '@/Components/ui/metric-card/MetricCard.svelte';
    import StatChart from '@/Components/ui/stat-chart/StatChart.svelte';
    import Card from '@/Components/ui/card/Card.svelte';
    import CardHeader from '@/Components/ui/card/CardHeader.svelte';
    import CardTitle from '@/Components/ui/card/CardTitle.svelte';
    import CardContent from '@/Components/ui/card/CardContent.svelte';
    import Badge from '@/Components/ui/badge/Badge.svelte';
    import Button from '@/Components/ui/button/Button.svelte';
    import {
        DollarSign,
        TrendingUp,
        TrendingDown,
        Users,
        Activity,
        AlertCircle,
        CheckCircle2,
        Clock,
        Receipt,
        CreditCard,
        Network,
        ArrowUpRight,
        HardDrive,
        ShieldAlert
    } from 'lucide-svelte';
    import { formatRupiah, formatDate } from '@/lib/utils';

    let {
        period = '',
        kpis = {},
        customer_counts = {},
        network_stats = {},
        ont_counts = {},
        approvals = {},
        cash_bank_accounts = [],
        recent_payments = [],
        recent_invoices = [],
        revenue_trend = [],
    } = $props();
</script>

<AuthenticatedLayout title="Owner Executive Dashboard">
    <div class="space-y-8">
        <!-- Top Executive Welcome & Action Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-stone-200 pb-5">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-stone-900 flex items-center gap-2.5">
                    Executive Dashboard
                    <Badge variant="primary">Periode {period}</Badge>
                </h1>
                <p class="text-xs text-stone-500 mt-1">Ringkasan menyeluruh performa finansial, operasional billing, dan status jaringan ISP.</p>
            </div>

            <div class="flex items-center gap-2.5">
                {#if approvals.total_pending > 0}
                    <Link href="/approvals">
                        <Button variant="destructive" size="sm" class="animate-pulse">
                            <ShieldAlert class="h-3.5 w-3.5 mr-1" />
                            {approvals.total_pending} Approval Menunggu
                        </Button>
                    </Link>
                {/if}

                <Link href="/reports/income-statement">
                    <Button variant="outline" size="sm">
                        <Receipt class="h-3.5 w-3.5 mr-1" />
                        Laporan Laba Rugi
                    </Button>
                </Link>
            </div>
        </div>

        <!-- KPI Keuangan Utama (Row 1) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <MetricCard
                title="Penerimaan Kas (Gross)"
                value={formatRupiah(kpis.gross_revenue)}
                subtitle="Total pembayaran bulan ini"
                color="indigo"
            >
                {#snippet icon()}
                    <DollarSign class="h-5 w-5" />
                {/snippet}
            </MetricCard>

            <MetricCard
                title="Beban Operasional"
                value={formatRupiah(kpis.month_expenses)}
                subtitle="Upstream, listrik & tim"
                color="rose"
            >
                {#snippet icon()}
                    <TrendingDown class="h-5 w-5" />
                {/snippet}
            </MetricCard>

            <MetricCard
                title="Laba Bersih Operasional"
                value={formatRupiah(kpis.net_profit)}
                subtitle={`Net Settlement - Beban`}
                trend={kpis.net_profit >= 0 ? 'PROFITABLE' : 'DEFICIT'}
                trendType={kpis.net_profit >= 0 ? 'positive' : 'negative'}
                color={kpis.net_profit >= 0 ? 'emerald' : 'rose'}
            >
                {#snippet icon()}
                    <TrendingUp class="h-5 w-5" />
                {/snippet}
            </MetricCard>

            <MetricCard
                title="Total Saldo Kas & Bank"
                value={formatRupiah(kpis.total_cash_bank)}
                subtitle="Likuiditas seluruh rekening"
                color="cyan"
            >
                {#snippet icon()}
                    <Activity class="h-5 w-5" />
                {/snippet}
            </MetricCard>
        </div>

        <!-- KPI Pelanggan, Billing & Network Health (Row 2) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <MetricCard
                title="Pelanggan Aktif"
                value={`${customer_counts.active} / ${customer_counts.total}`}
                subtitle={`${customer_counts.isolated} isolir, ${customer_counts.new_this_month} pasang baru`}
                color="emerald"
            >
                {#snippet icon()}
                    <Users class="h-5 w-5" />
                {/snippet}
            </MetricCard>

            <MetricCard
                title="Total Piutang (Receivables)"
                value={formatRupiah(kpis.total_receivables)}
                subtitle="Akumulasi tagihan outstanding"
                trend={`${100 - kpis.collection_rate}% Unpaid`}
                trendType="negative"
                color="amber"
            >
                {#snippet icon()}
                    <Receipt class="h-5 w-5" />
                {/snippet}
            </MetricCard>

            <MetricCard
                title="Tingkat Penagihan (Collection)"
                value={`${kpis.collection_rate}%`}
                subtitle={`${formatRupiah(kpis.total_paid_billed)} dari ${formatRupiah(kpis.total_billed)}`}
                color="purple"
            >
                {#snippet icon()}
                    <CreditCard class="h-5 w-5" />
                {/snippet}
            </MetricCard>

            <MetricCard
                title="Status MikroTik & Gateway"
                value={network_stats.router_status.toUpperCase()}
                subtitle={`${network_stats.ppp_counts.connected} PPPoE online, ${network_stats.pending_sync_count} pending sync`}
                color={network_stats.router_status === 'online' ? 'emerald' : 'rose'}
            >
                {#snippet icon()}
                    <Network class="h-5 w-5" />
                {/snippet}
            </MetricCard>
        </div>

        <!-- Charts and Breakdown -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- 6-Month Revenue Trend Chart (2 Cols) -->
            <div class="lg:col-span-2 space-y-6">
                <StatChart data={revenue_trend} title="Tren Pendapatan & Pertumbuhan Penjualan" height={220} />

                <!-- Recent Payments Table -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle>Pembayaran Terbaru</CardTitle>
                            <p class="text-xs text-stone-500 mt-0.5">Transaksi masuk yang telah diposting ke jurnal</p>
                        </div>
                        <Link href="/payments" class="text-xs text-stone-800 hover:underline flex items-center gap-1">
                            Lihat Semua <ArrowUpRight class="h-3 w-3" />
                        </Link>
                    </CardHeader>
                    <CardContent>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left">
                                <thead class="border-b border-stone-200 text-stone-500 uppercase">
                                    <tr>
                                        <th class="py-2">No. Bayar</th>
                                        <th class="py-2">Pelanggan</th>
                                        <th class="py-2">Metode</th>
                                        <th class="py-2 text-right">Gross</th>
                                        <th class="py-2 text-right">Net Masuk</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-stone-100">
                                    {#each recent_payments as pay}
                                        <tr class="hover:bg-stone-50">
                                            <td class="py-2.5 font-mono text-stone-700">{pay.payment_number}</td>
                                            <td class="py-2.5 font-medium text-stone-900">{pay.customer?.name}</td>
                                            <td class="py-2.5">
                                                <Badge variant={pay.payment_method === 'qris' ? 'purple' : 'default'}>
                                                    {pay.payment_method.toUpperCase()}
                                                </Badge>
                                            </td>
                                            <td class="py-2.5 text-right text-stone-700">{formatRupiah(pay.gross_amount)}</td>
                                            <td class="py-2.5 text-right font-semibold text-emerald-700">{formatRupiah(pay.net_amount)}</td>
                                        </tr>
                                    {/each}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Right Column: Likuiditas Rekening & Pending Approvals -->
            <div class="space-y-6">
                <!-- Cash & Bank Breakdown -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between pb-3">
                        <CardTitle>Saldo Kas & Bank</CardTitle>
                        <Link href="/accounting/ledger" class="text-xs text-stone-800 hover:underline">
                            Buku Besar
                        </Link>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        {#each cash_bank_accounts as cb}
                            <div class="flex items-center justify-between p-3 rounded-xl border border-stone-200 bg-stone-50">
                                <div>
                                    <p class="text-xs font-semibold text-stone-800">{cb.name}</p>
                                    <p class="text-[10px] text-stone-500">{cb.bank_name} • {cb.account_number || '-'}</p>
                                </div>
                                <span class="text-xs font-bold text-stone-900 font-mono">{formatRupiah(cb.current_balance)}</span>
                            </div>
                        {/each}
                    </CardContent>
                </Card>

                <!-- Approval Queue Quick Box -->
                <Card class="border-stone-200 bg-white">
                    <CardHeader class="pb-3">
                        <CardTitle class="flex items-center justify-between">
                            <span>Status Approval Owner</span>
                            {#if approvals.total_pending > 0}
                                <Badge variant="destructive">{approvals.total_pending} Antrean</Badge>
                            {:else}
                                <Badge variant="success">Bersih</Badge>
                            {/if}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2 text-xs">
                        <div class="flex items-center justify-between py-1.5 border-b border-stone-200">
                            <span class="text-stone-500">Pengajuan Pengeluaran (Beban)</span>
                            <span class="font-bold text-stone-900">{approvals.pending_expenses}</span>
                        </div>
                        <div class="flex items-center justify-between py-1.5">
                            <span class="text-stone-500">Pengajuan Reversal Pembayaran</span>
                            <span class="font-bold text-stone-900">{approvals.pending_reversals}</span>
                        </div>

                        {#if approvals.total_pending > 0}
                            <Link href="/approvals" class="block pt-2">
                                <Button variant="default" size="sm" class="w-full">
                                    Buka Halaman Persetujuan
                                </Button>
                            </Link>
                        {/if}
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</AuthenticatedLayout>
