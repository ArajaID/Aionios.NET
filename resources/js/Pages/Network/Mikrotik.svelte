<script>
    import { router, useForm } from '@inertiajs/svelte';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
    import Button from '@/Components/ui/button/Button.svelte';
    import Card from '@/Components/ui/card/Card.svelte';
    import CardHeader from '@/Components/ui/card/CardHeader.svelte';
    import CardTitle from '@/Components/ui/card/CardTitle.svelte';
    import CardContent from '@/Components/ui/card/CardContent.svelte';
    import Badge from '@/Components/ui/badge/Badge.svelte';
    import Alert from '@/Components/ui/alert/Alert.svelte';
    import { Network, Activity, RefreshCw, CheckCircle2, XCircle, Power, Zap, Clock } from 'lucide-svelte';
    import { formatDate } from '@/lib/utils';

    let {
        router_status = {},
        pending_jobs = [],
        ppp_accounts = [],
    } = $props();

    let testing = $state(false);
    let processingJobs = $state(false);

    function testConnection() {
        testing = true;
        router.post('/mikrotik/test', {}, {
            preserveScroll: true,
            onFinish: () => (testing = false),
        });
    }

    function processQueue() {
        processingJobs = true;
        router.post('/mikrotik/process-jobs', {}, {
            preserveScroll: true,
            onFinish: () => (processingJobs = false),
        });
    }

    function toggleIsolate(customerId) {
        router.post(`/mikrotik/toggle-isolate/${customerId}`, {}, { preserveScroll: true });
    }
</script>

<AuthenticatedLayout
    title="MikroTik RouterOS & PPPoE"
    breadcrumbs={[{ label: 'MikroTik & Jaringan' }]}
