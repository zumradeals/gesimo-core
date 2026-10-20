// Essais du service worker dans un faux navigateur (Node) : mémoire d'application, réseau coupé, déconnexion.
// Lancé par Pest (tests/Feature/Lot6/ServiceWorkerTest.php) ou à la main : node tests/Js/sw.test.mjs
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const source = readFileSync(new URL('../../resources/pwa/sw.js', import.meta.url), 'utf8').replaceAll('__VERSION__', 'test-v1');
const ORIGINE = 'https://gesimo.test';

class FauxReponse extends Response {
  constructor(corps, options = {}, extra = {}) { super(corps, options); this.extra = extra; }
  get redirected() { return this.extra.redirected ?? super.redirected; }
  get type() { return this.extra.type ?? super.type; }
  get url() { return this.extra.url ?? super.url; }
}
class FauxCache {
  constructor() { this.m = new Map(); }
  async match(r, options) { if (options?.ignoreVary !== true) return undefined; /* comme un vrai navigateur face à « Vary » : sans ignoreVary, pas de résultat */ const k = typeof r === 'string' ? new URL(r, ORIGINE).toString() : r.url; const v = this.m.get(k); return v ? v.clone() : undefined; }
  async put(r, rep) { this.m.set(typeof r === 'string' ? new URL(r, ORIGINE).toString() : r.url, rep); }
  async delete(r) { return this.m.delete(typeof r === 'string' ? new URL(r, ORIGINE).toString() : r.url); }
  async keys() { return [...this.m.keys()].map((u) => ({ url: u })); }
  async addAll(urls) { for (const u of urls) { const r = await net(new Request(new URL(u, ORIGINE))); await this.put(new URL(u, ORIGINE).toString(), r); } }
}
const stock = new Map();
const caches = {
  async open(n) { if (!stock.has(n)) stock.set(n, new FauxCache()); return stock.get(n); },
  async keys() { return [...stock.keys()]; },
  async delete(n) { return stock.delete(n); },
};

let reseau = 'ok';           // ok | coupe | lent
let reponses = {};           // chemin -> () => Response
let appels = [];
async function net(requete) {
  appels.push(`${requete.method} ${new URL(requete.url).pathname}`);
  if (reseau === 'coupe') throw new TypeError('Failed to fetch');
  if (reseau === 'lent') return new Promise(() => {});
  const f = reponses[new URL(requete.url).pathname];
  return f ? f() : new Response('ok', { status: 200 });
}
const ecouteurs = {};
let skipWaitingAppele = false;
const contexte = {
  self: { location: { origin: ORIGINE }, addEventListener: (t, f) => { ecouteurs[t] = f; }, skipWaiting: () => { skipWaitingAppele = true; }, clients: { claim: async () => {} } },
  caches, fetch: (r) => net(r instanceof Request ? r : new Request(r)), Response, Request, Headers, URL,
  setTimeout: (f, d) => setTimeout(f, d >= 3000 ? 0 : d), console, module: undefined, Date,
};
contexte.self.self = contexte.self;
vm.createContext(contexte);
vm.runInContext(source, contexte);

async function evenement(type, requete, extra = {}) {
  let reponse; const attentes = [];
  const e = { request: requete, waitUntil: (p) => attentes.push(p), respondWith: (p) => { reponse = p; }, data: extra.data, source: { postMessage: (m) => (extra.messages ??= []).push(m) } };
  ecouteurs[type](e);
  const r = reponse === undefined ? undefined : await reponse;
  await Promise.all(attentes);
  return { r, extra };
}
const nav = (chemin, methode = 'GET') => { const r = new Request(ORIGINE + chemin, { method: methode }); Object.defineProperty(r, 'mode', { value: 'navigate' }); return r; };
const page = (marque, corps = 'page', extra = {}) => () => new FauxReponse(corps, { status: 200, headers: { 'Content-Type': 'text/html', ...(marque ? { 'X-Gesimo-Cache': marque } : {}), ...(extra.entetes || {}) } }, extra);
const contenu = async (nom) => { const c = stock.get(nom); return c ? [...c.m.keys()].map((u) => new URL(u).pathname) : []; };
const nomCache = (prefixe) => [...stock.keys()].find((n) => n.startsWith(prefixe));
let n = 0; const ok = (nom) => console.log(`  ✓ ${nom}`);

