<script>
    import { useForm, router } from '@inertiajs/svelte';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
    import Button from '@/Components/ui/button/Button.svelte';
    import Input from '@/Components/ui/input/Input.svelte';
    import Card from '@/Components/ui/card/Card.svelte';
    import CardHeader from '@/Components/ui/card/CardHeader.svelte';
    import CardTitle from '@/Components/ui/card/CardTitle.svelte';
    import CardDescription from '@/Components/ui/card/CardDescription.svelte';
    import CardContent from '@/Components/ui/card/CardContent.svelte';
    import Badge from '@/Components/ui/badge/Badge.svelte';
    import Dialog from '@/Components/ui/dialog/Dialog.svelte';
    import { Package as PackageIcon, Plus, Edit3, CheckCircle2, ArrowRight } from 'lucide-svelte';
    import { formatRupiah } from '@/lib/utils';

    let { packages = [] } = $props();

    let createModalOpen = $state(false);
    let editModalOpen = $state(false);
    let selectedPackage = $state(null);

    const createForm = useForm({
        code: '',
        name: '',
        download_speed_mbps: 10,
        upload_speed_mbps: 10,
        price: 150000,
        ppp_profile: 'default',
        description: '',
    });

    const editForm = useForm({
        name: '',
        download_speed_mbps: 10,
        upload_speed_mbps: 10,
        price: 150000,
        ppp_profile: 'default',
        is_active: true,
        description: '',
    });

    function handleCreate(e) {
        e.preventDefault();
        createForm.post('/packages', {
            onSuccess: () => {
                createModalOpen = false;
                createForm.reset();
            },
        });
    }

    function openEdit(pkg) {
        selectedPackage = pkg;
        editForm.name = pkg.name;
        editForm.download_speed_mbps = pkg.download_speed_mbps;
        editForm.upload_speed_mbps = pkg.upload_speed_mbps;
        editForm.price = pkg.price;
        editForm.ppp_profile = pkg.ppp_profile;
        editForm.is_active = Boolean(pkg.is_active);
        editForm.description = pkg.description || '';
        editModalOpen = true;
    }

    function handleEdit(e) {
        e.preventDefault();
        editForm.put(`/packages/${selectedPackage.id}`, {
            onSuccess: () => (editModalOpen = false),
        });
    }
</script>

<AuthenticatedLayout
    title="Paket Internet"
    breadcrumbs={[{ label: 'Paket Internet' }]}
