import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InputError from '@/components/input-error';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { index } from '@/routes/holidays';

interface Holiday {
    id: number;
    name: string;
    date: string;
    type: 'regular' | 'special';
}

type DialogMode = 'create' | 'edit' | 'delete' | null;

export default function HolidaysIndex({ holidays }: { holidays: Holiday[] }) {
    const [mode, setMode] = useState<DialogMode>(null);
    const [target, setTarget] = useState<Holiday | null>(null);

    const createForm = useForm({ name: '', date: '', type: 'regular' });
    const editForm = useForm({ name: '', date: '', type: 'regular' });

    function openCreate() {
        createForm.reset();
        setMode('create');
    }

    function openEdit(h: Holiday) {
        editForm.clearErrors();
        editForm.setData({ name: h.name, date: h.date, type: h.type });
        setTarget(h);
        setMode('edit');
    }

    function openDelete(h: Holiday) {
        setTarget(h);
        setMode('delete');
    }

    function closeDialog() {
        setMode(null);
        setTarget(null);
    }

    function submitCreate(e: React.FormEvent) {
        e.preventDefault();
        createForm.post('/holidays', { onSuccess: closeDialog });
    }

    function submitEdit(e: React.FormEvent) {
        e.preventDefault();
        editForm.put(`/holidays/${target?.id}`, { onSuccess: closeDialog });
    }

    function confirmDelete() {
        router.delete(`/holidays/${target?.id}`, { onSuccess: closeDialog });
    }

    return (
        <>
            <Head title="Holidays" />
            <div className="p-6 space-y-4">
                <div className="flex justify-between items-center">
                    <h1 className="text-2xl font-bold">Holidays</h1>
                    <Button onClick={openCreate}>Add Holiday</Button>
                </div>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Date</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {holidays.map((h) => (
                            <TableRow key={h.id}>
                                <TableCell>{h.name}</TableCell>
                                <TableCell>{h.date}</TableCell>
                                <TableCell>
                                    <Badge variant={h.type === 'regular' ? 'default' : 'secondary'}>
                                        {h.type === 'regular' ? 'Regular (2×)' : 'Special (1.3×)'}
                                    </Badge>
                                </TableCell>
                                <TableCell className="space-x-2">
                                    <Button variant="outline" size="sm" onClick={() => openEdit(h)}>Edit</Button>
                                    <Button variant="destructive" size="sm" onClick={() => openDelete(h)}>Delete</Button>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>

            {/* Create Dialog */}
            <Dialog open={mode === 'create'} onOpenChange={(open) => { if (!open) closeDialog(); }}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add Holiday</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submitCreate} className="space-y-4">
                        <div>
                            <Label>Name</Label>
                            <Input value={createForm.data.name} onChange={e => createForm.setData('name', e.target.value)} />
                            <InputError message={createForm.errors.name} />
                        </div>
                        <div>
                            <Label>Date</Label>
                            <Input type="date" value={createForm.data.date} onChange={e => createForm.setData('date', e.target.value)} />
                            <InputError message={createForm.errors.date} />
                        </div>
                        <div>
                            <Label>Type</Label>
                            <Select value={createForm.data.type} onValueChange={v => createForm.setData('type', v)}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="regular">Regular Holiday (2× pay)</SelectItem>
                                    <SelectItem value="special">Special Holiday (1.3× pay)</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={createForm.errors.type} />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={closeDialog}>Cancel</Button>
                            <Button type="submit" disabled={createForm.processing}>Save Holiday</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Dialog */}
            <Dialog open={mode === 'edit'} onOpenChange={(open) => { if (!open) closeDialog(); }}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit Holiday</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submitEdit} className="space-y-4">
                        <div>
                            <Label>Name</Label>
                            <Input value={editForm.data.name} onChange={e => editForm.setData('name', e.target.value)} />
                            <InputError message={editForm.errors.name} />
                        </div>
                        <div>
                            <Label>Date</Label>
                            <Input type="date" value={editForm.data.date} onChange={e => editForm.setData('date', e.target.value)} />
                            <InputError message={editForm.errors.date} />
                        </div>
                        <div>
                            <Label>Type</Label>
                            <Select value={editForm.data.type} onValueChange={v => editForm.setData('type', v)}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="regular">Regular Holiday (2× pay)</SelectItem>
                                    <SelectItem value="special">Special Holiday (1.3× pay)</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={editForm.errors.type} />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={closeDialog}>Cancel</Button>
                            <Button type="submit" disabled={editForm.processing}>Update Holiday</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Confirmation Dialog */}
            <Dialog open={mode === 'delete'} onOpenChange={(open) => { if (!open) closeDialog(); }}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Holiday</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete <strong>{target?.name}</strong>? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={closeDialog}>Cancel</Button>
                        <Button variant="destructive" onClick={confirmDelete}>Delete</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

HolidaysIndex.layout = {
    breadcrumbs: [
        { title: 'Holidays', href: index() },
    ],
};
