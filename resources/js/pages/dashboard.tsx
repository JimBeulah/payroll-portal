import { Head, Link } from '@inertiajs/react';
import {
    Users,
    UserCheck,
    UserX,
    Wallet,
    ClipboardList,
    Lock,
    TrendingUp,
    ArrowRight,
} from 'lucide-react';
import {
    BarChart,
    Bar,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
    PieChart,
    Pie,
    Cell,
    Legend,
} from 'recharts';
import { Badge } from '@/components/ui/badge';
import { formatDate } from '@/lib/utils';
import { dashboard } from '@/routes';

interface Stats {
    total_employees: number;
    active_employees: number;
    inactive_employees: number;
    latest_run_net_pay: number;
    latest_run_employees: number;
    latest_run_period: string | null;
    total_runs_this_year: number;
    locked_runs: number;
}

interface TrendPoint {
    label: string;
    net_pay: number;
    gross_pay: number;
    employee_count: number;
}

interface DepartmentStat {
    department: string;
    total: number;
}

interface RecentRun {
    id: number;
    period_start: string;
    period_end: string;
    payable_date: string;
    status: 'draft' | 'locked';
    net_pay: number;
    employee_count: number;
}

interface TopEarner {
    name: string;
    department: string;
    net_pay: number;
}

interface DashboardProps {
    stats: Stats;
    payrollTrend: TrendPoint[];
    departmentStats: DepartmentStat[];
    recentRuns: RecentRun[];
    topEarners: TopEarner[];
}

const DEPT_COLORS = [
    '#6366f1', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981',
    '#3b82f6', '#ef4444', '#14b8a6', '#f97316', '#a855f7',
];

function fmt(value: number) {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(value);
}

function fmtShort(value: number) {
    if (value >= 1_000_000) {
return `₱${(value / 1_000_000).toFixed(1)}M`;
}

    if (value >= 1_000) {
return `₱${(value / 1_000).toFixed(1)}K`;
}

    return `₱${value}`;
}

interface KpiCardProps {
    title: string;
    value: string | number;
    sub?: string;
    icon: React.ReactNode;
    iconClass: string;
    trend?: string;
}

function KpiCard({ title, value, sub, icon, iconClass, trend }: KpiCardProps) {
    return (
        <div className="group relative overflow-hidden rounded-2xl border border-border/60 bg-card px-5 py-4 shadow-sm transition-shadow hover:shadow-md">
            <div className="flex items-start justify-between">
                <div className="space-y-1">
                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">{title}</p>
                    <p className="text-3xl font-bold tracking-tight text-foreground">{value}</p>
                    {sub && <p className="text-xs text-muted-foreground">{sub}</p>}
                    {trend && (
                        <span className="inline-flex items-center gap-1 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                            <TrendingUp className="h-3 w-3" />
                            {trend}
                        </span>
                    )}
                </div>
                <div className={iconClass}>{icon}</div>
            </div>
        </div>
    );
}

 
function CustomBarTooltip({ active, payload, label }: any) {
    if (!active || !payload?.length) {
return null;
}

    return (
        <div className="rounded-xl border border-border bg-card px-3 py-2 shadow-lg text-sm">
            <p className="font-semibold text-foreground mb-1">{label}</p>
            {payload.map((p: { name: string; value: number; color: string }, i: number) => (
                <p key={i} style={{ color: p.color }} className="text-xs">
                    {p.name}: {fmtShort(p.value)}
                </p>
            ))}
        </div>
    );
}

