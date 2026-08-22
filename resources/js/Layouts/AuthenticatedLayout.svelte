<script>
    import { page, Link, router } from '@inertiajs/svelte';
    import {
        LayoutDashboard,
        Users,
        Package,
        Sparkles,
        HardDrive,
        Network,
        Receipt,
        CreditCard,
        TrendingDown,
        TrendingUp,
        Landmark,
        BookOpen,
        FileText,
        BookMarked,
        Scale,
        Lock,
        PieChart,
        CheckSquare,
        History,
        Settings,
        LogOut,
        Bell,
        Search,
        ChevronDown,
        Menu,
        X,
        CheckCircle2,
        AlertTriangle,
        Info,
        ShieldAlert,
        ExternalLink,
        DollarSign,
        UserCog
    } from 'lucide-svelte';
    import { cn } from '@/lib/utils';
    import Breadcrumb from '@/Components/ui/breadcrumb/Breadcrumb.svelte';

    let { title = '', breadcrumbs = [], children } = $props();

    let sidebarOpen = $state(false);
    let notificationsOpen = $state(false);

    const user = $derived(page.props.auth?.user);
    const flash = $derived(page.props.flash || {});
    const notifications = $derived(page.props.notifications || []);
    const unreadCount = $derived(page.props.unread_notifications_count || 0);
    const appSettings = $derived(page.props.app_settings || {});
    const currentUrl = $derived(page.url);

    function isCurrent(path) {
        if (path === '/dashboard') return currentUrl === '/dashboard' || currentUrl === '/';
        return currentUrl.startsWith(path);
    }

    function handleLogout() {
        router.post('/logout');
    }

    function markAllRead() {
        router.post('/notifications/read-all', {}, { preserveScroll: true });
    }

    function markNotificationRead(notif) {
        router.post(`/notifications/${notif.id}/read`, {}, { preserveScroll: true });
    }
</script>

<svelte:head>
    <title>{title ? `${title} - ${appSettings.brand_name || 'Aionios.NET'}` : (appSettings.brand_name || 'Aionios.NET')}</title>
</svelte:head>

