"""Build a clean downloadable source package. Standard Python only."""
from pathlib import Path
import zipfile
import hashlib
import json
import shutil

root = Path(__file__).resolve().parent.parent
out = root.parent / 'output' / 'open-analytics'
out.mkdir(parents=True, exist_ok=True)
names = ['README.md', 'LICENSE', 'AI-PROMPT.md', 'POST.md', 'EXTENSIONS.md', 'VERIFY.md', 'start.html', 'guide.css', 'guide.js', 'demo.html', 'demo-30.html', 'demo-90.html', 'package.json', '.gitignore']
folders = ['public', 'examples', 'tests', 'scripts']
files = [root / name for name in names]
for folder in folders:
    files += sorted(p for p in (root / folder).rglob('*') if p.is_file() and '__pycache__' not in p.parts)
archive = out / 'svoya-statistika-v1.0.0.zip'
with zipfile.ZipFile(archive, 'w', compression=zipfile.ZIP_DEFLATED) as z:
    for file in files:
        if not file.is_file():
            raise FileNotFoundError(file)
        z.write(file, 'svoya-statistika/' + file.relative_to(root).as_posix())
manifest = {file.relative_to(root).as_posix(): hashlib.sha256(file.read_bytes()).hexdigest() for file in files}
(out / 'manifest.json').write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + '\n')
(out / 'SHA256SUMS.txt').write_text(hashlib.sha256(archive.read_bytes()).hexdigest() + '  ' + archive.name + '\n')
# A local shareable preview directory. No PHP or credentials are executed here.
preview = out / 'preview'
preview.mkdir(exist_ok=True)
for name in ['start.html','demo.html','demo-30.html','demo-90.html','guide.css','guide.js','README.md','AI-PROMPT.md','EXTENSIONS.md','LICENSE']:
    shutil.copyfile(root/name, preview/name)
(preview/'public/site-stats').mkdir(parents=True,exist_ok=True)
shutil.copyfile(root/'public/site-stats/ui.css',preview/'public/site-stats/ui.css')
(preview/'examples').mkdir(exist_ok=True)
shutil.copyfile(root/'examples/consent.html',preview/'examples/consent.html')
print(f'Built {archive.name}: {len(files)} files, {archive.stat().st_size} bytes')
