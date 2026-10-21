// Le catalogue et ses vrais filtres Livewire, sur l’inventaire réservé aux tests.
import { createRequire } from 'node:module';
import assert from 'node:assert/strict';
const [runtime, output, base = 'http://127.0.0.1:8123'] = process.argv.slice(2);
assert(['127.0.0.1', 'localhost'].includes(new URL(base).hostname));
const { chromium } = createRequire(runtime + '/package.json')('playwright');
const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, locale: 'fr-FR', serviceWorkers: 'block' });
const page = await context.newPage();
const errors = [];
page.on('pageerror', e => errors.push(e.message));
const cards = page.locator('.lo-card');
try {
    await page.goto(base + '/recherche');
    await page.waitForFunction(() => !!document.querySelector('[x-data="filtresLogements"]')._x_dataStack);
    assert(await cards.count() >= 12);
    await page.getByText('Tarif de référence', { exact: true }).first().waitFor();
    assert(await page.locator('#budget').isDisabled());
    assert.equal(await page.getByText('Montant du séjour', { exact: true }).count(), 0);
    for (const width of [320, 390, 768, 1024, 1280, 1440, 1920, 2560]) {
        await page.setViewportSize({ width, height: 844 });
        await page.waitForFunction(mobile => document.querySelector('.lo-panel').open !== mobile, width <= 900);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `Débordement à ${width} px`);
        assert.equal(await page.locator('.lo-filter-button').isVisible(), width <= 900);
        if (width >= 1280) assert.equal(await page.locator('.lo-grid').evaluate(el => getComputedStyle(el).gridTemplateColumns.split(' ').length), 3);
    }
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.locator('img').evaluateAll(imgs => Promise.all(imgs.map(img => { img.loading = 'eager'; return img.decode(); })));
    await page.screenshot({ path: output + '/logements-ordinateur.png', fullPage: true });
    await page.locator('#commune').selectOption('Marcory');
    await page.getByRole('button', { name: 'Rechercher', exact: true }).click();
    await page.waitForFunction(() => [...document.querySelectorAll('.lo-location')].length > 0 && [...document.querySelectorAll('.lo-location')].every(e => e.textContent.includes('Marcory')));
    await page.locator('.lo-reset').click();
    await page.waitForFunction(() => document.querySelectorAll('.lo-card').length >= 12);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.locator('.lo-filter-button').click();
    assert(await page.locator('.lo-panel').evaluate(el => el.matches(':modal')));
    await page.getByRole('checkbox', { name: 'Wi-Fi', exact: true }).check();
    await page.waitForFunction(() => document.querySelectorAll('.lo-card').length === 1);
    assert(await page.locator('.lo-panel').evaluate(el => el.matches(':modal')), 'Les mises à jour Livewire gardent le panneau ouvert.');
    await page.screenshot({ path: output + '/logements-filtres-telephone.png' });
    await page.getByRole('button', { name: 'Afficher les logements', exact: true }).click();
    assert(await page.locator('.lo-panel').evaluate(el => !el.open));
    await page.locator('.lo-filter-button').click();
    await page.locator('.lo-reset').click();
    await page.waitForFunction(() => document.querySelectorAll('.lo-card').length >= 12);
    await page.keyboard.press('Escape');
    assert(await page.locator('.lo-panel').evaluate(el => !el.open));
    const date = delta => { const d = new Date(); d.setUTCDate(d.getUTCDate() + delta); return d.toISOString().slice(0, 10); };
    await page.locator('#commune').selectOption('Cocody');
    await page.locator('#arrivee').fill(date(40));
    await page.locator('#depart').fill(date(42));
    await page.getByRole('button', { name: 'Rechercher', exact: true }).click();
    await page.getByText('Montant du séjour', { exact: true }).first().waitFor();
    assert.equal(await page.getByText('Tarif de référence', { exact: true }).count(), 0);
    await page.locator('.lo-filter-button').click();
    assert(await page.locator('#budget').isEnabled());
    await page.locator('#budget').fill('1');
    await page.getByRole('heading', { name: 'Essayons d’autres critères.', exact: true }).waitFor({ state: 'attached' });
    await page.locator('#budget').fill('');
    await cards.first().waitFor({ state: 'attached' });
    await page.getByRole('button', { name: 'Afficher les logements', exact: true }).click();
    await page.screenshot({ path: output + '/logements-telephone.png', fullPage: true });
    const lien = new URL(await page.locator('.lo-details').first().getAttribute('href'), base);
    assert.equal(lien.searchParams.get('arrivee'), date(40));
    assert.equal(lien.searchParams.get('depart'), date(42));
    assert.equal(lien.searchParams.get('personnes'), '2');
    await page.locator('.lo-details').first().click();
    await page.waitForURL(lien.toString());
    assert.deepEqual(errors, []);
    console.log('✓ Catalogue sans dates, filtres réels, huit largeurs, dialogue mobile, budget total et fiche avec dates vérifiés.');
} catch (e) {
    await page.screenshot({ path: output + '/echec-logements.png', fullPage: true }).catch(() => {});
    throw e;
} finally {
    await browser.close();
}
