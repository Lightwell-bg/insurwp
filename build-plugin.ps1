<#
.SYNOPSIS
    Собирает ZIP плагина InsurWP для установки в WordPress.

.DESCRIPTION
    Копирует только рантайм-файлы плагина (PHP, ассеты, тарифы по умолчанию,
    readme.txt) во временную папку insurwp/ и упаковывает её в ZIP — архив
    распаковывается прямо в wp-content/plugins/insurwp/.

    Каталог miniapp/ (Telegram Mini App) в архив не входит: это отдельное
    приложение со своей выкаткой на miniapp.bginfo.eu, см. DEPLOY.md.
    Не входят также tests/, composer.json, phpunit.xml — файлы разработки.

.OUTPUTS
    build/insurwp-<версия>.zip
#>

$ErrorActionPreference = 'Stop'

$root       = $PSScriptRoot
$pluginFile = Join-Path $root 'insurwp.php'

$match = Select-String -Path $pluginFile -Pattern 'Version:\s*([\d.]+)' | Select-Object -First 1
if (-not $match) {
    throw "Не удалось прочитать версию из insurwp.php"
}
$version = $match.Matches[0].Groups[1].Value

$buildDir = Join-Path $root 'build'
$stageDir = Join-Path $buildDir 'insurwp'
$zipPath  = Join-Path $buildDir "insurwp-$version.zip"

if (Test-Path $buildDir) {
    Remove-Item $buildDir -Recurse -Force
}
New-Item -ItemType Directory -Path $stageDir | Out-Null

# Только то, что должно попасть на сервер WordPress.
$include = @('insurwp.php', 'readme.txt', 'includes', 'assets', 'data')

foreach ($item in $include) {
    $source = Join-Path $root $item
    if (-not (Test-Path $source)) {
        throw "Не найден файл или папка: $item"
    }
    Copy-Item -Path $source -Destination $stageDir -Recurse
}

Compress-Archive -Path $stageDir -DestinationPath $zipPath -Force
Remove-Item $stageDir -Recurse -Force

Write-Host "Готово: $zipPath"
