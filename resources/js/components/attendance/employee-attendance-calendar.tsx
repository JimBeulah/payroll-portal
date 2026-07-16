interface ManualAttendance {
    id: number;
    date: string;
    sw: string | null;
    ew: string | null;
    shift_start: string;
    shift_end: string;
    is_override: boolean;
}

interface DayAttendance {
    sw: string;
    ew: string;
    late_minutes: number;
    undertime_minutes: number;
    overtime_minutes: number;
}

interface LeaveDay {
    reason: string | null;
}

interface Holiday {
    date: string;
    name: string;
    type: 'regular' | 'special';
}

interface Props {
    periodStart: string;
    periodEnd: string;
    manualAttendances: ManualAttendance[];
    attendanceData: Record<string, DayAttendance>;
    leaveData: Record<string, LeaveDay>;
    holidays: Holiday[];
}

function toHHMM(value: string): string {
    if (!value) return '';
    const parts = value.substring(0, 5).split(':');
    return parts[0].padStart(2, '0') + ':' + (parts[1] ?? '00').padStart(2, '0');
}

function to12hr(value: string): string {
    if (!value) return '';
    const hhmm = toHHMM(value);
    const [h, m] = hhmm.split(':').map(Number);
    const period = h >= 12 ? 'pm' : 'am';
    const hour = h % 12 === 0 ? 12 : h % 12;
    return `${hour}:${String(m).padStart(2, '0')}${period}`;
}

function timeToMins(hhmm: string): number {
    const [h, m] = toHHMM(hhmm).split(':').map(Number);
    return h * 60 + m;
}

function computeDayStats(sw: string, ew: string, shiftStart: string, shiftEnd: string) {
    let sStart = timeToMins(shiftStart);
    let sEnd = timeToMins(shiftEnd);
    const aStart = timeToMins(sw);
    const aEnd = timeToMins(ew);

    if (sEnd <= sStart) sEnd += 1440;
    let adjAEnd = aEnd;
    if (adjAEnd <= aStart) adjAEnd += 1440;

    const shiftMins = sEnd - sStart;
    const breakMins = Math.max(0, shiftMins - 480);
    const bStart = sStart + 240;
    const bEnd = bStart + breakMins;

    let late = 0;
    let undertime = 0;
    let overtime = 0;

    if (aStart > sStart) {
        let raw = aStart - sStart;
        if (breakMins > 0 && aStart > bStart) {
            raw -= Math.min(aStart, bEnd) - bStart;
        }
        late = Math.max(0, raw);
    }

    if (adjAEnd < sEnd) {
        let raw = sEnd - adjAEnd;
        if (breakMins > 0 && adjAEnd < bEnd) {
            raw -= bEnd - Math.max(adjAEnd, bStart);
        }
        undertime = Math.max(0, raw);
    }

    if (adjAEnd > sEnd) {
        let rawOt = adjAEnd - sEnd;
        // Deduct unpaid lunch (12pm–1pm) if OT window overlaps it (same day or next day)
        const lunchStart = 12 * 60;
        const lunchEnd = 13 * 60;
        for (const offset of [0, 1440]) {
            const ls = lunchStart + offset;
            const le = lunchEnd + offset;
            rawOt -= Math.max(0, Math.min(adjAEnd, le) - Math.max(sEnd, ls));
        }
        overtime = Math.max(0, rawOt);
    }

    return { late_minutes: late, undertime_minutes: undertime, overtime_minutes: overtime };
}

function fmtMinutes(minutes: number): string {
    if (minutes < 60) return `${minutes}m`;
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return m === 0 ? `${h}hr` : `${h}hr ${m}m`;
}

