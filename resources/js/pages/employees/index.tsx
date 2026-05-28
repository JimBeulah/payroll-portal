import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { index } from '@/routes/employees';

interface Employee {
    id: number;
    name: string;
    department: string;
    daily_rate: string;
    shift_start: string;
    shift_end: string;
    is_active: boolean;
}

type DialogMode = 'create' | 'edit' | 'delete' | null;

export default function EmployeesIndex({ employees }: { employees: Employee[] }) {
    const [mode, setMode] = useState<DialogMode>(null);
    const [target, setTarget] = useState<Employee | null>(null);

    const createForm = useForm({ name: '', department: '', daily_rate: '', shift_start: '08:00', shift_end: '17:00' });
    const editForm = useForm({ name: '', department: '', daily_rate: '', shift_start: '', shift_end: '' });

    function openCreate() {
        createForm.reset();
        setMode('create');
    }

    function openEdit(emp: Employee) {
        editForm.clearErrors();
        editForm.setData({
            name: emp.name,
            department: emp.department,
            daily_rate: emp.daily_rate,
            shift_start: emp.shift_start.slice(0, 5),
            shift_end: emp.shift_end.slice(0, 5),
        });
        setTarget(emp);
        setMode('edit');
    }

    function openDelete(emp: Employee) {
        setTarget(emp);
        setMode('delete');
    }

    function closeDialog() {
        setMode(null);
        setTarget(null);
    }

    function submitCreate(e: React.FormEvent) {
        e.preventDefault();
        createForm.post('/employees', { onSuccess: closeDialog });
    }

    function submitEdit(e: React.FormEvent) {
        e.preventDefault();
        editForm.put(`/employees/${target?.id}`, { onSuccess: closeDialog });
    }

    function confirmDelete() {
        router.delete(`/employees/${target?.id}`, { onSuccess: closeDialog });
    }

    return (
        <>
            <Head title="Employees" />
            <div className="p-6 space-y-4">
                <div className="flex justify-between items-center">
                    <h1 className="text-2xl font-bold">Employees</h1>
                    <Button onClick={openCreate}>Add Employee</Button>
                </div>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Department</TableHead>
                            <TableHead>Daily Rate</TableHead>
                            <TableHead>Shift</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {employees.map((emp) => (
                            <TableRow key={emp.id}>
                                <TableCell>{emp.name}</TableCell>
                                <TableCell>{emp.department}</TableCell>
                                <TableCell>₱{Number(emp.daily_rate).toLocaleString()}</TableCell>
                                <TableCell>{emp.shift_start} – {emp.shift_end}</TableCell>
                                <TableCell>{emp.is_active ? 'Active' : 'Inactive'}</TableCell>
                                <TableCell className="space-x-2">
                                    <Button variant="outline" size="sm" onClick={() => openEdit(emp)}>Edit</Button>
                                    <Button variant="destructive" size="sm" onClick={() => openDelete(emp)}>Delete</Button>
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
                        <DialogTitle>Add Employee</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submitCreate} className="space-y-4">
                        <div>
                            <Label>Name</Label>
                            <Input value={createForm.data.name} onChange={e => createForm.setData('name', e.target.value)} />
                            <InputError message={createForm.errors.name} />
                        </div>
                        <div>
                            <Label>Department</Label>
                            <Input value={createForm.data.department} onChange={e => createForm.setData('department', e.target.value)} />
                            <InputError message={createForm.errors.department} />
                        </div>
                        <div>
                            <Label>Daily Rate (₱)</Label>
                            <Input type="number" step="0.01" value={createForm.data.daily_rate} onChange={e => createForm.setData('daily_rate', e.target.value)} />
                            <InputError message={createForm.errors.daily_rate} />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label>Shift Start</Label>
                                <Input type="time" value={createForm.data.shift_start} onChange={e => createForm.setData('shift_start', e.target.value)} />
                                <InputError message={createForm.errors.shift_start} />
                            </div>
                            <div>
                                <Label>Shift End</Label>
                                <Input type="time" value={createForm.data.shift_end} onChange={e => createForm.setData('shift_end', e.target.value)} />
                                <InputError message={createForm.errors.shift_end} />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={closeDialog}>Cancel</Button>
                            <Button type="submit" disabled={createForm.processing}>Save Employee</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Dialog */}
            <Dialog open={mode === 'edit'} onOpenChange={(open) => { if (!open) closeDialog(); }}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit Employee</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submitEdit} className="space-y-4">
                        <div>
                            <Label>Name</Label>
                            <Input value={editForm.data.name} onChange={e => editForm.setData('name', e.target.value)} />
                            <InputError message={editForm.errors.name} />
                        </div>
                        <div>
                            <Label>Department</Label>
                            <Input value={editForm.data.department} onChange={e => editForm.setData('department', e.target.value)} />
                            <InputError message={editForm.errors.department} />
                        </div>
                        <div>
                            <Label>Daily Rate (₱)</Label>
                            <Input type="number" step="0.01" value={editForm.data.daily_rate} onChange={e => editForm.setData('daily_rate', e.target.value)} />
                            <InputError message={editForm.errors.daily_rate} />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label>Shift Start</Label>
                                <Input type="time" value={editForm.data.shift_start} onChange={e => editForm.setData('shift_start', e.target.value)} />
                                <InputError message={editForm.errors.shift_start} />
                            </div>
                            <div>
                                <Label>Shift End</Label>
                                <Input type="time" value={editForm.data.shift_end} onChange={e => editForm.setData('shift_end', e.target.value)} />
                                <InputError message={editForm.errors.shift_end} />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={closeDialog}>Cancel</Button>
                            <Button type="submit" disabled={editForm.processing}>Update Employee</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Confirmation Dialog */}
            <Dialog open={mode === 'delete'} onOpenChange={(open) => { if (!open) closeDialog(); }}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Employee</DialogTitle>
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

EmployeesIndex.layout = {
    breadcrumbs: [
        { title: 'Employees', href: index() },
    ],
};
