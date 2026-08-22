<script>
    import { cn } from '@/lib/utils';
    let { class: className = '', children, ...rest } = $props();
</script>

<div class="relative w-full overflow-auto rounded-xl border border-stone-200 bg-white shadow-xs">
    <table class={cn('w-full caption-bottom text-sm text-left', className)} {...rest}>
        {#if children}
            {@render children()}
        {/if}
    </table>
</div>
