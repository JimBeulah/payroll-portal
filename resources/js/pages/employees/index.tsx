import { useState, useMemo } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InputError from '@/components/input-error';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { index } from '@/routes/employees';

interface Employee {
    id: number;
    name: string;
    employee_number: string | null;
    gender: string | null;
    department: string | null;
    daily_rate: string;
    shift_start: string;
    shift_end: string;
    is_active: boolean;
}

type DialogMode = 'create' | 'edit' | 'delete' | null;

const PAGE_SIZE = 10;

export default function EmployeesIndex({ employees }: { employees: Employee[] }) {
    const [mode, setMode] = useState<DialogMode>(null);
    const [target, setTarget] = useState<Employee | null>(null);

    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [page, setPage] = useState(1);

    const createForm = useForm({ name: '', employee_number: '', gender: '', department: '', daily_rate: '', shift_start: '08:00', shift_end: '17:00' });
    const editForm = useForm({ name: '', employee_number: '', gender: '', department: '', daily_rate: '', shift_start: '', shift_end: '', is_active: true });

    const filtered = useMemo(() => {
        const q = search.toLowerCase();
        return employees.filter(emp => {
            const matchesSearch = !q || emp.name.toLowerCase().includes(q) || (emp.department ?? '').toLowerCase().includes(q);
            const matchesStatus =
                statusFilter === 'all' ||
                (statusFilter === 'active' ? emp.is_active : !emp.is_active);
            return matchesSearch && matchesStatus;
        });
    }, [employees, search, statusFilter]);

    const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
    const paginated = filtered.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE);

    function openCreate() {
        createForm.setData({ name: '', employee_number: '', gender: '', department: '', daily_rate: '', shift_start: '08:00', shift_end: '17:00' });
        createForm.clearErrors();
        setMode('create');
    }

    function openEdit(emp: Employee) {
        editForm.clearErrors();
        editForm.setData({
            name: emp.name,
            employee_number: emp.employee_number ?? '',
            gender: emp.gender ?? '',
            department: emp.department ?? '',
            daily_rate: emp.daily_rate,
            shift_start: emp.shift_start.slice(0, 5),
            shift_end: emp.shift_end.slice(0, 5),
            is_active: emp.is_active,
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

                {/* Search & Filter */}
                <div className="flex flex-col sm:flex-row gap-3">
                    <Input
                        placeholder="Search by name or department…"
                        value={search}
                        onChange={e => { setSearch(e.target.value); setPage(1); }}
                        className="max-w-sm"
                    />
                    <Select value={statusFilter} onValueChange={v => { setStatusFilter(v); setPage(1); }}>
                        <SelectTrigger className="w-40">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Status</SelectItem>
                            <SelectItem value="active">Active</SelectItem>
                            <SelectItem value="inactive">Inactive</SelectItem>
                        </SelectContent>
                    </Select>
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
                        {paginated.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={6} className="text-center text-muted-foreground py-8">
                                    No employees found.
                                </TableCell>
                            </TableRow>
                        ) : (
                            paginated.map((emp) => (
                                <TableRow key={emp.id}>
                                    <TableCell>{emp.name}</TableCell>
                                    <TableCell>{emp.department ?? <span className="text-muted-foreground">—</span>}</TableCell>
                                    <TableCell>₱{Number(emp.daily_rate).toLocaleString()}</TableCell>
                                    <TableCell>{emp.shift_start.slice(0, 5)} – {emp.shift_end.slice(0, 5)}</TableCell>
                                    <TableCell>{emp.is_active ? 'Active' : 'Inactive'}</TableCell>
                                    <TableCell className="space-x-2">
                                        <Button variant="outline" size="sm" onClick={() => openEdit(emp)}>Edit</Button>
                                        <Button variant="destructive" size="sm" onClick={() => openDelete(emp)}>Delete</Button>
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>

                {/* Pagination */}
                <div className="flex items-center justify-between text-sm text-muted-foreground">
                    <span>
                        {filtered.length === 0
                            ? 'No results'
                            : `${(page - 1) * PAGE_SIZE + 1}–${Math.min(page * PAGE_SIZE, filtered.length)} of ${filtered.length} employee${filtered.length !== 1 ? 's' : ''}`}
                    </span>
                    <div className="flex gap-2">
                        <Button variant="outline" size="sm" onClick={() => setPage(p => p - 1)} disabled={page === 1}>
                            Previous
                        </Button>
                        <Button variant="outline" size="sm" onClick={() => setPage(p => p + 1)} disabled={page === totalPages}>
                            Next
                        </Button>
                    </div>
                </div>
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
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label>Employee Number <span className="text-muted-foreground font-normal">(optional)</span></Label>
                                <Input value={createForm.data.employee_number} onChange={e => createForm.setData('employee_number', e.target.value)} placeholder="e.g. EMP-001" />
                                <InputError message={createForm.errors.employee_number} />
                            </div>
                            <div>
                                <Label>Gender <span className="text-muted-foreground font-normal">(optional)</span></Label>
                                <Select value={createForm.data.gender || '_none'} onValueChange={v => createForm.setData('gender', v === '_none' ? '' : v)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select gender" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="_none">— None —</SelectItem>
                                        <SelectItem value="Male">Male</SelectItem>
                                        <SelectItem value="Female">Female</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={createForm.errors.gender} />
                            </div>
                        </div>
                        <div>
                            <Label>Department <span className="text-muted-foreground font-normal">(optional)</span></Label>
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
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label>Employee Number <span className="text-muted-foreground font-normal">(optional)</span></Label>
                                <Input value={editForm.data.employee_number} onChange={e => editForm.setData('employee_number', e.target.value)} placeholder="e.g. EMP-001" />
                                <InputError message={editForm.errors.employee_number} />
                            </div>
                            <div>
                                <Label>Gender <span className="text-muted-foreground font-normal">(optional)</span></Label>
                                <Select value={editForm.data.gender || '_none'} onValueChange={v => editForm.setData('gender', v === '_none' ? '' : v)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select gender" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="_none">— None —</SelectItem>
                                        <SelectItem value="Male">Male</SelectItem>
                                        <SelectItem value="Female">Female</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={editForm.errors.gender} />
                            </div>
                        </div>
                        <div>
                            <Label>Department <span className="text-muted-foreground font-normal">(optional)</span></Label>
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
                        <div>
                            <Label>Status</Label>
                            <Select value={editForm.data.is_active ? 'active' : 'inactive'} onValueChange={v => editForm.setData('is_active', v === 'active')}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="active">Active</SelectItem>
                                    <SelectItem value="inactive">Inactive</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={editForm.errors.is_active} />
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
