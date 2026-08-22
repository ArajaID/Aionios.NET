<script>
    import { Link, useForm, router } from '@inertiajs/svelte';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
    import Button from '@/Components/ui/button/Button.svelte';
    import Input from '@/Components/ui/input/Input.svelte';
    import Select from '@/Components/ui/select/Select.svelte';
    import Table from '@/Components/ui/table/Table.svelte';
    import TableHeader from '@/Components/ui/table/TableHeader.svelte';
    import TableBody from '@/Components/ui/table/TableBody.svelte';
    import TableRow from '@/Components/ui/table/TableRow.svelte';
    import TableHead from '@/Components/ui/table/TableHead.svelte';
    import TableCell from '@/Components/ui/table/TableCell.svelte';
    import Badge from '@/Components/ui/badge/Badge.svelte';
    import Dialog from '@/Components/ui/dialog/Dialog.svelte';
    import Pagination from '@/Components/ui/pagination/Pagination.svelte';
    import { HardDrive, Plus, Search, Eye, ArrowRightLeft, CheckCircle2 } from 'lucide-svelte';
    import { formatDate } from '@/lib/utils';

    let {
        onts = { data: [], links: [] },
        customers = [],
        filters = {},
    } = $props();

    let search = $state(filters.search || '');
    let status = $state(filters.status || '');

    let createModalOpen = $state(false);
    let assignModalOpen = $state(false);
    let returnModalOpen = $state(false);
    let selectedOnt = $state(null);

    const createForm = useForm({
        ont_id: 'ONT-' + String(Math.floor(1000 + Math.random() * 9000)),
        brand: 'Huawei',
        model: 'HG8245H5',
        serial_number: '',
        mac_address: '',
        condition: 'good',
        notes: '',
    });

    const assignForm = useForm({
        customer_id: customers[0]?.id || '',
        notes: '',
    });

    const returnForm = useForm({
        condition: 'good',
        status: 'available',
        notes: '',
    });

    function handleFilter() {
        router.get('/ont', { search, status }, { preserveState: true, replace: true });
    }

    function handleCreate(e) {
        e.preventDefault();
        createForm.post('/ont', {
            onSuccess: () => {
                createModalOpen = false;
                createForm.reset();
            },
        });
    }

    function openAssign(ont) {
        selectedOnt = ont;
        assignModalOpen = true;
    }

    function handleAssign(e) {
        e.preventDefault();
        assignForm.post(`/ont/${selectedOnt.id}/assign`, {
            onSuccess: () => (assignModalOpen = false),
        });
    }

    function openReturn(ont) {
        selectedOnt = ont;
        returnForm.condition = ont.condition;
        returnModalOpen = true;
    }

    function handleReturn(e) {
        e.preventDefault();
        returnForm.post(`/ont/${selectedOnt.id}/return`, {
            onSuccess: () => (returnModalOpen = false),
        });
    }
</script>

<AuthenticatedLayout
    title="Inventori ONT"
    breadcrumbs={[{ label: 'Inventori ONT' }]}
