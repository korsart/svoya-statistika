import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import {fileURLToPath} from 'node:url';
import {createHash} from 'node:crypto';
import {execFileSync} from 'node:child_process';
const root=path.resolve(fileURLToPath(new URL('../',import.meta.url)));
const out=path.resolve(root,'../output/open-analytics');
const manifest=JSON.parse(fs.readFileSync(path.join(out,'manifest.json')));
for(const name of ['README.md','LICENSE','AI-PROMPT.md','POST.md','EXTENSIONS.md','VERIFY.md','start.html','guide.js','guide.css','demo.html','demo-30.html','demo-90.html','public/site-stats/index.php','public/site-stats/collect.php','public/site-stats/install.php','public/site-stats/tracker.php','public/site-stats/extensions.php','examples/consent.html','examples/integrations.example.php'])assert.ok(manifest[name],name);
assert.ok(fs.readFileSync(path.join(root,'LICENSE'),'utf8').includes('MIT License'));
for(const [name,hash] of Object.entries(manifest)) {
 assert.ok(!/site-stats-private|report-access|\.env|node_modules|GATES|DESIGN/.test(name));
 const file=fs.readFileSync(path.join(root,name));assert.equal(createHash('sha256').update(file).digest('hex'),hash,name);
 const text=file.toString('utf8');assert.ok(!/trike-dev\.ru|BEGIN (?:RSA |OPENSSH )?PRIVATE KEY|ghp_[A-Za-z0-9]{20}/.test(text),'private/production references in '+name);
}
// Traverse all local links in entry HTML and demos. PHP endpoints are intentionally absent from static demos.
for(const name of ['start.html','demo.html','demo-30.html','demo-90.html']) {
 const html=fs.readFileSync(path.join(root,name),'utf8');
 for(const match of html.matchAll(/<(?:a|link|script|iframe|img)\b[^>]*(?:href|src)="([^"]+)"/g)){
  const href=match[1].split('#')[0]; if(!href||/^(?:https?:|mailto:|tel:)/.test(href))continue;
  assert.ok(fs.existsSync(path.resolve(root,href)),name+': '+href);
 }
 if(name.startsWith('demo')){assert.ok(html.includes('Все цифры вымышлены'));assert.ok(!html.includes('tracker.php'));assert.ok(!html.includes('<script'));}
}
assert.ok(fs.readFileSync(path.join(root,'public/site-stats/install-key.php'),'utf8').includes("return 'REPLACE_"),'no working install secret in the archive');
const result=execFileSync('python3',['-c',`import zipfile,json,hashlib; from pathlib import Path; o=Path(${JSON.stringify(out)}); m=json.loads((o/'manifest.json').read_text()); z=zipfile.ZipFile(o/'svoya-statistika-v1.0.0.zip'); assert z.testzip() is None; assert len(z.namelist())==len(m); assert all(hashlib.sha256(z.read('svoya-statistika/'+n)).hexdigest()==h for n,h in m.items()); print('ZIP VERIFIED')`],{encoding:'utf8'});
assert.ok(result.includes('ZIP VERIFIED'));
console.log('PACKAGE VERIFIED');
