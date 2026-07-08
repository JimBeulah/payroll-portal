import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index } from '@/routes/users';
import type { Auth } from '@/types/auth';

interface UserAccount {
    id: number;
    name: string;
    username: string;
    email: string | null;
    role: 'admin' | 'hr' | 'overseer';
}

type DialogMode = 'create' | 'edit' | 'delete' | null;

const emptyForm = { name: '', username: '', email: '', role: 'hr', password: '' };

export default function UsersIndex({ users }: { users: UserAccount[] }) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const [mode, setMode] = useState<DialogMode>(null);
    const [target, setTarget] = useState<UserAccount | null>(null);

    const createForm = useForm(emptyForm);
    const editForm = useForm(emptyForm);

    function openCreate() {
        createForm.setData(emptyForm);
        createForm.clearErrors();
        setMode('create');
    }

    function openEdit(user: UserAccount) {
        editForm.clearErrors();
        editForm.setData({
            name: user.name,
            username: user.username,
            email: user.email ?? '',
            role: user.role,
            password: '',
        });
        setTarget(user);
        setMode('edit');
    }

    function openDelete(user: UserAccount) {
        setTarget(user);
        setMode('delete');
    }

    function closeDialog() {
        setMode(null);
        setTarget(null);
    }

    function submitCreate(e: React.FormEvent) {
        e.preventDefault();
        createForm.post('/settings/users', {
            onSuccess: closeDialog,
            preserveScroll: true,
        });
    }

    function submitEdit(e: React.FormEvent) {
        e.preventDefault();
        editForm.put(`/settings/users/${target?.id}`, {
            onSuccess: closeDialog,
            preserveScroll: true,
        });
    }

    function confirmDelete() {
        router.delete(`/settings/users/${target?.id}`, {
            onSuccess: closeDialog,
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title="Users" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Users"
                        description="Manage admin, HR, and overseer login accounts"
                    />
                    <Button onClick={openCreate}>Add User</Button>
                </div>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Username</TableHead>
                            <TableHead>Email</TableHead>
                            <TableHead>Role</TableHead>
                            <TableHead className="text-right">
                                Actions
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {users.length === 0 ? (
                            <TableRow>
                                <TableCell
                                    colSpan={5}
                                    className="py-8 text-center text-muted-foreground"
                                >
                                    No admin or HR accounts yet.
                                </TableCell>
                            </TableRow>
                        ) : (
                            users.map((user) => (
                                <TableRow key={user.id}>
                                    <TableCell>{user.name}</TableCell>
                                    <TableCell>{user.username}</TableCell>
                                    <TableCell>
                                        {user.email ?? (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                user.role === 'admin'
                                                    ? 'default'
                                                    : user.role === 'hr'
                                                        ? 'secondary'
                                                        : 'outline'
                                            }
                                            className="uppercase"
                                        >
                                            {user.role}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="space-x-2 text-right">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => openEdit(user)}
                                        >
                                            Edit
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            disabled={user.id === auth.user.id}
                                            onClick={() => openDelete(user)}
                                        >
                                            Delete
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </div>

            {/* Create Dialog */}
            <Dialog
                open={mode === 'create'}
                onOpenChange={(open) => {
                    if (!open) {
closeDialog();
}
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add User</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submitCreate} className="space-y-4">
                        <div>
                            <Label>Name</Label>
                            <Input
                                value={createForm.data.name}
                                onChange={(e) =>
                                    createForm.setData('name', e.target.value)
                                }
                            />
                            <InputError message={createForm.errors.name} />
                        </div>
                        <div>
                            <Label>Username</Label>
                            <Input
                                value={createForm.data.username}
                                onChange={(e) =>
                                    createForm.setData(
                                        'username',
                                        e.target.value,
                                    )
                                }
                                autoComplete="off"
                            />
                            <InputError
                                message={createForm.errors.username}
                            />
                        </div>
                        <div>
                            <Label>
                                Email{' '}
                                <span className="font-normal text-muted-foreground">
                                    (optional)
                                </span>
                            </Label>
                            <Input
                                type="email"
                                value={createForm.data.email}
                                onChange={(e) =>
                                    createForm.setData(
                                        'email',
                                        e.target.value,
                                    )
                                }
                            />
                            <InputError message={createForm.errors.email} />
                        </div>
                        <div>
                            <Label>Role</Label>
                            <Select
                                value={createForm.data.role}
                                onValueChange={(v) =>
                                    createForm.setData(
                                        'role',
                                        v as 'admin' | 'hr' | 'overseer',
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="admin">
                                        Admin
                                    </SelectItem>
                                    <SelectItem value="hr">HR</SelectItem>
                                    <SelectItem value="overseer">
                                        Overseer (read-only)
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={createForm.errors.role} />
                        </div>
                        <div>
                            <Label>Password</Label>
                            <Input
                                type="text"
                                value={createForm.data.password}
                                onChange={(e) =>
                                    createForm.setData(
                                        'password',
                                        e.target.value,
                                    )
                                }
                                autoComplete="off"
                            />
                            <InputError
                                message={createForm.errors.password}
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={closeDialog}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={createForm.processing}
                            >
                                Save User
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Dialog */}
            <Dialog
                open={mode === 'edit'}
                onOpenChange={(open) => {
                    if (!open) {
closeDialog();
}
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit User</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submitEdit} className="space-y-4">
                        <div>
                            <Label>Name</Label>
                            <Input
                                value={editForm.data.name}
                                onChange={(e) =>
                                    editForm.setData('name', e.target.value)
                                }
                            />
                            <InputError message={editForm.errors.name} />
                        </div>
                        <div>
                            <Label>Username</Label>
                            <Input
                                value={editForm.data.username}
                                onChange={(e) =>
                                    editForm.setData(
                                        'username',
                                        e.target.value,
                                    )
                                }
                                autoComplete="off"
                            />
                            <InputError message={editForm.errors.username} />
                        </div>
                        <div>
                            <Label>
                                Email{' '}
                                <span className="font-normal text-muted-foreground">
                                    (optional)
                                </span>
                            </Label>
                            <Input
                                type="email"
                                value={editForm.data.email}
                                onChange={(e) =>
                                    editForm.setData('email', e.target.value)
                                }
                            />
                            <InputError message={editForm.errors.email} />
                        </div>
                        <div>
                            <Label>Role</Label>
                            <Select
                                value={editForm.data.role}
                                disabled={target?.id === auth.user.id}
                                onValueChange={(v) =>
                                    editForm.setData(
                                        'role',
                                        v as 'admin' | 'hr' | 'overseer',
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="admin">
                                        Admin
                                    </SelectItem>
                                    <SelectItem value="hr">HR</SelectItem>
                                    <SelectItem value="overseer">
                                        Overseer (read-only)
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={editForm.errors.role} />
                        </div>
                        <div>
                            <Label>
                                New Password{' '}
                                <span className="font-normal text-muted-foreground">
                                    (leave blank to keep current)
                                </span>
                            </Label>
                            <Input
                                type="text"
                                value={editForm.data.password}
                                onChange={(e) =>
                                    editForm.setData(
                                        'password',
                                        e.target.value,
                                    )
                                }
                                autoComplete="off"
                            />
                            <InputError message={editForm.errors.password} />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={closeDialog}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={editForm.processing}
                            >
                                Update User
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Confirmation Dialog */}
            <Dialog
                open={mode === 'delete'}
                onOpenChange={(open) => {
                    if (!open) {
closeDialog();
}
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete User</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete{' '}
                            <strong>{target?.name}</strong>&apos;s account?
                            This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={closeDialog}>
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={confirmDelete}>
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Users',
            href: index(),
        },
    ],
};
