$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $PSScriptRoot
$distPath = Join-Path $repoRoot 'dist'
$backendPublicPath = Join-Path $repoRoot 'backend\public'

if (-not (Test-Path -LiteralPath $distPath)) {
    throw "Frontend build output not found at '$distPath'. Run 'npm run build' first."
}

if (-not (Test-Path -LiteralPath $backendPublicPath)) {
    throw "Laravel public directory not found at '$backendPublicPath'."
}

$frontendEntries = @(
    'assets',
    'icons',
    'favicon.ico',
    'index.html',
    'undraw_working-remotely_ivtz-1024x815.webp'
)

foreach ($entry in $frontendEntries) {
    $targetPath = Join-Path $backendPublicPath $entry
    if (Test-Path -LiteralPath $targetPath) {
        Remove-Item -LiteralPath $targetPath -Recurse -Force
    }
}

Get-ChildItem -LiteralPath $distPath -Force | ForEach-Object {
    $destination = Join-Path $backendPublicPath $_.Name
    Copy-Item -LiteralPath $_.FullName -Destination $destination -Recurse -Force
}

Write-Host "Frontend build staged into $backendPublicPath"
