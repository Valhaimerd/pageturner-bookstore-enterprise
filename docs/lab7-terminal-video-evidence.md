# Lab 7 Terminal Video Evidence

Use one PowerShell window from the project root and keep `php artisan serve` running in another terminal.

```powershell
cd C:\Users\USEmbassy\Desktop\pageturner-bookstore
php artisan serve
```

In the recording terminal, run:

```powershell
cd C:\Users\USEmbassy\Desktop\pageturner-bookstore
powershell -ExecutionPolicy Bypass -File .\scripts\lab7-evidence.ps1
```

The default run shows:

- database book count
- mass seeder command help
- migration and PostgreSQL index proof
- catalog endpoint `200 OK`
- `benchmark:books --iterations=100`
- full-text search API result
- cache configuration and cached catalog keys
- guarded `BookCatalogLoadTest`

For a real small seeder success proof, use:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\lab7-evidence.ps1 -IncludeTinySeed
```

For the full Lab 7 seeder, only run this if you intend to insert the full dataset:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\lab7-evidence.ps1 -RunFullSeed
```

Useful options:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\lab7-evidence.ps1 -PauseBetweenSections
powershell -ExecutionPolicy Bypass -File .\scripts\lab7-evidence.ps1 -SkipBenchmark
powershell -ExecutionPolicy Bypass -File .\scripts\lab7-evidence.ps1 -SkipLoadTest
powershell -ExecutionPolicy Bypass -File .\scripts\lab7-evidence.ps1 -BaseUrl "http://127.0.0.1:8000"
```

The script prints section headers and captions so the video clearly identifies each Lab 7 proof item.
