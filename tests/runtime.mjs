import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
const prefix = process.env.STATS_TEST_RUNTIME;
const load = name => import(prefix ? pathToFileURL(path.join(prefix, 'node_modules', '@php-wasm', name, 'index.js')).href : '@php-wasm/' + name);
const [{PHP}, {loadNodeRuntime}] = await Promise.all([load('universal'),load('node')]);
export async function runtime(version='8.2') {
  const php = new PHP(await loadNodeRuntime(version, {emscriptenOptions:{processId:process.pid}}));
  php.mkdir('/app/public_html/site-stats');
  for (const f of fs.readdirSync(new URL('../public/site-stats/', import.meta.url))) php.writeFile('/app/public_html/site-stats/'+f,fs.readFileSync(new URL('../public/site-stats/'+f, import.meta.url)));
  return php;
}
export const server={HTTPS:'on',HTTP_HOST:'example.test',DOCUMENT_ROOT:'/app/public_html'};
export function request(php,file,options={}) {
  return php.run({scriptPath:'/app/public_html/site-stats/'+file,relativeUri:'/site-stats/'+file,method:'GET',...options,...(typeof options.body === 'string' ? {body:new TextEncoder().encode(options.body)} : {}),$_SERVER:{...server,SCRIPT_NAME:'/site-stats/'+file,...options.$_SERVER}});
}
export const configCode="<?php return ['title'=>'Пример сайта','origin'=>'https://example.test','timezone'=>'Europe/Moscow','password_hash'=>password_hash('only-test-password', PASSWORD_DEFAULT),'pages'=>['p0'=>['path'=>'/','label'=>'Главная'],'p1'=>['path'=>'/services','label'=>'Услуги'],'p2'=>['path'=>'/contacts','label'=>'Контакты']]];";
export function configure(php) {php.mkdir('/app/site-stats-private'); php.writeFile('/app/site-stats-private/config.php',configCode);}
export function dispose(php) { php.exit(); }
