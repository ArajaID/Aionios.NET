<script>
    import { useForm, Link } from '@inertiajs/svelte';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
    import Button from '@/Components/ui/button/Button.svelte';
    import Input from '@/Components/ui/input/Input.svelte';
    import Card from '@/Components/ui/card/Card.svelte';
    import CardHeader from '@/Components/ui/card/CardHeader.svelte';
    import CardTitle from '@/Components/ui/card/CardTitle.svelte';
    import CardContent from '@/Components/ui/card/CardContent.svelte';
    import { ArrowLeft, Save, Edit3 } from 'lucide-svelte';

    let { customer = {}, errors = {} } = $props();

    const form = useForm({
        name: customer.name || '',
        phone: customer.phone || '',
        address: customer.address || '',
        notes: customer.notes || '',
    });

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
                <p class="text-xs text-stone-500 mt-1">Perbarui informasi kontak dan alamat pelanggan.</p>
            </div>

            <Link href={`/customers/${customer.id}`}>
                <Button variant="outline" size="sm">
                    <ArrowLeft class="h-3.5 w-3.5 mr-1" />
                    Batal
                </Button>
            </Link>
        </div>

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
