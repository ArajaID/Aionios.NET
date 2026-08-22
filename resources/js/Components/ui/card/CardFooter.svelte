<script>
    import { cn } from '@/lib/utils';
    let { class: className = '', children, ...rest } = $props();
</script>

<div class={cn('flex items-center p-5 pt-0', className)} {...rest}>
    {#if children}
        {@render children()}
    {/if}
</div>
