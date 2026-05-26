<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: Arial, sans-serif; font-size: 11px; margin: 20px; }
    .header-table { width: 100%; border-collapse: collapse; }
    .logo { font-weight: bold; font-size: 18px; }
    .company { text-align: center; font-weight: bold; font-size: 12px; }
    table.data { width: 100%; border-collapse: collapse; margin-top: 10px; }
    table.data td, table.data th { border: 1px solid #333; padding: 4px 8px; }
    table.data th { background: #eee; }
    .right { text-align: right; }
    .bold { font-weight: bold; }
</style>
</head>
<body>
<table class="header-table">
    <tr>
        <td style="width:80px" class="logo">BHAGOH</td>
        <td class="company">
            Beulah Information Technology Services and Business Solutions Inc.<br>
            <strong>Payslip for the month of {{ $run->period_start->format('F') }}
            {{ $run->period_start->format('d') }}-{{ $run->period_end->format('d') }},{{ $run->period_start->format('Y') }}</strong>
        </td>
    </tr>
</table>

<table class="header-table" style="margin-top:8px">
    <tr>
        <td><strong>{{ strtoupper($employee->name) }}</strong></td>
        <td>Paid Days: {{ $run->period_start->format('M d') }} - {{ $run->period_end->format('M d') }}</td>
    </tr>
    <tr>
        <td>Department: {{ $employee->department }}</td>
        <td>Days Present: {{ $entry->days_present }}</td>
    </tr>
    <tr>
        <td></td>
        <td>Daily Rate: {{ number_format($employee->daily_rate, 2) }}</td>
    </tr>
</table>

<table class="data" style="margin-top:10px">
    <tr>
        <th>Earnings</th><th>Amount</th><th>Deductions</th><th>Amount</th>
    </tr>
    <tr>
        <td>Basic Pay:</td>
        <td class="right">{{ number_format($entry->total_basic_pay, 2) }}</td>
        <td>Cash Advance</td>
        <td class="right">{{ $entry->cash_advance > 0 ? number_format($entry->cash_advance, 2) : '' }}</td>
    </tr>
    <tr>
        <td>Overtime</td>
        <td class="right">{{ $entry->overtime_pay > 0 ? number_format($entry->overtime_pay, 2) : '' }}</td>
        <td>Late</td>
        <td class="right">{{ $entry->late_deduction > 0 ? number_format($entry->late_deduction, 2) : '' }}</td>
    </tr>
    <tr>
        <td>Holiday Adjustment</td>
        <td class="right">{{ $entry->holiday_pay > 0 ? number_format($entry->holiday_pay, 2) : '' }}</td>
        <td>Undertime</td>
        <td class="right">{{ $entry->undertime_deduction > 0 ? number_format($entry->undertime_deduction, 2) : '' }}</td>
    </tr>
    <tr>
        <td></td><td></td>
        <td>Others</td>
        <td class="right">{{ $entry->other_deductions > 0 ? number_format($entry->other_deductions, 2) : '' }}</td>
    </tr>
    <tr>
        <td class="bold">Gross Salary:</td>
        <td class="right bold">{{ number_format($entry->gross_pay, 2) }}</td>
        <td class="bold">Total Deductions</td>
        <td class="right bold">{{ number_format($entry->total_deductions, 2) }}</td>
    </tr>
    <tr>
        <td colspan="2" class="bold">Net Pay: <span style="float:right">&#8369;{{ number_format($entry->net_pay, 2) }}</span></td>
        <td colspan="2"></td>
    </tr>
</table>

<p style="margin-top:10px;font-size:10px">
    Note: Full details of your pay for the covered period are given above. Please check carefully and notify HR of any discrepancies.
</p>

<table class="header-table" style="margin-top:50px">
    <tr>
        <td style="text-align:center;border-top:1px solid #333;width:200px">Human Resource</td>
        <td></td>
        <td style="text-align:center;border-top:1px solid #333;width:200px">Manager</td>
    </tr>
</table>
<p style="margin-top:30px">Approved by: ____________________</p>
</body>
</html>