export default function Dashboard({ stats, payrollTrend, departmentStats, recentRuns, topEarners }: DashboardProps) {
    const hasData = payrollTrend.length > 0;

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-col gap-6 p-4 md:p-6">

                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground">Overview</h1>
                        <p className="mt-0.5 text-sm text-muted-foreground">
                            {stats.latest_run_period
                                ? `Latest payroll period: ${stats.latest_run_period}`
                                : 'No payroll runs yet'}
                        </p>
                    </div>
                    <Link
                        href="/payroll-runs"
                        className="inline-flex items-center gap-1.5 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm transition-opacity hover:opacity-90"
                    >
                        Payroll Runs <ArrowRight className="h-3.5 w-3.5" />
                    </Link>
                </div>

                {/* KPI Cards */}
                <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <KpiCard
                        title="Total Employees"
                        value={stats.total_employees}
                        sub={`${stats.active_employees} active · ${stats.inactive_employees} inactive`}
                        icon={<Users className="h-5 w-5" />}
                        iconClass="text-indigo-500"
                    />
                    <KpiCard
                        title="Active Staff"
                        value={stats.active_employees}
                        sub="Currently employed"
                        icon={<UserCheck className="h-5 w-5" />}
                        iconClass="text-emerald-500"
                    />
                    <KpiCard
                        title="Latest Net Payroll"
                        value={fmtShort(stats.latest_run_net_pay)}
                        sub={stats.latest_run_employees > 0 ? `${stats.latest_run_employees} employees` : 'No entries yet'}
                        icon={<Wallet className="h-5 w-5" />}
                        iconClass="text-violet-500"
                    />
                    <KpiCard
                        title="Runs This Year"
                        value={stats.total_runs_this_year}
                        sub={`${stats.locked_runs} locked`}
                        icon={<ClipboardList className="h-5 w-5" />}
                        iconClass="text-amber-500"
                    />
                </div>

                {/* Charts row */}
                <div className="grid gap-4 md:grid-cols-3">

                    {/* Payroll trend - spans 2 cols */}
                    <div className="col-span-2 rounded-2xl border border-border/60 bg-card p-5 shadow-sm">
                        <div className="mb-4 flex items-center justify-between">
                            <div>
                                <h2 className="text-sm font-semibold text-foreground">Net Payroll Trend</h2>
                                <p className="text-xs text-muted-foreground">Last {payrollTrend.length} payroll runs</p>
                            </div>
                            <div className="flex items-center gap-3 text-xs text-muted-foreground">
                                <span className="flex items-center gap-1"><span className="inline-block h-2.5 w-2.5 rounded-sm bg-indigo-500" />Net Pay</span>
                                <span className="flex items-center gap-1"><span className="inline-block h-2.5 w-2.5 rounded-sm bg-indigo-200 dark:bg-indigo-800" />Gross Pay</span>
                            </div>
                        </div>
                        {hasData ? (
                            <ResponsiveContainer width="100%" height={220}>
                                <BarChart data={payrollTrend} barSize={18} barGap={4}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="currentColor" className="text-border/50" vertical={false} />
                                    <XAxis dataKey="label" tick={{ fontSize: 11, fill: 'currentColor' }} className="text-muted-foreground" axisLine={false} tickLine={false} />
                                    <YAxis tickFormatter={fmtShort} tick={{ fontSize: 11, fill: 'currentColor' }} className="text-muted-foreground" axisLine={false} tickLine={false} width={60} />
                                    <Tooltip content={<CustomBarTooltip />} cursor={{ fill: 'currentColor', className: 'text-muted/30 opacity-30' }} />
                                    <Bar dataKey="gross_pay" name="Gross Pay" fill="#c7d2fe" radius={[4, 4, 0, 0]} className="dark:fill-indigo-900" />
                                    <Bar dataKey="net_pay" name="Net Pay" fill="#6366f1" radius={[4, 4, 0, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        ) : (
                            <EmptyState message="No payroll runs recorded yet." />
                        )}
                    </div>

                    {/* Department distribution */}
                    <div className="rounded-2xl border border-border/60 bg-card p-5 shadow-sm">
                        <div className="mb-4">
                            <h2 className="text-sm font-semibold text-foreground">Department Distribution</h2>
                            <p className="text-xs text-muted-foreground">Active employees</p>
                        </div>
                        {departmentStats.length > 0 ? (
                            <ResponsiveContainer width="100%" height={220}>
                                <PieChart>
                                    <Pie
                                        data={departmentStats}
                                        dataKey="total"
                                        nameKey="department"
                                        cx="50%"
                                        cy="45%"
                                        innerRadius={55}
                                        outerRadius={80}
                                        paddingAngle={3}
                                    >
                                        {departmentStats.map((_, i) => (
                                            <Cell key={i} fill={DEPT_COLORS[i % DEPT_COLORS.length]} />
                                        ))}
                                    </Pie>
                                    <Legend
                                        formatter={(value) => (
                                            <span className="text-xs text-muted-foreground">{value}</span>
                                        )}
                                        wrapperStyle={{ fontSize: 11 }}
                                    />
                                    <Tooltip
                                        formatter={(value) => [`${value} employees`]}
                                        contentStyle={{ fontSize: 12, borderRadius: 12, border: '1px solid var(--border)', background: 'var(--card)' }}
                                    />
                                </PieChart>
                            </ResponsiveContainer>
                        ) : (
                            <EmptyState message="No active employees found." />
                        )}
                    </div>
                </div>

                {/* Bottom row: Recent runs + Top earners */}
                <div className="grid gap-4 md:grid-cols-2">

                    {/* Recent payroll runs */}
                    <div className="rounded-2xl border border-border/60 bg-card p-5 shadow-sm">
                        <div className="mb-4 flex items-center justify-between">
                            <div>
                                <h2 className="text-sm font-semibold text-foreground">Recent Payroll Runs</h2>
                                <p className="text-xs text-muted-foreground">Last 5 runs</p>
                            </div>
                            <Link href="/payroll-runs" className="text-xs font-medium text-primary hover:underline">
                                View all
                            </Link>
                        </div>
                        {recentRuns.length > 0 ? (
                            <div className="space-y-2">
                                {recentRuns.map((run) => (
                                    <Link
                                        key={run.id}
                                        href={`/payroll-runs/${run.id}`}
                                        className="flex items-center justify-between rounded-xl px-3 py-2.5 transition-colors hover:bg-muted/60"
                                    >
                                        <div className="flex items-center gap-3">
                                            <div className={`flex h-8 w-8 items-center justify-center rounded-lg ${run.status === 'locked' ? 'bg-emerald-100 dark:bg-emerald-900/40' : 'bg-amber-100 dark:bg-amber-900/40'}`}>
                                                {run.status === 'locked'
                                                    ? <Lock className="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
                                                    : <ClipboardList className="h-3.5 w-3.5 text-amber-600 dark:text-amber-400" />
                                                }
                                            </div>
                                            <div>
                                                <p className="text-xs font-medium text-foreground leading-tight">
                                                    {formatDate(run.period_start)} – {formatDate(run.period_end)}
                                                </p>
                                                <p className="text-[11px] text-muted-foreground">
                                                    {run.employee_count} employees · Due {formatDate(run.payable_date)}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-xs font-semibold text-foreground">{fmtShort(run.net_pay)}</p>
                                            <Badge
                                                variant={run.status === 'locked' ? 'default' : 'secondary'}
                                                className="mt-0.5 text-[10px] h-4 px-1.5"
                                            >
                                                {run.status}
                                            </Badge>
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        ) : (
                            <EmptyState message="No payroll runs yet." />
                        )}
                    </div>

                    {/* Top earners */}
                    <div className="rounded-2xl border border-border/60 bg-card p-5 shadow-sm">
                        <div className="mb-4">
                            <h2 className="text-sm font-semibold text-foreground">Top Earners</h2>
                            <p className="text-xs text-muted-foreground">
                                {topEarners.length > 0 ? 'Latest payroll run' : 'No data available'}
                            </p>
                        </div>
                        {topEarners.length > 0 ? (
                            <div className="space-y-3">
                                {topEarners.map((earner, i) => {
                                    const max = topEarners[0]?.net_pay ?? 1;
                                    const pct = Math.round((earner.net_pay / max) * 100);

                                    return (
                                        <div key={i} className="space-y-1">
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center gap-2">
                                                    <span className="flex h-5 w-5 items-center justify-center rounded-full bg-muted text-[10px] font-bold text-muted-foreground">
                                                        {i + 1}
                                                    </span>
                                                    <div>
                                                        <p className="text-xs font-medium text-foreground leading-tight">{earner.name}</p>
                                                        <p className="text-[11px] text-muted-foreground">{earner.department}</p>
                                                    </div>
                                                </div>
                                                <span className="text-xs font-semibold text-foreground">{fmt(earner.net_pay)}</span>
                                            </div>
                                            <div className="h-1.5 w-full rounded-full bg-muted">
                                                <div
                                                    className="h-1.5 rounded-full bg-indigo-500 transition-all"
                                                    style={{ width: `${pct}%` }}
                                                />
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        ) : (
                            <EmptyState message="Run a payroll to see top earners." />
                        )}
                    </div>
                </div>

                {/* Quick actions */}
                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    {[
                        { label: 'New Payroll Run', href: '/payroll-runs', icon: <ClipboardList className="h-4 w-4" />, color: 'text-indigo-600 bg-indigo-50 dark:bg-indigo-900/30 dark:text-indigo-300' },
                        { label: 'Manage Employees', href: '/employees', icon: <Users className="h-4 w-4" />, color: 'text-emerald-600 bg-emerald-50 dark:bg-emerald-900/30 dark:text-emerald-300' },
                        { label: 'Inactive Staff', href: '/employees', icon: <UserX className="h-4 w-4" />, color: 'text-rose-600 bg-rose-50 dark:bg-rose-900/30 dark:text-rose-300' },
                        { label: 'Locked Runs', href: '/payroll-runs', icon: <Lock className="h-4 w-4" />, color: 'text-amber-600 bg-amber-50 dark:bg-amber-900/30 dark:text-amber-300' },
                    ].map((action) => (
                        <Link
                            key={action.label}
                            href={action.href}
                            className="flex items-center gap-3 rounded-xl border border-border/60 bg-card px-4 py-3 text-sm font-medium text-foreground shadow-sm transition-all hover:shadow-md hover:-translate-y-0.5"
                        >
                            <span className={`rounded-lg p-1.5 ${action.color}`}>{action.icon}</span>
                            {action.label}
                        </Link>
                    ))}
                </div>

            </div>
        </>
    );
}

function EmptyState({ message }: { message: string }) {
    return (
        <div className="flex h-40 items-center justify-center rounded-xl border border-dashed border-border">
            <p className="text-sm text-muted-foreground">{message}</p>
        </div>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
    ],
};
