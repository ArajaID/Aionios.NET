<script>
    import { useForm, router, page } from '@inertiajs/svelte';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
    import Button from '@/Components/ui/button/Button.svelte';
    import Input from '@/Components/ui/input/Input.svelte';
    import Card from '@/Components/ui/card/Card.svelte';
    import CardHeader from '@/Components/ui/card/CardHeader.svelte';
    import CardTitle from '@/Components/ui/card/CardTitle.svelte';
    import CardContent from '@/Components/ui/card/CardContent.svelte';
    import Badge from '@/Components/ui/badge/Badge.svelte';
    import Dialog from '@/Components/ui/dialog/Dialog.svelte';
    import ConfirmationDialog from '@/Components/ui/confirmation-dialog/ConfirmationDialog.svelte';
    import Alert from '@/Components/ui/alert/Alert.svelte';
    import { Lock, Unlock, Plus } from 'lucide-svelte';
    import { formatDate } from '@/lib/utils';

    let { periods = [] } = $props();

    const user = $derived(page.props.auth?.user);

    let createModalOpen = $state(false);
    let closeModalOpen = $state(false);
    let reopenModalOpen = $state(false);
    let selectedPeriod = $state(null);
    let closing = $state(false);

    const createForm = useForm({
        period: new Date().toISOString().slice(0, 7),
    });

    const reopenForm = useForm({
        reopen_reason: '',
    });

    function handleCreate(e) {
        e.preventDefault();
        createForm.post('/accounting/periods', {
            onSuccess: () => (createModalOpen = false),
        });
    }

    function openClosePeriod(period) {
        selectedPeriod = period;
        closeModalOpen = true;
    }

    function confirmClosePeriod() {
        if (!selectedPeriod || closing) return;

        closing = true;
        router.post('/accounting/periods/close', { period: selectedPeriod.period }, {
            preserveScroll: true,
            onSuccess: () => (closeModalOpen = false),
            onFinish: () => (closing = false),
        });
    }

    function openReopen(period) {
        selectedPeriod = period;
        reopenForm.reopen_reason = '';
        reopenModalOpen = true;
    }

    function handleReopen(e) {
        e.preventDefault();
        reopenForm.post(`/accounting/periods/${selectedPeriod.id}/reopen`, {
            onSuccess: () => (reopenModalOpen = false),
        });
    }
</script>

<AuthenticatedLayout
    title="Kunci Periode Akuntansi (Period Locking)"
    breadcrumbs={[{ label: 'Kunci Periode' }]}