// --- installation et activation ------------------------------------------------------------
reponses['/hors-connexion'] = page(null, 'HORS CONNEXION');
reponses['/icons/icone-192.png'] = () => new Response('png', { status: 200 });
await evenement('install', undefined);
assert.ok((await contenu('statique-test-v1')).includes('/hors-connexion')); ok('la page « hors connexion » est enregistrée à l\'installation');
assert.equal(skipWaitingAppele, false); ok('la mise à jour n\'est pas imposée (pas de skipWaiting automatique)');
await caches.open('statique-ancienne'); await caches.open('prive-ancienne');
await evenement('activate', undefined);
assert.ok(!(await caches.keys()).some((k) => k.includes('ancienne'))); ok('les anciennes versions du cache sont supprimées (dont les copies privées)');

// --- pages publiques (visiteur) ---------------------------------------------------------------
reponses['/faq'] = page('public', 'FAQ publique');
await evenement('fetch', nav('/faq'));
assert.deepEqual(await contenu('public-test-v1'), ['/faq']); ok('une page publique consultée est enregistrée');
reseau = 'coupe';
let { r } = await evenement('fetch', nav('/faq'));
assert.equal(await r.text(), 'FAQ publique'); ok('réseau coupé : la page publique déjà consultée reste lisible');
({ r } = await evenement('fetch', nav('/a-propos')));
assert.equal(await r.text(), 'HORS CONNEXION'); ok('réseau coupé : une page jamais consultée affiche la page « hors connexion »');
reseau = 'ok';

// --- « Mes réservations » (client) -------------------------------------------------------------
reponses['/mes-reservations'] = page('prive', 'MES RESERVATIONS Awa');
await evenement('fetch', nav('/mes-reservations'));
assert.deepEqual(await contenu('prive-test-v1'), ['/mes-reservations']); ok('« Mes réservations » est enregistrée (copie privée)');
reseau = 'coupe';
({ r } = await evenement('fetch', nav('/mes-reservations')));
assert.equal(await r.text(), 'MES RESERVATIONS Awa'); ok('réseau coupé : « Mes réservations » reste lisible');
const vieille = stock.get('prive-test-v1'); const k = [...vieille.m.keys()][0];
const ancienne = vieille.m.get(k); const entetes = new Headers(ancienne.headers); entetes.set('X-Gesimo-Enregistre', String(Date.now() - 8 * 24 * 3600 * 1000));
vieille.m.set(k, new Response('MES RESERVATIONS Awa', { status: 200, headers: entetes }));
({ r } = await evenement('fetch', nav('/mes-reservations')));
assert.equal(await r.text(), 'HORS CONNEXION'); ok('une copie privée de plus de 7 jours n\'est plus montrée');
reseau = 'ok';

// --- ce qui n'est JAMAIS enregistré -----------------------------------------------------------
for (const chemin of ['/payer/5', '/paiement/retour/5', '/reservation/5/recu', '/reservation/5/confirmation', '/reservation/5/annuler', '/gerant/argent', '/admin/paiements', '/admin/documents/3', '/reserver/2', '/compte', '/compte/profil', '/connexion', '/installation', '/livewire-abc/update']) {
  reponses[chemin] = page('prive', 'DONNEES SENSIBLES ' + chemin); // même si le serveur se trompait et marquait la page
  await evenement('fetch', nav(chemin));
}
const toutes = [...(await contenu('public-test-v1')), ...(await contenu('prive-test-v1')), ...(await contenu('statique-test-v1'))].filter((c) => c !== '/hors-connexion');
assert.ok(!toutes.some((c) => /payer|paiement|recu|confirmation|annuler|gerant|admin|reserver|compte|connexion|installation|livewire-abc/.test(c))); ok('paiement, reçus, pièces, argent, gérant, admin, comptes : jamais enregistrés (même marqués par erreur)');
reseau = 'coupe';
({ r } = await evenement('fetch', nav('/payer/5')));
assert.equal(await r.text(), 'HORS CONNEXION'); ok('réseau coupé sur un paiement : message clair, pas de copie');
reseau = 'ok';

