<script>
    import { onMount } from 'svelte';
    import { router, useForm } from '@inertiajs/svelte';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
    import Button from '@/Components/ui/button/Button.svelte';
    import Input from '@/Components/ui/input/Input.svelte';
    import Card from '@/Components/ui/card/Card.svelte';
    import CardHeader from '@/Components/ui/card/CardHeader.svelte';
    import CardTitle from '@/Components/ui/card/CardTitle.svelte';
    import CardDescription from '@/Components/ui/card/CardDescription.svelte';
    import CardContent from '@/Components/ui/card/CardContent.svelte';
    import CardFooter from '@/Components/ui/card/CardFooter.svelte';
    import Badge from '@/Components/ui/badge/Badge.svelte';
    import { Network, Activity, RefreshCw, Clock, Settings, Save, Eye, EyeOff, X, Cpu, MemoryStick, HardDrive, Router } from 'lucide-svelte';
    import { formatDate } from '@/lib/utils';

    let {
        router_status = null,
        pending_jobs = [],
        ppp_accounts = [],
    } = $props();

    const safeRouter = $derived(router_status || {});
    const safeJobs = $derived(pending_jobs || []);
    const safeAccounts = $derived(ppp_accounts || []);

    let testing = $state(false);
    let processingJobs = $state(false);
    let showPassword = $state(false);
    let configurationOpen = $state(false);

    let resource = $state(router_status?.resource_data || {});
    let liveStatus = $state(router_status?.status || 'unknown');
    let lastResourceUpdate = $state(router_status?.last_connected_at || null);
    let refreshingResource = $state(false);
    let activeConnections = $state([]);

    const routerForm = useForm({
        name: router_status?.name || 'Main Gateway',
        host: router_status?.host || '192.168.88.1',
        port: router_status?.port || 80,
        username: router_status?.username || 'admin',
        password: '',
        timeout: router_status?.timeout || 5,
        api_type: router_status?.api_type || 'rest',
        is_active: router_status?.is_active ?? true,
    });

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

    function saveRouter(e) {
        e.preventDefault();
        routerForm.post('/mikrotik/router', {
            preserveScroll: true,
            onSuccess: () => {
                routerForm.password = '';
                showPassword = false;
                configurationOpen = false;
            },
        });
    }

    function formatBytes(value) {
        const bytes = Number(value) || 0;
        if (bytes === 0) return '-';
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        const unit = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
        return `${(bytes / 1024 ** unit).toFixed(unit > 1 ? 1 : 0)} ${units[unit]}`;
    }

    function usagePercent(total, free) {
        const totalValue = Number(total) || 0;
        const freeValue = Number(free) || 0;
        if (!totalValue) return 0;
        return Math.max(0, Math.min(100, Math.round(((totalValue - freeValue) / totalValue) * 100)));
    }

    async function refreshResource() {
        if (refreshingResource || document.visibilityState !== 'visible') return;

        refreshingResource = true;
        try {
            const response = await fetch('/mikrotik/resource', {
                headers: { Accept: 'application/json' },
            });
            const result = await response.json();

            liveStatus = result.status || (response.ok ? 'online' : 'offline');
            lastResourceUpdate = result.checked_at || new Date().toISOString();
            activeConnections = Array.isArray(result.active_connections) ? result.active_connections : [];
            if (result.data && Object.keys(result.data).length > 0) {
                resource = result.data;
            }
        } catch (error) {
            liveStatus = 'offline';
            lastResourceUpdate = new Date().toISOString();
        } finally {
            refreshingResource = false;
        }
    }

    function activeConnectionFor(username) {
        return activeConnections.find((connection) => connection.name === username) || null;
    }

    onMount(() => {
        refreshResource();
        const interval = window.setInterval(refreshResource, 5000);
        const handleVisibility = () => {
            if (document.visibilityState === 'visible') refreshResource();
        };
        document.addEventListener('visibilitychange', handleVisibility);

        return () => {
            window.clearInterval(interval);
            document.removeEventListener('visibilitychange', handleVisibility);
        };
    });
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
                    Integrasi RouterOS 7.24 REST API dan antrean sinkronisasi saat router offline.
                </p>
            </div>

            <div class="flex items-center gap-2.5">
                <Button variant="outline" size="sm" onclick={() => (configurationOpen = true)}>
                    <Settings class="h-3.5 w-3.5" />
                    Konfigurasi Router
                </Button>

                <Button variant="outline" size="sm" onclick={testConnection} disabled={testing}>
                    <Activity class="h-3.5 w-3.5 mr-1 text-stone-800" />
                    {testing ? 'Menguji...' : 'Tes Koneksi Router'}
                </Button>

                {#if safeJobs.length > 0}
                    <Button variant="default" size="sm" onclick={processQueue} disabled={processingJobs}>
                        <RefreshCw class="h-3.5 w-3.5 mr-1" />
                        {processingJobs ? 'Memproses...' : 'Proses Antrean Sinkronisasi'}
                    </Button>
                {/if}
            </div>
        </div>

        <!-- Router Connection Configuration Modal -->
        {#if configurationOpen}
            <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4 sm:p-6">
                <button
                    type="button"
                    class="fixed inset-0 cursor-default bg-stone-900/60 backdrop-blur-xs"
                    onclick={() => (configurationOpen = false)}
                    aria-label="Tutup konfigurasi router"
                ></button>

                <Card class="relative z-10 max-h-[90vh] w-full max-w-4xl overflow-y-auto shadow-2xl">
            <form onsubmit={saveRouter}>
                <CardHeader>
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <Settings class="h-4 w-4 text-stone-800" />
                                Konfigurasi Koneksi MikroTik
                            </CardTitle>
                            <CardDescription class="mt-1">Data ini digunakan untuk koneksi aplikasi ke API RouterOS.</CardDescription>
                        </div>
                        <button type="button" onclick={() => (configurationOpen = false)} class="rounded-lg p-1.5 text-stone-400 hover:bg-stone-100 hover:text-stone-700" aria-label="Tutup">
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                </CardHeader>

                <CardContent class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="space-y-1.5">
                        <label for="mt_name" class="text-xs font-semibold text-stone-700">Nama Router</label>
                        <Input id="mt_name" bind:value={routerForm.name} required />
                        {#if routerForm.errors.name}<p class="text-[10px] text-rose-600">{routerForm.errors.name}</p>{/if}
                    </div>

                    <div class="space-y-1.5">
                        <label for="mt_host" class="text-xs font-semibold text-stone-700">IP / Host Router</label>
                        <Input id="mt_host" bind:value={routerForm.host} placeholder="192.168.88.1" required />
                        {#if routerForm.errors.host}<p class="text-[10px] text-rose-600">{routerForm.errors.host}</p>{/if}
                    </div>

                    <div class="space-y-1.5">
                        <label for="mt_port" class="text-xs font-semibold text-stone-700">Port API</label>
                        <Input id="mt_port" type="number" bind:value={routerForm.port} min="1" max="65535" required />
                        {#if routerForm.errors.port}<p class="text-[10px] text-rose-600">{routerForm.errors.port}</p>{/if}
                    </div>

                    <div class="space-y-1.5">
                        <label for="mt_username" class="text-xs font-semibold text-stone-700">Username API</label>
                        <Input id="mt_username" bind:value={routerForm.username} autocomplete="username" required />
                        {#if routerForm.errors.username}<p class="text-[10px] text-rose-600">{routerForm.errors.username}</p>{/if}
                    </div>

                    <div class="space-y-1.5">
                        <label for="mt_password" class="text-xs font-semibold text-stone-700">Password API</label>
                        <div class="relative">
                            <Input
                                id="mt_password"
                                type={showPassword ? 'text' : 'password'}
                                bind:value={routerForm.password}
                                placeholder={router_status?.id ? 'Kosongkan jika tidak diubah' : 'Masukkan password'}
                                required={!router_status?.id}
                                autocomplete="new-password"
                                class="pr-10"
                            />
                            <button
                                type="button"
                                onclick={() => (showPassword = !showPassword)}
                                class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-stone-400 hover:text-stone-700"
                                aria-label={showPassword ? 'Sembunyikan password' : 'Tampilkan password'}
                            >
                                {#if showPassword}<EyeOff class="h-4 w-4" />{:else}<Eye class="h-4 w-4" />{/if}
                            </button>
                        </div>
                        {#if routerForm.errors.password}<p class="text-[10px] text-rose-600">{routerForm.errors.password}</p>{/if}
                        <p class="text-[10px] text-stone-500">Password tersimpan tidak ditampilkan kembali.</p>
                    </div>

                    <div class="space-y-1.5">
                        <label for="mt_api_type" class="text-xs font-semibold text-stone-700">Tipe API</label>
                        <select id="mt_api_type" bind:value={routerForm.api_type} class="flex h-9 w-full rounded-lg border border-stone-300 bg-white px-3 text-sm text-stone-900" required>
                            <option value="rest">REST API</option>
                            <option value="api">RouterOS API</option>
                            <option value="api_ssl">RouterOS API SSL</option>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label for="mt_timeout" class="text-xs font-semibold text-stone-700">Timeout (detik)</label>
                        <Input id="mt_timeout" type="number" bind:value={routerForm.timeout} min="1" max="30" required />
                        {#if routerForm.errors.timeout}<p class="text-[10px] text-rose-600">{routerForm.errors.timeout}</p>{/if}
                    </div>

                    <div class="space-y-1.5">
                        <label for="mt_active" class="text-xs font-semibold text-stone-700">Status Router</label>
                        <select id="mt_active" bind:value={routerForm.is_active} class="flex h-9 w-full rounded-lg border border-stone-300 bg-white px-3 text-sm text-stone-900">
                            <option value={true}>Aktif</option>
                            <option value={false}>Nonaktif</option>
                        </select>
                    </div>
                </CardContent>

                <CardFooter class="flex items-center justify-end border-t border-stone-200 pt-4">
                    <Button type="submit" disabled={routerForm.processing}>
                        <Save class="h-4 w-4" />
                        {routerForm.processing ? 'Menyimpan...' : 'Simpan Konfigurasi Router'}
                    </Button>
                </CardFooter>
            </form>
                </Card>
            </div>
        {/if}

        <!-- Status Gateway Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <Card class="border-stone-200 bg-white">
                <CardHeader class="pb-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase text-stone-500">Status Gateway</span>
                        <Badge variant={liveStatus === 'online' ? 'success' : 'danger'}>
                            {liveStatus.toUpperCase()}
                        </Badge>
                    </div>
                    <CardTitle class="text-lg mt-1">{safeRouter.name || 'MikroTik CCR2004'}</CardTitle>
                </CardHeader>
                <CardContent class="space-y-2 text-xs pt-1">
                    <div class="flex justify-between py-1 border-b border-stone-200">
                        <span class="text-stone-500">Host IP:</span>
                        <span class="font-mono text-stone-800">{safeRouter.host || '192.168.88.1'}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-stone-200">
                        <span class="text-stone-500">API Port:</span>
                        <span class="font-mono text-stone-800">{safeRouter.port || 8728}</span>
                    </div>
                    <div class="flex justify-between py-1">
                        <span class="text-stone-500">Versi RouterOS:</span>
                        <span class="font-mono text-stone-800 font-semibold">{safeRouter.version || 'v7.24 (Stable)'}</span>
                    </div>
                </CardContent>
            </Card>

            <Card class="border-stone-200 bg-white">
                <CardHeader class="pb-2">
                    <span class="text-xs font-semibold uppercase text-stone-500">Sesi PPPoE Pelanggan</span>
                    <CardTitle class="text-lg mt-1">{activeConnections.length} Sesi Online</CardTitle>
                </CardHeader>
                <CardContent class="space-y-2 text-xs pt-1">
                    <div class="flex justify-between py-1 border-b border-stone-200">
                        <span class="text-stone-500">Active Connections:</span>
                        <span class="font-bold text-emerald-700">
                            {activeConnections.length} Online
                        </span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-stone-200">
                        <span class="text-stone-500">Tidak Terhubung:</span>
                        <span class="font-bold text-stone-700">
                            {Math.max(0, safeAccounts.length - activeConnections.length)} Offline
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
                    <CardTitle class="text-lg mt-1">{safeJobs.length} Menunggu Sync</CardTitle>
                </CardHeader>
                <CardContent class="space-y-2 text-xs pt-1">
                    <p class="text-stone-500 leading-relaxed">
                        Jika router offline, seluruh eksekusi aktivasi/isolir/unisolir diantrekan secara aman dan otomatis dieksekusi saat koneksi pulih.
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Live MikroTik Resource Snapshot -->
        <Card>
            <CardHeader>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <CardTitle class="flex items-center gap-2">
                            <Router class="h-4 w-4 text-cyan-700" />
                            Resource MikroTik
                        </CardTitle>
                        <CardDescription>Snapshot terakhir dari endpoint RouterOS `/rest/system/resource`.</CardDescription>
                    </div>
                    <div class="text-left sm:text-right">
                        <Badge variant={liveStatus === 'online' ? 'success' : 'danger'}>
                            <span class={`mr-1 inline-block h-1.5 w-1.5 rounded-full ${liveStatus === 'online' ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500'}`}></span>
                            {refreshingResource ? 'MEMPERBARUI' : liveStatus === 'online' ? 'LIVE / ONLINE' : 'SNAPSHOT / OFFLINE'}
                        </Badge>
                        <p class="mt-1 text-[10px] text-stone-500">Diperbarui otomatis tiap 5 detik: {formatDate(lastResourceUpdate, true)}</p>
                    </div>
                </div>
            </CardHeader>

            <CardContent>
                {#if Object.keys(resource).length > 0}
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <div class="rounded-xl border border-stone-200 bg-stone-50 p-4">
                            <div class="mb-3 flex items-center gap-2 text-xs font-bold text-stone-800">
                                <Cpu class="h-4 w-4 text-cyan-700" /> CPU
                            </div>
                            <p class="text-2xl font-black font-mono text-stone-900">{resource['cpu-load'] ?? 0}%</p>
                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-stone-200">
                                <div class="h-full rounded-full bg-cyan-600" style={`width: ${Math.min(Number(resource['cpu-load']) || 0, 100)}%`}></div>
                            </div>
                            <dl class="mt-4 space-y-2 text-xs">
                                <div class="flex justify-between gap-3"><dt class="text-stone-500">CPU</dt><dd class="text-right font-mono font-semibold">{resource.cpu || '-'}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-stone-500">Core / Frekuensi</dt><dd class="font-mono">{resource['cpu-count'] || '-'} / {resource['cpu-frequency'] ? `${resource['cpu-frequency']} MHz` : '-'}</dd></div>
                            </dl>
                        </div>

                        <div class="rounded-xl border border-stone-200 bg-stone-50 p-4">
                            <div class="mb-3 flex items-center gap-2 text-xs font-bold text-stone-800">
                                <MemoryStick class="h-4 w-4 text-violet-700" /> Memory
                            </div>
                            <p class="text-2xl font-black font-mono text-stone-900">{usagePercent(resource['total-memory'], resource['free-memory'])}%</p>
                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-stone-200">
                                <div class="h-full rounded-full bg-violet-600" style={`width: ${usagePercent(resource['total-memory'], resource['free-memory'])}%`}></div>
                            </div>
                            <dl class="mt-4 space-y-2 text-xs">
                                <div class="flex justify-between gap-3"><dt class="text-stone-500">Terpakai</dt><dd class="font-mono font-semibold">{formatBytes((Number(resource['total-memory']) || 0) - (Number(resource['free-memory']) || 0))}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-stone-500">Total</dt><dd class="font-mono">{formatBytes(resource['total-memory'])}</dd></div>
                            </dl>
                        </div>

                        <div class="rounded-xl border border-stone-200 bg-stone-50 p-4">
                            <div class="mb-3 flex items-center gap-2 text-xs font-bold text-stone-800">
                                <HardDrive class="h-4 w-4 text-amber-700" /> Storage
                            </div>
                            <p class="text-2xl font-black font-mono text-stone-900">{usagePercent(resource['total-hdd-space'], resource['free-hdd-space'])}%</p>
                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-stone-200">
                                <div class="h-full rounded-full bg-amber-600" style={`width: ${usagePercent(resource['total-hdd-space'], resource['free-hdd-space'])}%`}></div>
                            </div>
                            <dl class="mt-4 space-y-2 text-xs">
                                <div class="flex justify-between gap-3"><dt class="text-stone-500">Tersedia</dt><dd class="font-mono font-semibold">{formatBytes(resource['free-hdd-space'])}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-stone-500">Total</dt><dd class="font-mono">{formatBytes(resource['total-hdd-space'])}</dd></div>
                            </dl>
                        </div>
                    </div>

                    <dl class="mt-4 grid grid-cols-1 gap-x-8 gap-y-2 rounded-xl border border-stone-200 p-4 text-xs sm:grid-cols-2 lg:grid-cols-4">
                        <div><dt class="text-stone-500">Board</dt><dd class="mt-0.5 font-mono font-semibold text-stone-900">{resource['board-name'] || '-'}</dd></div>
                        <div><dt class="text-stone-500">Platform</dt><dd class="mt-0.5 font-mono font-semibold text-stone-900">{resource.platform || resource.architecture || '-'}</dd></div>
                        <div><dt class="text-stone-500">RouterOS</dt><dd class="mt-0.5 font-mono font-semibold text-stone-900">{resource.version || '-'}</dd></div>
                        <div><dt class="text-stone-500">Uptime</dt><dd class="mt-0.5 font-mono font-semibold text-stone-900">{resource.uptime || '-'}</dd></div>
                        <div><dt class="text-stone-500">Architecture</dt><dd class="mt-0.5 font-mono font-semibold text-stone-900">{resource['architecture-name'] || resource.architecture || '-'}</dd></div>
                        <div><dt class="text-stone-500">Build Time</dt><dd class="mt-0.5 font-mono font-semibold text-stone-900">{resource['build-time'] || '-'}</dd></div>
                        <div><dt class="text-stone-500">Bad Blocks</dt><dd class="mt-0.5 font-mono font-semibold text-stone-900">{resource['bad-blocks'] ?? '-'}%</dd></div>
                        <div><dt class="text-stone-500">Write Sectors</dt><dd class="mt-0.5 font-mono font-semibold text-stone-900">{resource['write-sect-total'] || '-'}</dd></div>
                    </dl>
                {:else}
                    <div class="rounded-xl border border-dashed border-stone-300 bg-stone-50 px-6 py-10 text-center">
                        <Activity class="mx-auto h-8 w-8 text-stone-300" />
                        <p class="mt-3 text-sm font-semibold text-stone-700">Data resource belum tersedia</p>
                        <p class="mt-1 text-xs text-stone-500">Simpan konfigurasi lalu jalankan Tes Koneksi Router untuk mengambil data langsung dari MikroTik.</p>
                    </div>
                {/if}
            </CardContent>
        </Card>

        <!-- Sync Queue Table -->
        {#if safeJobs.length > 0}
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
                                {#each safeJobs as job}
                                    <tr class="hover:bg-stone-50">
                                        <td class="py-2.5 px-4 font-mono font-semibold text-amber-800">{job.command}</td>
                                        <td class="py-2.5 px-4 font-mono text-stone-800">{job.payload?.username || job.payload?.name || '-'}</td>
                                        <td class="py-2.5 px-4 text-stone-500">{formatDate(job.created_at, true)}</td>
                                        <td class="py-2.5 px-4 text-center font-mono">{job.attempts}x</td>
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
                                <th class="py-3 px-4 text-center">Active Connection</th>
                                <th class="py-3 px-4 text-right">Aksi Isolir</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            {#each safeAccounts as acc}
                                {@const activeConnection = activeConnectionFor(acc.username)}
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
                                        {#if activeConnection}
                                            <div class="flex flex-col items-center gap-1">
                                                <Badge variant="success">
                                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                                    ONLINE
                                                </Badge>
                                                <span class="font-mono text-[10px] text-stone-500">
                                                    {activeConnection.address || '-'} · {activeConnection.uptime || '-'}
                                                </span>
                                            </div>
                                        {:else}
                                            <Badge variant="default">
                                                <span class="h-1.5 w-1.5 rounded-full bg-stone-400"></span>
                                                OFFLINE
                                            </Badge>
                                        {/if}
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
