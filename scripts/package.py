"""Build only distributable theme files into dist/ion-power.zip; never include Git metadata, scripts or credentials."""
import re
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile

root = Path(__file__).resolve().parents[1]
output = root / "dist" / "ion-power.zip"
output.parent.mkdir(exist_ok=True)
files = [root / name for name in ("style.css", "theme.json", "readme.txt", "screenshot.png")]
files += list(root.glob("*.php"))
for name in ("assets", "includes", "parts"):
    files.extend(path for path in (root / name).rglob("*") if path.is_file())
files = [f for f in files if f.exists()]
for file in files:
    relative = file.relative_to(root)
    if any(part.startswith(".") for part in relative.parts) or file.suffix.lower() in (".key", ".pem", ".pfx", ".p12", ".env"):
        raise ValueError("Unexpected sensitive file in theme distribution: " + str(relative))
    if file.suffix.lower() in (".php", ".json", ".css", ".js", ".html", ".txt") and re.search(r"(github_pat_[A-Za-z0-9_]{30,}|gh[pousr]_[A-Za-z0-9]{30,})", file.read_text(encoding="utf-8", errors="ignore")):
        raise ValueError("Token-like text found in " + str(relative))
with ZipFile(output, "w", ZIP_DEFLATED) as archive:
    for file in sorted(set(files)):
        archive.write(file, Path("ion-power") / file.relative_to(root))
print("Built:", output, output.stat().st_size // 1024, "KB")
