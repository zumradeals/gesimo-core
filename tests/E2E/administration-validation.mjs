// Administration : tableau de bord, résidences à valider, validation, dossier gérant, réservations, litiges dans un vrai navigateur.
// Données : tests/Support/preparer-reservation.php puis preparer-espace-voyageur.php. Usage : node administration-validation.mjs <runtime> <dossier> [base]
import { createRequire } from 'node:module';
import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
const [runtime, output, base = 'http://127.0.0.1:8123'] = process.argv.slice(2);
assert(['127.0.0.1', 'localhost'].includes(new URL(base).hostname));
const { chromium } = createRequire(runtime + '/package.json')('playwright');
mkdirSync(output, { recursive: true });
const browser = await chromium.launch({ headless: true });
const erreurs = [];
const pages = { tableau: '/admin', residences: '/admin/residences', valider: '/admin/residences/1', reservations: '/admin/reservations', litiges: '/admin/litiges' };
for (const [w, nom] of [[1440, 'ordinateur'], [390, 'telephone']]) {
    const context = await browser.newContext({ viewport: { width: w, height: 900 }, locale: 'fr-FR', serviceWorkers: 'block' });
    const page = await context.newPage();
    page.on('pageerror', e => erreurs.push(`${nom}: ${e.message}`));
    await page.goto(base + '/connexion');
    await page.locator('#telephone').fill('0799100002');
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
        await page.goto(base + '/admin/residences/1');
        assert(await page.getByText('Pièce d’identité du gérant').first().isVisible());
        assert(await page.getByText('Contrat de bail').first().isVisible());
        assert.equal(await page.getByText('facultatifs').count(), 0);
        await page.goto(base + '/admin');
        assert.equal(await page.getByText('👋').count(), 0);
    }
    await context.close();
}
assert.deepEqual(erreurs, [], 'Erreurs JavaScript : ' + erreurs.join(' | '));
await browser.close();
console.log('Parcours administration : OK');
