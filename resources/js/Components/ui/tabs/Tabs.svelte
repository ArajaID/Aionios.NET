<script>
    import { cn } from '@/lib/utils';
    let { value = $bindable(''), class: className = '', children, ...rest } = $props();
</script>

<div class={cn('w-full', className)} {...rest}>
    {#if children}
        {@render children()}
    {/if}
</div>
