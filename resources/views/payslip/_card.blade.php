{{-- Variables expected: $entry (PayrollEntry), $employee (Employee), $run (PayrollRun) --}}
<table style="width:100%;border-collapse:collapse;">
    <tr>
        <td style="width:64px;vertical-align:middle;padding-right:6px;">
            <img src="{{ public_path('payroll-logo.png') }}" alt="Logo" style="width:60px;height:auto;">
        </td>
        <td style="text-align:center;vertical-align:middle;">
            <div style="font-weight:bold;font-size:1.1em;">Beulah Information Technology Services and Business Solutions Inc.</div>
            <div style="font-weight:bold;">
                Payslip for the month of
                {{ $run->period_start->format('F') }}
                {{ $run->period_start->format('d') }}-{{ $run->period_end->format('d') }},
                {{ $run->period_start->format('Y') }}
            </div>
        </td>
    </tr>
</table>

<table style="width:100%;border-collapse:collapse;margin-top:5px;">
    <tr>
        <td style="width:55%;padding:1px 0;"><strong>Employee Name:</strong> {{ strtoupper($employee->name) }}</td>
        <td style="padding:1px 0;"><strong>Paid Days:</strong> {{ $run->period_start->format('M d') }} - {{ $run->period_end->format('M d') }}</td>
    </tr>
    <tr>
        <td style="padding:1px 0;"><strong>ID Number:</strong> {{ $employee->employee_number ?? '' }}</td>
        <td style="padding:1px 0;"><strong>Days Present:</strong> {{ $entry->days_present }}</td>
    </tr>
    <tr>
        <td style="padding:1px 0;"><strong>Gender:</strong> {{ $employee->gender ?? '' }}</td>
        <td style="padding:1px 0;"><strong>Rate:</strong> {{ number_format($employee->daily_rate, 2) }}</td>
    </tr>
</table>

<table style="width:100%;border-collapse:collapse;margin-top:6px;">
    <thead>
        <tr style="background:#eeeeee;">
            <th style="border:1px solid #333;padding:3px 5px;text-align:left;">Earnings</th>
            <th style="border:1px solid #333;padding:3px 5px;text-align:right;">Amount</th>
            <th style="border:1px solid #333;padding:3px 5px;text-align:left;">Deductions</th>
            <th style="border:1px solid #333;padding:3px 5px;text-align:right;">Amount</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td style="border:1px solid #333;padding:3px 5px;">Basic Pay:</td>
            <td style="border:1px solid #333;padding:3px 5px;text-align:right;">{{ number_format($entry->total_basic_pay, 2) }}</td>
            <td style="border:1px solid #333;padding:3px 5px;">Cash Advance</td>
            <td style="border:1px solid #333;padding:3px 5px;text-align:right;">{{ $entry->cash_advance > 0 ? number_format($entry->cash_advance, 2) : '' }}</td>
        </tr>
        <tr>
            <td style="border:1px solid #333;padding:3px 5px;">Overtime</td>
            <td style="border:1px solid #333;padding:3px 5px;text-align:right;">{{ $entry->overtime_pay > 0 ? number_format($entry->overtime_pay, 2) : '' }}</td>
            <td style="border:1px solid #333;padding:3px 5px;">Late</td>
            <td style="border:1px solid #333;padding:3px 5px;text-align:right;">{{ $entry->late_deduction > 0 ? number_format($entry->late_deduction, 2) : '' }}</td>
        </tr>
        <tr>
            <td style="border:1px solid #333;padding:3px 5px;">Holiday Adjustment</td>
            <td style="border:1px solid #333;padding:3px 5px;text-align:right;">{{ $entry->holiday_pay > 0 ? number_format($entry->holiday_pay, 2) : '' }}</td>
            <td style="border:1px solid #333;padding:3px 5px;">Undertime</td>
            <td style="border:1px solid #333;padding:3px 5px;text-align:right;">{{ $entry->undertime_deduction > 0 ? number_format($entry->undertime_deduction, 2) : '' }}</td>
        </tr>
        <tr>
            <td style="border:1px solid #333;padding:3px 5px;"></td>
            <td style="border:1px solid #333;padding:3px 5px;"></td>
            <td style="border:1px solid #333;padding:3px 5px;">Others</td>
            <td style="border:1px solid #333;padding:3px 5px;text-align:right;">{{ $entry->other_deductions > 0 ? number_format($entry->other_deductions, 2) : '' }}</td>
        </tr>
        <tr>
            <td style="border:1px solid #333;padding:3px 5px;font-weight:bold;">Gross Salary:</td>
            <td style="border:1px solid #333;padding:3px 5px;text-align:right;font-weight:bold;">{{ number_format($entry->gross_pay, 2) }}</td>
            <td style="border:1px solid #333;padding:3px 5px;font-weight:bold;">Total Deductions</td>
            <td style="border:1px solid #333;padding:3px 5px;text-align:right;font-weight:bold;">{{ number_format($entry->total_deductions, 2) }}</td>
        </tr>
        <tr>
            <td colspan="2" style="border:1px solid #333;padding:3px 5px;font-weight:bold;">
                Net Pay:
                <span style="float:right;">&#8369;{{ number_format($entry->net_pay, 2) }}</span>
            </td>
            <td colspan="2" style="border:1px solid #333;padding:3px 5px;"></td>
        </tr>
    </tbody>
</table>

<p style="margin-top:6px;font-size:0.85em;">
    Note: Full details of your pay for the covered period are given above. Please check carefully and notify HR of any discrepancies.
</p>

<table style="width:100%;margin-top:24px;">
    <tr>
        <td style="width:42%;text-align:center;">
            Prepared by:
            <div style="border-top:1px solid #333;margin-top:22px;padding-top:2px;">Human Resource</div>
        </td>
        <td style="width:16%;"></td>
        <td style="width:42%;text-align:center;">
            Approved by:
            <div style="border-top:1px solid #333;margin-top:22px;padding-top:2px;">Manager</div>
        </td>
    </tr>
</table>
