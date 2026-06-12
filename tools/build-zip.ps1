<#
.SYNOPSIS
    Build a WordPress.org-ready distribution zip for this plugin.

.DESCRIPTION
    Stages only the files that ship to users (an explicit allowlist, so no dev
    file can leak), then packs them with forward-slash entry paths so the zip
    extracts correctly on the Linux machines WordPress.org and SVN run on.

    Do NOT use Compress-Archive / [IO.Compression.ZipFile]::CreateFromDirectory
    on Windows PowerShell 5.1 for this: they write backslash separators that
    break on Linux (entries become literal-backslash filenames, not folders).

.PARAMETER Version
    Override the version used in the zip filename. Defaults to the "Version:"
    header in the main plugin file.

.PARAMETER OutDir
    Where to write the zip. Defaults to <plugin-parent>\_build (outside the
    repo, so build artifacts are never committed).

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File tools\build-zip.ps1
#>
[CmdletBinding()]
param(
    [string]$Version,
    [string]$OutDir
)

$ErrorActionPreference = 'Stop'

# --- Locate paths -----------------------------------------------------------
$repo = Split-Path $PSScriptRoot -Parent
$slug = Split-Path $repo -Leaf
$mainFile = Join-Path $repo "$slug.php"

if (-not (Test-Path $mainFile)) {
    throw "Main plugin file not found: $mainFile"
}

if (-not $Version) {
    $headerLine = Select-String -Path $mainFile -Pattern '^\s*\*\s*Version:\s*(.+?)\s*$' | Select-Object -First 1
    if (-not $headerLine) { throw "Could not read 'Version:' from $mainFile" }
    $Version = $headerLine.Matches[0].Groups[1].Value.Trim()
}

if (-not $OutDir) {
    $OutDir = Join-Path (Split-Path $repo -Parent) '_build'
}

# --- Allowlist: exactly what ships to users ---------------------------------
# Globs auto-pick up new files in these dirs (e.g. a new includes/*.php class)
# while top-level dev files and dirs are never copied.
$include = @(
    "$slug.php",
    'readme.txt',
    'uninstall.php',
    'includes\*.php',
    'assets\admin\css\*.css',
    'assets\admin\js\*.js',
    'languages\*.pot'
)

# --- Stage ------------------------------------------------------------------
$stageParent = Join-Path $OutDir 'stage'
$stage = Join-Path $stageParent $slug
if (Test-Path $stageParent) { Remove-Item -Recurse -Force $stageParent }
New-Item -ItemType Directory -Force -Path $stage | Out-Null

$copied = 0
foreach ($pattern in $include) {
    $matches = Get-ChildItem -Path (Join-Path $repo $pattern) -File -ErrorAction SilentlyContinue
    foreach ($file in $matches) {
        $rel = $file.FullName.Substring($repo.Length + 1)
        $dest = Join-Path $stage $rel
        $destDir = Split-Path $dest -Parent
        if (-not (Test-Path $destDir)) { New-Item -ItemType Directory -Force -Path $destDir | Out-Null }
        Copy-Item $file.FullName $dest
        $copied++
    }
}

if ($copied -eq 0) { throw "Nothing was staged. Check the allowlist and repo path." }

# --- Lint staged PHP (best-effort; skipped if php not on PATH) ---------------
$php = Get-Command php -ErrorAction SilentlyContinue
if ($php) {
    foreach ($f in (Get-ChildItem -Recurse -File -Filter *.php $stage)) {
        $out = & php -l $f.FullName 2>&1
        if ($LASTEXITCODE -ne 0) { throw "PHP lint failed: $($f.FullName)`n$out" }
    }
    Write-Host "PHP lint: clean" -ForegroundColor Green
} else {
    Write-Host "PHP not on PATH - skipping lint" -ForegroundColor Yellow
}

# --- Pack with forward-slash entries ----------------------------------------
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

if (-not (Test-Path $OutDir)) { New-Item -ItemType Directory -Force -Path $OutDir | Out-Null }
$zip = Join-Path $OutDir "$slug-$Version.zip"
if (Test-Path $zip) { Remove-Item -Force $zip }

$backslash = [char]0x5C
$forward = [char]0x2F
$fs = [System.IO.File]::Open($zip, [System.IO.FileMode]::CreateNew)
$archive = New-Object System.IO.Compression.ZipArchive($fs, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    foreach ($file in (Get-ChildItem -Recurse -File $stage | Sort-Object FullName)) {
        $rel = $file.FullName.Substring($stageParent.Length + 1).Replace($backslash, $forward)
        $entry = $archive.CreateEntry($rel, [System.IO.Compression.CompressionLevel]::Optimal)
        $stream = $entry.Open()
        $bytes = [System.IO.File]::ReadAllBytes($file.FullName)
        $stream.Write($bytes, 0, $bytes.Length)
        $stream.Dispose()
    }
} finally {
    $archive.Dispose()
    $fs.Dispose()
}

# --- Verify -----------------------------------------------------------------
$reader = [System.IO.Compression.ZipFile]::OpenRead($zip)
$entries = $reader.Entries | ForEach-Object { $_.FullName }
$reader.Dispose()

$bad = $entries | Where-Object { $_ -match '\\' -or $_ -match '(^|/)\.(git|claude|distignore|wporg-assets)' -or $_ -match '(^|/)tools/' -or $_ -match 'SECURITY\.md' }
if ($bad) { throw "Dev/invalid entries leaked into the zip:`n$($bad -join "`n")" }

Write-Host ""
Write-Host "Built: $zip" -ForegroundColor Cyan
Write-Host ("Size:  {0:N0} bytes   Files: {1}   Version: {2}" -f (Get-Item $zip).Length, $entries.Count, $Version)
Write-Host "Entries:"
$entries | ForEach-Object { Write-Host "  $_" }
Write-Host ""
Write-Host "Test this zip on a clean install before uploading to WordPress.org." -ForegroundColor Green
