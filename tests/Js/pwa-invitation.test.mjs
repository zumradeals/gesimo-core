// Le vrai script de page dans un navigateur simulé : événements, changements de page et stockage partagé.
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import { test } from 'node:test';
const source = readFileSync(new URL('../../resources/js/pwa.js', import.meta.url), 'utf8');
const stockage = () => { const m = new Map(); return { getItem: k => m.get(k) ?? null, setItem: (k, v) => m.set(k, v), removeItem: k => m.delete(k) }; };
async function page({ local = stockage(), session = stockage(), mobile = true, tactile = mobile, ios = false, chemin = '/gerant/profil', installee = false, maj = false } = {}) {
  const elements = new Map(), events = {}, timers = [], intervals = [], messages = [];
  const document = { readyState: 'complete', hidden: false, documentElement: { classList: { toggle() {} } },
    getElementById: id => elements.get(id), addEventListener() {}, body: { appendChild() {} },
    createElement() { return { innerHTML: '', className: '', setAttribute() {}, appendChild(el) { elements.set(el.id, el); }, remove() { elements.delete(this.id); }, children: {}, querySelector(s) { return this.children[s] ??= {}; } }; } };
  const reg = { waiting: maj ? { postMessage: m => messages.push(m) } : null, addEventListener() {}, update: async () => {} };
  const navigator = { userAgent: mobile ? (ios ? 'iPhone Safari' : 'Android Chrome') : 'Windows Chrome', maxTouchPoints: tactile ? 5 : 0, onLine: true,
    serviceWorker: { register: async () => reg, controller: {}, addEventListener() {} } };
  const window = { navigator, matchMedia: q => ({ matches: q.includes('standalone') ? installee : tactile }), addEventListener: (e, f) => { events[e] = f; } };
  const context = vm.createContext({ window, document, navigator, localStorage: local, sessionStorage: session, location: { protocol: 'https:', hostname: 'avelis.test', pathname: chemin },
    setTimeout: f => timers.push(f), setInterval: f => intervals.push(f), Date, console });
  vm.runInContext(source, context);
  await Promise.resolve();
  return { elements, events, timers, intervals, messages, reg, context, async proposer(outcome = 'dismissed', echec = false) { events.beforeinstallprompt({ preventDefault() {}, prompt: async () => { if (echec) throw Error('indisponible'); messages.push('installer'); }, userChoice: Promise.resolve({ outcome }) }); } };
}
function habitue() { const l = stockage(); l.setItem('gesimo:pwa', JSON.stringify({ visites: 2, pages: 3 })); return l; }
test('aucune invitation sur ordinateur, même tactile et avec fenêtre étroite', async () => {
  for (const tactile of [false, true]) { const p = await page({ local: habitue(), mobile: false, tactile }); await p.proposer(); p.timers.forEach(f => f()); assert(!p.elements.has('pwa-installer')); }
});
test('téléphone : Installer / Plus tard, une seule proposition pendant la session même sans clic', async () => {
  const local = habitue(), session = stockage(); const p = await page({ local, session }); await p.proposer();
  const el = p.elements.get('pwa-installer'); assert(el); assert.match(el.innerHTML, />Installer</); assert.match(el.innerHTML, />Plus tard</);
  const autre = await page({ local, session, chemin: '/gerant/residences' }); await autre.proposer(); assert(!autre.elements.has('pwa-installer'));
});
test('Plus tard reste mémorisé 30 jours, y compris dans une nouvelle session', async () => {
  const local = habitue(); const p = await page({ local }); await p.proposer(); p.elements.get('pwa-installer').querySelector('.js-plus-tard').onclick();
  assert(!p.elements.has('pwa-installer')); assert(JSON.parse(local.getItem('gesimo:pwa')).refuse_le);
  const suite = await page({ local }); await suite.proposer(); assert(!suite.elements.has('pwa-installer'));
});
test('acceptation, refus natif et échec du dialogue sont traités sans relance', async () => {
  for (const [outcome, echec] of [['accepted', false], ['dismissed', false], ['dismissed', true]]) {
    const local = habitue(), p = await page({ local }); await p.proposer(outcome, echec);
    await p.elements.get('pwa-installer').querySelector('.js-installer').onclick(); assert(!p.elements.has('pwa-installer'));
    const d = JSON.parse(local.getItem('gesimo:pwa')); assert(outcome === 'accepted' ? d.installee : d.refuse_le);
  }
});
test('sur iPhone Installer ouvre les instructions sans prétendre avoir installé', async () => {
  const local = habitue(), p = await page({ local, ios: true }); p.timers.forEach(f => f()); const el = p.elements.get('pwa-installer');
  assert(el); assert.match(el.innerHTML, />Installer</); await el.querySelector('.js-installer').onclick();
  assert.match(el.querySelector('#pwa-aide-ios').innerHTML, /Sur l'écran d'accueil/); assert(!JSON.parse(local.getItem('gesimo:pwa')).installee);
});
test('aucune proposition déjà installé, première visite ou pendant un paiement', async () => {
  for (const opts of [{ installee: true, local: habitue() }, {}, { chemin: '/payer/1', local: habitue() }]) { const p = await page(opts); await p.proposer(); p.timers.forEach(f => f()); assert(!p.elements.has('pwa-installer')); }
});
test('stockage invalide ou bloqué : pas de panne ni de sollicitations répétées', async () => {
  const local = stockage(); local.setItem('gesimo:pwa', 'null'); await page({ local });
  const bloque = { getItem() { throw Error('bloqué'); }, setItem() { throw Error('bloqué'); } };
  const p = await page({ local: habitue(), session: bloque }); await p.proposer(); assert(!p.elements.has('pwa-installer'));
});
test('aucune bannière de mise à jour sur téléphone, ordinateur ou page sensible, même avec un worker en attente', async () => {
  for (const mobile of [false, true]) {
    for (const chemin of ['/', '/gerant/profil', '/payer/1']) {
      const p = await page({ mobile, chemin, maj: true });
      p.intervals.forEach(f => f());
      assert(!p.elements.has('pwa-maj'));
      assert.equal(p.messages.length, 0, 'aucune activation forcée');
      assert.equal(p.intervals.length, 0, 'aucune minuterie de rappel');
      const suite = await page({ mobile, chemin, maj: true });
      assert(!suite.elements.has('pwa-maj'));
    }
  }
});
