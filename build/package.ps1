# Package the seo-page-monitor plugin into a timestamped zip file
# Usage: Open PowerShell in this folder and run: ./package.ps1

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Definition
$pluginDir = Resolve-Path (Join-Path $scriptDir "..")
$pluginPath = $pluginDir.Path
$zipName = "seo-page-monitor-" + (Get-Date -Format "yyyyMMddHHmm") + ".zip"
$parentPath = Split-Path -Parent $pluginPath
$zipPath = Join-Path $parentPath $zipName

Write-Host "Packaging plugin from: $pluginPath"

# Exclude non-install folders and files (keep vendor included)
$excludeDirs = @('build', 'node_modules', '.git', '.github', 'tests')
$excludeFiles = @(
  '.gitattributes',
  '.gitignore',
  '.phpunit.result.cache',
  'composer.json',
  'composer.lock',
  'package.json',
  'package-lock.json',
  'phpunit.xml',
  'webpack.config.js',
  'GOOGLE_SHEETS.md',
  'IMPROVEMENTS.md',
  'INSTALLATION.md',
  'SECURITY.md'
)

# Gather all files except excluded dirs/files
$allItems = Get-ChildItem -Path $pluginPath -Recurse -Force -File | Where-Object {
    $relative = $_.FullName.Substring($pluginPath.Length)
    # Trim any leading path separators using char casts for PS 5.1 compatibility
    $relative = $relative.TrimStart([char]92, [char]47)
    $segments = $relative -split "[/\\]"
    # Exclude if any path segment is in excludeDirs
    ($segments | Where-Object { $excludeDirs -contains $_ }).Count -eq 0 -and
    # Exclude if file name is in excludeFiles
    ($excludeFiles -notcontains $_.Name)
}

if ($allItems.Count -eq 0) {
    Write-Error "No files to package."
    exit 1
}

Compress-Archive -Path ($allItems | ForEach-Object { $_.FullName }) -DestinationPath $zipPath -Force
Write-Host "Created package: $zipPath"
