<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; }
    body {
        font-family: Arial, sans-serif;
        font-size: 11px;
        margin: 0;
        padding: 15mm;
        color: #111;
    }
</style>
</head>
<body>
    @include('payslip._card', [
        'entry'    => $entry,
        'employee' => $employee,
        'run'      => $run,
    ])
</body>
</html>
