<script>
    import { useForm, Link } from '@inertiajs/svelte';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
    import Button from '@/Components/ui/button/Button.svelte';
    import Input from '@/Components/ui/input/Input.svelte';
    import Select from '@/Components/ui/select/Select.svelte';
    import Card from '@/Components/ui/card/Card.svelte';
    import CardHeader from '@/Components/ui/card/CardHeader.svelte';
    import CardTitle from '@/Components/ui/card/CardTitle.svelte';
    import CardDescription from '@/Components/ui/card/CardDescription.svelte';
    import CardContent from '@/Components/ui/card/CardContent.svelte';
    import CardFooter from '@/Components/ui/card/CardFooter.svelte';
    import Badge from '@/Components/ui/badge/Badge.svelte';
    import Alert from '@/Components/ui/alert/Alert.svelte';
    import {
        CreditCard,
        ArrowLeft,
        CheckCircle2,
        ArrowRight,
        Receipt,
        FileText,
        ShieldAlert,
        Landmark,
        Sparkles
    } from 'lucide-svelte';
    import { formatRupiah, formatDate } from '@/lib/utils';

    let {
        customers = [],
        cash_bank_accounts = [],
        default_mdr = 0.7,
        preselected_customer_id = null,
        errors = {},
    } = $props();

    const form = useForm({
        customer_id: preselected_customer_id || (customers[0]?.id || ''),
        payment_date: new Date().toISOString().split('T')[0],
        payment_method: 'manual',
        cash_bank_account_id: cash_bank_accounts[0]?.id || '',
        custom_mdr: default_mdr,
        notes: 'Pelunasan tagihan pelanggan',
    });

    // Reactive selected customer object
    const selectedCustomer = $derived(
        customers.find((c) => c.id === Number(form.customer_id))
    );

    const unpaidInvoices = $derived(selectedCustomer?.unpaid_invoices || []);
    const grossAmount = $derived(
        unpaidInvoices.reduce((sum, inv) => sum + Number(inv.total_amount || 0), 0)
    );

    // MDR calculations
    const mdrPercentage = $derived(form.payment_method === 'qris' ? Number(form.custom_mdr || 0) : 0);
    const mdrFee = $derived(
        form.payment_method === 'qris'
            ? Math.round(((grossAmount * mdrPercentage) / 100) * 100) / 100
            : 0
    );
    const netAmount = $derived(Math.max(0, grossAmount - mdrFee));

    const selectedCashBank = $derived(
        cash_bank_accounts.find((cb) => cb.id === Number(form.cash_bank_account_id))
    );

    function handleSubmit(e) {
        e.preventDefault();
        form.post('/payments');
    }
</script>

<AuthenticatedLayout
    title="Konfirmasi Pembayaran Multi-Step"
    breadcrumbs={[
        { label: 'Pembayaran', href: '/payments' },
        { label: 'Konfirmasi Pembayaran' },
    ]}