>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-stone-900 flex items-center gap-2">
                    <HardDrive class="h-5 w-5 text-stone-800" />
                    Pelacakan & Inventori ONT
                </h1>
                <p class="text-xs text-stone-500 mt-1">Lacak posisi perangkat ONT, histori pemakaian per pelanggan, serial number, dan kondisi fisik.</p>
            </div>

            <Button variant="default" size="sm" onclick={() => (createModalOpen = true)}>
                <Plus class="h-4 w-4 mr-1" />
                Registrasi ONT Baru
            </Button>
        </div>

        <!-- Filter Bar -->
        <div class="rounded-xl border border-stone-200 bg-white p-4">
            <form
                onsubmit={(e) => {
                    e.preventDefault();
                    handleFilter();
                }}
                class="grid grid-cols-1 sm:grid-cols-3 gap-3"
            >
                <div class="sm:col-span-2 relative">
                    <Input
                        bind:value={search}
                        placeholder="Cari ID ONT, Serial Number, Brand, Model, atau MAC..."
                        class="pl-9"
                    />
                    <Search class="absolute left-3 top-2.5 h-4 w-4 text-stone-500" />
                </div>

                <div class="flex items-center gap-2">
                    <Select bind:value={status} onchange={handleFilter}>
                        <option value="">Semua Status</option>
                        <option value="available">Available (Siap Pasang)</option>
                        <option value="installed">Installed (Terpasang)</option>
                        <option value="returned">Returned (Ditarik)</option>
                        <option value="damaged">Damaged (Rusak)</option>
                        <option value="lost">Lost (Hilang)</option>
                    </Select>

                    <Button type="button" variant="outline" size="sm" onclick={() => { search = ''; status = ''; handleFilter(); }}>
                        Reset
                    </Button>
                </div>
            </form>
        </div>

        <!-- Table -->
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>ONT ID</TableHead>
                    <TableHead>Brand & Model</TableHead>
                    <TableHead>Serial Number</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Kondisi</TableHead>
                    <TableHead>Pelanggan Saat Ini</TableHead>
                    <TableHead class="text-right">Aksi</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {#if onts.data.length === 0}
                    <TableRow>
                        <TableCell colspan="7" class="text-center py-8 text-stone-500">
                            Tidak ada data ONT yang sesuai.
                        </TableCell>
                    </TableRow>
                {:else}
                    {#each onts.data as ont}
                        <TableRow>
                            <TableCell class="font-mono font-semibold text-stone-800">
                                <Link href={`/ont/${ont.id}`} class="hover:underline">
                                    {ont.ont_id}
                                </Link>
                            </TableCell>

                            <TableCell>
                                <span class="font-medium text-stone-900">{ont.brand}</span>
                                <span class="text-[11px] text-stone-500 block">{ont.model}</span>
                            </TableCell>

                            <TableCell class="font-mono text-xs text-stone-800">
                                {ont.serial_number}
                                {#if ont.mac_address}
                                    <span class="text-[10px] text-stone-500 block">{ont.mac_address}</span>
                                {/if}
                            </TableCell>

                            <TableCell>
                                <Badge
                                    variant={ont.status === 'installed'
                                        ? 'primary'
                                        : ont.status === 'available'
                                        ? 'success'
                                        : ont.status === 'returned'
                                        ? 'warning'
                                        : 'danger'}
                                >
                                    {ont.status?.toUpperCase()}
                                </Badge>
                            </TableCell>

                            <TableCell>
                                <span class="text-xs font-semibold capitalize {ont.condition === 'good' ? 'text-emerald-700' : ont.condition === 'fair' ? 'text-amber-800' : 'text-rose-700'}">
                                    {ont.condition}
                                </span>
                            </TableCell>

                            <TableCell>
                                {#if ont.current_customer}
                                    <Link href={`/customers/${ont.current_customer.id}`} class="text-xs text-stone-800 hover:text-stone-800 hover:underline">
                                        {ont.current_customer.name} ({ont.current_customer.customer_id})
                                    </Link>
                                {:else}
                                    <span class="text-xs text-stone-500">Di Gudang (Tersedia)</span>
                                {/if}
                            </TableCell>

                            <TableCell class="text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    {#if ont.status === 'available'}
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="h-7 px-2.5 text-[11px]"
                                            onclick={() => openAssign(ont)}
                                        >
                                            Pasang
                                        </Button>
                                    {:else if ont.status === 'installed'}
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            class="h-7 px-2 text-[11px]"
                                            onclick={() => openReturn(ont)}
                                        >
                                            Tarik
                                        </Button>
                                    {/if}

                                    <Link href={`/ont/${ont.id}`}>
                                        <Button variant="outline" size="sm" class="h-7 px-2 text-[11px]">
                                            <Eye class="h-3 w-3" />
                                        </Button>
                                    </Link>
                                </div>
                            </TableCell>
                        </TableRow>
                    {/each}
                {/if}
            </TableBody>
        </Table>

        <Pagination links={onts.links} />
    </div>

    <!-- CREATE ONT MODAL -->
    <Dialog bind:open={createModalOpen} title="Registrasi ONT Baru ke Inventori">
        <form onsubmit={handleCreate} class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1.5">
                    <label for="ont_id" class="text-xs font-semibold text-stone-700">ONT ID</label>
                    <Input id="ont_id" bind:value={createForm.ont_id} required />
                </div>
                <div class="space-y-1.5">
                    <label for="ont_brand" class="text-xs font-semibold text-stone-700">Brand / Merk</label>
                    <Input id="ont_brand" bind:value={createForm.brand} placeholder="Huawei / ZTE / Fiberhome" required />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1.5">
                    <label for="ont_model" class="text-xs font-semibold text-stone-700">Tipe Model</label>
                    <Input id="ont_model" bind:value={createForm.model} placeholder="HG8245H5 / F609" required />
                </div>
                <div class="space-y-1.5">
                    <label for="ont_condition" class="text-xs font-semibold text-stone-700">Kondisi Fisik</label>
                    <Select id="ont_condition" bind:value={createForm.condition} required>
                        <option value="good">Good (Bagus / Baru)</option>
                        <option value="fair">Fair (Normal / Bekas)</option>
                        <option value="bad">Bad (Kurang Baik)</option>
                    </Select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1.5">
                    <label for="ont_sn" class="text-xs font-semibold text-stone-700">Serial Number (SN)</label>
                    <Input id="ont_sn" bind:value={createForm.serial_number} placeholder="HWTC12345678" required />
                </div>
                <div class="space-y-1.5">
                    <label for="ont_mac" class="text-xs font-semibold text-stone-700">MAC Address (Opsional)</label>
                    <Input id="ont_mac" bind:value={createForm.mac_address} placeholder="48:EE:0C:..." />
                </div>
            </div>

            <div class="space-y-1.5">
                <label for="ont_notes" class="text-xs font-semibold text-stone-700">Catatan Inventori</label>
                <Input id="ont_notes" bind:value={createForm.notes} placeholder="Catatan batch pengadaan..." />
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <Button type="button" variant="outline" onclick={() => (createModalOpen = false)}>Batal</Button>
                <Button type="submit" disabled={createForm.processing}>
                    {createForm.processing ? 'Menyimpan...' : 'Simpan ONT'}
                </Button>
            </div>
        </form>
    </Dialog>

    <!-- ASSIGN ONT MODAL -->
    <Dialog bind:open={assignModalOpen} title={`Pasang ONT ${selectedOnt?.ont_id} ke Pelanggan`}>
        <form onsubmit={handleAssign} class="space-y-4">
            <div class="space-y-1.5">
                <label for="assign_ont_cust" class="text-xs font-semibold text-stone-700">Pilih Pelanggan</label>
                <Select id="assign_ont_cust" bind:value={assignForm.customer_id} required>
                    {#each customers as cust}
                        <option value={cust.id}>{cust.name} ({cust.customer_id})</option>
                    {/each}
                </Select>
            </div>

            <div class="space-y-1.5">
                <label for="assign_ont_notes" class="text-xs font-semibold text-stone-700">Catatan Pemasangan</label>
                <Input id="assign_ont_notes" bind:value={assignForm.notes} placeholder="Lokasi penempatan ONT di rumah..." />
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <Button type="button" variant="outline" onclick={() => (assignModalOpen = false)}>Batal</Button>
                <Button type="submit" variant="success" disabled={assignForm.processing}>
                    {assignForm.processing ? 'Memproses...' : 'Pasang ke Pelanggan'}
                </Button>
            </div>
        </form>
    </Dialog>

    <!-- RETURN ONT MODAL -->
    <Dialog bind:open={returnModalOpen} title={`Tarik ONT ${selectedOnt?.ont_id} dari Pelanggan`}>
        <form onsubmit={handleReturn} class="space-y-4">
            <div class="space-y-1.5">
                <label for="return_cond" class="text-xs font-semibold text-stone-700">Kondisi Fisik Saat Penarikan</label>
                <Select id="return_cond" bind:value={returnForm.condition} required>
                    <option value="good">Good (Normal / Masih Bagus)</option>
                    <option value="fair">Fair (Normal Bekas)</option>
                    <option value="bad">Bad (Rusak / Butuh Servis)</option>
                </Select>
            </div>

            <div class="space-y-1.5">
                <label for="return_status" class="text-xs font-semibold text-stone-700">Status Penyimpanan Baru</label>
                <Select id="return_status" bind:value={returnForm.status} required>
                    <option value="available">Available (Siap Dipakai Lagi)</option>
                    <option value="returned">Returned (Gudang Ditarik)</option>
                    <option value="damaged">Damaged (Rusak)</option>
                    <option value="lost">Lost (Hilang)</option>
                </Select>
            </div>

            <div class="space-y-1.5">
                <label for="return_notes" class="text-xs font-semibold text-stone-700">Catatan Penarikan</label>
                <Input id="return_notes" bind:value={returnForm.notes} placeholder="Kelengkapan adaptor, kabel, kondisi..." />
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <Button type="button" variant="outline" onclick={() => (returnModalOpen = false)}>Batal</Button>
                <Button type="submit" variant="destructive" disabled={returnForm.processing}>
                    {returnForm.processing ? 'Menyimpan...' : 'Konfirmasi Penarikan'}
                </Button>
            </div>
        </form>
    </Dialog>
</AuthenticatedLayout>