>
    <div class="max-w-4xl mx-auto space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-stone-900 flex items-center gap-2">
                    <Lock class="h-5 w-5 text-stone-800" />
                    Kunci Periode & Tutup Buku (Period Locking)
                </h1>
                <p class="text-xs text-stone-500 mt-1">
                    Cegah modifikasi atau penambahan transaksi pada periode akuntansi yang telah ditutup.
                </p>
            </div>

            {#if user?.role === 'owner'}
                <Button variant="default" size="sm" onclick={() => (createModalOpen = true)}>
                    <Plus class="h-4 w-4 mr-1" />
                    Buka Periode Baru
                </Button>
            {/if}
        </div>

        <Alert variant="warning" title="Aturan Period Locking">
            Ketika sebuah periode akuntansi berstatus <strong>CLOSED</strong>, sistem secara otomatis menolak seluruh input pembayaran, beban pengeluaran, pemasukan lain, dan posting jurnal pada rentang tanggal periode tersebut untuk menjaga integritas laporan historis.
        </Alert>

        <Card>
            <CardHeader>
                <CardTitle>Daftar Periode Akuntansi Pembukuan</CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="border-b border-stone-200 text-stone-500 uppercase text-[10px] bg-stone-50">
                            <tr>
                                <th class="py-3 px-4">Periode (YYYY-MM)</th>
                                <th class="py-3 px-4">Status Buku</th>
                                <th class="py-3 px-4">Waktu Ditutup</th>
                                <th class="py-3 px-4">Oleh Admin</th>
                                <th class="py-3 px-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            {#if periods.length === 0}
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-stone-500">Belum ada periode akuntansi terdaftar.</td>
                                </tr>
                            {:else}
                                {#each periods as p}
                                    <tr class="hover:bg-stone-50">
                                        <td class="py-3 px-4 font-mono font-bold text-stone-900">{p.period}</td>
                                        <td class="py-3 px-4">
                                            <Badge variant={p.status === 'open' ? 'success' : 'danger'}>
                                                {p.status === 'open' ? 'OPEN (TERBUKA)' : 'CLOSED (TERKUNCI)'}
                                            </Badge>
                                        </td>
                                        <td class="py-3 px-4 text-stone-500">
                                            {p.closed_at ? formatDate(p.closed_at, true) : '-'}
                                        </td>
                                        <td class="py-3 px-4 text-stone-700">
                                            {p.closer?.name || '-'}
                                        </td>
                                        <td class="py-3 px-4 text-right">
                                            {#if p.status === 'open' && user?.role === 'owner'}
                                                <Button
                                                    variant="destructive"
                                                    size="sm"
                                                    class="h-7 px-2.5 text-[11px]"
                                                    onclick={() => openClosePeriod(p)}
                                                >
                                                    <Lock class="h-3 w-3 mr-1" />
                                                    Tutup & Kunci Periode
                                                </Button>
                                            {:else if p.status === 'closed' && user?.role === 'owner'}
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    class="h-7 px-2.5 text-[11px] text-amber-800"
                                                    onclick={() => openReopen(p)}
                                                >
                                                    <Unlock class="h-3 w-3 mr-1" />
                                                    Buka Kunci (Reopen)
                                                </Button>
                                            {:else}
                                                <span class="text-stone-500 text-[11px]">
                                                    {p.status === 'open' ? 'Terbuka (Hanya Owner dapat menutup)' : 'Terkunci (Hanya Owner)'}
                                                </span>
                                            {/if}
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

    <ConfirmationDialog
        bind:open={closeModalOpen}
        title={`Tutup & Kunci Periode ${selectedPeriod?.period ?? ''}`}
        confirmLabel="Ya, Tutup Periode"
        variant="destructive"
        processing={closing}
        onconfirm={confirmClosePeriod}
    >
        Periode <strong>{selectedPeriod?.period}</strong> akan dikunci. Seluruh penambahan dan
        perubahan transaksi pada periode ini akan diblokir untuk menjaga integritas laporan.
    </ConfirmationDialog>

    <!-- CREATE PERIOD MODAL -->
    <Dialog bind:open={createModalOpen} title="Buka Periode Akuntansi Baru">
        <form onsubmit={handleCreate} class="space-y-4">
            <div class="space-y-1.5">
                <label for="period_code" class="text-xs font-semibold text-stone-700">Kode Periode (YYYY-MM)</label>
                <Input id="period_code" bind:value={createForm.period} placeholder="2026-09" required />
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <Button type="button" variant="outline" onclick={() => (createModalOpen = false)}>Batal</Button>
                <Button type="submit" disabled={createForm.processing}>
                    {createForm.processing ? 'Membuka...' : 'Buka Periode'}
                </Button>
            </div>
        </form>
    </Dialog>

    <!-- REOPEN PERIOD MODAL -->
    <Dialog bind:open={reopenModalOpen} title={`Buka Kembali Periode ${selectedPeriod?.period}`}>
        <form onsubmit={handleReopen} class="space-y-4">
            <Alert variant="destructive" title="Peringatan Reopen Periode">
                Membuka kembali periode yang telah ditutup dapat mengubah angka laporan keuangan historis. Seluruh aktivitas perubahan akan dicatat pada Log Audit.
            </Alert>

            <div class="space-y-1.5">
                <label for="reopen_reason" class="text-xs font-semibold text-stone-700">Alasan Reopen (Wajib)</label>
                <Input id="reopen_reason" bind:value={reopenForm.reopen_reason} placeholder="e.g. Koreksi pembukuan atas temuan audit..." required />
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <Button type="button" variant="outline" onclick={() => (reopenModalOpen = false)}>Batal</Button>
                <Button type="submit" variant="destructive" disabled={reopenForm.processing}>
                    {reopenForm.processing ? 'Membuka...' : 'Konfirmasi Buka Kunci'}
                </Button>
            </div>
        </form>
    </Dialog>
</AuthenticatedLayout>
