import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import {randomUUID} from 'node:crypto';
const code=fs.readFileSync(new URL('../public/site-stats/tracker.js',import.meta.url),'utf8');
class Element { constructor(href='',cta=false,revoke=false) {this.href=href;this.cta=cta;this.revoke=revoke;} closest(selector) {return selector==='a[href]' && this.href || selector==='[data-stats-event="cta"]' && this.cta || selector==='[data-stats-revoke]' && this.revoke ? this : null;}getAttribute(){return this.href;} }
function harness({accepted=false,url='https://example.test/?utm_source=telegram&email=private@example.com',origin='https://example.test',referrer='',failFetch=false}={}) {
  const local=new Map(accepted?[['site-stats:consent:v1','accepted']]:[]), tab=new Map(), handlers={},sent=[], timers=new Map();let now=Date.now(),timerId=0;
  const storage=map=>({getItem:key=>map.get(key)||null,setItem:(k,v)=>map.set(k,v),removeItem:k=>map.delete(k)});
  const parsed=new URL(url);
  const location={origin:parsed.origin,hostname:parsed.hostname,pathname:parsed.pathname,search:parsed.search,href:url};
  const window={__siteStatsConfig:{origin,pages:{p0:'/',p1:'/services',p2:'/contacts'}},addEventListener:(name,fn)=>{handlers[name]=fn;}};
  class Clock extends Date {static now(){return now;}}
  const context=vm.createContext({window,document:{currentScript:{src:'https://example.test/site-stats/tracker.php'},referrer,addEventListener:(name,fn)=>{handlers[name]=fn;}},location,navigator:{userAgent:'Mozilla/5.0 iPhone Mobile',maxTouchPoints:1},history:{pushState(_a,_b,path){location.pathname=path;},replaceState(_a,_b,path){location.pathname=path;}},Date:Clock,crypto:{randomUUID},Element,URL,URLSearchParams,localStorage:storage(local),sessionStorage:storage(tab),setTimeout:(fn)=>{timers.set(++timerId,fn);return timerId;},clearTimeout:id=>timers.delete(id),fetch:async(endpoint,opts)=>{sent.push({endpoint,body:JSON.parse(opts.body),options:opts});if(failFetch)throw new Error('offline');return {status:204};}});
  vm.runInContext(code,context);
  return {window,local,tab,sent,handlers,timers,context,location,advance:ms=>{now+=ms;}};
}
const h=harness();assert.equal(h.sent.length,0);assert.equal(h.tab.size,0);
h.window.SiteStats.consent(true);assert.equal(h.sent.length,1);assert.equal(h.sent[0].body.event,'pageview');assert.equal(h.sent[0].body.source,'telegram');assert.equal(h.sent[0].body.page,'p0');assert.equal(h.sent[0].body.device,'mobile');assert.ok(!JSON.stringify(h.sent).includes('private@example.com'));
assert.deepEqual(Object.keys(h.sent[0].body).sort(),['device','event','id','page','session','source']);
assert.equal(h.sent[0].options.credentials,'omit');
h.window.SiteStats.consent(true);assert.equal(h.sent.length,1,'consent sync does not duplicate pageview');
h.handlers.click({target:new Element('tel:+79991234567')});assert.equal(h.sent.at(-1).body.event,'phone');assert.ok(!JSON.stringify(h.sent).includes('79991234567'));
h.handlers.click({target:new Element('https://t.me/example')});assert.equal(h.sent.at(-1).body.event,'telegram');
h.handlers.click({target:new Element('https://evil.test/t.me/')});assert.equal(h.sent.at(-1).body.event,'telegram','lookalike external link produces no new event');assert.equal(h.sent.length,3);
h.handlers.click({target:new Element('',true)});assert.equal(h.sent.at(-1).body.event,'cta');
assert.equal(h.window.SiteStats.track('lead'),true);assert.equal(h.sent.at(-1).body.event,'lead');assert.equal(h.window.SiteStats.track({email:'private@example.com'}),false);
const firstId=h.sent[0].body.session;
h.context.history.pushState({},'', '/services');assert.equal(h.sent.at(-1).body.page,'p1');assert.equal(h.sent.at(-1).body.session,firstId);
const before=h.sent.length;h.context.history.replaceState({},'', '/services');assert.equal(h.sent.length,before);
h.context.history.pushState({},'', '/customer/private@example.com');assert.equal(h.sent.length,before);assert.equal(h.window.SiteStats.track('lead'),false);assert.ok(!JSON.stringify(h.sent).includes('customer'));
h.context.history.pushState({},'', '/contacts');h.advance(1800001);h.window.SiteStats.track('cta');assert.notEqual(h.sent.at(-1).body.session,firstId);
h.window.SiteStats.consent(false);const stop=h.sent.length;assert.equal(h.tab.size,0);h.handlers.click({target:new Element('tel:+70000000000')});h.context.history.pushState({},'', '/');assert.equal(h.sent.length,stop);
h.local.set('site-stats:consent:v1','accepted');h.handlers.storage({key:'site-stats:consent:v1'});assert.equal(h.sent.length,stop+1);
h.local.set('site-stats:consent:v1','rejected');h.handlers.storage({key:'site-stats:consent:v1'});assert.equal(h.window.SiteStats.isEnabled(),false);
for (const [url,referrer,expected] of [['https://example.test/?utm_source=unknown-secret','','other_campaign'],['https://example.test/','https://google.com/search?q=private','search'],['https://example.test/','https://notyoutube.com/path','referral'],['https://example.test/','','direct']]) {const x=harness({accepted:true,url,referrer});assert.equal(x.sent[0].body.source,expected);assert.ok(!JSON.stringify(x.sent).includes('unknown-secret'));assert.ok(!JSON.stringify(x.sent).includes('search?q'));}
assert.equal(harness({accepted:true,origin:'https://another.test'}).window.SiteStats,undefined);
const retry=harness({accepted:true,failFetch:true});await new Promise(resolve=>setImmediate(resolve));assert.equal(retry.timers.size,1);const deferred=[...retry.timers.values()][0];retry.window.SiteStats.consent(false);assert.equal(retry.timers.size,0);deferred();assert.equal(retry.sent.length,1,'revoke stops even an already queued retry');
vm.runInContext(code,h.context);assert.equal(h.sent.length,stop+1,'duplicate script installs no second handler');
console.log('TRACKER VERIFIED');