// --- réponses non autorisées -----------------------------------------------------------------
reponses['/logement/9'] = page(null, 'sans marque');
await evenement('fetch', nav('/logement/9'));
reponses['/logement/8'] = page(null, 'no-store', { entetes: { 'Cache-Control': 'no-store, private' } });
await evenement('fetch', nav('/logement/8'));
assert.ok(!(await contenu('public-test-v1')).some((c) => c.startsWith('/logement'))); ok('une page sans marquage du serveur n\'est pas enregistrée');
reponses['/logement/7'] = page('public', 'marquee', { entetes: { 'Cache-Control': 'no-cache, no-store, must-revalidate' } });
await evenement('fetch', nav('/logement/7'));
assert.ok((await contenu('public-test-v1')).includes('/logement/7')); ok('une page marquée par le serveur est enregistrée même si Livewire ajoute « no-store » (anti-retour arrière)');

// --- déconnexion ------------------------------------------------------------------------------
await evenement('fetch', nav('/mes-reservations'));
assert.ok((await contenu('prive-test-v1')).length > 0);
reponses['/deconnexion'] = () => new Response('', { status: 200 });
const logout = new Request(ORIGINE + '/deconnexion', { method: 'POST' });
await evenement('fetch', logout);
assert.ok(!(await caches.keys()).some((c) => c.startsWith('prive-')) || (await contenu('prive-test-v1')).length === 0); ok('déconnexion : la copie privée est effacée');
assert.ok((await contenu('public-test-v1')).includes('/faq')); ok('déconnexion : les pages publiques restent');
reseau = 'coupe';
({ r } = await evenement('fetch', nav('/mes-reservations')));
assert.equal(await r.text(), 'HORS CONNEXION'); ok('après déconnexion, aucune donnée personnelle n\'est lisible hors connexion');
reseau = 'ok';

// déconnexion avec réseau coupé : on efface quand même
await evenement('fetch', nav('/mes-reservations'));
reseau = 'coupe';
await evenement('fetch', new Request(ORIGINE + '/deconnexion', { method: 'POST' })).catch(() => {});
assert.equal((await contenu('prive-test-v1')).length, 0); ok('déconnexion sans réseau : la copie privée est quand même effacée');
reseau = 'ok';

// --- session expirée ---------------------------------------------------------------------------
await evenement('fetch', nav('/mes-reservations'));
assert.equal((await contenu('prive-test-v1')).length, 1);
reponses['/mes-reservations'] = () => new FauxReponse('', { status: 200 }, { type: 'opaqueredirect' });
await evenement('fetch', nav('/mes-reservations'));
assert.equal((await contenu('prive-test-v1')).length, 0); ok('le serveur demande de se reconnecter : la copie privée est effacée');
reponses['/mes-reservations'] = page('prive', 'MES RESERVATIONS Awa');

// --- jamais rejoué ------------------------------------------------------------------------------
appels = [];
const post = await evenement('fetch', new Request(ORIGINE + '/livewire-abc/update', { method: 'POST', body: '{}' }));
assert.equal(post.r, undefined); ok('une requête POST (paiement, réservation…) n\'est jamais interceptée, mise en attente ni rejouée');
const livewire = nav('/livewire-abc/update');
await evenement('fetch', livewire);
assert.ok(!(await contenu('public-test-v1')).includes('/livewire-abc/update'));

// --- réseau faible ------------------------------------------------------------------------------
await evenement('fetch', nav('/mes-reservations'));
reseau = 'lent';
({ r } = await evenement('fetch', nav('/mes-reservations')));
assert.equal(await r.text(), 'MES RESERVATIONS Awa'); ok('réseau très faible : la copie enregistrée s\'affiche sans attendre indéfiniment');
reseau = 'ok';

// --- messages ---------------------------------------------------------------------------------
await evenement('message', undefined, { data: { type: 'passer-a-la-nouvelle-version' } });
assert.equal(skipWaitingAppele, true); ok('la nouvelle version ne s\'installe que sur demande (« Mettre à jour »)');
await evenement('fetch', nav('/mes-reservations'));
const msg = await evenement('message', undefined, { data: { type: 'vider-prive' } });
assert.equal((await contenu('prive-test-v1')).length, 0); ok('message « vider-prive » : copie privée effacée');
console.log('Service worker : tous les essais réussissent.');
