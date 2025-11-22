SEO Page Monitor — Packaging Instructions

What this does
- The scripts in this `build/` folder create a timestamped zip of the plugin suitable for manual installation via the WordPress admin (Plugins → Add New → Upload Plugin) or to distribute.

Files
- `package.ps1` — PowerShell script to create a timestamped zip (Windows PowerShell, recommended for Windows users).
- `package.bat` — Batch wrapper that calls PowerShell to create the zip (for convenience on Windows CMD).
- `build.sh` — POSIX shell script for macOS/Linux/WSL. Uses `zip` (install via your package manager if missing).

Usage
Windows (PowerShell):
```powershell
cd path\to\wp-content\plugins\seo-page-monitor\build
./package.ps1
```
Windows (CMD):
```cmd
cd path\to\wp-content\plugins\seo-page-monitor\build
package.bat
```
macOS / Linux / WSL:
```sh
cd path/to/wp-content/plugins/seo-page-monitor/build
chmod +x build.sh
./build.sh
```

Notes & recommendations
- The generated zip will be created alongside the plugin folder (one level up from `build/`) with a name like `seo-page-monitor-YYYYMMDDHHMM.zip`.
- Ensure you do not include development artifacts (node_modules, vendor, local build caches) in distributed packages unless intended. The `build.sh` excludes the `build/` folder by default. Adjust excludes as needed.
- Verify the produced zip installs correctly by uploading it to a test WordPress site.
- For CI/CD: add a pipeline step to run one of these scripts and attach the generated zip as an artifact.

Want me to:
- Add a `.gitattributes` to exclude vendor/node_modules from the zip, or
- Add an automated CI workflow (GitHub Actions) that builds and publishes the zip? Tell me which and I will add it.
