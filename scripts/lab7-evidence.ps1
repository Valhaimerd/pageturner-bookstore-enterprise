param(
    [string] $BaseUrl = "http://127.0.0.1:8000",
    [int] $BenchmarkIterations = 100,
    [switch] $IncludeTinySeed,
    [switch] $RunFullSeed,
    [int] $FullSeedCount = 1000000,
    [int] $FullSeedChunk = 1000,
    [switch] $SkipBenchmark,
    [switch] $SkipLoadTest,
    [switch] $PauseBetweenSections
)

$ErrorActionPreference = "Continue"

function Write-Section {
    param(
        [string] $Number,
        [string] $Title,
        [string] $Caption
    )

    Write-Host ""
    Write-Host "============================================================" -ForegroundColor DarkGray
    Write-Host "LAB 7 - $Number. $Title" -ForegroundColor Cyan
    Write-Host "Caption: $Caption" -ForegroundColor Gray
    Write-Host "============================================================" -ForegroundColor DarkGray
}

function Invoke-EvidenceCommand {
    param([string] $Command)

    Write-Host ""
    Write-Host "PS> $Command" -ForegroundColor Yellow
    Invoke-Expression $Command

    if ($LASTEXITCODE -ne $null -and $LASTEXITCODE -ne 0) {
        Write-Warning "Command exited with code $LASTEXITCODE. Continue recording; the output above is still useful evidence."
    }
}

function Wait-IfRequested {
    if ($PauseBetweenSections) {
        Read-Host "Press Enter to continue"
    }
}

if (-not (Test-Path ".\artisan")) {
    Write-Error "Run this script from the Laravel project root, where artisan exists."
    exit 1
}

Write-Host "Lab 7 evidence script" -ForegroundColor Green
Write-Host "Project: $(Get-Location)"
Write-Host "Base URL: $BaseUrl"
Write-Host "Keep php artisan serve running in another terminal for browser and curl proof."

Write-Section "1" "Database Book Count" "Shows that the system contains the Lab 7 book records."
Invoke-EvidenceCommand 'php artisan tinker --execute="echo DB::table(''books'')->count(), PHP_EOL;"'
Wait-IfRequested

Write-Section "2" "Seeder Execution Result" "Shows the MassBookSeeder command and optionally a successful proof batch."
Invoke-EvidenceCommand "php artisan books:seed-mass --help"

if ($IncludeTinySeed) {
    $tinySeedIsbnStart = 900000000 + (Get-Random -Minimum 0 -Maximum 999999)
    Invoke-EvidenceCommand "php artisan books:seed-mass --count=10 --chunk=10 --isbn-start=$tinySeedIsbnStart"
}

if ($RunFullSeed) {
    Invoke-EvidenceCommand "php artisan books:seed-mass --count=$FullSeedCount --chunk=$FullSeedChunk"
}

if (-not $IncludeTinySeed -and -not $RunFullSeed) {
    Write-Host ""
    Write-Host "Seeder note: add -IncludeTinySeed for a short successful run, or -RunFullSeed for the full Lab 7 seed." -ForegroundColor Gray
}
Wait-IfRequested

Write-Section "3" "Migration / Index Proof" "Shows migration index definitions and actual PostgreSQL books table indexes."
Invoke-EvidenceCommand 'Select-String -Path database\migrations\2026_05_07_000002_optimize_books_table_indexes.php -Pattern "idx_books_lab7|fullText|index"'
Invoke-EvidenceCommand 'php artisan tinker --execute="foreach (DB::select(''select indexname, indexdef from pg_indexes where tablename = ? order by indexname'', [''books'']) as $idx) { echo $idx->indexname, '' | '', $idx->indexdef, PHP_EOL; }"'
Wait-IfRequested

Write-Section "4" "Catalog Listing Performance" "Shows the catalog endpoint loading successfully with paginated book records."
Invoke-EvidenceCommand "curl.exe -I `"$BaseUrl/books`""
Write-Host ""
Write-Host "Browser proof URL: $BaseUrl/books" -ForegroundColor Green
Wait-IfRequested

if (-not $SkipBenchmark) {
    Write-Section "5" "Benchmark Command Result" "Shows catalog, ISBN, category, full-text, and export benchmark results."
    Invoke-EvidenceCommand "php artisan benchmark:books --iterations=$BenchmarkIterations"
    Wait-IfRequested
}

Write-Section "6" "Full-Text Search Result" "Shows book search working against the large catalog."
Invoke-EvidenceCommand "curl.exe `"$BaseUrl/api/v1/books?search=Laravel&perPage=5`""
Write-Host ""
Write-Host "Browser proof URL: $BaseUrl/books?search=Laravel" -ForegroundColor Green
Wait-IfRequested

Write-Section "7" "Redis / Cache Proof" "Shows current cache configuration and cached catalog keys."
Invoke-EvidenceCommand 'php artisan tinker --execute="echo ''default='', config(''cache.default''), PHP_EOL; echo ''catalog_store='', config(''cache.catalog_store''), PHP_EOL;"'
Invoke-EvidenceCommand "curl.exe `"$BaseUrl/api/v1/books?perPage=20`""
Invoke-EvidenceCommand 'php artisan tinker --execute="DB::table(''cache'')->where(''key'', ''like'', ''%books%'')->limit(10)->pluck(''key'')->each(fn ($key) => print($key.PHP_EOL));"'
Wait-IfRequested

if (-not $SkipLoadTest) {
    Write-Section "9" "Load / Performance Test Result" "Shows the guarded BookCatalogLoadTest running and passing."
    Write-Host ""
    Write-Host 'PS> $env:LAB7_PERFORMANCE_TESTS="true"; php artisan test --filter=BookCatalogLoadTest; Remove-Item Env:\LAB7_PERFORMANCE_TESTS' -ForegroundColor Yellow

    try {
        $env:LAB7_PERFORMANCE_TESTS = "true"
        php artisan test --filter=BookCatalogLoadTest

        if ($LASTEXITCODE -ne 0) {
            Write-Warning "Load/performance test exited with code $LASTEXITCODE."
        }
    }
    finally {
        Remove-Item Env:\LAB7_PERFORMANCE_TESTS -ErrorAction SilentlyContinue
    }
}

Write-Host ""
Write-Host "Lab 7 evidence run complete." -ForegroundColor Green