>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-stone-900 flex items-center gap-2">
                    <Network class="h-5 w-5 text-stone-800" />
                    Manajemen MikroTik RouterOS & Gateway
                </h1>
                <p class="text-xs text-stone-500 mt-1">
                    Sesuai PRD Bagian 17-19: Integrasi RouterOS 7.24 REST/API & Antrean Sinkronisasi saat Router Offline.
                </p>
            </div>

            <div class="flex items-center gap-2.5">
                <Button variant="outline" size="sm" onclick={testConnection} disabled={testing}>
                    <Activity class="h-3.5 w-3.5 mr-1 text-stone-800" />
                    {testing ? 'Menguji...' : 'Tes Koneksi Router'}
                </Button>

                {#if pending_jobs.length > 0}
                    <Button variant="default" size="sm" onclick={processQueue} disabled={processingJobs}>
                        <RefreshCw class="h-3.5 w-3.5 mr-1" />
                        {processingJobs ? 'Memproses...' : 'Proses Antrean Sinkronisasi'}
                    </Button>
                {/if}
            </div>
        </div>

        <!-- Status Gateway Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <Card class="border-stone-200 bg-white">
                <CardHeader class="pb-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase text-stone-500">Status Gateway</span>
                        <Badge variant={router_status.status === 'online' ? 'success' : 'danger'}>
                            {router_status.status?.toUpperCase()}
                        </Badge>
                    </div>
                    <CardTitle class="text-lg mt-1">{router_status.name || 'MikroTik CCR2004'}</CardTitle>
                </CardHeader>
                <CardContent class="space-y-2 text-xs pt-1">
                    <div class="flex justify-between py-1 border-b border-stone-200">
                        <span class="text-stone-500">Host IP:</span>
                        <span class="font-mono text-stone-800">{router_status.host || '192.168.88.1'}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-stone-200">
                        <span class="text-stone-500">API Port:</span>
                        <span class="font-mono text-stone-800">{router_status.port || 8728}</span>
                    </div>
                    <div class="flex justify-between py-1">
                        <span class="text-stone-500">Versi RouterOS:</span>
                        <span class="font-mono text-stone-800 font-semibold">{router_status.version || 'v7.24 (Stable)'}</span>
                    </div>
                </CardContent>
            </Card>

            <Card class="border-stone-200 bg-white">
                <CardHeader class="pb-2">
                    <span class="text-xs font-semibold uppercase text-stone-500">Sesi PPPoE Pelanggan</span>
                    <CardTitle class="text-lg mt-1">{ppp_accounts.length} Akun Terdaftar</CardTitle>
                </CardHeader>
                <CardContent class="space-y-2 text-xs pt-1">
                    <div class="flex justify-between py-1 border-b border-stone-200">
                        <span class="text-stone-500">Profile Normal:</span>
                        <span class="font-bold text-emerald-700">
                            {ppp_accounts.filter((a) => a.profile !== 'ISOLIR').length} Aktif
                        </span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-stone-200">
                        <span class="text-stone-500">Profile ISOLIR:</span>
                        <span class="font-bold text-rose-700">
                            {ppp_accounts.filter((a) => a.profile === 'ISOLIR').length} Isolir
                        </span>
                    </div>
                    <div class="flex justify-between py-1">
                        <span class="text-stone-500">Service Type:</span>
                        <span class="font-mono text-stone-800">pppoe</span>
                    </div>
                </CardContent>
            </Card>

            <Card class="border-stone-200 bg-white">
                <CardHeader class="pb-2">
                    <span class="text-xs font-semibold uppercase text-stone-500">Antrean Offline (Jobs)</span>
                    <CardTitle class="text-lg mt-1">{pending_jobs.length} Menunggu Sync</CardTitle>
                </CardHeader>
                <CardContent class="space-y-2 text-xs pt-1">
                    <p class="text-stone-500 leading-relaxed">
                        Jika router offline, seluruh eksekusi aktivasi/isolir/unisolir diantrekan secara aman dan otomatis dieksekusi saat koneksi pulih.
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Sync Queue Table -->
        {#if pending_jobs.length > 0}
            <Card class="border-amber-500/30 bg-white">
                <CardHeader class="flex flex-row items-center justify-between pb-3">
                    <div class="flex items-center gap-2">
                        <Clock class="h-4 w-4 text-amber-800" />
                        <CardTitle class="text-stone-900 font-bold">Antrean Sinkronisasi Router (Network Jobs Queue)</CardTitle>
                    </div>
                    <Button variant="default" size="sm" onclick={processQueue} disabled={processingJobs}>
                        <RefreshCw class="h-3 w-3 mr-1" />
                        Eksekusi Semua Antrean
                    </Button>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="border-b border-stone-200 text-stone-500 uppercase text-[10px] bg-stone-50">
                                <tr>
                                    <th class="py-2.5 px-4">Perintah / Aksi</th>
                                    <th class="py-2.5 px-4">Target PPPoE</th>
                                    <th class="py-2.5 px-4">Waktu Dibuat</th>
                                    <th class="py-2.5 px-4 text-center">Retry</th>
                                    <th class="py-2.5 px-4">Pesan Terakhir</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                {#each pending_jobs as job}
                                    <tr class="hover:bg-stone-50">
                                        <td class="py-2.5 px-4 font-mono font-semibold text-amber-800">{job.action}</td>
                                        <td class="py-2.5 px-4 font-mono text-stone-800">{job.payload?.username || '-'}</td>
                                        <td class="py-2.5 px-4 text-stone-500">{formatDate(job.created_at, true)}</td>
                                        <td class="py-2.5 px-4 text-center font-mono">{job.retry_count}x</td>
                                        <td class="py-2.5 px-4 text-rose-700">{job.error_message || 'Menunggu proses'}</td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        {/if}

        <!-- PPPoE Secrets Table -->
        <Card>
            <CardHeader>
                <CardTitle>Daftar Akun PPPoE & Profile MikroTik</CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="border-b border-stone-200 text-stone-500 uppercase text-[10px] bg-stone-50">
                            <tr>
                                <th class="py-3 px-4">PPPoE Username</th>
                                <th class="py-3 px-4">Pelanggan</th>
                                <th class="py-3 px-4">Current PPP Profile</th>
                                <th class="py-3 px-4 text-center">Status</th>
                                <th class="py-3 px-4 text-right">Aksi Isolir</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            {#each ppp_accounts as acc}
                                <tr class="hover:bg-stone-50">
                                    <td class="py-2.5 px-4 font-mono font-semibold text-stone-800">{acc.username}</td>
                                    <td class="py-2.5 px-4">
                                        <span class="font-medium text-stone-800">{acc.customer?.name}</span>
                                        <span class="text-[10px] text-stone-500 block font-mono">{acc.customer?.customer_id}</span>
                                    </td>
                                    <td class="py-2.5 px-4 font-mono">
                                        <Badge variant={acc.profile === 'ISOLIR' ? 'danger' : 'primary'}>
                                            {acc.profile}
                                        </Badge>
                                    </td>
                                    <td class="py-2.5 px-4 text-center">
                                        <Badge variant={acc.status === 'active' ? 'success' : 'default'}>
                                            {acc.status?.toUpperCase()}
                                        </Badge>
                                    </td>
                                    <td class="py-2.5 px-4 text-right">
                                        {#if acc.profile === 'ISOLIR'}
                                            <Button
                                                variant="success"
                                                size="sm"
                                                class="h-6 px-2 text-[11px]"
                                                onclick={() => toggleIsolate(acc.customer_id)}
                                            >
                                                Un-Isolir
                                            </Button>
                                        {:else}
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                class="h-6 px-2 text-[11px]"
                                                onclick={() => toggleIsolate(acc.customer_id)}
                                            >
                                                Isolir
                                            </Button>
                                        {/if}
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</AuthenticatedLayout>
