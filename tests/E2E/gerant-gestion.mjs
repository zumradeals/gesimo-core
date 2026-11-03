// Gérant : gestion (Mes résidences, éditeur, profil, Argent, Mises en avant, Personnel, Aide) dans un vrai navigateur.
// Données : tests/Support/preparer-reservation.php. Usage : node gerant-gestion.mjs <runtime> <dossier-captures> [base]
import { createRequire } from 'node:module';
import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
const [runtime, output, base = 'http://127.0.0.1:8123'] = process.argv.slice(2);
assert(['127.0.0.1', 'localhost'].includes(new URL(base).hostname));
const { chromium } = createRequire(runtime + '/package.json')('playwright');
mkdirSync(output, { recursive: true });
const browser = await chromium.launch({ headless: true });
const erreurs = [];
const pages = { residences: '/gerant/residences', editeur: '/gerant/residences/1', profil: '/gerant/profil', argent: '/gerant/argent', mises: '/gerant/mise-en-avant', acheter: '/gerant/mise-en-avant/nouvelle', personnel: '/gerant/personnel', aide: '/gerant/aide' };
for (const [w, nom] of [[1440, 'ordinateur'], [390, 'telephone']]) {
    const context = await browser.newContext({ viewport: { width: w, height: 900 }, locale: 'fr-FR', serviceWorkers: 'block' });
    const page = await context.newPage();
    page.on('pageerror', e => erreurs.push(`${nom}: ${e.message}`));
    await page.goto(base + '/connexion');
    await page.locator('#telephone').fill('0799100001');
    await page.locator('#motDePasse').fill('mot-de-passe-essai-navigateur');
    await page.getByRole('button', { name: 'Me connecter' }).click();
    await page.waitForURL(url => !url.pathname.startsWith('/connexion'));
    for (const [cle, chemin] of Object.entries(pages)) {
        const r = await page.goto(base + chemin);
        await page.waitForLoadState('networkidle');
        assert.equal(r.status(), 200, `${chemin} : ${r.status()}`);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `Débordement sur ${chemin} (${nom})`);
        await page.screenshot({ path: `${output}/${nom}-${cle}.png`, fullPage: true });
        console.log(nom, cle, 'ok');
    }
    if (nom === 'ordinateur') {
        await page.goto(base + '/gerant/residences');
        assert(await page.getByRole('link', { name: /Accès/ }).first().isVisible());
        await page.goto(base + '/gerant/profil');
        assert.equal(await page.getByText('Mes documents').count(), 0, 'Plus d\'onglet documents.');
        assert(await page.getByRole('link', { name: 'Ma vérification' }).first().isVisible());
        await page.goto(base + '/gerant/residences/1');
        assert.equal(await page.locator('.stepper .st').count(), 3);
        await page.getByRole('tab', { name: /Mes logements/ }).click().catch(() => {});
    }
    await context.close();
}
assert.deepEqual(erreurs, [], 'Erreurs JavaScript : ' + erreurs.join(' | '));
await browser.close();
console.log('Parcours gérant gestion : OK');
