// Pages d'information v3 dans un vrai navigateur : pas de débordement, pas d'erreur JavaScript, sommaire, barre d'action mobile.
// Données : tests/Support/preparer-reservation.php. Usage : node pages-information.mjs <runtime> <dossier> <slug-gerant> [base]
import { createRequire } from 'node:module';
import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
const [runtime, output, slug, base = 'http://127.0.0.1:8123'] = process.argv.slice(2);
assert(['127.0.0.1', 'localhost'].includes(new URL(base).hostname));
const { chromium } = createRequire(runtime + '/package.json')('playwright');
mkdirSync(output, { recursive: true });
const browser = await chromium.launch({ headless: true });
const erreurs = [];
const pages = { partenaire: '/devenir-partenaire', apropos: '/a-propos', aide: '/faq', contact: '/contact', conditions: '/conditions', confidentialite: '/confidentialite', mentions: '/mentions-legales', profil: `/hebergeurs/${slug}` };
for (const [w, nom] of [[1440, 'ordinateur'], [390, 'telephone']]) {
    const context = await browser.newContext({ viewport: { width: w, height: 900 }, locale: 'fr-FR', serviceWorkers: 'block' });
    const page = await context.newPage();
    page.on('pageerror', e => erreurs.push(`${nom}: ${e.message}`));
    for (const [cle, chemin] of Object.entries(pages)) {
        const r = await page.goto(base + chemin);
        await page.waitForLoadState('networkidle');
        assert.equal(r.status(), 200, `${chemin} : ${r.status()}`);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `Débordement sur ${chemin} (${nom})`);
        await page.screenshot({ path: `${output}/${nom}-${cle}.png`, fullPage: true });
        console.log(nom, cle, 'ok');
    }
    if (nom === 'ordinateur') {
        await page.goto(base + '/conditions');
        assert(await page.locator('.pi-sommaire').isVisible(), 'Sommaire visible sur ordinateur.');
        await page.locator('.pi-sommaire a').nth(2).click();
        assert((await page.evaluate(() => location.hash)) === '#sec-3');
        await page.goto(base + '/contact');
        assert((await page.locator('form').first().boundingBox()).x < (await page.getByText('Nos coordonnées').boundingBox()).x, 'Le formulaire est à gauche des coordonnées.');
        await page.locator('#nom').fill('');
        await page.getByRole('button', { name: 'Envoyer' }).click();
        await page.locator('.erreur-champ').first().waitFor();
    } else {
        await page.goto(base + '/devenir-partenaire');
        assert(await page.locator('.pa-barre').isVisible(), 'Barre d\'action visible sur téléphone.');
        await page.goto(base + '/conditions');
        assert.equal(await page.locator('.pi-sommaire').isVisible(), false);
    }
    await context.close();
}
assert.deepEqual(erreurs, [], 'Erreurs JavaScript : ' + erreurs.join(' | '));
await browser.close();
console.log('Parcours pages d\'information : OK');
