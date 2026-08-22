<script>
    import { router, useForm } from '@inertiajs/svelte';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
    import Button from '@/Components/ui/button/Button.svelte';
    import Input from '@/Components/ui/input/Input.svelte';
    import Card from '@/Components/ui/card/Card.svelte';
    import CardContent from '@/Components/ui/card/CardContent.svelte';
    import Badge from '@/Components/ui/badge/Badge.svelte';
    import Dialog from '@/Components/ui/dialog/Dialog.svelte';
    import Table from '@/Components/ui/table/Table.svelte';
    import TableBody from '@/Components/ui/table/TableBody.svelte';
    import TableCell from '@/Components/ui/table/TableCell.svelte';
    import TableHead from '@/Components/ui/table/TableHead.svelte';
    import TableHeader from '@/Components/ui/table/TableHeader.svelte';
    import TableRow from '@/Components/ui/table/TableRow.svelte';
    import { AlertTriangle, Edit3, Plus, ShieldCheck, Trash2, UserCheck, UserCog, Users } from 'lucide-svelte';

    let { users = [] } = $props();

    let createModalOpen = $state(false);
    let editModalOpen = $state(false);
    let deleteModalOpen = $state(false);
    let selectedUser = $state(null);
    let deleting = $state(false);

    const activeUsers = $derived(users.filter((user) => user.is_active).length);
    const ownerCount = $derived(users.filter((user) => user.role === 'owner' && user.is_active).length);

    const createForm = useForm({
        name: '',
        email: '',
        phone: '',
        role: 'admin_keuangan',
        password: '',
        password_confirmation: '',
        is_active: true,
    });

    const editForm = useForm({
        name: '',
        email: '',
        phone: '',
        role: 'admin_keuangan',
        password: '',
        password_confirmation: '',
        is_active: true,
    });

    const roleLabel = (role) => ({
        owner: 'Owner',
        admin_keuangan: 'Admin Keuangan',
        admin_jaringan: 'Admin Jaringan',
    })[role] || role;

    const roleVariant = (role) => role === 'owner' ? 'warning' : role === 'admin_keuangan' ? 'success' : 'info';

    function formatDate(value) {
        if (!value) return '-';
        return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(value));
    }

    function handleCreate(event) {
        event.preventDefault();
        createForm.post('/users', {
            preserveScroll: true,
            onSuccess: () => {
                createModalOpen = false;
                createForm.reset();
            },
        });
    }

    function openEdit(user) {
        selectedUser = user;
        editForm.clearErrors();
        editForm.name = user.name;
        editForm.email = user.email;
        editForm.phone = user.phone || '';
        editForm.role = user.role;
        editForm.password = '';
        editForm.password_confirmation = '';
        editForm.is_active = Boolean(user.is_active);
        editModalOpen = true;
    }

    function handleEdit(event) {
        event.preventDefault();
        editForm.put(`/users/${selectedUser.id}`, {
            preserveScroll: true,
            onSuccess: () => (editModalOpen = false),
        });
    }

    function openDelete(user) {
        selectedUser = user;
        deleteModalOpen = true;
    }

    function handleDelete() {
        if (!selectedUser || selectedUser.is_current_user) return;
        deleting = true;
        router.delete(`/users/${selectedUser.id}`, {
            preserveScroll: true,
            onSuccess: () => (deleteModalOpen = false),
            onFinish: () => (deleting = false),
        });
    }
</script>

