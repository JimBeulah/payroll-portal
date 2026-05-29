<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Payslips — {{ $run->period_start->format('M d') }}–{{ $run->period_end->format('M d, Y') }}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: Arial, sans-serif;
        font-size: 9px;
        background: #f5f5f5;
        color: #111;
    }

    /* --- Screen: print button bar --- */
    #print-bar {
        position: fixed;
        top: 0; left: 0; right: 0;
        background: #1e293b;
        color: #fff;
        padding: 10px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        z-index: 100;
        font-size: 13px;
    }
    #print-bar button {
        background: #3b82f6;
        color: #fff;
        border: none;
        padding: 8px 20px;
        border-radius: 4px;
        font-size: 13px;
        cursor: pointer;
    }
    #print-bar button:hover { background: #2563eb; }

    /* --- Screen: page wrapper --- */
    .page-wrapper {
        margin-top: 52px;
        padding: 10px;
    }

    /* --- A4 page simulation on screen --- */
    .a4-page {
        width: 210mm;
        min-height: 297mm;
        background: #fff;
        margin: 0 auto 16px auto;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        display: grid;
        grid-template-columns: 1fr 1fr;
        grid-template-rows: 1fr 1fr;
        gap: 0;
    }

    /* --- Each payslip cell --- */
    .payslip-cell {
        height: 148.5mm;
        padding: 6mm;
        border: 1px dashed #ccc;
        overflow: hidden;
        page-break-inside: avoid;
    }

    /* --- Print styles --- */
    @media print {
        @page { size: A4 portrait; margin: 0; }

        body { background: #fff; font-size: 9px; }

        #print-bar { display: none; }

        .page-wrapper { margin-top: 0; padding: 0; }

        .a4-page {
            width: 210mm;
            height: 297mm;
            margin: 0;
            box-shadow: none;
            page-break-after: always;
        }

        .a4-page:last-child { page-break-after: auto; }

        .payslip-cell {
            border: 0.5pt solid #999;
        }
    }
</style>
</head>
<body>

<div id="print-bar">
    <span>
        Payslips &mdash; {{ $run->period_start->format('M d') }}–{{ $run->period_end->format('M d, Y') }}
        ({{ $entries->count() }} {{ Str::plural('employee', $entries->count()) }})
    </span>
    <button onclick="window.print()">&#128438; Print All</button>
</div>

<div class="page-wrapper">
    @foreach ($entries->chunk(4) as $pageEntries)
        <div class="a4-page">
            @foreach ($pageEntries as $entry)
                <div class="payslip-cell">
                    @include('payslip._card', [
                        'entry'       => $entry,
                        'employee'    => $entry->employee,
                        'run'         => $run,
                        'companyName' => $companyName,
                        'logoSrc'     => $logoSrc,
                    ])
                </div>
            @endforeach
            {{-- Fill empty cells so grid stays 2×2 --}}
            @for ($i = $pageEntries->count(); $i < 4; $i++)
                <div class="payslip-cell"></div>
            @endfor
        </div>
    @endforeach
</div>

</body>
</html>