<div class="min-h-screen bg-stone-100 text-stone-900 flex flex-col md:flex-row antialiased">
    <!-- Mobile Sidebar Backdrop -->
    {#if sidebarOpen}
        <button
            type="button"
            class="fixed inset-0 z-40 bg-stone-900/60 backdrop-blur-xs md:hidden cursor-default"
            onclick={() => (sidebarOpen = false)}
            aria-label="Close sidebar backdrop"
        ></button>
    {/if}

    <!-- Sidebar Navigation -->
    <aside
        class={cn(
            'fixed inset-y-0 left-0 z-50 flex h-dvh w-72 shrink-0 flex-col border-r border-stone-200 bg-white transition-transform duration-300 md:sticky md:top-0 md:translate-x-0 shadow-xs',
            sidebarOpen ? 'translate-x-0' : '-translate-x-full'
        )}
    >
        <!-- Logo & Company Brand -->
        <div class="flex h-16 shrink-0 items-center justify-between border-b border-stone-200 px-6 bg-stone-50/50">
            <Link href="/dashboard" class="flex items-center gap-3 group">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-stone-900 text-stone-50 font-black shadow-sm group-hover:scale-105 transition-transform">
                    A
                </div>
                <div>
                    <span class="text-sm font-bold tracking-tight text-stone-900 block leading-tight">
                        {appSettings.brand_name || 'Aionios.NET'}
                    </span>
                    <span class="text-[10px] font-semibold text-stone-500 block uppercase tracking-wider">
                        ISP Operations & Finance
                    </span>
                </div>
            </Link>

            <button
                type="button"
                class="rounded-lg p-1 text-stone-500 hover:bg-stone-100 hover:text-stone-900 md:hidden"
                onclick={() => (sidebarOpen = false)}
            >
                <X class="h-5 w-5" />
            </button>
        </div>

        <!-- Navigation Links (Tailored by Role) -->
        <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 space-y-6">
            <!-- Main Section -->
            <div>
                <p class="px-3 text-[10px] font-bold uppercase tracking-wider text-stone-400 mb-2">Utama</p>
                <nav class="space-y-1">
                    <Link
                        href="/dashboard"
                        class={cn(
                            'flex items-center gap-3 rounded-lg px-3 py-2 text-xs font-semibold transition-colors',
                            isCurrent('/dashboard')
                                ? 'bg-stone-900 text-stone-50 shadow-xs'
                                : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'
                        )}
                    >
                        <LayoutDashboard class="h-4 w-4 shrink-0" />
                        <span>Dashboard</span>
                    </Link>
                </nav>
            </div>

            <!-- Customer & Network Section -->
            {#if user?.role === 'owner' || user?.role === 'admin_jaringan' || user?.role === 'admin_keuangan'}
                <div>
                    <p class="px-3 text-[10px] font-bold uppercase tracking-wider text-stone-400 mb-2">Pelanggan & Jaringan</p>
                    <nav class="space-y-1">
                        <Link
                            href="/customers"
                            class={cn(
                                'flex items-center gap-3 rounded-lg px-3 py-2 text-xs font-semibold transition-colors',
                                isCurrent('/customers')
                                    ? 'bg-stone-900 text-stone-50 shadow-xs'
                                    : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'
                            )}
                        >
                            <Users class="h-4 w-4 shrink-0" />
                            <span>Data Pelanggan</span>
                        </Link>

                        {#if user?.role === 'owner' || user?.role === 'admin_jaringan'}
                            <Link
                                href="/packages"
                                class={cn(
                                    'flex items-center gap-3 rounded-lg px-3 py-2 text-xs font-semibold transition-colors',
                                    isCurrent('/packages')
                                        ? 'bg-stone-900 text-stone-50 shadow-xs'
                                        : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'
                                )}
                            >
                                <Package class="h-4 w-4 shrink-0" />
                                <span>Paket Internet</span>
                            </Link>

                            <Link
                                href="/promotions"
                                class={cn(
                                    'flex items-center gap-3 rounded-lg px-3 py-2 text-xs font-semibold transition-colors',
                                    isCurrent('/promotions')
                                        ? 'bg-stone-900 text-stone-50 shadow-xs'
                                        : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'
                                )}
                            >
                                <Sparkles class="h-4 w-4 shrink-0" />
                                <span>Promo & Diskon</span>
                            </Link>

                            <Link
                                href="/ont"
                                class={cn(
                                    'flex items-center gap-3 rounded-lg px-3 py-2 text-xs font-semibold transition-colors',
                                    isCurrent('/ont')
                                        ? 'bg-stone-900 text-stone-50 shadow-xs'
                                        : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'
                                )}
                            >
                                <HardDrive class="h-4 w-4 shrink-0" />
                                <span>Inventori ONT</span>
                            </Link>

                            <Link
                                href="/mikrotik"
                                class={cn(
                                    'flex items-center gap-3 rounded-lg px-3 py-2 text-xs font-semibold transition-colors',
                                    isCurrent('/mikrotik')
                                        ? 'bg-stone-900 text-stone-50 shadow-xs'
                                        : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'
                                )}
                            >
                                <Network class="h-4 w-4 shrink-0" />
                                <span>MikroTik RouterOS</span>
                            </Link>
                        {/if}
                    </nav>
                </div>
            {/if}

            <!-- Billing & Financial Operations Section -->
            {#if user?.role === 'owner' || user?.role === 'admin_keuangan'}
                <div>
                    <p class="px-3 text-[10px] font-bold uppercase tracking-wider text-stone-400 mb-2">Billing & Kasir</p>
                    <nav class="space-y-1">
                        <Link
                            href="/invoices"
                            class={cn(
                                'flex items-center gap-3 rounded-lg px-3 py-2 text-xs font-semibold transition-colors',
                                isCurrent('/invoices')
                                    ? 'bg-stone-900 text-stone-50 shadow-xs'
                                    : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'
                            )}
                        >
                            <Receipt class="h-4 w-4 shrink-0" />
                            <span>Tagihan & Invoice</span>
                        </Link>

                        <Link
                            href="/payments"
                            class={cn(
                                'flex items-center gap-3 rounded-lg px-3 py-2 text-xs font-semibold transition-colors',
                                isCurrent('/payments')
                                    ? 'bg-stone-900 text-stone-50 shadow-xs'
                                    : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'
                            )}
                        >
                            <CreditCard class="h-4 w-4 shrink-0" />
                            <span>Pembayaran & QRIS</span>
                        </Link>

                        <Link
                            href="/expenses"
                            class={cn(
                                'flex items-center gap-3 rounded-lg px-3 py-2 text-xs font-semibold transition-colors',
                                isCurrent('/expenses')
                                    ? 'bg-stone-900 text-stone-50 shadow-xs'
                                    : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'
                            )}
                        >
                            <TrendingDown class="h-4 w-4 shrink-0" />
                            <span>Pengeluaran (Beban)</span>
                        </Link>

                        <Link
                            href="/other-income"
                            class={cn(
                                'flex items-center gap-3 rounded-lg px-3 py-2 text-xs font-semibold transition-colors',
                                isCurrent('/other-income')
                                    ? 'bg-stone-900 text-stone-50 shadow-xs'
                                    : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'
                            )}
                        >
                            <TrendingUp class="h-4 w-4 shrink-0" />
                            <span>Pemasukan Lain</span>
                        </Link>

                        <Link
                            href="/capital"
                            class={cn(
                                'flex items-center gap-3 rounded-lg px-3 py-2 text-xs font-semibold transition-colors',
                                isCurrent('/capital')
                                    ? 'bg-stone-900 text-stone-50 shadow-xs'
                                    : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'
                            )}
                        >
                            <Landmark class="h-4 w-4 shrink-0" />
                            <span>Modal & Ekuitas</span>
                        </Link>
                    </nav>
                </div>

                <!-- Accounting Section -->
                <div>
                    <p class="px-3 text-[10px] font-bold uppercase tracking-wider text-stone-400 mb-2">Akuntansi & Pembukuan</p>
                    <nav class="space-y-1">
                        <Link
                            href="/accounting/coa"
                            class={cn(
                                'flex items-center gap-3 rounded-lg px-3 py-2 text-xs font-semibold transition-colors',
                                isCurrent('/accounting/coa') || isCurrent('/accounting/opening-balance')
                                    ? 'bg-stone-900 text-stone-50 shadow-xs'
                                    : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'
                            )}
                        >
                            <BookOpen class="h-4 w-4 shrink-0" />
                            <span>Chart of Accounts (COA)</span>
                        </Link>

                        <Link
                            href="/accounting/journals"
                            class={cn(
                                'flex items-center gap-3 rounded-lg px-3 py-2 text-xs font-semibold transition-colors',
                                isCurrent('/accounting/journals')
                                    ? 'bg-stone-900 text-stone-50 shadow-xs'
                                    : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'
                            )}
                        >
                            <FileText class="h-4 w-4 shrink-0" />
                            <span>Jurnal Umum</span>
                        </Link>

                        <Link
                            href="/accounting/ledger"
                            class={cn(
                                'flex items-center gap-3 rounded-lg px-3 py-2 text-xs font-semibold transition-colors',
                                isCurrent('/accounting/ledger')
                                    ? 'bg-stone-900 text-stone-50 shadow-xs'
                                    : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'
                            )}
                        >
                            <BookMarked class="h-4 w-4 shrink-0" />
                            <span>Buku Besar</span>
                        </Link>

                        <Link
                            href="/accounting/trial-balance"
                            class={cn(
                                'flex items-center gap-3 rounded-lg px-3 py-2 text-xs font-semibold transition-colors',
                                isCurrent('/accounting/trial-balance')
                                    ? 'bg-stone-900 text-stone-50 shadow-xs'
                                    : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'
                            )}
                        >
                            <Scale class="h-4 w-4 shrink-0" />
                            <span>Neraca Saldo</span>
                        </Link>

                        <Link
                            href="/accounting/periods"
                            class={cn(
                                'flex items-center gap-3 rounded-lg px-3 py-2 text-xs font-semibold transition-colors',
                                isCurrent('/accounting/periods')
                                    ? 'bg-stone-900 text-stone-50 shadow-xs'
                                    : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'
                            )}
                        >
                            <Lock class="h-4 w-4 shrink-0" />
                            <span>Kunci Periode</span>
                        </Link>
                    </nav>
                </div>

                <!-- Reports Section -->
                <div>
                    <p class="px-3 text-[10px] font-bold uppercase tracking-wider text-stone-400 mb-2">Laporan Keuangan</p>
                    <nav class="space-y-1">
                        <Link
                            href="/reports/income-statement"
                            class={cn(
                                'flex items-center gap-3 rounded-lg px-3 py-2 text-xs font-semibold transition-colors',
                                isCurrent('/reports/income-statement')
                                    ? 'bg-stone-900 text-stone-50 shadow-xs'
                                    : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'
                            )}
                        >
                            <PieChart class="h-4 w-4 shrink-0" />
                            <span>Laba Rugi</span>
                        </Link>

                        <Link
                            href="/reports/balance-sheet"
                            class={cn(
                                'flex items-center gap-3 rounded-lg px-3 py-2 text-xs font-semibold transition-colors',
                                isCurrent('/reports/balance-sheet')
                                    ? 'bg-stone-900 text-stone-50 shadow-xs'
                                    : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'
                            )}
                        >
                            <Scale class="h-4 w-4 shrink-0" />
                            <span>Neraca</span>
                        </Link>

                        <Link
                            href="/reports/cash-flow"
                            class={cn(
                                'flex items-center gap-3 rounded-lg px-3 py-2 text-xs font-semibold transition-colors',
                                isCurrent('/reports/cash-flow')
                                    ? 'bg-stone-900 text-stone-50 shadow-xs'
                                    : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'
                            )}
                        >
                            <DollarSign class="h-4 w-4 shrink-0" />
                            <span>Arus Kas</span>
                        </Link>

                        <Link
                            href="/reports/equity-changes"
                            class={cn(
                                'flex items-center gap-3 rounded-lg px-3 py-2 text-xs font-semibold transition-colors',
                                isCurrent('/reports/equity-changes')
                                    ? 'bg-stone-900 text-stone-50 shadow-xs'
                                    : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'
                            )}
                        >
                            <TrendingUp class="h-4 w-4 shrink-0" />
                            <span>Perubahan Modal</span>
                        </Link>

                        <Link
                            href="/reports/receivables"
                            class={cn(
                                'flex items-center gap-3 rounded-lg px-3 py-2 text-xs font-semibold transition-colors',
                                isCurrent('/reports/receivables')
                                    ? 'bg-stone-900 text-stone-50 shadow-xs'
                                    : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'
                            )}
                        >
                            <Receipt class="h-4 w-4 shrink-0" />
                            <span>Laporan Piutang</span>
                        </Link>

                        <Link
                            href="/reports/revenue"
                            class={cn(
                                'flex items-center gap-3 rounded-lg px-3 py-2 text-xs font-semibold transition-colors',
                                isCurrent('/reports/revenue')
                                    ? 'bg-stone-900 text-stone-50 shadow-xs'
                                    : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'
                            )}
                        >
                            <CreditCard class="h-4 w-4 shrink-0" />
                            <span>Pendapatan & MDR</span>
                        </Link>
                    </nav>
                </div>
            {/if}

            <!-- Owner Controls Section -->
            {#if user?.role === 'owner'}
                <div>
                    <p class="px-3 text-[10px] font-bold uppercase tracking-wider text-stone-400 mb-2">Manajemen Owner</p>
                    <nav class="space-y-1">
                        <Link
                            href="/users"
                            class={cn(
                                'flex items-center gap-3 rounded-lg px-3 py-2 text-xs font-semibold transition-colors',
                                isCurrent('/users')
                                    ? 'bg-stone-900 text-stone-50 shadow-xs'
                                    : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'
                            )}
                        >
                            <UserCog class="h-4 w-4 shrink-0" />
                            <span>Manajemen User</span>
                        </Link>

                        <Link
                            href="/approvals"
                            class={cn(
                                'flex items-center justify-between rounded-lg px-3 py-2 text-xs font-semibold transition-colors',
                                isCurrent('/approvals')
                                    ? 'bg-stone-900 text-stone-50 shadow-xs'
                                    : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'
                            )}
                        >
                            <div class="flex items-center gap-3">
                                <CheckSquare class="h-4 w-4 shrink-0" />
                                <span>Persetujuan (Approvals)</span>
                            </div>
                        </Link>

                        <Link
                            href="/audit-logs"
                            class={cn(
                                'flex items-center gap-3 rounded-lg px-3 py-2 text-xs font-semibold transition-colors',
                                isCurrent('/audit-logs')
                                    ? 'bg-stone-900 text-stone-50 shadow-xs'
                                    : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'
                            )}
                        >
                            <History class="h-4 w-4 shrink-0" />
                            <span>Audit Trail</span>
                        </Link>

                        <Link
                            href="/settings"
                            class={cn(
                                'flex items-center gap-3 rounded-lg px-3 py-2 text-xs font-semibold transition-colors',
                                isCurrent('/settings')
                                    ? 'bg-stone-900 text-stone-50 shadow-xs'
                                    : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'
                            )}
                        >
                            <Settings class="h-4 w-4 shrink-0" />
                            <span>Pengaturan Sistem</span>
                        </Link>
                    </nav>
                </div>
            {/if}
        </div>

        <!-- Current User Box -->
        <div class="shrink-0 border-t border-stone-200 p-4 bg-stone-50/70">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3 truncate">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-stone-200 text-xs font-bold text-stone-800 border border-stone-300">
                        {user?.name ? user.name.charAt(0).toUpperCase() : 'U'}
                    </div>
                    <div class="truncate">
                        <p class="text-xs font-bold text-stone-900 truncate">{user?.name}</p>
                        <p class="text-[10px] text-stone-500 capitalize">{user?.role ? user.role.replace('_', ' ') : 'User'}</p>
                    </div>
                </div>

                <button
                    type="button"
                    onclick={handleLogout}
                    title="Keluar"
                    class="rounded-lg p-2 text-stone-400 hover:bg-stone-200 hover:text-rose-600 transition-colors cursor-pointer"
                >
                    <LogOut class="h-4 w-4" />
                </button>
            </div>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0">
        <!-- Top App Header -->
        <header class="h-16 border-b border-stone-200 bg-white/95 backdrop-blur-md px-4 sm:px-8 flex items-center justify-between sticky top-0 z-30 shadow-2xs">
            <div class="flex items-center gap-4">
                <button
                    type="button"
                    class="rounded-lg p-1.5 text-stone-600 hover:bg-stone-100 hover:text-stone-900 md:hidden"
                    onclick={() => (sidebarOpen = true)}
                >
                    <Menu class="h-5 w-5" />
                </button>

                {#if breadcrumbs.length > 0}
                    <Breadcrumb items={breadcrumbs} />
                {:else if title}
                    <h1 class="text-sm font-bold text-stone-900">{title}</h1>
                {/if}
            </div>

            <!-- Top Actions -->
            <div class="flex items-center gap-3">
                <!-- Notifications Popover -->
                <div class="relative">
                    <button
                        type="button"
                        onclick={() => (notificationsOpen = !notificationsOpen)}
                        class="relative rounded-lg p-2 text-stone-600 hover:bg-stone-100 hover:text-stone-900 transition-colors cursor-pointer"
                        aria-label="Notifications"
                    >
                        <Bell class="h-4 w-4" />
                        {#if unreadCount > 0}
                            <span class="absolute top-1.5 right-1.5 flex h-2 w-2 rounded-full bg-rose-500 ring-2 ring-white animate-pulse"></span>
                        {/if}
                    </button>

                    {#if notificationsOpen}
                        <button
                            type="button"
                            class="fixed inset-0 z-30 cursor-default"
                            onclick={() => (notificationsOpen = false)}
                            aria-label="Close notification menu"
                        ></button>

                        <div class="absolute right-0 mt-2 z-40 w-80 rounded-2xl border border-stone-200 bg-white p-4 shadow-xl animate-in zoom-in-95">
                            <div class="flex items-center justify-between border-b border-stone-200 pb-3 mb-3">
                                <h4 class="text-xs font-bold text-stone-900">Notifikasi Sistem</h4>
                                {#if unreadCount > 0}
                                    <button
                                        type="button"
                                        onclick={markAllRead}
                                        class="text-[11px] font-semibold text-stone-900 hover:underline"
                                    >
                                        Tandai Semua Dibaca
                                    </button>
                                {/if}
                            </div>

                            <div class="max-h-72 overflow-y-auto space-y-2.5">
                                {#if notifications.length === 0}
                                    <p class="text-xs text-stone-500 text-center py-4">Tidak ada notifikasi baru.</p>
                                {:else}
                                    {#each notifications as notif}
                                        <button
                                            type="button"
                                            onclick={() => markNotificationRead(notif)}
                                            class={cn(
                                                'w-full text-left p-2.5 rounded-xl text-xs transition-colors border block',
                                                notif.is_read
                                                    ? 'border-transparent text-stone-600 hover:bg-stone-50'
                                                    : 'border-stone-200 bg-stone-50 text-stone-900 hover:bg-stone-100'
                                            )}
                                        >
                                            <p class="font-bold text-stone-900 mb-0.5">{notif.title}</p>
                                            <p class="text-[11px] text-stone-600 leading-relaxed">{notif.message}</p>
                                        </button>
                                    {/each}
                                {/if}
                            </div>
                        </div>
                    {/if}
                </div>

                <!-- User Role Badge -->
                <div class="hidden sm:flex items-center gap-2 border-l border-stone-200 pl-3">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-stone-100 text-stone-800 border border-stone-300">
                        {user?.role === 'owner' ? 'Owner / Super Admin' : user?.role === 'admin_keuangan' ? 'Admin Keuangan' : 'Admin Jaringan'}
                    </span>
                </div>
            </div>
        </header>

        <!-- Main Body & Flash Messages -->
        <main class="flex-1 p-4 sm:p-8 max-w-7xl w-full mx-auto space-y-6">
            <!-- Flash Message Banners -->
            {#if flash.success}
                <div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50/90 p-4 text-xs text-emerald-900 shadow-xs animate-in fade-in">
                    <CheckCircle2 class="h-4 w-4 shrink-0 text-emerald-600" />
                    <span class="flex-1 font-semibold">{flash.success}</span>
                </div>
            {/if}

            {#if flash.error}
                <div class="flex items-center gap-3 rounded-xl border border-rose-200 bg-rose-50/90 p-4 text-xs text-rose-900 shadow-xs animate-in fade-in">
                    <ShieldAlert class="h-4 w-4 shrink-0 text-rose-600" />
                    <span class="flex-1 font-semibold">{flash.error}</span>
                </div>
            {/if}

            {#if flash.warning}
                <div class="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50/90 p-4 text-xs text-amber-900 shadow-xs animate-in fade-in">
                    <AlertTriangle class="h-4 w-4 shrink-0 text-amber-600" />
                    <span class="flex-1 font-semibold">{flash.warning}</span>
                </div>
            {/if}

            {#if flash.info}
                <div class="flex items-center gap-3 rounded-xl border border-blue-200 bg-blue-50/90 p-4 text-xs text-blue-900 shadow-xs animate-in fade-in">
                    <Info class="h-4 w-4 shrink-0 text-blue-600" />
                    <span class="flex-1 font-semibold">{flash.info}</span>
                </div>
            {/if}

            {#if children}
                {@render children()}
            {/if}
        </main>
    </div>
</div>