<AuthenticatedLayout title="Manajemen User" breadcrumbs={[{ label: 'Manajemen Owner' }, { label: 'Manajemen User' }]}>
    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="flex items-center gap-2 text-xl font-bold tracking-tight text-stone-900">
                    <UserCog class="h-5 w-5 text-stone-800" />
                    Manajemen User Login
                </h1>
                <p class="mt-1 text-xs text-stone-500">Kelola akun, role akses, status login, dan kata sandi pengguna sistem.</p>
            </div>
            <Button size="sm" onclick={() => (createModalOpen = true)}>
                <Plus class="mr-1 h-4 w-4" />
                Tambah User
            </Button>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <Card><CardContent class="flex items-center gap-3 p-4"><Users class="h-8 w-8 text-stone-500" /><div><p class="text-[10px] font-bold uppercase tracking-wider text-stone-500">Total User</p><p class="text-2xl font-black text-stone-900">{users.length}</p></div></CardContent></Card>
            <Card><CardContent class="flex items-center gap-3 p-4"><UserCheck class="h-8 w-8 text-emerald-600" /><div><p class="text-[10px] font-bold uppercase tracking-wider text-stone-500">User Aktif</p><p class="text-2xl font-black text-stone-900">{activeUsers}</p></div></CardContent></Card>
            <Card><CardContent class="flex items-center gap-3 p-4"><ShieldCheck class="h-8 w-8 text-amber-600" /><div><p class="text-[10px] font-bold uppercase tracking-wider text-stone-500">Owner Aktif</p><p class="text-2xl font-black text-stone-900">{ownerCount}</p></div></CardContent></Card>
        </div>

        <Card class="overflow-hidden">
            <div class="overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Pengguna</TableHead>
                            <TableHead>Kontak</TableHead>
                            <TableHead>Role</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Dibuat</TableHead>
                            <TableHead class="text-right">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {#if users.length === 0}
                            <TableRow><TableCell colspan={6} class="py-10 text-center text-stone-500">Belum ada akun pengguna.</TableCell></TableRow>
                        {:else}
                            {#each users as user}
                                <TableRow>
                                    <TableCell>
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-stone-200 bg-stone-100 text-xs font-black text-stone-700">{user.name.charAt(0).toUpperCase()}</div>
                                            <div><p class="font-bold text-stone-900">{user.name}</p>{#if user.is_current_user}<span class="text-[10px] font-semibold text-blue-700">Akun Anda</span>{/if}</div>
                                        </div>
                                    </TableCell>
                                    <TableCell><p class="font-medium text-stone-800">{user.email}</p><p class="text-[10px] text-stone-500">{user.phone || 'Tanpa nomor telepon'}</p></TableCell>
                                    <TableCell><Badge variant={roleVariant(user.role)}>{roleLabel(user.role)}</Badge></TableCell>
                                    <TableCell><Badge variant={user.is_active ? 'success' : 'default'}>{user.is_active ? 'AKTIF' : 'NON-AKTIF'}</Badge></TableCell>
                                    <TableCell class="text-xs text-stone-600">{formatDate(user.created_at)}</TableCell>
                                    <TableCell>
                                        <div class="flex justify-end gap-2">
                                            <Button variant="outline" size="sm" onclick={() => openEdit(user)}><Edit3 class="h-3.5 w-3.5" /><span class="hidden sm:inline">Edit</span></Button>
                                            <Button variant="destructive" size="sm" onclick={() => openDelete(user)} disabled={user.is_current_user} title={user.is_current_user ? 'Akun yang sedang digunakan tidak dapat dihapus' : 'Hapus user'}><Trash2 class="h-3.5 w-3.5" /></Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            {/each}
                        {/if}
                    </TableBody>
                </Table>
            </div>
        </Card>
    </div>

    <Dialog bind:open={createModalOpen} title="Tambah User Login Baru">
        <form onsubmit={handleCreate} class="space-y-4">
            {@render UserFormFields(createForm, 'create')}
            <div class="flex justify-end gap-2 pt-3"><Button type="button" variant="outline" onclick={() => (createModalOpen = false)}>Batal</Button><Button type="submit" disabled={createForm.processing}>{createForm.processing ? 'Menyimpan...' : 'Simpan User'}</Button></div>
        </form>
    </Dialog>

    <Dialog bind:open={editModalOpen} title={`Edit User: ${selectedUser?.name || ''}`}>
        <form onsubmit={handleEdit} class="space-y-4">
            {@render UserFormFields(editForm, 'edit', true, selectedUser?.is_current_user)}
            <div class="flex justify-end gap-2 pt-3"><Button type="button" variant="outline" onclick={() => (editModalOpen = false)}>Batal</Button><Button type="submit" disabled={editForm.processing}>{editForm.processing ? 'Menyimpan...' : 'Perbarui User'}</Button></div>
        </form>
    </Dialog>

    <Dialog bind:open={deleteModalOpen} title="Hapus User Login" maxWidth="max-w-md">
        <div class="space-y-4">
            <div class="flex gap-3 rounded-xl border border-rose-200 bg-rose-50 p-4"><AlertTriangle class="h-5 w-5 shrink-0 text-rose-600" /><div class="text-xs text-rose-900"><p class="font-bold">Hapus akun {selectedUser?.name}?</p><p class="mt-1 leading-relaxed">Akun tidak dapat login lagi. Riwayat transaksi dan audit yang pernah dibuat tetap disimpan.</p></div></div>
            <div class="flex justify-end gap-2"><Button type="button" variant="outline" onclick={() => (deleteModalOpen = false)}>Batal</Button><Button type="button" variant="destructive" onclick={handleDelete} disabled={deleting || selectedUser?.is_current_user}><Trash2 class="h-4 w-4" />{deleting ? 'Menghapus...' : 'Hapus User'}</Button></div>
        </div>
    </Dialog>
</AuthenticatedLayout>

{#snippet UserFormFields(form, prefix, passwordOptional = false, lockOwner = false)}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div class="space-y-1.5"><label for={`${prefix}_name`} class="text-xs font-semibold text-stone-700">Nama Lengkap</label><Input id={`${prefix}_name`} bind:value={form.name} required />{#if form.errors.name}<p class="text-[11px] text-rose-600">{form.errors.name}</p>{/if}</div>
        <div class="space-y-1.5"><label for={`${prefix}_phone`} class="text-xs font-semibold text-stone-700">Nomor Telepon</label><Input id={`${prefix}_phone`} bind:value={form.phone} placeholder="08xxxxxxxxxx" />{#if form.errors.phone}<p class="text-[11px] text-rose-600">{form.errors.phone}</p>{/if}</div>
    </div>
    <div class="space-y-1.5"><label for={`${prefix}_email`} class="text-xs font-semibold text-stone-700">Email Login</label><Input id={`${prefix}_email`} type="email" bind:value={form.email} required />{#if form.errors.email}<p class="text-[11px] text-rose-600">{form.errors.email}</p>{/if}</div>
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div class="space-y-1.5"><label for={`${prefix}_role`} class="text-xs font-semibold text-stone-700">Role Akses</label><select id={`${prefix}_role`} class="flex h-9 w-full rounded-lg border border-stone-200 bg-stone-50 px-3 text-sm text-stone-900 disabled:opacity-60" bind:value={form.role} disabled={lockOwner}><option value="owner">Owner</option><option value="admin_keuangan">Admin Keuangan</option><option value="admin_jaringan">Admin Jaringan</option></select>{#if form.errors.role}<p class="text-[11px] text-rose-600">{form.errors.role}</p>{/if}</div>
        <div class="space-y-1.5"><label for={`${prefix}_status`} class="text-xs font-semibold text-stone-700">Status Akun</label><select id={`${prefix}_status`} class="flex h-9 w-full rounded-lg border border-stone-200 bg-stone-50 px-3 text-sm text-stone-900 disabled:opacity-60" bind:value={form.is_active} disabled={lockOwner}><option value={true}>Aktif</option><option value={false}>Non-Aktif</option></select>{#if form.errors.is_active}<p class="text-[11px] text-rose-600">{form.errors.is_active}</p>{/if}</div>
    </div>
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div class="space-y-1.5"><label for={`${prefix}_password`} class="text-xs font-semibold text-stone-700">Kata Sandi {passwordOptional ? '(opsional)' : ''}</label><Input id={`${prefix}_password`} type="password" bind:value={form.password} required={!passwordOptional} minlength="8" placeholder={passwordOptional ? 'Kosongkan jika tidak diubah' : 'Minimal 8 karakter'} />{#if form.errors.password}<p class="text-[11px] text-rose-600">{form.errors.password}</p>{/if}</div>
        <div class="space-y-1.5"><label for={`${prefix}_password_confirmation`} class="text-xs font-semibold text-stone-700">Konfirmasi Kata Sandi</label><Input id={`${prefix}_password_confirmation`} type="password" bind:value={form.password_confirmation} required={!passwordOptional && Boolean(form.password)} minlength="8" /></div>
    </div>
{/snippet}
