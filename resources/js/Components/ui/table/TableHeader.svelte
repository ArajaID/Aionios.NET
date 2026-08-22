<script>
    import { cn } from '@/lib/utils';
    let { class: className = '', children, ...rest } = $props();
</script>

<thead class={cn('border-b border-stone-800 bg-stone-950/60 text-xs font-semibold text-stone-400 uppercase tracking-wider', className)} {...rest}>
    {#if children}
        {@render children()}
    {/if}
</thead>
