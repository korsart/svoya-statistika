// Development-only preview. Never deploy this Node server; production runs plain PHP.
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {runtime,configure,request} from '../tests/runtime.mjs';
const root=path.resolve(fileURLToPath(new URL('../',import.meta.url)));
const php=await runtime();configure(php);
const port=5189;
const config=php.readFileAsText('/app/site-stats-private/config.php').replace('https://example.test','http://127.0.0.1:'+port);
php.writeFile('/app/site-stats-private/config.php',config);
php.writeFile('/app/public_html/test.html',fs.readFileSync(path.join(root,'examples/site.html')));
const configForTest=config.replace("'p2'=>['path'=>'/contacts','label'=>'Контакты']", "'p2'=>['path'=>'/test.html','label'=>'Проверка']");php.writeFile('/app/site-stats-private/config.php',configForTest);
let queue=Promise.resolve();
const types={'.html':'text/html; charset=utf-8','.css':'text/css; charset=utf-8','.js':'application/javascript; charset=utf-8','.md':'text/plain; charset=utf-8','.json':'application/json; charset=utf-8','.png':'image/png','.zip':'application/zip'};
const server=http.createServer(async(req,res)=>{
  try {
    const url=new URL(req.url,'http://127.0.0.1:'+port);
    if(url.pathname.startsWith('/site-stats/') && url.pathname.endsWith('.php')) {
      const file=path.basename(url.pathname);
      if(!['index.php','collect.php','tracker.php','help.php','install.php'].includes(file)){res.writeHead(404);res.end();return;}
      const chunks=[];for await(const chunk of req){chunks.push(chunk);if(Buffer.concat(chunks).length>11000){res.writeHead(413);res.end();return;}}
      const task=async()=>{
        const options={method:req.method,relativeUri:url.pathname+url.search,headers:req.headers,body:new Uint8Array(Buffer.concat(chunks)),$_SERVER:{HTTP_HOST:'127.0.0.1:'+port,HTTPS:'on',...(file==='index.php'||file==='help.php'?{PHP_AUTH_USER:'admin',PHP_AUTH_PW:'only-test-password'}:{})}};
        const result=await request(php,file,options);
        res.writeHead(result.httpStatusCode,result.headers);res.end(Buffer.from(result.bytes));
      };
      queue=queue.then(task).catch(e=>{res.writeHead(500);res.end(String(e));});await queue;return;
    }
    const raw=decodeURIComponent(url.pathname==='/'?'/start.html':url.pathname);
    let target=path.resolve(root,'.'+raw);
    if(raw==='/test.html')target=path.join(root,'examples/site.html');
    if(raw==='/services')target=path.join(root,'examples/site.html');
    if(raw.startsWith('/site-stats/') && !raw.endsWith('.php')) target=path.join(root,'public',raw);
    if(!target.startsWith(root+path.sep)||!fs.existsSync(target)||!fs.statSync(target).isFile()){res.writeHead(404);res.end('Not found');return;}
    if(target.endsWith('.php')){res.writeHead(404);res.end();return;}
    res.writeHead(200,{'Content-Type':types[path.extname(target)]||'text/plain; charset=utf-8','Cache-Control':'no-store'});fs.createReadStream(target).pipe(res);
  }catch(e){res.writeHead(500);res.end(String(e));}
});
server.listen(port,'127.0.0.1',()=>console.log('PREVIEW http://127.0.0.1:'+port+'/start.html'));
