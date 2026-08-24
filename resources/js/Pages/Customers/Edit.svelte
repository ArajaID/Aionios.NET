<script>
    import { useForm, Link } from '@inertiajs/svelte';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
    import Button from '@/Components/ui/button/Button.svelte';
    import Input from '@/Components/ui/input/Input.svelte';
    import Select from '@/Components/ui/select/Select.svelte';
    import Card from '@/Components/ui/card/Card.svelte';
    import CardHeader from '@/Components/ui/card/CardHeader.svelte';
    import CardTitle from '@/Components/ui/card/CardTitle.svelte';
    import CardContent from '@/Components/ui/card/CardContent.svelte';
    import { ArrowLeft, Save, Edit3 } from 'lucide-svelte';
    import { formatRupiah } from '@/lib/utils';

    let { customer = {}, packages = [], is_owner = false, errors = {} } = $props();

    const form = useForm({
        name: customer.name || '',
        phone: customer.phone || '',
        address: customer.address || '',
        notes: customer.notes || '',
        package_id: customer.package_id || packages[0]?.id || '',
        package_change_reason: '',
    });

    const isPackageChanged = $derived(
        Number(form.package_id) !== Number(customer.package_id)
    );

    function handleSubmit(e) {
        e.preventDefault();
        form.put(`/customers/${customer.id}`);
    }
</script>

<AuthenticatedLayout
    title={`Edit: ${customer.name}`}
    breadcrumbs={[
        { label: 'Pelanggan', href: '/customers' },
        { label: customer.customer_id, href: `/customers/${customer.id}` },
        { label: 'Edit' },
    ]}
>
    <div class="max-w-2xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-stone-900 flex items-center gap-2">
                    <Edit3 class="h-5 w-5 text-stone-800" />
                    Edit Data Pelanggan
                </h1>
                <p class="text-xs text-stone-500 mt-1">Perbarui informasi kontak, alamat, dan paket layanan pelanggan.</p>
            </div>

            <Link href={`/customers/${customer.id}`}>
                <Button variant="outline" size="sm">
                    <ArrowLeft class="h-3.5 w-3.5 mr-1" />
                    Kembali
                </Button>
            </Link>
        </div>

        {#if customer.pending_package_change_request}
            <div class="rounded-xl border border-amber-500/30 bg-amber-50 p-4 text-xs text-amber-900 flex items-start gap-3">
                <div class="h-2 w-2 rounded-full bg-amber-500 mt-1 shrink-0"></div>
                <div>
                    <strong>Pengajuan Perubahan Paket Sedang Menunggu Approval Owner:</strong>
                    <p class="mt-0.5 text-amber-800">
                        Pengajuan ganti paket ke <strong>{customer.pending_package_change_request.new_package?.name}</strong> saat ini sedang menunggu persetujuan Owner di menu Approvals.
                    </p>
                </div>
            </div>
        {/if}

        <form onsubmit={handleSubmit}>
            <Card>
                <CardHeader>
                    <CardTitle>Identitas Pelanggan: {customer.customer_id}</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-1.5">
                        <label for="name" class="text-xs font-semibold text-stone-700">Nama Lengkap</label>
                        <Input id="name" bind:value={form.name} required />
                        {#if errors.name}
                            <p class="text-xs text-rose-700">{errors.name}</p>
                        {/if}
                    </div>

                    <div class="space-y-1.5">
                        <label for="phone" class="text-xs font-semibold text-stone-700">Nomor Telepon / WhatsApp</label>
                        <Input id="phone" bind:value={form.phone} required />
                        {#if errors.phone}
                            <p class="text-xs text-rose-700">{errors.phone}</p>
                        {/if}
                    </div>

                    <div class="space-y-1.5">
                        <label for="address" class="text-xs font-semibold text-stone-700">Alamat Pemasangan</label>
                        <Input id="address" bind:value={form.address} required />
                        {#if errors.address}
                            <p class="text-xs text-rose-700">{errors.address}</p>
                        {/if}
                    </div>

                    <div class="space-y-1.5">
                        <label for="package_id" class="text-xs font-semibold text-stone-700">Paket Internet Langganan</label>
                        <Select id="package_id" bind:value={form.package_id} required>
                            {#each packages as pkg}
                                <option value={pkg.id}>
                                    {pkg.name} ({pkg.download_speed_mbps} Mbps) - {formatRupiah(pkg.price)}/bln
                                </option>
                            {/each}
                        </Select>
                        {#if errors.package_id}
                            <p class="text-xs text-rose-700">{errors.package_id}</p>
                        {/if}
                        <p class="text-[11px] text-stone-500">
                            {is_owner
                                ? 'Sebagai Owner, perubahan paket akan langsung diterapkan ke MikroTik seketika.'
                                : 'Sebagai Staf, perubahan paket akan dikirim sebagai permohonan ke Owner untuk disetujui.'}
                        </p>
                    </div>

                    {#if isPackageChanged && !is_owner}
                        <div class="space-y-1.5 p-3 rounded-xl bg-amber-500/10 border border-amber-500/20">
                            <label for="package_change_reason" class="text-xs font-semibold text-amber-900">Alasan Perubahan Paket (Wajib untuk Staf)</label>
                            <Input
                                id="package_change_reason"
                                bind:value={form.package_change_reason}
                                placeholder="e.g. Permintaan pelanggan upgrade kecepatan / hemat biaya..."
                                required
                            />
                        </div>
                    {/if}

                    <div class="space-y-1.5">
                        <label for="notes" class="text-xs font-semibold text-stone-700">Catatan Tambahan</label>
                        <Input id="notes" bind:value={form.notes} placeholder="Catatan teknis..." />
                    </div>

                    <div class="flex justify-end gap-3 pt-4">
                        <Link href={`/customers/${customer.id}`}>
                            <Button type="button" variant="outline">Batal</Button>
                        </Link>
                        <Button type="submit" disabled={form.processing} class="px-6">
                            <Save class="h-4 w-4 mr-1" />
                            {form.processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </form>
    </div>
</AuthenticatedLayout>
