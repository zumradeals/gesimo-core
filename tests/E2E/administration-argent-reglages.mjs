// Administration : argent et réglages dans un vrai navigateur (Paiements, Reversements, Contrôle, Mises en avant, Journal, Modèles d'e-mails, Réglages).
// Données : tests/Support/preparer-reservation.php puis preparer-espace-voyageur.php. Usage : node administration-argent-reglages.mjs <runtime> <dossier> [base]
import { createRequire } from 'node:module';
import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
const [runtime, output, base = 'http://127.0.0.1:8123'] = process.argv.slice(2);
assert(['127.0.0.1', 'localhost'].includes(new URL(base).hostname));
const { chromium } = createRequire(runtime + '/package.json')('playwright');
mkdirSync(output, { recursive: true });
const browser = await chromium.launch({ headless: true });
const erreurs = [];
const pages = { paiements: '/admin/paiements', reversements: '/admin/reversements', controle: '/admin/controle', mises: '/admin/mises-en-avant', journal: '/admin/journal', mails: '/admin/messages', reglages: '/admin/reglages', 'reglages-reservation': '/admin/reglages/reservation', tests: '/admin/reglages/tests' };
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
    if (nom === 'telephone') {
        await page.goto(base + '/admin/paiements');
        assert.equal(await page.locator('.tab-r thead').first().isVisible(), false, 'Sur téléphone, les lignes sont des cartes (pas d\'en-tête de tableau).');
    } else {
        await page.goto(base + '/admin/paiements');
        assert(await page.locator('.tab-r thead').first().isVisible(), 'Sur ordinateur, le tableau est conservé.');
        await page.goto(base + '/admin/reglages');
        assert(await page.getByText('Réglages de la plateforme').first().isVisible());
    }
    await context.close();
}
assert.deepEqual(erreurs, [], 'Erreurs JavaScript : ' + erreurs.join(' | '));
await browser.close();
console.log('Parcours administration argent et réglages : OK');
