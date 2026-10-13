import fs from 'node:fs';
import {runtime,request,configure,dispose} from '../tests/runtime.mjs';
const php=await runtime(); configure(php);
const levels=[42,65,53,78,105,94,86];
const data={days:{},sessions:{},seen:{}};
// Fixed date, deterministic demonstration, deliberately disconnected from real site data.
const end=new Date('2026-10-13T12:00:00Z');
for (let i=89;i>=0;i--) {
  const day=new Date(end);day.setUTCDate(day.getUTCDate()-i);const key=day.toISOString().slice(0,10);
  const views=levels[(89-i)%7];const sessions=Math.round(views*.69);const lead=(i%3)+1;
  const telegram=Math.round(sessions*.45), search=Math.round(sessions*.35), direct=sessions-telegram-search;
  const home=Math.round(views*.57),services=Math.round(views*.31),contacts=views-home-services;
  const mobile=Math.round(sessions*.7),desktop=sessions-mobile;
  data.days[key]={totals:{views,sessions,lead,telegram:Math.round(views*.04),phone:2,cta:3,email:1,whatsapp:1},sources:{telegram:{sessions:telegram,views:Math.round(views*.45),lead},search:{sessions:search,views:Math.round(views*.35),lead:0},direct:{sessions:direct,views:views-Math.round(views*.45)-Math.round(views*.35),lead:0}},pages:{p0:home,p1:services,p2:contacts},devices:{mobile,desktop}};
}
php.writeFile('/app/site-stats-private/counts.json',JSON.stringify(data));
// Render real template with a fixed display clock. No separate fake UI implementation.
const source=php.readFileAsText('/app/public_html/site-stats/index.php').replace("$today = new DateTimeImmutable('today', $tz)","$today = new DateTimeImmutable('2026-10-13', $tz)").replace('prune($data, $tz); write_store($data);','/* Fixed demo, no pruning */');
php.writeFile('/app/public_html/site-stats/index.php',source.replace("require __DIR__ . '/view.php';", "$demo=true; $cssUrl='public/site-stats/ui.css'; $homeUrl='start.html'; $helpUrl='start.html#install'; require __DIR__ . '/view.php';"));
for (const period of [7,30,90]) {
  const response=await request(php,'index.php',{relativeUri:'/site-stats/index.php?days='+period,$_SERVER:{PHP_AUTH_USER:'admin',PHP_AUTH_PW:'only-test-password'}});
  if(response.httpStatusCode!==200)throw new Error(response.errors+response.text);
  const html=response.text.replace(/href="\?days=(7|30|90)"/g,(_,n)=>'href="'+(n==='7'?'demo.html':'demo-'+n+'.html')+'"');
  fs.writeFileSync(new URL('../'+(period===7?'demo.html':'demo-'+period+'.html'),import.meta.url),html);
}
dispose(php);console.log('DEMO GENERATED');
