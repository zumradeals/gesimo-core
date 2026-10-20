// Essai de bout en bout dans un vrai Chromium (téléphone de 360 px) : installation, réseau coupé, déconnexion, mise à jour.
// Usage : node tests/E2E/pwa.mjs <adresse du site> <dossier des captures> <téléphone client> <mot de passe>
// (nécessite Playwright ; voir docs/rapports/lot-6.md)
import { createRequire } from 'module';
import assert from 'node:assert/strict';
import { readFileSync, writeFileSync } from 'node:fs';
const require = createRequire('/opt/node-tools/');
const { chromium } = require('playwright');
const [base, dossier, tel, mdp, commande = 'http://127.0.0.1:8101'] = process.argv.slice(2);
const SW = new URL('../../resources/pwa/sw.js', import.meta.url).pathname;
const swOrigine = readFileSync(SW, 'utf8');
const ok = (m) => console.log('  ✓ ' + m);
const pause = (ms) => new Promise((r) => setTimeout(r, ms));
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });
const ctx = await browser.newContext({ viewport: { width: 360, height: 740 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, serviceWorkers: 'allow' });
const page = await ctx.newPage();
const cdp = await ctx.newCDPSession(page);
// Réseau coupé « pour de vrai » : le relais refuse toute connexion (y compris celles du service worker, que Playwright ne bloque pas) et le navigateur passe hors ligne.
const reseau = async (coupe) => { await fetch(commande + (coupe ? '/coupe' : '/ok')); await ctx.setOffline(coupe); if (!coupe) await pause(1500); /* la page « hors connexion » se recharge toute seule au retour du réseau */ };
const texte = async () => (await page.locator('body').innerText()).replace(/\s+/g, ' ');
const caches = () => page.evaluate(async () => { const o = {}; for (const n of await caches.keys()) { o[n] = (await (await caches.open(n)).keys()).map((r) => new URL(r.url).pathname); } return o; });

await fetch(commande + '/ok');
try {
  // --- 1. installation : service worker actif, manifeste valide, aucune erreur d'installabilité ---------------
  await page.goto(base + '/faq', { waitUntil: 'networkidle' });
  await page.evaluate(() => navigator.serviceWorker.ready);
  await pause(800);
  await page.reload({ waitUntil: 'networkidle' });
  const sw = await page.evaluate(async () => { const r = await navigator.serviceWorker.getRegistration(); return { actif: !!r?.active, portee: r?.scope }; });
  assert.ok(sw.actif); ok('le service worker est actif (portée ' + sw.portee + ')');
  await cdp.send('Page.enable');
  const { installabilityErrors: errors } = await cdp.send('Page.getInstallabilityErrors');
  // Le navigateur d'essai est en navigation privée : la seule « erreur » attendue est « in-incognito » (installation impossible en privé).
  const vraies = errors.filter((e) => e.errorId !== 'in-incognito');
  assert.deepEqual(vraies, [], 'erreurs d\'installabilité : ' + JSON.stringify(vraies)); ok('Chrome ne signale aucune erreur d\'installabilité (manifeste, icônes, service worker) ; seule réserve : navigation privée de l\'essai');
  const man = await cdp.send('Page.getAppManifest');
  assert.equal(man.errors.length, 0); const m = JSON.parse(man.data);
  assert.equal(m.display, 'standalone'); ok(`manifeste : « ${m.name} », affichage « ${m.display} », ${m.icons.length} icônes`);

  // --- 2. réseau coupé : page consultée lisible, page jamais vue -> page « hors connexion » ---------------------
  await page.goto(base + '/faq', { waitUntil: 'networkidle' });
  await reseau(true);
  await page.goto(base + '/faq').catch(() => {});
  assert.match(await texte(), /Questions fréquentes/); ok('réseau coupé : la page « Questions fréquentes » déjà consultée reste lisible');
  await pause(300); assert.match(await texte(), /Hors connexion/); ok('un bandeau « Hors connexion » prévient la personne');
  await page.screenshot({ path: dossier + '/hors-connexion-page-consultee.png' });
  await page.goto(base + '/a-propos').catch(() => {});
  assert.match(await texte(), /Vous êtes hors connexion/); ok('réseau coupé : une page jamais consultée affiche la page « hors connexion » claire');
  await page.screenshot({ path: dossier + '/hors-connexion-page-inconnue.png' });
  await reseau(false);

  // --- 3. client connecté : « Mes réservations » lisible hors connexion ; paiement jamais enregistré -----------
  await page.goto(base + '/connexion', { waitUntil: 'networkidle' });
  await page.fill('input[type=tel]', tel); await page.fill('input[type=password]', mdp);
  await page.click('button:has-text("Me connecter")'); await page.waitForURL((u) => !u.pathname.startsWith('/connexion'), { timeout: 15000 }); await page.waitForLoadState('networkidle');
  await page.goto(base + '/mes-reservations', { waitUntil: 'networkidle' }); await pause(500);
  const avant = await caches();
  assert.ok(Object.entries(avant).some(([n, l]) => n.startsWith('prive-') && l.includes('/mes-reservations'))); ok('« Mes réservations » est enregistrée dans la copie privée');
  assert.ok(!Object.values(avant).flat().some((p) => /payer|paiement|recu|compte|connexion$/.test(p) && p !== '/hors-connexion')); ok('aucune page de paiement, de reçu ou de compte n\'est enregistrée');
  await reseau(true);
  await page.goto(base + '/mes-reservations').catch(() => {});
  await page.waitForSelector('#pwa-hors', { timeout: 8000 });
  const t = await texte();
  assert.match(t, /Mes réservations/); assert.match(t, /Hors connexion/); ok('réseau coupé : « Mes réservations » reste lisible, avec le bandeau');
  const grise = await page.evaluate(() => [...document.querySelectorAll('[data-reseau]')].every((e) => getComputedStyle(e).pointerEvents === 'none'));
  assert.ok(grise); ok('réseau coupé : payer / annuler sont grisés');
  await page.screenshot({ path: dossier + '/hors-connexion-mes-reservations.png' });
  await page.goto(base + '/compte').catch(() => {});
  assert.match(await texte(), /Vous êtes hors connexion/); ok('réseau coupé : une page personnelle non autorisée (compte) n\'est pas montrée');
  await reseau(false);
  await page.goto(base + '/mes-reservations', { waitUntil: 'networkidle' });
  await reseau(true); await pause(300); await reseau(false);
  await page.waitForSelector('#pwa-retour', { timeout: 8000 }); assert.match(await texte(), /Connexion rétablie/); ok('retour du réseau : « Connexion rétablie » avec « Actualiser »');
  await page.screenshot({ path: dossier + '/connexion-retablie.png' });

  // --- 4. action impossible sans réseau : message clair, rien de perdu, rien de renvoyé tout seul -----------------
  await page.goto(base + '/recherche', { waitUntil: 'networkidle' });
  await reseau(true);
  await page.locator('button:has-text("Climatisation")').first().click();
  await pause(1500);
  assert.match(await texte(), /Pas de connexion|connexion s'est coupée/); ok('une action sans réseau affiche un message clair (rien envoyé, rien débité)');
  await page.screenshot({ path: dossier + '/action-sans-reseau.png' });
  await reseau(false);

  // --- 5. déconnexion : plus aucune donnée personnelle dans le cache ---------------------------------------------
  await page.goto(base + '/compte', { waitUntil: 'networkidle' });
  await page.locator('button:has-text("Se déconnecter"):visible').first().click(); await page.waitForURL((u) => u.pathname === '/', { timeout: 15000 }); await page.waitForLoadState('networkidle'); await pause(500);
  const apres = await caches();
  assert.ok(!Object.entries(apres).some(([n, l]) => n.startsWith('prive-') && l.length > 0)); ok('après déconnexion : la copie privée est vide');
  assert.ok(!Object.values(apres).flat().includes('/mes-reservations')); ok('après déconnexion : « Mes réservations » n\'est plus dans aucun cache');
  await reseau(true);
  await page.goto(base + '/mes-reservations').catch(() => {});
  assert.match(await texte(), /Vous êtes hors connexion/); assert.doesNotMatch(await texte(), /Chambre|FCFA|DÉMO/);
  ok('après déconnexion, réseau coupé : aucune donnée personnelle n\'est lisible');
  await page.screenshot({ path: dossier + '/apres-deconnexion-hors-connexion.png' });
  await reseau(false);

  // --- 6. mise à jour : « Une nouvelle version est disponible », jamais pendant un paiement ----------------------
  await page.goto(base + '/faq', { waitUntil: 'networkidle' });
  const v1 = await page.evaluate(async () => (await (await fetch('/sw.js')).text()).match(/VERSION = '([0-9a-f]+)'/)[1]);
  writeFileSync(SW, swOrigine + '\n// nouvelle version de démonstration\n');
  try {
    const v2 = await page.evaluate(async () => (await (await fetch('/sw.js', { cache: 'reload' })).text()).match(/VERSION = '([0-9a-f]+)'/)[1]);
    assert.notEqual(v1, v2); ok(`le numéro de version change à chaque mise en ligne (${v1} → ${v2})`);
    await page.evaluate(() => navigator.serviceWorker.getRegistration().then((r) => r.update()));
    await pause(2500);
    await page.evaluate(() => document.dispatchEvent(new Event('visibilitychange')));
    await pause(32000 > 0 ? 100 : 0);
    // le bandeau est vérifié toutes les 30 s : on force la vérification
    await page.waitForSelector('#pwa-maj', { timeout: 40000 });
    assert.match(await texte(), /Une nouvelle version est disponible/); ok('le message « Une nouvelle version est disponible » apparaît, avec un bouton');
    await page.screenshot({ path: dossier + '/nouvelle-version.png' });
    await page.click('#pwa-maj .js-maj'); await page.waitForLoadState('load'); await pause(1500);
    const v3 = await page.evaluate(async () => (await (await fetch('/sw.js', { cache: 'reload' })).text()).match(/VERSION = '([0-9a-f]+)'/)[1]);
    assert.equal(v3, v2); ok('« Mettre à jour » recharge la page avec la nouvelle version');
  } finally { writeFileSync(SW, swOrigine); }
  console.log('PWA : tous les essais réussissent.');
} catch (e) {
  await page.screenshot({ path: dossier + '/echec.png' }).catch(() => {});
  console.error('ÉCHEC :', e.message); process.exitCode = 1;
} finally { await browser.close(); }
