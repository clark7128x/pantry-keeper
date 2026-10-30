param([string]$Php = (Join-Path $PSScriptRoot '../.tools/php/php.exe'))
$ErrorActionPreference = 'Stop'
$worker = Join-Path $PSScriptRoot 'remind.php'
if (-not (Test-Path -LiteralPath (Join-Path $PSScriptRoot '../config.local.php'))) {
    throw 'Configure Web Push with bin/setup-push.php before starting the worker.'
}
Write-Output 'Reminder worker runs every minute. Press Ctrl+C to stop.'
while ($true) {
    & $Php $worker
    if ($LASTEXITCODE -ne 0) { Write-Warning 'Reminder run failed; inspect the message above. It will retry in one minute.' }
    Start-Sleep -Seconds 60
}
