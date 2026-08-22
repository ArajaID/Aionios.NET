<script>
    import { useForm, router } from '@inertiajs/svelte';
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
    import Alert from '@/Components/ui/alert/Alert.svelte';
    import { Sparkles, Plus, Users, UserCheck, XCircle, ArrowRight } from 'lucide-svelte';
    import { formatRupiah, formatDate } from '@/lib/utils';

    let {
        promotions = [],
        active_assignments = [],
        customers = [],
    } = $props();

    let createModalOpen = $state(false);
    let assignModalOpen = $state(false);

    const createForm = useForm({
        code: '',
        name: '',
        type: 'speed_boost',
        discount_type: 'fixed',
        discount_value: 0,
        duration_months: 3,
        promo_ppp_profile: '',
        description: '',
    });

    const assignForm = useForm({
        customer_id: customers[0]?.id || '',
        promotion_id: promotions[0]?.id || '',
        start_date: new Date().toISOString().split('T')[0],
    });

    function handleCreate(e) {
        e.preventDefault();
        createForm.post('/promotions', {
            onSuccess: () => {
                createModalOpen = false;
                createForm.reset();
            },
        });
    }

    function handleAssign(e) {
        e.preventDefault();
        assignForm.post('/promotions/assign', {
            onSuccess: () => {
                assignModalOpen = false;
                assignForm.reset();
            },
        });
    }

    function cancelAssignment(id) {
        if (confirm('Yakin ingin membatalkan promo untuk pelanggan ini? Profil bandwidth / harga akan dikembalikan ke normal.')) {
            router.post(`/promotions/${id}/cancel`);
        }
    }
</script>

<AuthenticatedLayout
    title="Promo & Diskon"
    breadcrumbs={[{ label: 'Promo' }]}