>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-stone-900 flex items-center gap-2">
                    <PackageIcon class="h-5 w-5 text-stone-800" />
                    Manajemen Paket Internet
                </h1>
                <p class="text-xs text-stone-500 mt-1">Konfigurasi bandwidth, tarif bulanan, dan PPP Profile MikroTik terkait.</p>
            </div>

            <Button variant="default" size="sm" onclick={() => (createModalOpen = true)}>
                <Plus class="h-4 w-4 mr-1" />
                Tambah Paket Baru
            </Button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            {#each packages as pkg}
                <Card class="relative overflow-hidden flex flex-col justify-between border-stone-200 bg-white hover:border-stone-300 transition-all">
                    <div>
                        <CardHeader class="pb-3">
                            <div class="flex items-center justify-between">
                                <Badge variant={pkg.is_active ? 'success' : 'default'}>
                                    {pkg.is_active ? 'AKTIF' : 'NON-AKTIF'}
                                </Badge>
                                <span class="text-xs font-mono text-stone-500 bg-stone-800/80 px-2 py-0.5 rounded border border-stone-300">
                                    {pkg.code}
                                </span>
                            </div>

                            <CardTitle class="text-lg mt-2">{pkg.name}</CardTitle>
                            <CardDescription>{pkg.description || 'Layanan akses internet broadband.'}</CardDescription>
                        </CardHeader>

                        <CardContent class="space-y-3 text-xs">
                            <div class="p-3 rounded-xl bg-stone-50 border border-stone-200 space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-stone-500">Kecepatan:</span>
                                    <span class="font-bold text-stone-800 text-sm font-mono">
                                        {pkg.download_speed_mbps} Mbps / {pkg.upload_speed_mbps} Mbps
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-stone-500">PPP Profile:</span>
                                    <span class="font-mono text-stone-800">{pkg.ppp_profile}</span>
                                </div>
                                <div class="flex items-center justify-between pt-1 border-t border-stone-200">
                                    <span class="text-stone-500">Pelanggan Terdaftar:</span>
                                    <span class="font-semibold text-stone-800">{pkg.customers_count || 0} Pelanggan</span>
                                </div>
                            </div>
                        </CardContent>
                    </div>

                    <div class="p-5 pt-0 border-t border-stone-200 mt-2 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] text-stone-500 uppercase tracking-wider block">Tarif Bulanan</span>
                            <span class="text-lg font-extrabold text-stone-900 font-mono">{formatRupiah(pkg.price)}</span>
                        </div>

                        <Button variant="outline" size="sm" onclick={() => openEdit(pkg)}>
                            <Edit3 class="h-3.5 w-3.5 mr-1" />
                            Edit
                        </Button>
                    </div>
                </Card>
            {/each}
        </div>
    </div>

    <!-- CREATE MODAL -->
    <Dialog bind:open={createModalOpen} title="Tambah Paket Internet Baru">
        <form onsubmit={handleCreate} class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1.5">
                    <label for="pkg_code" class="text-xs font-semibold text-stone-700">Kode Paket</label>
                    <Input id="pkg_code" bind:value={createForm.code} placeholder="PKG-20M" required />
                </div>
                <div class="space-y-1.5">
                    <label for="pkg_name" class="text-xs font-semibold text-stone-700">Nama Paket</label>
                    <Input id="pkg_name" bind:value={createForm.name} placeholder="Home Family 20 Mbps" required />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1.5">
                    <label for="pkg_download" class="text-xs font-semibold text-stone-700">Download Speed (Mbps)</label>
                    <Input id="pkg_download" type="number" bind:value={createForm.download_speed_mbps} required min="1" />
                </div>
                <div class="space-y-1.5">
                    <label for="pkg_upload" class="text-xs font-semibold text-stone-700">Upload Speed (Mbps)</label>
                    <Input id="pkg_upload" type="number" bind:value={createForm.upload_speed_mbps} required min="1" />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1.5">
                    <label for="pkg_price" class="text-xs font-semibold text-stone-700">Harga Bulanan (Rp)</label>
                    <Input id="pkg_price" type="number" bind:value={createForm.price} required min="0" />
                </div>
                <div class="space-y-1.5">
                    <label for="pkg_profile" class="text-xs font-semibold text-stone-700">PPP Profile MikroTik</label>
                    <Input id="pkg_profile" bind:value={createForm.ppp_profile} placeholder="PROFILE-20M" required />
                </div>
            </div>

            <div class="space-y-1.5">
                <label for="pkg_desc" class="text-xs font-semibold text-stone-700">Deskripsi</label>
                <Input id="pkg_desc" bind:value={createForm.description} placeholder="Deskripsi paket..." />
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <Button type="button" variant="outline" onclick={() => (createModalOpen = false)}>Batal</Button>
                <Button type="submit" disabled={createForm.processing}>
                    {createForm.processing ? 'Menyimpan...' : 'Simpan Paket'}
                </Button>
            </div>
        </form>
    </Dialog>

    <!-- EDIT MODAL -->
    <Dialog bind:open={editModalOpen} title={`Edit Paket: ${selectedPackage?.name}`}>
        <form onsubmit={handleEdit} class="space-y-4">
            <div class="space-y-1.5">
                <label for="edit_pkg_name" class="text-xs font-semibold text-stone-700">Nama Paket</label>
                <Input id="edit_pkg_name" bind:value={editForm.name} required />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1.5">
                    <label for="edit_pkg_dl" class="text-xs font-semibold text-stone-700">Download (Mbps)</label>
                    <Input id="edit_pkg_dl" type="number" bind:value={editForm.download_speed_mbps} required />
                </div>
                <div class="space-y-1.5">
                    <label for="edit_pkg_ul" class="text-xs font-semibold text-stone-700">Upload (Mbps)</label>
                    <Input id="edit_pkg_ul" type="number" bind:value={editForm.upload_speed_mbps} required />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1.5">
                    <label for="edit_pkg_price" class="text-xs font-semibold text-stone-700">Harga Bulanan (Rp)</label>
                    <Input id="edit_pkg_price" type="number" bind:value={editForm.price} required />
                </div>
                <div class="space-y-1.5">
                    <label for="edit_pkg_profile" class="text-xs font-semibold text-stone-700">PPP Profile MikroTik</label>
                    <Input id="edit_pkg_profile" bind:value={editForm.ppp_profile} required />
                </div>
            </div>

            <div class="space-y-1.5">
                <label for="edit_pkg_status" class="text-xs font-semibold text-stone-700">Status Aktif</label>
                <select
                    id="edit_pkg_status"
                    class="flex h-9 w-full rounded-lg border border-stone-200 bg-stone-50 px-3 py-1 text-sm text-stone-900"
                    bind:value={editForm.is_active}
                >
                    <option value={true}>Aktif (Bisa Dipilih)</option>
                    <option value={false}>Non-Aktif</option>
                </select>
            </div>

            <div class="space-y-1.5">
                <label for="edit_pkg_desc" class="text-xs font-semibold text-stone-700">Deskripsi</label>
                <Input id="edit_pkg_desc" bind:value={editForm.description} />
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <Button type="button" variant="outline" onclick={() => (editModalOpen = false)}>Batal</Button>
                <Button type="submit" disabled={editForm.processing}>
                    {editForm.processing ? 'Menyimpan...' : 'Perbarui Paket'}
                </Button>
            </div>
        </form>
    </Dialog>
</AuthenticatedLayout>
