<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Payslip &mdash; {{ $employee->name }}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: Arial, sans-serif;
        font-size: 11px;
        background: #f5f5f5;
        color: #111;
    }

    #action-bar {
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
        gap: 12px;
    }
    #action-bar .buttons { display: flex; gap: 8px; }
    #action-bar button {
        background: #3b82f6;
        color: #fff;
        border: none;
        padding: 8px 16px;
        border-radius: 4px;
        font-size: 13px;
        cursor: pointer;
        white-space: nowrap;
    }
    #action-bar button:hover { background: #2563eb; }
    #action-bar button:disabled { opacity: 0.6; cursor: default; }

    .page-wrapper {
        margin-top: 52px;
        padding: 24px;
        display: flex;
        justify-content: center;
    }

    #payslip-card {
        background: #fff;
        padding: 14mm;
        width: 148mm;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }

    @media print {
        #action-bar { display: none; }
        .page-wrapper { margin-top: 0; padding: 0; }
        #payslip-card { box-shadow: none; }
    }
</style>
</head>
<body>

<div id="action-bar">
    <span>{{ strtoupper($employee->name) }}</span>
    <div class="buttons">
        <button id="btn-copy" onclick="copyImage()">&#128203; Copy Image</button>
        <button id="btn-download" onclick="downloadImage()">&#11015; Download PNG</button>
    </div>
</div>

<div class="page-wrapper">
    <div id="payslip-card">
        @include('payslip._card', [
            'entry'       => $entry,
            'employee'    => $employee,
            'run'         => $run,
            'companyName' => $companyName,
            'logoSrc'     => $logoSrc,
        ])
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
    const filename = '{{ str($employee->name)->slug() }}-payslip.png';

    async function captureCanvas() {
        return html2canvas(document.getElementById('payslip-card'), {
            scale: 2,
            useCORS: true,
            backgroundColor: '#ffffff',
        });
    }

    async function downloadImage() {
        const btn = document.getElementById('btn-download');
        btn.disabled = true;
        btn.textContent = 'Generating…';
        try {
            const canvas = await captureCanvas();
            const link = document.createElement('a');
            link.download = filename;
            link.href = canvas.toDataURL('image/png');
            link.click();
        } finally {
            btn.disabled = false;
            btn.innerHTML = '&#11015; Download PNG';
        }
    }

    async function copyImage() {
        const btn = document.getElementById('btn-copy');
        btn.disabled = true;
        btn.textContent = 'Copying…';
        try {
            const canvas = await captureCanvas();
            canvas.toBlob(async (blob) => {
                try {
                    await navigator.clipboard.write([
                        new ClipboardItem({ 'image/png': blob })
                    ]);
                    btn.textContent = '✓ Copied!';
                    setTimeout(() => {
                        btn.innerHTML = '&#128203; Copy Image';
                        btn.disabled = false;
                    }, 2000);
                } catch {
                    alert('Clipboard access denied. Use Download PNG instead.');
                    btn.innerHTML = '&#128203; Copy Image';
                    btn.disabled = false;
                }
            }, 'image/png');
        } catch {
            btn.innerHTML = '&#128203; Copy Image';
            btn.disabled = false;
        }
    }
</script>
</body>
</html>
