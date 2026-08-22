<script>
    import { Link } from '@inertiajs/svelte';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
    import Button from '@/Components/ui/button/Button.svelte';
    import Card from '@/Components/ui/card/Card.svelte';
    import CardHeader from '@/Components/ui/card/CardHeader.svelte';
    import CardTitle from '@/Components/ui/card/CardTitle.svelte';
    import CardContent from '@/Components/ui/card/CardContent.svelte';
    import Badge from '@/Components/ui/badge/Badge.svelte';
    import { HardDrive, ArrowLeft, History, User, Calendar, Wrench } from 'lucide-svelte';
    import { formatDate } from '@/lib/utils';

    let { ont = {} } = $props();
</script>

<AuthenticatedLayout
    title={`Detail ONT: ${ont.ont_id}`}
    breadcrumbs={[
        { label: 'Inventori ONT', href: '/ont' },
        { label: ont.ont_id },
    ]}
>
    <div class="max-w-4xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-cyan-500/15 text-cyan-800 border border-cyan-500/30">
                    <HardDrive class="h-6 w-6" />
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-stone-900 flex items-center gap-2">
                        {ont.ont_id}
                        <Badge
                            variant={ont.status === 'installed'
                                ? 'primary'
                                : ont.status === 'available'
                                ? 'success'
                                : 'warning'}
                        >
                            {ont.status?.toUpperCase()}
                        </Badge>
                    </h1>
                    <p class="text-xs text-stone-500 font-mono">SN: {ont.serial_number} • MAC: {ont.mac_address || '-'}</p>
                </div>
            </div>

            <Link href="/ont">
                <Button variant="outline" size="sm">
                    <ArrowLeft class="h-3.5 w-3.5 mr-1" />
                    Kembali
                </Button>
            </Link>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <Card>
                <CardHeader>
                    <CardTitle>Spesifikasi & Status Perangkat</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3 text-xs">
                    <div class="flex justify-between py-2 border-b border-stone-200">
                        <span class="text-stone-500">Brand / Pabrikan:</span>
                        <span class="font-bold text-stone-900">{ont.brand}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-stone-200">
                        <span class="text-stone-500">Tipe Model:</span>
                        <span class="font-mono text-stone-900">{ont.model}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-stone-200">
                        <span class="text-stone-500">Kondisi Fisik:</span>
                        <span class="font-semibold capitalize text-emerald-700">{ont.condition}</span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="text-stone-500">Catatan:</span>
                        <span class="text-stone-700">{ont.notes || '-'}</span>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Posisi & Pelanggan Saat Ini</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3 text-xs">
                    {#if ont.current_customer}
                        <div class="p-4 rounded-xl bg-stone-50 border border-stone-200 space-y-2">
                            <span class="text-[10px] text-stone-500 uppercase tracking-wider block font-semibold">Terpasang Pada</span>
                            <p class="text-sm font-bold text-stone-800">{ont.current_customer.name}</p>
                            <p class="text-stone-500 font-mono">Customer ID: {ont.current_customer.customer_id}</p>
                            <p class="text-stone-500">Alamat: {ont.current_customer.address}</p>
                            <p class="text-stone-500">Tgl Pasang: {formatDate(ont.installed_at)}</p>

                            <Link href={`/customers/${ont.current_customer.id}`} class="block pt-2">
                                <Button variant="outline" size="sm" class="w-full text-[11px]">
                                    Lihat Profil Pelanggan
                                </Button>
                            </Link>
                        </div>
                    {:else}
                        <div class="p-6 rounded-xl bg-stone-50 border border-stone-200 text-center text-stone-500">
                            Perangkat ini saat ini berada di gudang inventori (Tidak terpasang ke pelanggan).
                        </div>
                    {/if}
                </CardContent>
            </Card>
        </div>

        <!-- ONT history timeline -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <History class="h-4 w-4 text-stone-800" />
                    Jejak Rekam Histori Perangkat (ONT Lifecycle Traceability)
                </CardTitle>
            </CardHeader>
            <CardContent>
                {#if !ont.histories || ont.histories.length === 0}
                    <p class="text-xs text-stone-500 py-6 text-center">Belum ada riwayat histori tercatat.</p>
                {:else}
                    <div class="relative pl-6 space-y-6 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-stone-800">
                        {#each ont.histories as hist}
                            <div class="relative">
                                <div class="absolute -left-6 top-1 h-3.5 w-3.5 rounded-full bg-cyan-500 ring-4 ring-stone-900"></div>
                                <div class="text-xs space-y-1">
                                    <div class="flex items-center gap-2">
                                        <Badge variant={hist.action === 'assigned' ? 'primary' : hist.action === 'returned' ? 'warning' : 'default'}>
                                            {hist.action?.toUpperCase()}
                                        </Badge>
                                        <span class="text-stone-500 font-mono">{formatDate(hist.created_at, true)}</span>
                                    </div>

                                    {#if hist.customer}
                                        <p class="text-stone-800 font-medium">
                                            Lokasi Pelanggan: <Link href={`/customers/${hist.customer.id}`} class="text-stone-800 hover:underline">{hist.customer.name} ({hist.customer.customer_id})</Link>
                                        </p>
                                    {/if}

                                    <p class="text-stone-500">{hist.notes || 'Aksi pemindahan ONT.'}</p>
                                    <p class="text-[11px] text-stone-500">Kondisi: {hist.condition} • Dicatat Oleh: {hist.admin?.name || 'Sistem'}</p>
                                </div>
                            </div>
                        {/each}
                    </div>
                {/if}
            </CardContent>
        </Card>
    </div>
</AuthenticatedLayout>
