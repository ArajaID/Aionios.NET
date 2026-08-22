<script>
    import { cn } from '@/lib/utils';
    let { class: className = '', children, ...rest } = $props();
</script>

<div class={cn('flex flex-col space-y-1.5 p-5', className)} {...rest}>
    {#if children}
        {@render children()}
    {/if}
</div>
