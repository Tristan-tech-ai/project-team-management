# Runs both apps locally: Admin App on http://127.0.0.1:8101 and Team App on http://127.0.0.1:8102. Ctrl+C stops both.
Set-Location $PSScriptRoot
$team = Start-Process php -ArgumentList 'artisan', 'serve', '--host=127.0.0.1', '--port=8102' -WorkingDirectory (Join-Path $PSScriptRoot 'team-app') -PassThru -NoNewWindow
Write-Host 'Admin App: http://127.0.0.1:8101   Team App: http://127.0.0.1:8102   (Ctrl+C untuk berhenti)' -ForegroundColor Cyan
try {
    Push-Location (Join-Path $PSScriptRoot 'admin-app')
    php artisan serve --host=127.0.0.1 --port=8101
} finally {
    Pop-Location
    if (-not $team.HasExited) { Stop-Process -Id $team.Id }
}
