# One-time installation on Windows: MySQL in Docker, both apps, initial data, and demo accounts.
# Usage (PowerShell, from this folder):  .\setup.ps1          Without Docker (own MySQL):  .\setup.ps1 -NoDocker
param([switch]$NoDocker, [switch]$NoSample)
$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

function Step($text) { Write-Host "`n== $text" -ForegroundColor Cyan }
function Run($exe, [string[]]$arguments) {
    & $exe @arguments
    if ($LASTEXITCODE -ne 0) { throw "Perintah gagal: $exe $($arguments -join ' ')" }
}

foreach ($tool in 'php', 'composer', 'npm') {
    if (-not (Get-Command $tool -ErrorAction SilentlyContinue)) { throw "$tool belum terpasang. Lihat bagian Persyaratan di README.md." }
}

if (-not $NoDocker) {
    if (-not (Get-Command docker -ErrorAction SilentlyContinue)) { throw 'Docker belum terpasang. Pasang Docker Desktop, atau jalankan .\setup.ps1 -NoDocker dengan MySQL sendiri (isi DB_* di admin-app\.env).' }
    Step 'Menyalakan MySQL (Docker)'
    Run docker @('compose', 'up', '-d', '--wait')
}

foreach ($app in 'admin-app', 'team-app') {
    Step "Memasang $app"
    Push-Location $app
    try {
        Run composer @('install', '--no-interaction', '--no-progress')
        Run npm @('install', '--no-audit', '--no-fund')
        Run npm @('run', 'build')
        $setupArgs = @('artisan', 'ptm:setup')
        if ($NoSample -and $app -eq 'admin-app') { $setupArgs += '--no-sample' }
        Run php $setupArgs
    } finally { Pop-Location }
}

Step 'Selesai. Jalankan kedua app dengan: .\start.ps1'
