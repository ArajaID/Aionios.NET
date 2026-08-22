<script>
    import Dialog from '@/Components/ui/dialog/Dialog.svelte';
    import Button from '@/Components/ui/button/Button.svelte';
    import { AlertTriangle, CheckCircle2, RotateCcw, ShieldAlert } from 'lucide-svelte';

    let {
        open = $bindable(false),
        title = 'Konfirmasi Tindakan',
        confirmLabel = 'Konfirmasi',
        cancelLabel = 'Batal',
        variant = 'warning',
        processing = false,
        onconfirm,
        children,
    } = $props();

    const styles = {
        success: {
            box: 'border-emerald-200 bg-emerald-50',
            icon: 'text-emerald-600',
            title: 'text-emerald-950',
            text: 'text-emerald-800',
            button: 'success',
        },
        destructive: {
            box: 'border-rose-200 bg-rose-50',
            icon: 'text-rose-600',
            title: 'text-rose-950',
            text: 'text-rose-800',
            button: 'destructive',
        },
        reversal: {
            box: 'border-amber-200 bg-amber-50',
            icon: 'text-amber-600',
            title: 'text-amber-950',
            text: 'text-amber-800',
            button: 'destructive',
        },
        warning: {
            box: 'border-amber-200 bg-amber-50',
            icon: 'text-amber-600',
            title: 'text-amber-950',
            text: 'text-amber-800',
            button: 'default',
        },
    };

    const theme = $derived(styles[variant] || styles.warning);
</script>

<Dialog bind:open {title} maxWidth="max-w-md">
    <div class="space-y-5">
        <div class={`flex gap-3 rounded-xl border p-4 ${theme.box}`}>
            <div class={`mt-0.5 shrink-0 ${theme.icon}`}>
                {#if variant === 'success'}
                    <CheckCircle2 class="h-5 w-5" />
                {:else if variant === 'destructive'}
                    <ShieldAlert class="h-5 w-5" />
                {:else if variant === 'reversal'}
                    <RotateCcw class="h-5 w-5" />
                {:else}
                    <AlertTriangle class="h-5 w-5" />
                {/if}
            </div>

            <div class="min-w-0">
                <p class={`text-xs font-bold ${theme.title}`}>Pastikan data sudah benar</p>
                <div class={`mt-1 text-xs leading-relaxed ${theme.text}`}>
                    {#if children}
                        {@render children()}
                    {/if}
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <Button type="button" variant="outline" onclick={() => (open = false)} disabled={processing}>
                {cancelLabel}
            </Button>
            <Button type="button" variant={theme.button} onclick={onconfirm} disabled={processing}>
                {processing ? 'Memproses...' : confirmLabel}
            </Button>
        </div>
    </div>
</Dialog>