>
    <div class="space-y-8">
        <!-- Top Action Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-stone-200 pb-5">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-stone-900 flex items-center gap-2">
                    <Sparkles class="h-5 w-5 text-stone-800" />
                    Manajemen Promo & Diskon Pelanggan
                </h1>
                <p class="text-xs text-stone-500 mt-1">Dukung Promo Speed Boost (harga tetap), Speed Tetap (harga turun), dan Special Discount.</p>
            </div>

            <div class="flex items-center gap-2.5">
                <Button variant="outline" size="sm" onclick={() => (assignModalOpen = true)}>
                    <UserCheck class="h-4 w-4 mr-1 text-emerald-700" />
                    Berikan Promo ke Pelanggan
                </Button>

                <Button variant="default" size="sm" onclick={() => (createModalOpen = true)}>
                    <Plus class="h-4 w-4 mr-1" />
                    Buat Promo Baru
                </Button>
            </div>
        </div>

        <Alert variant="info" title="Aturan Bisnis Promo ISP (PRD Bagian 11 & 12)">
            - <strong>Speed Boost:</strong> Sistem mengubah PPP Profile di MikroTik tanpa mengubah harga invoice.<br />
            - <strong>Price Cut & Special Discount:</strong> Dievaluasi saat invoice reguler diterbitkan tanggal 1. Tagihan pertama pelanggan baru selalu menggunakan harga normal.
        </Alert>

        <!-- Promotion Campaigns Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            {#each promotions as promo}
                <Card class="border-stone-200 bg-white">
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between">
                            <Badge variant={promo.type === 'speed_boost' ? 'primary' : promo.type === 'price_cut' ? 'success' : 'purple'}>
                                {promo.type === 'speed_boost' ? 'SPEED BOOST' : promo.type === 'price_cut' ? 'PRICE CUT' : 'SPECIAL DISCOUNT'}
                            </Badge>
                            <span class="text-xs font-mono text-stone-500 bg-stone-800 px-2 py-0.5 rounded border border-stone-300">
                                {promo.code}
                            </span>
                        </div>
                        <CardTitle class="text-base mt-2">{promo.name}</CardTitle>
                        <p class="text-xs text-stone-500">{promo.description || '-'}</p>
                    </CardHeader>

                    <CardContent class="space-y-2 text-xs pt-2 border-t border-stone-200">
                        <div class="flex justify-between py-1 border-b border-stone-200">
                            <span class="text-stone-500">Durasi Promo:</span>
                            <span class="font-bold text-stone-800">{promo.duration_months} Bulan</span>
                        </div>

                        {#if promo.type === 'speed_boost'}
                            <div class="flex justify-between py-1">
                                <span class="text-stone-500">Target PPP Profile:</span>
                                <span class="font-mono text-stone-800 font-semibold">{promo.promo_ppp_profile || '-'}</span>
                            </div>
                        {:else if promo.type === 'price_cut'}
                            <div class="flex justify-between py-1">
                                <span class="text-stone-500">Harga Promo:</span>
                                <span class="font-mono font-bold text-emerald-700">{formatRupiah(promo.discount_value)}/bln</span>
                            </div>
                        {:else}
                            <div class="flex justify-between py-1">
                                <span class="text-stone-500">Nilai Diskon:</span>
                                <span class="font-mono font-bold text-purple-700">
                                    {promo.discount_type === 'percentage' ? `${promo.discount_value}%` : formatRupiah(promo.discount_value)}
                                </span>
                            </div>
                        {/if}
                    </CardContent>
                </Card>
            {/each}
        </div>

        <!-- Active Customer Assignments Table -->
        <Card>
            <CardHeader class="pb-3">
                <CardTitle>Daftar Pelanggan Penerima Promo Aktif</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="border-b border-stone-200 text-stone-500 uppercase">
                            <tr>
                                <th class="py-2.5">Pelanggan</th>
                                <th class="py-2.5">Promo</th>
                                <th class="py-2.5">Tipe</th>
                                <th class="py-2.5">Mulai</th>
                                <th class="py-2.5">Berakhir</th>
                                <th class="py-2.5 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            {#if active_assignments.length === 0}
                                <tr>
                                    <td colspan="6" class="py-6 text-center text-stone-500">Tidak ada penugasan promo aktif saat ini.</td>
                                </tr>
                            {:else}
                                {#each active_assignments as cp}
                                    <tr class="hover:bg-stone-50">
                                        <td class="py-2.5 font-medium text-stone-900">
                                            {cp.customer?.name} ({cp.customer?.customer_id})
                                        </td>
                                        <td class="py-2.5 font-semibold text-stone-800">{cp.promotion?.name}</td>
                                        <td class="py-2.5">
                                            <Badge variant="outline">{cp.promotion?.type}</Badge>
                                        </td>
                                        <td class="py-2.5 text-stone-500">{formatDate(cp.start_date)}</td>
                                        <td class="py-2.5 text-amber-800 font-medium">{formatDate(cp.end_date)}</td>
                                        <td class="py-2.5 text-right">
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                class="h-6 px-2 text-[11px]"
                                                onclick={() => cancelAssignment(cp.id)}
                                            >
                                                Batalkan
                                            </Button>
                                        </td>
                                    </tr>
                                {/each}
                            {/if}
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- CREATE PROMOTION MODAL -->
    <Dialog bind:open={createModalOpen} title="Buat Program Promo Baru">
        <form onsubmit={handleCreate} class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1.5">
                    <label for="promo_code" class="text-xs font-semibold text-stone-700">Kode Promo</label>
                    <Input id="promo_code" bind:value={createForm.code} placeholder="PROMO-BOOST30" required />
                </div>
                <div class="space-y-1.5">
                    <label for="promo_name" class="text-xs font-semibold text-stone-700">Nama Promo</label>
                    <Input id="promo_name" bind:value={createForm.name} placeholder="Speed Boost 30M" required />
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1.5">
                    <label for="promo_type" class="text-xs font-semibold text-stone-700">Tipe Promo</label>
                    <Select id="promo_type" bind:value={createForm.type} required>
                        <option value="speed_boost">Speed Boost (Harga Tetap)</option>
                        <option value="price_cut">Speed Tetap (Harga Turun)</option>
                        <option value="special_discount">Special Discount</option>
                    </Select>
                </div>
                <div class="space-y-1.5">
                    <label for="promo_duration" class="text-xs font-semibold text-stone-700">Durasi (Bulan)</label>
                    <Input id="promo_duration" type="number" bind:value={createForm.duration_months} min="1" max="36" required />
                </div>
            </div>

            {#if createForm.type === 'speed_boost'}
                <div class="space-y-1.5">
                    <label for="promo_profile" class="text-xs font-semibold text-stone-700">PPP Profile Tujuan di MikroTik</label>
                    <Input id="promo_profile" bind:value={createForm.promo_ppp_profile} placeholder="PROFILE-30M" required />
                </div>
            {:else if createForm.type === 'price_cut'}
                <div class="space-y-1.5">
                    <label for="promo_price" class="text-xs font-semibold text-stone-700">Harga Promo Bulanan (Rp)</label>
                    <Input id="promo_price" type="number" bind:value={createForm.discount_value} min="0" required />
                </div>
            {:else}
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label for="promo_disc_type" class="text-xs font-semibold text-stone-700">Jenis Diskon</label>
                        <Select id="promo_disc_type" bind:value={createForm.discount_type} required>
                            <option value="fixed">Nominal Tetap (Rp)</option>
                            <option value="percentage">Persentase (%)</option>
                        </Select>
                    </div>
                    <div class="space-y-1.5">
                        <label for="promo_disc_val" class="text-xs font-semibold text-stone-700">Besaran Diskon</label>
                        <Input id="promo_disc_val" type="number" bind:value={createForm.discount_value} min="0" required />
                    </div>
                </div>
            {/if}

            <div class="space-y-1.5">
                <label for="promo_desc" class="text-xs font-semibold text-stone-700">Deskripsi Ketentuan</label>
                <Input id="promo_desc" bind:value={createForm.description} placeholder="Keterangan promo..." />
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <Button type="button" variant="outline" onclick={() => (createModalOpen = false)}>Batal</Button>
                <Button type="submit" disabled={createForm.processing}>
                    {createForm.processing ? 'Menyimpan...' : 'Simpan Program Promo'}
                </Button>
            </div>
        </form>
    </Dialog>

    <!-- ASSIGN PROMO MODAL -->
    <Dialog bind:open={assignModalOpen} title="Berikan Promo ke Pelanggan">
        <form onsubmit={handleAssign} class="space-y-4">
            <div class="space-y-1.5">
                <label for="assign_customer" class="text-xs font-semibold text-stone-700">Pilih Pelanggan Aktif</label>
                <Select id="assign_customer" bind:value={assignForm.customer_id} required>
                    {#each customers as cust}
                        <option value={cust.id}>{cust.name} ({cust.customer_id})</option>
                    {/each}
                </Select>
            </div>

            <div class="space-y-1.5">
                <label for="assign_promo" class="text-xs font-semibold text-stone-700">Pilih Program Promo</label>
                <Select id="assign_promo" bind:value={assignForm.promotion_id} required>
                    {#each promotions as promo}
                        <option value={promo.id}>{promo.name} ({promo.duration_months} Bulan)</option>
                    {/each}
                </Select>
            </div>

            <div class="space-y-1.5">
                <label for="assign_start" class="text-xs font-semibold text-stone-700">Tanggal Mulai Berlaku</label>
                <Input id="assign_start" type="date" bind:value={assignForm.start_date} required />
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <Button type="button" variant="outline" onclick={() => (assignModalOpen = false)}>Batal</Button>
                <Button type="submit" variant="success" disabled={assignForm.processing}>
                    {assignForm.processing ? 'Menugaskan...' : 'Tugaskan Promo'}
                </Button>
            </div>
        </form>
    </Dialog>
</AuthenticatedLayout>
