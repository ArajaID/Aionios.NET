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
    import Pagination from '@/Components/ui/pagination/Pagination.svelte';
    import { FileText, Plus, Search, Trash2, CheckCircle2, AlertTriangle } from 'lucide-svelte';
    import { formatRupiah, formatDate } from '@/lib/utils';

    let {
        journals = { data: [], links: [] },
        coas = [],
        filters = {},
    } = $props();

    let search = $state(filters.search || '');
    let type = $state(filters.type || '');
    let manualModalOpen = $state(false);

    const manualForm = useForm({
        date: new Date().toISOString().split('T')[0],
        description: '',
        lines: [
            { chart_of_account_id: coas[0]?.id || '', debit: 0, credit: 0, memo: '' },
            { chart_of_account_id: coas[1]?.id || '', debit: 0, credit: 0, memo: '' },
        ],
    });

    const manualDebit = $derived(
        manualForm.lines.reduce((sum, l) => sum + Number(l.debit || 0), 0)
    );

    const manualCredit = $derived(
        manualForm.lines.reduce((sum, l) => sum + Number(l.credit || 0), 0)
    );

    const isManualBalanced = $derived(
        manualDebit > 0 && Math.round(manualDebit * 100) === Math.round(manualCredit * 100)
    );

    function addRow() {
        manualForm.lines = [
            ...manualForm.lines,
            { chart_of_account_id: coas[0]?.id || '', debit: 0, credit: 0, memo: '' },
        ];
    }

    function removeRow(idx) {
        if (manualForm.lines.length <= 2) return;
        manualForm.lines = manualForm.lines.filter((_, i) => i !== idx);
    }

    function handleFilter() {
        router.get('/accounting/journals', { search, type }, { preserveState: true, replace: true });
    }

    function handleManualSubmit(e) {
        e.preventDefault();
        if (!isManualBalanced) return;
        manualForm.post('/accounting/journals/manual', {
            onSuccess: () => {
                manualModalOpen = false;
                manualForm.reset();
            },
        });
    }
</script>

<AuthenticatedLayout
    title="Jurnal Umum (General Journal)"
    breadcrumbs={[{ label: 'Jurnal Umum' }]}
