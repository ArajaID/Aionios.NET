<script>
    import { Link, router } from '@inertiajs/svelte';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
    import MetricCard from '@/Components/ui/metric-card/MetricCard.svelte';
    import Card from '@/Components/ui/card/Card.svelte';
    import CardHeader from '@/Components/ui/card/CardHeader.svelte';
    import CardTitle from '@/Components/ui/card/CardTitle.svelte';
    import CardContent from '@/Components/ui/card/CardContent.svelte';
    import Badge from '@/Components/ui/badge/Badge.svelte';
    import Button from '@/Components/ui/button/Button.svelte';
    import {
        Users,
        Network,
        HardDrive,
        Sparkles,
        PlusCircle,
        RefreshCw,
        CheckCircle2,
        XCircle,
        AlertTriangle,
        ArrowUpRight,
        Activity
    } from 'lucide-svelte';
    import { formatDate } from '@/lib/utils';

    let {
        customer_counts = {},
        network_stats = {},
        ont_counts = {},
        package_stats = [],
        expiring_promos = [],
    } = $props();

    let testingConnection = $state(false);

    function testMikrotik() {
        testingConnection = true;
        router.post('/mikrotik/test', {}, {
            preserveScroll: true,
            onFinish: () => (testingConnection = false),
        });
    }

    function processJobs() {
        router.post('/mikrotik/process-jobs', {}, { preserveScroll: true });
    }
</script>

