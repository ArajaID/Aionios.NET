<script>
    import { Link, useForm, router } from '@inertiajs/svelte';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
    import Button from '@/Components/ui/button/Button.svelte';
    import Input from '@/Components/ui/input/Input.svelte';
    import Select from '@/Components/ui/select/Select.svelte';
    import Card from '@/Components/ui/card/Card.svelte';
    import CardHeader from '@/Components/ui/card/CardHeader.svelte';
    import CardTitle from '@/Components/ui/card/CardTitle.svelte';
    import CardContent from '@/Components/ui/card/CardContent.svelte';
    import Badge from '@/Components/ui/badge/Badge.svelte';
    import Dialog from '@/Components/ui/dialog/Dialog.svelte';
    import Tabs from '@/Components/ui/tabs/Tabs.svelte';
    import TabsList from '@/Components/ui/tabs/TabsList.svelte';
    import TabsTrigger from '@/Components/ui/tabs/TabsTrigger.svelte';
    import TabsContent from '@/Components/ui/tabs/TabsContent.svelte';
    import Alert from '@/Components/ui/alert/Alert.svelte';
    import {
        Users,
        Edit3,
        CreditCard,
        Receipt,
        Network,
        HardDrive,
        Sparkles,
        History,
        ShieldAlert,
        Power,
        RotateCcw,
        CheckCircle2,
        XCircle,
        AlertTriangle,
        Calendar
    } from 'lucide-svelte';
    import { formatRupiah, formatDate } from '@/lib/utils';

    let {
        customer = {},
        available_onts = [],
        packages = [],
        promotions = [],
    } = $props();

    let activeTab = $state('overview');
    let terminateDialogOpen = $state(false);
    let reactivateDialogOpen = $state(false);

    const terminateForm = useForm({
        reason: '',
    });

    const reactivateForm = useForm({
        activated_at: new Date().toISOString().split('T')[0],
        package_id: customer.package_id || packages[0]?.id || '',
        ont_id: available_onts[0]?.id || '',
        ppp_password: '',
        notes: 'Reaktivasi layanan pelanggan',
    });

    function handleTerminate(e) {
        e.preventDefault();
        terminateForm.post(`/customers/${customer.id}/terminate`, {
            onSuccess: () => (terminateDialogOpen = false),
        });
    }

    function handleReactivate(e) {
        e.preventDefault();
        reactivateForm.post(`/customers/${customer.id}/reactivate`, {
            onSuccess: () => (reactivateDialogOpen = false),
        });
    }

    function handleToggleIsolate() {
        router.post(`/mikrotik/toggle-isolate/${customer.id}`, {}, { preserveScroll: true });
    }
</script>

<AuthenticatedLayout
    title={`Pelanggan: ${customer.name}`}
    breadcrumbs={[
        { label: 'Pelanggan', href: '/customers' },
        { label: customer.customer_id },
    ]}