>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-stone-900 flex items-center gap-2">
                    <FileText class="h-5 w-5 text-stone-800" />
                    Jurnal Umum & Pembukuan Otomatis
                </h1>
                <p class="text-xs text-stone-500 mt-1">Daftar seluruh jurnal otomatis dari payment, beban, pemasukan lain, modal, dan penyesuaian manual.</p>
            </div>

            <Button variant="default" size="sm" onclick={() => (manualModalOpen = true)}>
                <Plus class="h-4 w-4 mr-1" />
                Buat Jurnal Penyesuaian Manual
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
                        placeholder="Cari No. Jurnal atau Keterangan..."
                        class="pl-9"
                    />
                    <Search class="absolute left-3 top-2.5 h-4 w-4 text-stone-500" />
                </div>

                <div class="flex items-center gap-2">
                    <Select bind:value={type} onchange={handleFilter}>
                        <option value="">Semua Tipe Transaksi</option>
                        <option value="payment">Payment (Pembayaran)</option>
                        <option value="expense">Expense (Beban)</option>
                        <option value="other_income">Other Income (Pemasukan Lain)</option>
                        <option value="capital">Capital (Modal)</option>
                        <option value="reversal">Reversal (Pembalik)</option>
                        <option value="opening_balance">Opening Balance (Saldo Awal)</option>
                        <option value="manual">Manual (Penyesuaian)</option>
                    </Select>

                    <Button type="button" variant="outline" size="sm" onclick={() => { search = ''; type = ''; handleFilter(); }}>
                        Reset
                    </Button>
                </div>
            </form>
        </div>

        <!-- Journal Entries List -->
        <div class="space-y-4">
            {#if journals.data.length === 0}
                <Card>
                    <CardContent class="py-10 text-center text-stone-500 text-xs">
                        Tidak ada catatan jurnal yang ditemukan.
                    </CardContent>
                </Card>
            {:else}
                {#each journals.data as jrn}
                    <Card class="border-stone-200 bg-white">
                        <CardHeader class="py-3 px-4 border-b border-stone-200 bg-stone-50 flex flex-row items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="font-mono font-bold text-stone-800 text-xs">{jrn.entry_number}</span>
                                <Badge variant="outline">{jrn.reference_type?.toUpperCase()}</Badge>
                                <span class="text-xs text-stone-500">Tgl: {formatDate(jrn.date)}</span>
                            </div>
                            <div class="text-xs text-stone-500">
                                <span>Oleh: {jrn.creator?.name || 'Sistem Otomatis'}</span>
                            </div>
                        </CardHeader>

                        <CardContent class="p-4 space-y-2">
                            <p class="text-xs font-medium text-stone-800">{jrn.description}</p>

                            <div class="overflow-x-auto rounded-lg border border-stone-200 bg-stone-50">
                                <table class="w-full text-xs text-left">
                                    <thead class="border-b border-stone-200 text-stone-500 uppercase text-[10px]">
                                        <tr>
                                            <th class="py-1.5 px-3">Kode & Nama Akun</th>
                                            <th class="py-1.5 px-3">Memo Line</th>
                                            <th class="py-1.5 px-3 text-right">Debit (Rp)</th>
                                            <th class="py-1.5 px-3 text-right">Kredit (Rp)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-stone-100">
                                        {#each jrn.lines as line}
                                            <tr class="hover:bg-white/30">
                                                <td class="py-1.5 px-3 font-medium text-stone-800">
                                                    <span class="font-mono text-stone-500 mr-1.5">{line.chart_of_account?.code}</span>
                                                    {line.chart_of_account?.name}
                                                </td>
                                                <td class="py-1.5 px-3 text-stone-500">{line.memo || '-'}</td>
                                                <td class="py-1.5 px-3 text-right font-mono {line.debit > 0 ? 'text-emerald-700 font-semibold' : 'text-stone-600'}">
                                                    {line.debit > 0 ? formatRupiah(line.debit) : '-'}
                                                </td>
                                                <td class="py-1.5 px-3 text-right font-mono {line.credit > 0 ? 'text-stone-800 font-semibold' : 'text-stone-600'}">
                                                    {line.credit > 0 ? formatRupiah(line.credit) : '-'}
                                                </td>
                                            </tr>
                                        {/each}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                {/each}
            {/if}
        </div>

        <Pagination links={journals.links} />
    </div>

    <!-- MANUAL JOURNAL MODAL -->
    <Dialog bind:open={manualModalOpen} title="Buat Jurnal Penyesuaian Manual" maxWidth="max-w-3xl">
        <form onsubmit={handleManualSubmit} class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1.5">
                    <label for="man_date" class="text-xs font-semibold text-stone-700">Tanggal Jurnal</label>
                    <Input id="man_date" type="date" bind:value={manualForm.date} required />
                </div>
                <div class="space-y-1.5">
                    <label for="man_desc" class="text-xs font-semibold text-stone-700">Keterangan / Transaksi</label>
                    <Input id="man_desc" bind:value={manualForm.description} placeholder="e.g. Reklasifikasi biaya operasional" required />
                </div>
            </div>

            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <label for="man_lines" class="text-xs font-semibold text-stone-700">Baris Akun Jurnal (Debit = Kredit)</label>
                    <Button type="button" variant="outline" size="sm" class="h-6 text-[11px]" onclick={addRow}>
                        + Tambah Baris
                    </Button>
                </div>

                <div class="overflow-x-auto rounded-xl border border-stone-200 bg-stone-50 p-2">
                    <table class="w-full text-xs text-left" id="man_lines">
                        <thead class="text-stone-500 uppercase text-[10px]">
                            <tr>
                                <th class="py-1 px-2">Akun COA</th>
                                <th class="py-1 px-2">Memo</th>
                                <th class="py-1 px-2 text-right w-36">Debit</th>
                                <th class="py-1 px-2 text-right w-36">Kredit</th>
                                <th class="py-1 px-2 text-center w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="space-y-1">
                            {#each manualForm.lines as line, i}
                                <tr>
                                    <td class="p-1">
                                        <Select bind:value={manualForm.lines[i].chart_of_account_id} class="h-8 text-xs" required>
                                            {#each coas as c}
                                                <option value={c.id}>{c.code} - {c.name}</option>
                                            {/each}
                                        </Select>
                                    </td>
                                    <td class="p-1">
                                        <Input bind:value={manualForm.lines[i].memo} placeholder="Memo..." class="h-8 text-xs" />
                                    </td>
                                    <td class="p-1">
                                        <Input type="number" bind:value={manualForm.lines[i].debit} min="0" class="h-8 text-xs text-right font-mono" />
                                    </td>
                                    <td class="p-1">
                                        <Input type="number" bind:value={manualForm.lines[i].credit} min="0" class="h-8 text-xs text-right font-mono" />
                                    </td>
                                    <td class="p-1 text-center">
                                        <button
                                            type="button"
                                            onclick={() => removeRow(i)}
                                            class="text-stone-500 hover:text-rose-700 p-1"
                                            title="Hapus baris"
                                        >
                                            <Trash2 class="h-3.5 w-3.5" />
                                        </button>
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>

                <!-- Live Balance Status -->
                <div class="flex items-center justify-between p-3 rounded-xl bg-white border border-stone-200 text-xs">
                    <div>
                        <span class="text-stone-500">Total Debit: </span>
                        <span class="font-mono font-bold text-emerald-700">{formatRupiah(manualDebit)}</span>
                    </div>
                    <div>
                        <span class="text-stone-500">Total Kredit: </span>
                        <span class="font-mono font-bold text-stone-800">{formatRupiah(manualCredit)}</span>
                    </div>
                    <div>
                        {#if isManualBalanced}
                            <span class="text-emerald-700 font-bold flex items-center gap-1">
                                <CheckCircle2 class="h-3.5 w-3.5" /> Balanced
                            </span>
                        {:else}
                            <span class="text-rose-700 font-bold flex items-center gap-1">
                                <AlertTriangle class="h-3.5 w-3.5" /> Selisih: {formatRupiah(Math.abs(manualDebit - manualCredit))}
                            </span>
                        {/if}
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <Button type="button" variant="outline" onclick={() => (manualModalOpen = false)}>Batal</Button>
                <Button type="submit" disabled={!isManualBalanced || manualForm.processing} class="bg-indigo-600 hover:bg-indigo-500">
                    {manualForm.processing ? 'Memposting...' : 'Posting Jurnal Manual'}
                </Button>
            </div>
        </form>
    </Dialog>
</AuthenticatedLayout>