<AuthenticatedLayout title="Dashboard Admin Jaringan">
    <div class="space-y-8">
        <!-- Top Action Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-stone-200 pb-5">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-stone-900 flex items-center gap-2.5">
                    Dashboard Jaringan & Pelanggan
                </h1>
                <p class="text-xs text-stone-500 mt-1">Monitoring status PPPoE MikroTik, aktivasi pelanggan baru, inventori ONT, dan penugasan promo.</p>
            </div>

            <div class="flex items-center gap-2.5 flex-wrap">
                <Link href="/customers/create">
                    <Button variant="default" size="sm">
                        <PlusCircle class="h-3.5 w-3.5 mr-1" />
                        Pasang Baru Pelanggan
                    </Button>
                </Link>

                <Button variant="outline" size="sm" onclick={testMikrotik} disabled={testingConnection}>
                    <Activity class="h-3.5 w-3.5 mr-1 text-stone-800" />
                    {testingConnection ? 'Mengecek...' : 'Cek Status MikroTik'}
                </Button>
            </div>
        </div>

        <!-- Metric Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <MetricCard
                title="Pelanggan Aktif"
                value={`${customer_counts.active} / ${customer_counts.total}`}
                subtitle={`${customer_counts.isolated} isolir • ${customer_counts.terminated} berhenti`}
                color="emerald"
            >
                {#snippet icon()}
                    <Users class="h-5 w-5" />
                {/snippet}
            </MetricCard>

            <MetricCard
                title="Status MikroTik Gateway"
                value={network_stats.router_status.toUpperCase()}
                subtitle={network_stats.router_name}
                color={network_stats.router_status === 'online' ? 'indigo' : 'rose'}
            >
                {#snippet icon()}
                    <Network class="h-5 w-5" />
                {/snippet}
            </MetricCard>

            <MetricCard
                title="Inventori ONT"
                value={`${ont_counts.installed} Terpasang`}
                subtitle={`${ont_counts.available} siap pasang • ${ont_counts.returned} ditarik`}
                color="cyan"
            >
                {#snippet icon()}
                    <HardDrive class="h-5 w-5" />
                {/snippet}
            </MetricCard>

            <MetricCard
                title="Antrean Sinkronisasi"
                value={`${network_stats.pending_sync_count} Job`}
                subtitle="Queue retry offline MikroTik"
                color={network_stats.pending_sync_count > 0 ? 'amber' : 'emerald'}
            >
                {#snippet icon()}
                    <RefreshCw class="h-5 w-5" />
                {/snippet}
            </MetricCard>
        </div>

        <!-- Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left 2 Cols: Package Distribution & Expiring Promos -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Package Distribution Cards -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between pb-3">
                        <div>
                            <CardTitle>Sebaran Paket Layanan Pelanggan</CardTitle>
                            <p class="text-xs text-stone-500 mt-0.5">Distribusi langganan profil kecepatan aktif</p>
                        </div>
                        <Link href="/packages" class="text-xs text-stone-800 hover:underline">
                            Kelola Paket
                        </Link>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            {#each package_stats as pkg}
                                <div class="p-3.5 rounded-xl border border-stone-200 bg-stone-50 flex flex-col justify-between">
                                    <div>
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-xs font-bold text-stone-900">{pkg.name}</span>
                                            <Badge variant="primary">{pkg.download_speed_mbps}M</Badge>
                                        </div>
                                        <p class="text-[11px] text-stone-500 font-mono">Profile: {pkg.ppp_profile}</p>
                                    </div>
                                    <div class="mt-3 pt-2 border-t border-stone-200 flex items-center justify-between">
                                        <span class="text-[11px] text-stone-500">Pelanggan:</span>
                                        <span class="text-xs font-bold text-stone-800">{pkg.customers_count} Aktif</span>
                                    </div>
                                </div>
                            {/each}
                        </div>
                    </CardContent>
                </Card>

                <!-- Expiring Promos Alerts -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between pb-3">
                        <div>
                            <CardTitle>Promo Pelanggan Akan Berakhir (&lt; 7 Hari)</CardTitle>
                            <p class="text-xs text-stone-500 mt-0.5">Sistem akan mengembalikan profile & harga ke normal otomatis</p>
                        </div>
                        <Link href="/promotions" class="text-xs text-stone-800 hover:underline">
                            Daftar Promo
                        </Link>
                    </CardHeader>
                    <CardContent>
                        {#if expiring_promos.length === 0}
                            <p class="text-xs text-stone-500 py-3 text-center">Tidak ada promo yang akan kedaluwarsa dalam 7 hari ke depan.</p>
                        {:else}
                            <div class="space-y-2">
                                {#each expiring_promos as ep}
                                    <div class="flex items-center justify-between p-3 rounded-xl border border-amber-500/20 bg-amber-500/5 text-xs">
                                        <div>
                                            <p class="font-semibold text-stone-900">{ep.customer?.name} ({ep.customer?.customer_id})</p>
                                            <p class="text-[11px] text-amber-800">Promo: {ep.promotion?.name} • Berakhir {formatDate(ep.end_date)}</p>
                                        </div>
                                        <Link href={`/customers/${ep.customer_id}`}>
                                            <Button variant="outline" size="sm" class="h-7 text-[11px]">
                                                Lihat Pelanggan
                                            </Button>
                                        </Link>
                                    </div>
                                {/each}
                            </div>
                        {/if}
                    </CardContent>
                </Card>
            </div>

            <!-- Right Column: MikroTik Sync Queue & ONT Quick View -->
            <div class="space-y-6">
                <!-- Sync Queue Action Box -->
                <Card class="border-stone-200 bg-white">
                    <CardHeader class="pb-3">
                        <CardTitle class="flex items-center justify-between">
                            <span>Antrean Sinkronisasi Jaringan</span>
                            <Badge variant={network_stats.pending_sync_count > 0 ? 'warning' : 'success'}>
                                {network_stats.pending_sync_count} Pending
                            </Badge>
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3 text-xs">
                        <p class="text-stone-500 leading-relaxed">
                            Jika router MikroTik sempat offline, perintah aktivasi / isolir tersimpan di antrean ini dan dapat diproses ulang seketika.
                        </p>

                        {#if network_stats.pending_sync_count > 0}
                            <Button variant="default" size="sm" class="w-full" onclick={processJobs}>
                                <RefreshCw class="h-3.5 w-3.5 mr-1" />
                                Proses Antrean Sinkronisasi Sekarang
                            </Button>
                        {:else}
                            <div class="flex items-center gap-2 text-emerald-700 font-medium p-2.5 rounded-lg bg-emerald-500/10 border border-emerald-500/20">
                                <CheckCircle2 class="h-4 w-4" />
                                <span>Seluruh perintah MikroTik tersinkronisasi.</span>
                            </div>
                        {/if}
                    </CardContent>
                </Card>

                <!-- ONT Quick Summary -->
                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle>Status Stok Perangkat ONT</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2.5 text-xs">
                        <div class="flex items-center justify-between py-1.5 border-b border-stone-200">
                            <span class="text-stone-500">Tersedia (Ready Stock)</span>
                            <Badge variant="success">{ont_counts.available} Unit</Badge>
                        </div>
                        <div class="flex items-center justify-between py-1.5 border-b border-stone-200">
                            <span class="text-stone-500">Terpasang di Pelanggan</span>
                            <Badge variant="primary">{ont_counts.installed} Unit</Badge>
                        </div>
                        <div class="flex items-center justify-between py-1.5 border-b border-stone-200">
                            <span class="text-stone-500">Ditarik dari Pelanggan</span>
                            <Badge variant="warning">{ont_counts.returned} Unit</Badge>
                        </div>
                        <div class="flex items-center justify-between py-1.5">
                            <span class="text-stone-500">Rusak / Hilang</span>
                            <Badge variant="danger">{ont_counts.damaged} Unit</Badge>
                        </div>
                        <Link href="/ont" class="block pt-2">
                            <Button variant="outline" size="sm" class="w-full">
                                Buka Inventori ONT
                            </Button>
                        </Link>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</AuthenticatedLayout>
