// Parcours réel sur base locale fictive ; aucune URL de production n'est autorisée.
import { createRequire } from 'node:module';
import { writeFileSync } from 'node:fs';
import assert from 'node:assert/strict';
const [runtime, output, base = 'http://127.0.0.1:8123'] = process.argv.slice(2);
assert(['127.0.0.1', 'localhost'].includes(new URL(base).hostname));
const { chromium } = createRequire(runtime + '/package.json')('playwright');
const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, locale: 'fr-FR', serviceWorkers: 'block' });
const page = await context.newPage();
const errors = [];
page.on('pageerror', e => errors.push(e.message));
try {
    await page.goto(base);
    await page.getByRole('heading', { name: 'Un lieu à vous. Le temps d’un séjour.' }).waitFor();
    assert.equal(await page.locator('[data-offre-fictive]').count(), 3);
    assert.equal(await page.locator('[data-offre-fictive] a, [data-offre-fictive] button, [data-offre-fictive] form').count(), 0);
    await page.getByText('Studio Papaye — ESSAI', { exact: true }).waitFor();
    await page.locator('img').evaluateAll(imgs => Promise.all(imgs.map(img => img.decode())));
    await page.screenshot({ path: output + '/accueil-ordinateur.png', fullPage: true });
    for (const width of [320, 390, 768, 1024, 1440]) {
        await page.setViewportSize({ width, height: 844 });
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `Débordement à ${width} px`);
        assert.equal(await page.locator('.mobile-tabs').isVisible(), width < 1024);
    }
    await page.setViewportSize({ width: 390, height: 844 });
    await page.screenshot({ path: output + '/accueil-telephone.png', fullPage: true });
    await page.screenshot({ path: output + '/accueil-telephone-ecran.png' });
    await page.locator('.site-f').scrollIntoViewIfNeeded();
    await page.getByRole('link', { name: 'Mentions légales', exact: true }).click();
    await page.waitForURL('**/mentions-legales');
    await page.locator('.mobile-tabs').getByRole('link', { name: 'Accueil', exact: true }).click();
    await page.waitForURL(base + '/');
    const date = delta => { const d = new Date(); d.setUTCDate(d.getUTCDate() + delta); return d.toISOString().slice(0, 10); };
    await page.locator('#accueil-commune').selectOption('Cocody');
    await page.locator('#accueil-arrivee').fill(date(40));
    await page.locator('#accueil-depart').fill(date(42));
    await page.getByRole('button', { name: 'Rechercher un logement', exact: true }).click();
    await page.waitForURL(u => u.pathname === '/recherche' && u.searchParams.get('commune') === 'Cocody');
    await page.getByText('Studio Papaye — ESSAI', { exact: true }).waitFor();
    assert.equal(await page.locator('.mobile-tabs [aria-current="page"]').innerText(), 'Logements');
    await page.screenshot({ path: output + '/recherche-telephone.png', fullPage: true });
    await page.locator('.mobile-tabs').getByRole('link', { name: 'Réservations', exact: true }).click();
    await page.waitForURL('**/connexion');
    await page.waitForFunction(() => !!window.Livewire);
    await page.locator('#telephone').fill('0799000003');
    await page.locator('#motDePasse').fill('mot-de-passe-essai-navigateur');
    await page.getByRole('button', { name: 'Me connecter', exact: true }).click();
    await page.waitForURL(u => !u.pathname.startsWith('/connexion'));
    await page.goto(base + '/mes-reservations');
    assert.equal(await page.locator('.mobile-tabs [aria-current="page"]').innerText(), 'Réservations');
    await page.locator('.mobile-tabs').getByRole('link', { name: 'Compte', exact: true }).click();
    await page.waitForURL('**/compte');
    assert.equal(await page.locator('.mobile-tabs [aria-current="page"]').innerText(), 'Compte');
    await page.locator('.mobile-tabs').getByRole('link', { name: 'Accueil', exact: true }).click();
    await page.waitForURL(base + '/');
    assert.equal(await page.locator('.mobile-tabs').count(), 1);
    assert.deepEqual(errors, []);
    console.log('✓ Accueil ordinateur/téléphone, photos, exemples non réservables, recherche réelle et quatre onglets vérifiés.');
} catch (e) {
    await page.screenshot({ path: output + '/echec-accueil.png', fullPage: true }).catch(() => {});
    writeFileSync(output + '/echec-accueil.txt', await page.locator('body').innerText());
    console.error(await page.locator('body').innerText());
    throw e;
} finally {
    await browser.close();
}
