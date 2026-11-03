// Gérant au quotidien, vérification et itinéraire dans un vrai navigateur.
// Données : tests/Support/preparer-reservation.php puis preparer-espace-voyageur.php (réservation 1 confirmée du voyageur ESSAI).
// Usage : node gerant-quotidien.mjs <runtime> <dossier-captures> [base]
import { createRequire } from 'node:module';
import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
const [runtime, output, base = 'http://127.0.0.1:8123'] = process.argv.slice(2);
assert(['127.0.0.1', 'localhost'].includes(new URL(base).hostname));
const { chromium } = createRequire(runtime + '/package.json')('playwright');
mkdirSync(output, { recursive: true });
const browser = await chromium.launch({ headless: true });
const MDP = 'mot-de-passe-essai-navigateur';
const erreurs = [];
async function connecter(context, tel) {
    const page = await context.newPage();
    page.on('pageerror', e => erreurs.push(e.message));
    await page.goto(base + '/connexion');
    await page.locator('#telephone').fill(tel);
    await page.locator('#motDePasse').fill(MDP);
    await page.getByRole('button', { name: 'Me connecter' }).click();
    await page.waitForURL(url => !url.pathname.startsWith('/connexion'));
    return page;
}
const pages = { aujourdhui: '/gerant/arrivees', reservations: '/gerant/reservations', calendrier: '/gerant/calendrier', telephone: '/gerant/telephone', verification: '/gerant/verification', acces: '/gerant/residences/1/acces' };
for (const [w, nom] of [[1440, 'ordinateur'], [390, 'telephone']]) {
    const context = await browser.newContext({ viewport: { width: w, height: 900 }, locale: 'fr-FR', serviceWorkers: 'block', geolocation: { latitude: 5.3599, longitude: -3.987 }, permissions: ['geolocation'] });
    const page = await connecter(context, '0799100001');
    for (const [cle, chemin] of Object.entries(pages)) {
        const r = await page.goto(base + chemin);
        await page.waitForLoadState('networkidle');
        assert.equal(r.status(), 200, `${chemin} : ${r.status()}`);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `Débordement sur ${chemin} (${nom})`);
        await page.screenshot({ path: `${output}/${nom}-${cle}.png`, fullPage: true });
        console.log(nom, cle, 'ok');
    }
    if (nom === 'ordinateur') {
        // Accès à la résidence : position du téléphone + repères
        await page.goto(base + '/gerant/residences/1/acces');
        await page.getByRole('button', { name: /Utiliser ma position actuelle/ }).click();
        await page.getByText('Position relevée').waitFor();
        await page.locator('#reperes').fill('Portail bleu, en face de la pharmacie.');
        await page.getByRole('button', { name: 'Enregistrer', exact: true }).click();
        await page.getByText('Accès enregistré').waitFor();
        // Lien Google Maps sans position : message expliqué
        await page.locator('#lien').fill('https://maps.app.goo.gl/AbCdEf');
        await page.locator('#lien').blur();
        await page.getByText('Ce lien ne contient pas de position').waitFor();
        // Vérification : deux documents à fournir
        await page.goto(base + '/gerant/verification');
        assert((await page.getByText('Contrat de bail de').count()) >= 1);
        // Menu du gérant
        assert(await page.getByRole('link', { name: 'Ma vérification' }).first().isVisible());
    }
    await context.close();
}
// Voyageur : Comment venir
for (const [w, nom] of [[390, 'telephone'], [1440, 'ordinateur']]) {
    const context = await browser.newContext({ viewport: { width: w, height: 900 }, locale: 'fr-FR', serviceWorkers: 'block' });
    const page = await connecter(context, '0799100003');
    for (const [cle, chemin] of Object.entries({ venir: '/reservation/1/venir', 'venir-avant': '/reservation/3/venir' })) {
        const r = await page.goto(base + chemin);
        await page.waitForLoadState('networkidle');
        assert.equal(r.status(), 200);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `Débordement sur ${chemin} (${nom})`);
        await page.screenshot({ path: `${output}/${nom}-${cle}.png`, fullPage: true });
    }
    await page.goto(base + '/reservation/1/venir');
    await page.getByRole('link', { name: /Me conduire à la résidence/ }).evaluate(a => a.addEventListener('click', e => e.preventDefault()));
    await page.getByRole('link', { name: /Me conduire à la résidence/ }).click();
    await page.getByRole('link', { name: 'Waze' }).waitFor({ state: 'visible' }); // les autres applications de cartes sont proposées
    assert((await page.getByRole('link', { name: 'Waze' }).getAttribute('href')).includes('waze.com/ul?ll=5.3599,-3.987'));
    assert((await page.getByRole('link', { name: /chauffeur/ }).getAttribute('href')).startsWith('https://wa.me/?text='));
    await page.goto(base + '/reservation/3/venir');
    assert(await page.getByText('après la confirmation de votre réservation').isVisible());
    assert.equal(await page.getByText('Portail bleu').count(), 0, 'Aucun repère avant la confirmation.');
    await context.close();
}
assert.deepEqual(erreurs, [], 'Erreurs JavaScript : ' + erreurs.join(' | '));
await browser.close();
console.log('Parcours gérant au quotidien : OK');