function formatDateString(d: Date): string {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

export default function EmployeeAttendanceCalendar({
    periodStart,
    periodEnd,
    manualAttendances,
    attendanceData,
    leaveData,
    holidays,
}: Props) {
    const start = new Date(`${periodStart}T00:00:00`);
    const end = new Date(`${periodEnd}T00:00:00`);

    const firstDay = new Date(start.getFullYear(), start.getMonth(), 1);
    const lastDay = new Date(end.getFullYear(), end.getMonth() + 1, 0);

    const dateArray: (Date | null)[] = [];
    const startingDayOfWeek = firstDay.getDay();
    for (let i = 0; i < startingDayOfWeek; i++) {
        dateArray.push(null);
    }
    for (let d = new Date(firstDay); d <= lastDay; d.setDate(d.getDate() + 1)) {
        dateArray.push(new Date(d));
    }

    const attendanceByDate: Record<string, ManualAttendance> = {};
    manualAttendances.forEach((a) => {
        attendanceByDate[a.date] = a;
    });

    const holidaysByDate: Record<string, Holiday> = {};
    holidays.forEach((h) => {
        holidaysByDate[h.date] = h;
    });

    function isInPeriod(date: Date): boolean {
        const dateStr = formatDateString(date);
        return dateStr >= periodStart && dateStr <= periodEnd;
    }

    function isToday(date: Date): boolean {
        const now = new Date();
        return (
            date.getFullYear() === now.getFullYear() &&
            date.getMonth() === now.getMonth() &&
            date.getDate() === now.getDate()
        );
    }

    const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    const monthName = firstDay.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

    return (
        <div className="space-y-4">
            <div className="border rounded-lg p-4">
                <h3 className="font-semibold mb-4 text-center">{monthName}</h3>

                <div className="grid grid-cols-7 gap-2 mb-2">
                    {dayNames.map((day) => (
                        <div key={day} className="text-xs font-semibold text-center text-muted-foreground py-2">
                            {day}
                        </div>
                    ))}
                </div>

                <div className="grid grid-cols-7 gap-2">
                    {dateArray.map((date, idx) => {
                        const dateStr = date ? formatDateString(date) : null;
                        const attendance = dateStr ? attendanceByDate[dateStr] : null;
                        const excelDay = dateStr ? (attendanceData[dateStr] ?? null) : null;
                        const leaveDay = dateStr ? (leaveData[dateStr] ?? null) : null;
                        const holiday = dateStr ? (holidaysByDate[dateStr] ?? null) : null;
                        const inPeriod = date ? isInPeriod(date) : false;
                        const today = date ? isToday(date) : false;

                        return (
                            <div
                                key={idx}
                                className={`
                                    min-h-20 p-2 rounded border text-xs relative
                                    ${
                                        !date || !inPeriod
                                            ? 'bg-muted/30 border-transparent opacity-40'
                                            : leaveDay
                                              ? 'bg-sky-500/10 border-sky-400/50'
                                              : holiday
                                                ? 'bg-violet-500/10 border-violet-400/50'
                                                : attendance
                                                  ? 'bg-primary/10 border-primary/40'
                                                  : excelDay
                                                    ? 'bg-muted/30 border-border'
                                                    : 'bg-background border-border'
                                    }
                                    ${today ? 'ring-1 ring-amber-500' : ''}
                                `}
                            >
                                <div className="text-sm font-semibold mb-1 leading-none">{date && date.getDate()}</div>

                                {holiday && !leaveDay && (
                                    <div className="text-[11px] space-y-0.5">
                                        <div className="text-[9px] text-violet-600 dark:text-violet-400 font-bold uppercase leading-none">
                                            Holiday
                                        </div>
                                        <div className="text-muted-foreground truncate" title={holiday.name}>
                                            {holiday.name}
                                        </div>
                                    </div>
                                )}

                                {leaveDay && (
                                    <div className="text-[11px] space-y-0.5">
                                        <div className="text-[9px] text-sky-600 dark:text-sky-400 font-bold uppercase leading-none">
                                            Leave (Unpaid)
                                        </div>
                                        {leaveDay.reason && (
                                            <div className="text-muted-foreground truncate" title={leaveDay.reason}>
                                                {leaveDay.reason}
                                            </div>
                                        )}
                                    </div>
                                )}

                                {excelDay && !attendance && !leaveDay && !holiday && (
                                    <div className="text-[11px] space-y-0.5">
                                        <div className="flex items-center gap-1">
                                            <span className="text-[9px] uppercase text-muted-foreground font-semibold shrink-0">T</span>
                                            <span className="text-foreground/90 font-medium">
                                                {to12hr(excelDay.sw)}–{to12hr(excelDay.ew)}
                                            </span>
                                        </div>
                                        {excelDay.late_minutes > 0 && (
                                            <div className="text-amber-500 font-medium">Late: {fmtMinutes(excelDay.late_minutes)}</div>
                                        )}
                                        {excelDay.undertime_minutes > 0 && (
                                            <div className="text-orange-500 font-medium">UT: {fmtMinutes(excelDay.undertime_minutes)}</div>
                                        )}
                                        {excelDay.overtime_minutes > 0 && (
                                            <div className="text-green-500 font-medium">OT: {fmtMinutes(excelDay.overtime_minutes)}</div>
                                        )}
                                    </div>
                                )}

                                {attendance && !leaveDay && !holiday && (() => {
                                    const stats =
                                        attendance.sw && attendance.ew
                                            ? computeDayStats(attendance.sw, attendance.ew, attendance.shift_start, attendance.shift_end)
                                            : null;
                                    return (
                                        <div className="text-[11px] font-medium space-y-0.5">
                                            <div className="flex items-center gap-1">
                                                <span className="text-[9px] uppercase tracking-wide text-muted-foreground font-semibold shrink-0">
                                                    S
                                                </span>
                                                <span className="text-primary">
                                                    {to12hr(attendance.shift_start)}–{to12hr(attendance.shift_end)}
                                                </span>
                                            </div>
                                            {(attendance.sw || attendance.ew) && (
                                                <div className="flex items-center gap-1">
                                                    <span className="text-[9px] uppercase tracking-wide text-muted-foreground font-semibold shrink-0">
                                                        T
                                                    </span>
                                                    <span className="text-muted-foreground">
                                                        {attendance.sw ? to12hr(attendance.sw) : '?'}–{attendance.ew ? to12hr(attendance.ew) : '?'}
                                                    </span>
                                                </div>
                                            )}
                                            {stats && stats.late_minutes > 0 && (
                                                <div className="text-amber-500">Late: {fmtMinutes(stats.late_minutes)}</div>
                                            )}
                                            {stats && stats.undertime_minutes > 0 && (
                                                <div className="text-orange-500">UT: {fmtMinutes(stats.undertime_minutes)}</div>
                                            )}
                                            {stats && stats.overtime_minutes > 0 && (
                                                <div className="text-green-500">OT: {fmtMinutes(stats.overtime_minutes)}</div>
                                            )}
                                        </div>
                                    );
                                })()}
                            </div>
                        );
                    })}
                </div>
            </div>

            <div className="flex flex-wrap gap-x-4 gap-y-2 text-xs text-muted-foreground">
                <LegendSwatch className="bg-primary/10 border-primary/40" label="Present" />
                <LegendSwatch className="bg-muted/30 border-border" label="From attendance file" />
                <LegendSwatch className="bg-sky-500/10 border-sky-400/50" label="Leave (Unpaid)" />
                <LegendSwatch className="bg-violet-500/10 border-violet-400/50" label="Holiday" />
                <LegendSwatch className="bg-background border-border" label="No record" />
            </div>
        </div>
    );
}

function LegendSwatch({ className, label }: { className: string; label: string }) {
    return (
        <div className="flex items-center gap-1.5">
            <span className={`inline-block w-3 h-3 rounded border ${className}`} />
            {label}
        </div>
    );
}
