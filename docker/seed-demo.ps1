# Seeds the demo data of seeds/demo.json into all six databases, in the order they depend on (task 1289).
# Run from the repository root: .\docker\seed-demo.ps1   (add -Fresh to wipe every database first)
# Catalog must be running for Intelligence (it reads each movie's genres and people from it).
param([switch]$Fresh)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$command = if ($Fresh) { 'migrate:fresh' } else { 'migrate' }

foreach ($service in 'identity', 'catalog', 'engagement', 'social', 'notification', 'intelligence') {
    Write-Host "== $service" -ForegroundColor Cyan
    Push-Location (Join-Path $root "services/$service")
    try {
        php artisan $command --force
        if ($LASTEXITCODE -ne 0) { throw "$service`: $command failed." }
        php artisan db:seed --force
        if ($LASTEXITCODE -ne 0) { throw "$service`: seeding failed (see the message above)." }
    } finally {
        Pop-Location
    }
}

Write-Host 'Demo data ready: log in as lina@example.com (or omar, sara, karim, nour) with the password "password".' -ForegroundColor Green