>
    <div class="max-w-4xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-stone-900 flex items-center gap-2">
                    <CreditCard class="h-5 w-5 text-stone-800" />
                    Konfirmasi Pembayaran Pelanggan
                </h1>
                <p class="text-xs text-stone-500 mt-1">
                    Wizard multi-step pelunasan seluruh tagihan outstanding, kalkulasi MDR QRIS, dan pembuatan jurnal otomatis.
                </p>
            </div>

            <Link href="/payments">
                <Button variant="outline" size="sm">
                    <ArrowLeft class="h-3.5 w-3.5 mr-1" />
                    Kembali
                </Button>
            </Link>
        </div>

        {#if customers.length === 0}
            <Alert variant="success" title="Semua Tagihan Lunas!">
                Saat ini tidak ada pelanggan yang memiliki tagihan belum lunas (Outstanding). Semua operasional billing berstatus lunas.
            </Alert>
        {:else}
            <form onsubmit={handleSubmit} class="space-y-6">
                <!-- STEP 1 & 2: Pilih Pelanggan & Daftar Tagihan Outstanding -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center justify-between">
                            <span>1. Pilih Pelanggan & Tagihan Outstanding</span>
                            <Badge variant="primary">Step 1 - 2</Badge>
                        </CardTitle>
                        <CardDescription>
                            Sesuai PRD Bagian 15: Tidak ada cicilan / pembayaran parsial. Pembayaran melunasi seluruh tagihan outstanding sekaligus.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-1.5">
                            <label for="payment_customer_id" class="text-xs font-semibold text-stone-700">Pilih Pelanggan</label>
                            <Select id="payment_customer_id" bind:value={form.customer_id} required>
                                {#each customers as cust}
                                    <option value={cust.id}>
                                        {cust.name} ({cust.customer_id}) - Outstanding: {formatRupiah(cust.outstanding_amount)} ({cust.unpaid_invoices?.length || 0} Invoice)
                                    </option>
                                {/each}
                            </Select>
                        </div>

                        <!-- Outstanding Invoices List Box -->
                        {#if selectedCustomer}
                            <div class="rounded-xl border border-stone-200 bg-stone-50 p-4 space-y-3">
                                <div class="flex items-center justify-between border-b border-stone-200 pb-2">
                                    <h4 class="text-xs font-bold text-stone-800 uppercase tracking-wider">
                                        Daftar Invoice yang Akan Dilunasi:
                                    </h4>
                                    <span class="text-xs font-mono font-bold text-rose-700">
                                        Total: {formatRupiah(grossAmount)}
                                    </span>
                                </div>

                                <div class="space-y-2">
                                    {#each unpaidInvoices as inv}
                                        <div class="flex items-center justify-between text-xs py-1.5 border-b border-stone-200">
                                            <div>
                                                <span class="font-mono font-semibold text-stone-800">{inv.invoice_number}</span>
                                                <span class="text-stone-500 ml-2 font-mono">Periode {inv.period}</span>
                                                <span class="text-[11px] text-stone-500 ml-2">(Jatuh Tempo: {formatDate(inv.due_date)})</span>
                                            </div>
                                            <span class="font-mono font-bold text-stone-900">{formatRupiah(inv.total_amount)}</span>
                                        </div>
                                    {/each}
                                </div>
                            </div>
                        {/if}
                    </CardContent>
                </Card>

                <!-- STEP 3 & 4: Metode Pembayaran & Kas/Bank -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center justify-between">
                            <span>2. Metode Pembayaran & Rekening Tujuan</span>
                            <Badge variant="primary">Step 3 - 4</Badge>
                        </CardTitle>
                        <CardDescription>Pilih instrumen penerimaan kas dan tentukan MDR jika menggunakan QRIS</CardDescription>
                    </CardHeader>
                    <CardContent class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label for="payment_date" class="text-xs font-semibold text-stone-700">Tanggal Pembayaran</label>
                            <Input id="payment_date" type="date" bind:value={form.payment_date} required />
                        </div>

                        <div class="space-y-1.5">
                            <label for="payment_method" class="text-xs font-semibold text-stone-700">Metode Pembayaran</label>
                            <Select id="payment_method" bind:value={form.payment_method} required>
                                <option value="manual">Manual Transfer / Kas Tunai (MDR 0%)</option>
                                <option value="qris">QRIS Manual (Configurable MDR)</option>
                            </Select>
                        </div>

                        <div class="space-y-1.5">
                            <label for="cash_bank_account_id" class="text-xs font-semibold text-stone-700">Rekening Kas / Bank Penerima</label>
                            <Select id="cash_bank_account_id" bind:value={form.cash_bank_account_id} required>
                                {#each cash_bank_accounts as cb}
                                    <option value={cb.id}>
                                        {cb.name} ({cb.bank_name || 'Kas'}) - Saldo: {formatRupiah(cb.current_balance)}
                                    </option>
                                {/each}
                            </Select>
                        </div>

                        {#if form.payment_method === 'qris'}
                            <div class="space-y-1.5">
                                <label for="custom_mdr" class="text-xs font-semibold text-stone-700">Tarif MDR QRIS (%)</label>
                                <Input id="custom_mdr" type="number" step="0.01" bind:value={form.custom_mdr} min="0" max="10" required />
                                <p class="text-[10px] text-stone-500">MDR bukan potongan pelanggan, melainkan beban transaksi perusahaan.</p>
                            </div>
                        {/if}

                        <div class="space-y-1.5 sm:col-span-2">
                            <label for="payment_notes" class="text-xs font-semibold text-stone-700">Catatan Pembayaran (Opsional)</label>
                            <Input id="payment_notes" bind:value={form.notes} placeholder="No. referensi transfer / catatan kasir..." />
                        </div>
                    </CardContent>
                </Card>

                <!-- STEP 5 & 6: Live Payment Preview & Jurnal Otomatis -->
                <Card class="border-stone-200 bg-white shadow-sm">
                    <CardHeader class="pb-3">
                        <CardTitle class="flex items-center justify-between text-stone-900 font-bold">
                            <div class="flex items-center gap-2">
                                <Sparkles class="h-4 w-4 text-stone-800" />
                                <span>3. Preview Transaksi & Jurnal Akuntansi</span>
                            </div>
                            <Badge variant="purple">Step 5 - 6 (Live Preview)</Badge>
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <!-- Nominal Summary -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-4 rounded-xl bg-stone-50 border border-stone-200">
                            <div>
                                <span class="text-[10px] text-stone-500 uppercase font-semibold block">Total Tagihan (Gross)</span>
                                <span class="text-base font-extrabold text-stone-900 font-mono">{formatRupiah(grossAmount)}</span>
                            </div>

                            <div>
                                <span class="text-[10px] text-rose-700 uppercase font-semibold block">
                                    Biaya MDR QRIS ({mdrPercentage}%)
                                </span>
                                <span class="text-base font-extrabold text-rose-700 font-mono">
                                    {mdrFee > 0 ? `-${formatRupiah(mdrFee)}` : 'Rp 0'}
                                </span>
                            </div>

                            <div>
                                <span class="text-[10px] text-emerald-700 uppercase font-semibold block">Penerimaan Kas Bersih (Net)</span>
                                <span class="text-base font-extrabold text-emerald-700 font-mono">{formatRupiah(netAmount)}</span>
                            </div>
                        </div>

                        <!-- Journal Preview Box (PRD Section 20) -->
                        <div class="space-y-2">
                            <h4 class="text-xs font-bold text-stone-800 flex items-center gap-1.5">
                                <FileText class="h-3.5 w-3.5 text-stone-700" />
                                Jurnal Otomatis yang Akan Dibuat Sistem (Debit = Kredit):
                            </h4>

                            <div class="overflow-x-auto rounded-xl border border-stone-200 bg-stone-50/50 p-3">
                                <table class="w-full text-xs text-left">
                                    <thead class="border-b border-stone-200 text-stone-500 uppercase text-[10px]">
                                        <tr>
                                            <th class="py-1.5">Akun COA</th>
                                            <th class="py-1.5 text-right">Debit (Rp)</th>
                                            <th class="py-1.5 text-right">Kredit (Rp)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-stone-100">
                                        <!-- Debit Kas / Bank (Net) -->
                                        <tr>
                                            <td class="py-2 text-stone-800 font-medium">
                                                [Debit] {selectedCashBank?.chart_of_account?.code || '1110'} - {selectedCashBank?.name || 'Kas/Bank'}
                                            </td>
                                            <td class="py-2 text-right font-mono font-bold text-emerald-700">{formatRupiah(netAmount)}</td>
                                            <td class="py-2 text-right font-mono text-stone-400">0</td>
                                        </tr>

                                        <!-- Debit MDR Expense if QRIS -->
                                        {#if mdrFee > 0}
                                            <tr>
                                                <td class="py-2 text-stone-800 font-medium">
                                                    [Debit] 5170 - Beban MDR QRIS ({mdrPercentage}%)
                                                </td>
                                                <td class="py-2 text-right font-mono font-bold text-rose-700">{formatRupiah(mdrFee)}</td>
                                                <td class="py-2 text-right font-mono text-stone-400">0</td>
                                            </tr>
                                        {/if}

                                        <!-- Kredit Pendapatan Internet (Gross) -->
                                        <tr>
                                            <td class="py-2 text-stone-800 font-medium pl-4">
                                                [Kredit] 4110 - Pendapatan Langganan Internet
                                            </td>
                                            <td class="py-2 text-right font-mono text-stone-400">0</td>
                                            <td class="py-2 text-right font-mono font-bold text-stone-900">{formatRupiah(grossAmount)}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {#if selectedCustomer?.status === 'isolated'}
                            <div class="p-3 rounded-xl border border-emerald-200 bg-emerald-50 text-xs text-emerald-900 flex items-center gap-2">
                                <CheckCircle2 class="h-4 w-4 shrink-0 text-emerald-600" />
                                <span>
                                    Pelanggan saat ini terisolir. Setelah pembayaran ini diposting, profil pelanggan akan otomatis di-unisolir dan dikembalikan ke profil normal/promo di MikroTik.
                                </span>
                            </div>
                        {/if}
                    </CardContent>

                    <CardFooter class="flex items-center justify-end gap-3 border-t border-stone-200 pt-4">
                        <Link href="/payments">
                            <Button type="button" variant="outline">Batal</Button>
                        </Link>
                        <Button
                            type="submit"
                            disabled={form.processing || grossAmount <= 0}
                            class="px-8 font-semibold bg-emerald-600 hover:bg-emerald-500"
                        >
                            {form.processing ? 'Memproses Posting...' : 'Konfirmasi & Posting Pembayaran'}
                        </Button>
                    </CardFooter>
                </Card>
            </form>
        {/if}
    </div>
</AuthenticatedLayout>
