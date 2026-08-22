<script>
    import { useForm, Link } from '@inertiajs/svelte';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
    import Button from '@/Components/ui/button/Button.svelte';
    import Input from '@/Components/ui/input/Input.svelte';
    import Card from '@/Components/ui/card/Card.svelte';
    import CardHeader from '@/Components/ui/card/CardHeader.svelte';
    import CardTitle from '@/Components/ui/card/CardTitle.svelte';
    import CardDescription from '@/Components/ui/card/CardDescription.svelte';
    import CardContent from '@/Components/ui/card/CardContent.svelte';
    import CardFooter from '@/Components/ui/card/CardFooter.svelte';
    import Alert from '@/Components/ui/alert/Alert.svelte';
    import Badge from '@/Components/ui/badge/Badge.svelte';
    import { FileSpreadsheet, ArrowLeft, CheckCircle2, AlertTriangle } from 'lucide-svelte';
    import { formatRupiah, formatDate } from '@/lib/utils';

    let { coas = [], history = [] } = $props();

    // Initialize lines with 0 for each coa
    const initialLines = coas.map((c) => ({
        chart_of_account_id: c.id,
        code: c.code,
        name: c.name,
        type: c.type,
        normal_balance: c.normal_balance,
        debit: 0,
        credit: 0,
    }));

    const form = useForm({
        date: new Date().toISOString().split('T')[0],
        notes: 'Posting Saldo Awal Migrasi Sistem',
        lines: initialLines,
    });

    const totalDebit = $derived(
        form.lines.reduce((sum, l) => sum + Number(l.debit || 0), 0)
    );

    const totalCredit = $derived(
        form.lines.reduce((sum, l) => sum + Number(l.credit || 0), 0)
    );

    const isBalanced = $derived(
        totalDebit > 0 && Math.round(totalDebit * 100) === Math.round(totalCredit * 100)
    );

    function handleSubmit(e) {
        e.preventDefault();
        if (!isBalanced) return;
        form.post('/accounting/opening-balance');
    }
</script>

<AuthenticatedLayout
    title="Posting Saldo Awal (Opening Balance)"
    breadcrumbs={[
        { label: 'COA', href: '/accounting/coa' },
        { label: 'Saldo Awal' },
    ]}
>
    <div class="max-w-5xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-stone-900 flex items-center gap-2">
                    <FileSpreadsheet class="h-5 w-5 text-emerald-700" />
                    Posting Saldo Awal Pembukuan (Opening Balance)
                </h1>
                <p class="text-xs text-stone-500 mt-1">
                    Masukkan saldo awal kas, bank, piutang, hutang, dan modal. Total Debit wajib sama dengan Total Kredit.
                </p>
            </div>

            <Link href="/accounting/coa">
                <Button variant="outline" size="sm">
                    <ArrowLeft class="h-3.5 w-3.5 mr-1" />
                    Kembali ke COA
                </Button>
            </Link>
        </div>

        <!-- Balancing Status Live Banner -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="p-4 rounded-xl border border-stone-200 bg-white">
                <span class="text-xs text-stone-500 block font-semibold uppercase">Total Debit</span>
                <span class="text-xl font-bold font-mono text-emerald-700">{formatRupiah(totalDebit)}</span>
            </div>

            <div class="p-4 rounded-xl border border-stone-200 bg-white">
                <span class="text-xs text-stone-500 block font-semibold uppercase">Total Kredit</span>
                <span class="text-xl font-bold font-mono text-stone-800">{formatRupiah(totalCredit)}</span>
            </div>

            <div class="p-4 rounded-xl border border-stone-200 bg-white flex flex-col justify-center">
                <span class="text-xs text-stone-500 block font-semibold uppercase">Status Keseimbangan</span>
                {#if isBalanced}
                    <span class="text-sm font-bold text-emerald-700 flex items-center gap-1.5 mt-1">
                        <CheckCircle2 class="h-4 w-4" />
                        SEIMBANG (BALANCED)
                    </span>
                {:else}
                    <span class="text-sm font-bold text-rose-700 flex items-center gap-1.5 mt-1">
                        <AlertTriangle class="h-4 w-4" />
                        SELISIH: {formatRupiah(Math.abs(totalDebit - totalCredit))}
                    </span>
                {/if}
            </div>
        </div>

        <form onsubmit={handleSubmit} class="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Formulir Input Saldo Awal</CardTitle>
                    <CardDescription>Masukkan nominal debit atau kredit pada akun yang memiliki saldo awal</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pb-4 border-b border-stone-200">
                        <div class="space-y-1.5">
                            <label for="ob_date" class="text-xs font-semibold text-stone-700">Tanggal Efektif Saldo Awal</label>
                            <Input id="ob_date" type="date" bind:value={form.date} required />
                        </div>
                        <div class="space-y-1.5">
                            <label for="ob_notes" class="text-xs font-semibold text-stone-700">Keterangan / Memo Pembukuan</label>
                            <Input id="ob_notes" bind:value={form.notes} required />
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="border-b border-stone-200 text-stone-500 uppercase text-[10px] bg-stone-50">
                                <tr>
                                    <th class="py-2.5 px-3">Kode</th>
                                    <th class="py-2.5 px-3">Nama Akun COA</th>
                                    <th class="py-2.5 px-3">Kelompok</th>
                                    <th class="py-2.5 px-3 text-right w-44">Debit (Rp)</th>
                                    <th class="py-2.5 px-3 text-right w-44">Kredit (Rp)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                {#each form.lines as line, i}
                                    <tr class="hover:bg-stone-50">
                                        <td class="py-2 px-3 font-mono font-bold text-stone-800">{line.code}</td>
                                        <td class="py-2 px-3 font-medium text-stone-900">{line.name}</td>
                                        <td class="py-2 px-3 capitalize text-stone-500">{line.type}</td>
                                        <td class="py-2 px-3">
                                            <Input
                                                type="number"
                                                bind:value={form.lines[i].debit}
                                                min="0"
                                                class="h-8 text-right font-mono"
                                            />
                                        </td>
                                        <td class="py-2 px-3">
                                            <Input
                                                type="number"
                                                bind:value={form.lines[i].credit}
                                                min="0"
                                                class="h-8 text-right font-mono"
                                            />
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                </CardContent>

                <CardFooter class="flex items-center justify-between border-t border-stone-200 pt-4">
                    <span class="text-xs text-stone-500">
                        {isBalanced ? '✓ Form siap diposting ke Jurnal Umum.' : 'Form belum seimbang.'}
                    </span>

                    <Button
                        type="submit"
                        disabled={!isBalanced || form.processing}
                        class="px-8 font-semibold bg-emerald-600 hover:bg-emerald-500"
                    >
                        {form.processing ? 'Memposting...' : 'Posting Saldo Awal ke Jurnal'}
                    </Button>
                </CardFooter>
            </Card>
        </form>
    </div>
</AuthenticatedLayout>