>
    <div class="space-y-6">
        <!-- Top Profile Banner Card -->
        <div class="rounded-2xl border border-stone-200 bg-white p-6 backdrop-blur-xl">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                <!-- Customer Info -->
                <div class="flex items-start gap-4">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-indigo-600/20 text-stone-800 border border-indigo-500/30 text-xl font-bold font-mono">
                        {customer.name?.charAt(0).toUpperCase()}
                    </div>

                    <div>
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <h1 class="text-xl font-bold text-stone-900">{customer.name}</h1>
                            <Badge
                                variant={customer.status === 'active'
                                    ? 'success'
                                    : customer.status === 'isolated'
                                    ? 'danger'
                                    : 'default'}
                            >
                                {customer.status?.toUpperCase()}
                            </Badge>
                            <span class="inline-flex rounded-md border border-stone-700 bg-stone-800 px-2.5 py-1 font-mono text-[11px] font-bold tracking-wide text-white shadow-sm">
                                {customer.customer_id}
                            </span>
                        </div>

                        <p class="text-xs text-stone-500 mt-1.5 flex items-center gap-4 flex-wrap">
                            <span>📞 {customer.phone}</span>
                            <span>📍 {customer.address}</span>
                            <span>📅 Aktif sejak: {formatDate(customer.activated_at)}</span>
                        </p>
                    </div>
                </div>

                <!-- Financial Status & Quick Action Buttons -->
                <div class="flex items-center gap-3 flex-wrap">
                    <!-- Outstanding Indicator -->
                    <div class="p-3 rounded-xl border border-stone-200 bg-stone-50 text-right min-w-[140px]">
                        <p class="text-[10px] uppercase tracking-wider text-stone-500 font-semibold">Tunggakan Tagihan</p>
                        <p class="text-base font-bold font-mono {customer.outstanding_amount > 0 ? 'text-rose-700' : 'text-emerald-700'}">
                            {formatRupiah(customer.outstanding_amount)}
                        </p>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center gap-2 flex-wrap">
                        {#if customer.outstanding_amount > 0}
                            <Link href={`/payments/create?customer_id=${customer.id}`}>
                                <Button variant="default" size="sm">
                                    <CreditCard class="h-3.5 w-3.5 mr-1" />
                                    Bayar Tagihan
                                </Button>
                            </Link>
                        {/if}

                        {#if customer.status === 'active'}
                            <Button variant="destructive" size="sm" onclick={handleToggleIsolate}>
                                <Power class="h-3.5 w-3.5 mr-1" />
                                Isolir Manual
                            </Button>

                            <Button variant="outline" size="sm" onclick={() => (terminateDialogOpen = true)}>
                                <XCircle class="h-3.5 w-3.5 mr-1 text-rose-700" />
                                Terminasi
                            </Button>
                        {:else if customer.status === 'isolated'}
                            <Button variant="success" size="sm" onclick={handleToggleIsolate}>
                                <CheckCircle2 class="h-3.5 w-3.5 mr-1" />
                                Un-Isolir Manual
                            </Button>

                            <Button variant="outline" size="sm" onclick={() => (terminateDialogOpen = true)}>
                                <XCircle class="h-3.5 w-3.5 mr-1 text-rose-700" />
                                Terminasi
                            </Button>
                        {:else if customer.status === 'terminated'}
                            <!-- Reactivation button with outstanding-balance rule enforcement -->
                            {#if customer.outstanding_amount > 0}
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled
                                    title="Reaktivasi diblokir karena masih ada tunggakan"
                                    class="opacity-50 cursor-not-allowed"
                                >
                                    <RotateCcw class="h-3.5 w-3.5 mr-1" />
                                    Reaktivasi (Tunggakan &gt; Rp0)
                                </Button>
                            {:else}
                                <Button
                                    variant="success"
                                    size="sm"
                                    onclick={() => (reactivateDialogOpen = true)}
                                >
                                    <RotateCcw class="h-3.5 w-3.5 mr-1" />
                                    Reaktivasi Pelanggan
                                </Button>
                            {/if}
                        {/if}

                        <Link href={`/customers/${customer.id}/edit`}>
                            <Button variant="outline" size="sm">
                                <Edit3 class="h-3.5 w-3.5 mr-1" />
                                Edit
                            </Button>
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabbed Navigation -->
        <Tabs value={activeTab}>
            <TabsList class="w-full justify-start overflow-x-auto">
                <TabsTrigger value="overview" activeValue={activeTab} onclick={() => (activeTab = 'overview')}>
                    <Users class="h-3.5 w-3.5" />
                    Ringkasan & Jaringan
                </TabsTrigger>
                <TabsTrigger value="invoices" activeValue={activeTab} onclick={() => (activeTab = 'invoices')}>
                    <Receipt class="h-3.5 w-3.5" />
                    Tagihan ({customer.invoices?.length || 0})
                </TabsTrigger>
                <TabsTrigger value="payments" activeValue={activeTab} onclick={() => (activeTab = 'payments')}>
                    <CreditCard class="h-3.5 w-3.5" />
                    Pembayaran ({customer.payments?.length || 0})
                </TabsTrigger>
                <TabsTrigger value="promotions" activeValue={activeTab} onclick={() => (activeTab = 'promotions')}>
                    <Sparkles class="h-3.5 w-3.5" />
                    Promo
                </TabsTrigger>
                <TabsTrigger value="history" activeValue={activeTab} onclick={() => (activeTab = 'history')}>
                    <History class="h-3.5 w-3.5" />
                    Riwayat Status ({customer.status_histories?.length || 0})
                </TabsTrigger>
            </TabsList>

            <!-- 1. OVERVIEW TAB -->
            <TabsContent value="overview" activeValue={activeTab} class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Paket & Billing Info -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Paket & Layanan Aktif</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3 text-xs">
                            <div class="flex justify-between py-2 border-b border-stone-200">
                                <span class="text-stone-500">Nama Paket:</span>
                                <span class="font-bold text-stone-900">{customer.package?.name ?? '-'}</span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-stone-200">
                                <span class="text-stone-500">Kecepatan Bandwidth:</span>
                                <span class="font-mono text-stone-800 font-semibold">
                                    {customer.package?.download_speed_mbps} Mbps / {customer.package?.upload_speed_mbps} Mbps
                                </span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-stone-200">
                                <span class="text-stone-500">Tarif Bulanan Normal:</span>
                                <span class="font-mono font-bold text-stone-900">{formatRupiah(customer.package?.price)}</span>
                            </div>
                            <div class="flex justify-between py-2">
                                <span class="text-stone-500">Catatan Pemasangan:</span>
                                <span class="text-stone-700">{customer.notes || '-'}</span>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- PPPoE & ONT Info -->
                    <Card>
                        <CardHeader>
                            <CardTitle>PPPoE & Perangkat ONT</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3 text-xs">
                            <div class="flex justify-between py-2 border-b border-stone-200">
                                <span class="text-stone-500">PPPoE Username:</span>
                                <span class="font-mono text-stone-900 font-semibold">{customer.ppp_account?.username ?? '-'}</span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-stone-200">
                                <span class="text-stone-500">PPPoE Profile di MikroTik:</span>
                                <Badge variant={customer.ppp_account?.profile === 'ISOLIR' ? 'danger' : 'primary'}>
                                    {customer.ppp_account?.profile ?? '-'}
                                </Badge>
                            </div>
                            <div class="flex justify-between py-2 border-b border-stone-200">
                                <span class="text-stone-500">Status PPPoE:</span>
                                <span class="font-semibold text-stone-800 uppercase">{customer.ppp_account?.status ?? '-'}</span>
                            </div>
                            <div class="flex justify-between py-2">
                                <span class="text-stone-500">ONT Terpasang:</span>
                                {#if customer.ont}
                                    <span class="font-mono text-stone-900">
                                        {customer.ont.brand} {customer.ont.model} (SN: {customer.ont.serial_number})
                                    </span>
                                {:else}
                                    <span class="text-stone-500">Tidak ada ONT terpasang</span>
                                {/if}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </TabsContent>

            <!-- 2. INVOICES TAB -->
            <TabsContent value="invoices" activeValue={activeTab}>
                <Card>
                    <CardHeader>
                        <CardTitle>Riwayat Tagihan & Billing</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left">
                                <thead class="border-b border-stone-200 text-stone-500 uppercase">
                                    <tr>
                                        <th class="py-2.5">No. Invoice</th>
                                        <th class="py-2.5">Periode</th>
                                        <th class="py-2.5">Tgl Terbit</th>
                                        <th class="py-2.5">Jatuh Tempo</th>
                                        <th class="py-2.5 text-right">Subtotal</th>
                                        <th class="py-2.5 text-right">Diskon</th>
                                        <th class="py-2.5 text-right">Total Tagihan</th>
                                        <th class="py-2.5 text-center">Status</th>
                                        <th class="py-2.5 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-stone-100">
                                    {#if !customer.invoices || customer.invoices.length === 0}
                                        <tr>
                                            <td colspan="9" class="py-6 text-center text-stone-500">Belum ada riwayat tagihan.</td>
                                        </tr>
                                    {:else}
                                        {#each customer.invoices as inv}
                                            <tr class="hover:bg-stone-50">
                                                <td class="py-2.5">
                                                    <div class="flex flex-col items-start gap-1.5">
                                                        <Link
                                                            href={`/invoices/${inv.id}`}
                                                            class="inline-flex rounded-lg border border-blue-200 bg-blue-50 px-2.5 py-1 font-mono text-[11px] font-bold tracking-wide text-blue-800 shadow-2xs transition-colors hover:border-blue-300 hover:bg-blue-100"
                                                        >
                                                            {inv.invoice_number}
                                                        </Link>
                                                        {#if inv.is_prorata}
                                                            <span class="inline-flex items-center gap-1 rounded-full border border-violet-200 bg-violet-50 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-violet-700">
                                                                <Calendar class="h-2.5 w-2.5" />
                                                                Prorata
                                                            </span>
                                                        {/if}
                                                    </div>
                                                </td>
                                                <td class="py-2.5 font-mono">{inv.period}</td>
                                                <td class="py-2.5 text-stone-500">{formatDate(inv.issue_date)}</td>
                                                <td class="py-2.5 text-stone-500">{formatDate(inv.due_date)}</td>
                                                <td class="py-2.5 text-right text-stone-500">{formatRupiah(inv.subtotal)}</td>
                                                <td class="py-2.5 text-right text-rose-700">{formatRupiah(inv.discount_amount)}</td>
                                                <td class="py-2.5 text-right font-bold text-stone-900">{formatRupiah(inv.total_amount)}</td>
                                                <td class="py-2.5 text-center">
                                                    <Badge variant={inv.status === 'paid' ? 'success' : inv.status === 'overdue' ? 'danger' : 'warning'}>
                                                        {inv.status?.toUpperCase()}
                                                    </Badge>
                                                </td>
                                                <td class="py-2.5 text-right">
                                                    <Link href={`/invoices/${inv.id}`}>
                                                        <Button variant="outline" size="sm" class="h-6 px-2 text-[11px]">
                                                            Lihat
                                                        </Button>
                                                    </Link>
                                                </td>
                                            </tr>
                                        {/each}
                                    {/if}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>

            <!-- 3. PAYMENTS TAB -->
            <TabsContent value="payments" activeValue={activeTab}>
                <Card>
                    <CardHeader>
                        <CardTitle>Riwayat Pembayaran</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left">
                                <thead class="border-b border-stone-200 text-stone-500 uppercase">
                                    <tr>
                                        <th class="py-2.5">No. Bayar</th>
                                        <th class="py-2.5">Tanggal</th>
                                        <th class="py-2.5">Metode</th>
                                        <th class="py-2.5">Kas/Bank Tujuan</th>
                                        <th class="py-2.5 text-right">Gross</th>
                                        <th class="py-2.5 text-right">MDR QRIS</th>
                                        <th class="py-2.5 text-right">Net Settlement</th>
                                        <th class="py-2.5 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-stone-100">
                                    {#if !customer.payments || customer.payments.length === 0}
                                        <tr>
                                            <td colspan="8" class="py-6 text-center text-stone-500">Belum ada pembayaran tercatat.</td>
                                        </tr>
                                    {:else}
                                        {#each customer.payments as pay}
                                            <tr class="hover:bg-stone-50">
                                                <td class="py-2.5 font-mono font-semibold text-stone-800">{pay.payment_number}</td>
                                                <td class="py-2.5 text-stone-500">{formatDate(pay.payment_date)}</td>
                                                <td class="py-2.5">
                                                    <Badge variant={pay.payment_method === 'qris' ? 'purple' : 'default'}>
                                                        {pay.payment_method?.toUpperCase()}
                                                    </Badge>
                                                </td>
                                                <td class="py-2.5 text-stone-700">{pay.cash_bank_account?.name ?? '-'}</td>
                                                <td class="py-2.5 text-right font-medium text-stone-900">{formatRupiah(pay.gross_amount)}</td>
                                                <td class="py-2.5 text-right text-rose-700">{formatRupiah(pay.mdr_fee)}</td>
                                                <td class="py-2.5 text-right font-bold text-emerald-700">{formatRupiah(pay.net_amount)}</td>
                                                <td class="py-2.5 text-center">
                                                    <Badge variant={pay.status === 'posted' ? 'success' : 'danger'}>
                                                        {pay.status?.toUpperCase()}
                                                    </Badge>
                                                </td>
                                            </tr>
                                        {/each}
                                    {/if}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>

            <!-- 4. PROMOTIONS TAB -->
            <TabsContent value="promotions" activeValue={activeTab}>
                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Promo Pelanggan</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {#if !customer.promotions || customer.promotions.length === 0}
                            <p class="text-xs text-stone-500 py-6 text-center">Pelanggan belum pernah menerima promo.</p>
                        {:else}
                            <div class="space-y-3">
                                {#each customer.promotions as cp}
                                    <div class="flex items-center justify-between p-4 rounded-xl border border-stone-200 bg-stone-50 text-xs">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="font-bold text-stone-900">{cp.promotion?.name}</span>
                                                <Badge variant={cp.status === 'active' ? 'primary' : 'default'}>
                                                    {cp.status?.toUpperCase()}
                                                </Badge>
                                            </div>
                                            <p class="text-stone-500 mt-1">
                                                Periode: {formatDate(cp.start_date)} s/d {formatDate(cp.end_date)}
                                            </p>
                                        </div>
                                        <div>
                                            <span class="text-stone-700 font-mono">
                                                {cp.promotion?.type === 'speed_boost' ? 'Upgrade Speed PPP Profile' : 'Potongan Harga Billing'}
                                            </span>
                                        </div>
                                    </div>
                                {/each}
                            </div>
                        {/if}
                    </CardContent>
                </Card>
            </TabsContent>

            <!-- 5. STATUS HISTORY TAB -->
            <TabsContent value="history" activeValue={activeTab}>
                <Card>
                    <CardHeader>
                        <CardTitle>Jejak Riwayat Status & Audit Layanan</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="relative pl-6 space-y-6 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-stone-800">
                            {#each customer.status_histories as hist}
                                <div class="relative">
                                    <div class="absolute -left-6 top-1 h-3.5 w-3.5 rounded-full bg-indigo-600 ring-4 ring-stone-900"></div>
                                    <div class="text-xs space-y-1">
                                        <div class="flex items-center gap-2">
                                            <Badge variant={hist.new_status === 'active' ? 'success' : hist.new_status === 'isolated' ? 'danger' : 'default'}>
                                                {hist.new_status.toUpperCase()}
                                            </Badge>
                                            <span class="text-stone-500 font-mono">{formatDate(hist.created_at, true)}</span>
                                        </div>
                                        <p class="text-stone-700 font-medium">{hist.reason}</p>
                                        <p class="text-[11px] text-stone-500">Oleh Admin: {hist.changer?.name || 'Sistem'}</p>
                                    </div>
                                </div>
                            {/each}
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>
        </Tabs>
    </div>

    <!-- TERMINATION MODAL -->
    <Dialog bind:open={terminateDialogOpen} title="Konfirmasi Terminasi Pelanggan">
        <form onsubmit={handleTerminate} class="space-y-4">
            <Alert variant="destructive" title="Perhatian Terminasi!">
                Pelanggan yang berhenti <strong>tidak dihapus</strong> dari database. Status diubah menjadi Terminated, PPP Secret dinonaktifkan di MikroTik, dan ONT ditarik, tetapi seluruh histori tetap tersimpan.
            </Alert>

            <div class="space-y-1.5">
                <label for="reason" class="text-xs font-semibold text-stone-700">Alasan Berhenti / Terminasi (Wajib)</label>
                <Input id="reason" bind:value={terminateForm.reason} placeholder="e.g. Pelanggan pindah rumah / permintaan berhenti..." required />
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <Button type="button" variant="outline" onclick={() => (terminateDialogOpen = false)}>Batal</Button>
                <Button type="submit" variant="destructive" disabled={terminateForm.processing}>
                    {terminateForm.processing ? 'Memproses...' : 'Konfirmasi Terminasi'}
                </Button>
            </div>
        </form>
    </Dialog>

    <!-- REACTIVATION MODAL -->
    <Dialog bind:open={reactivateDialogOpen} title="Reaktivasi Pelanggan Lama">
        <form onsubmit={handleReactivate} class="space-y-4">
            <Alert variant="info" title="Syarat Reaktivasi">
                Reaktivasi hanya diperbolehkan jika <strong>Tunggakan = Rp0</strong>. Tagihan pertama setelah reaktivasi akan dihitung secara prorata normal.
            </Alert>

            <div class="space-y-1.5">
                <label for="reactivate_activated_at" class="text-xs font-semibold text-stone-700">Tanggal Reaktivasi</label>
                <Input id="reactivate_activated_at" type="date" bind:value={reactivateForm.activated_at} required />
            </div>

            <div class="space-y-1.5">
                <label for="reactivate_package_id" class="text-xs font-semibold text-stone-700">Pilih Paket Layanan</label>
                <Select id="reactivate_package_id" bind:value={reactivateForm.package_id} required>
                    {#each packages as pkg}
                        <option value={pkg.id}>{pkg.name} ({formatRupiah(pkg.price)}/bln)</option>
                    {/each}
                </Select>
            </div>

            <div class="space-y-1.5">
                <label for="reactivate_ont_id" class="text-xs font-semibold text-stone-700">Pilih ONT dari Stok</label>
                <Select id="reactivate_ont_id" bind:value={reactivateForm.ont_id}>
                    <option value="">Gunakan ONT saat ini / Tentukan nanti</option>
                    {#each available_onts as ont}
                        <option value={ont.id}>{ont.ont_id} - {ont.brand} {ont.model} ({ont.serial_number})</option>
                    {/each}
                </Select>
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <Button type="button" variant="outline" onclick={() => (reactivateDialogOpen = false)}>Batal</Button>
                <Button type="submit" variant="success" disabled={reactivateForm.processing}>
                    {reactivateForm.processing ? 'Mengaktifkan...' : 'Konfirmasi Reaktivasi'}
                </Button>
            </div>
        </form>
    </Dialog>
</AuthenticatedLayout>
