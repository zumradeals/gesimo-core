// Navigation publique et inscription gérant : uniquement sur la base locale de test.
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
try {
    await page.goto(base + '/devenir-partenaire');
    await page.getByRole('heading', { level: 1, name: /Votre résidence mérite/ }).waitFor();
    await page.waitForFunction(() => !!window.Livewire);
    await page.locator('img').evaluateAll(imgs => Promise.all(imgs.map(img => { img.loading = 'eager'; return img.decode(); })));
    const labels = ['Accueil', 'Logements', 'À propos', 'Devenir partenaire', 'Aide', 'Contact'];
    assert.deepEqual(await page.getByRole('navigation', { name: 'Menu principal', exact: true }).locator('a').allTextContents(), labels);
    for (const width of [320, 390, 768, 1024, 1280, 1440, 1920, 2560]) {
        await page.setViewportSize({ width, height: 844 });
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `Débordement à ${width} px`);
        assert.equal(await page.locator('.site-menu-mobile').isVisible(), width < 1024);
        assert.equal(await page.locator('.h-nav').isVisible(), width >= 1024);
        assert.equal(await page.locator('.mobile-tabs').isVisible(), width < 1024);
    }
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.screenshot({ path: output + '/partenaire-ordinateur.png', fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.screenshot({ path: output + '/partenaire-telephone.png', fullPage: true });
    const menu = page.locator('.site-menu-mobile');
    await menu.locator('summary').click();
    await menu.getByRole('link', { name: 'Accueil', exact: true }).waitFor({ state: 'visible' });
    await page.keyboard.press('Escape');
    assert.equal(await menu.getAttribute('open'), null);
    await menu.locator('summary').click();
    await menu.getByRole('link', { name: 'À propos', exact: true }).click();
    await page.waitForURL('**/a-propos');
    await page.locator('.site-menu-mobile summary').click();
    await page.getByRole('navigation', { name: 'Menu principal mobile', exact: true }).getByRole('link', { name: 'Devenir partenaire', exact: true }).click();
    await page.waitForURL('**/devenir-partenaire');
    await page.getByText('Le certificat de résidence est-il obligatoire ?', { exact: true }).click();
    await page.getByText('Non. Il complète votre identité', { exact: false }).waitFor({ state: 'visible' });
    await page.locator('.pa-hero').getByRole('link', { name: /Devenir gérant partenaire/ }).click();
    await page.waitForURL(u => u.pathname === '/inscription' && u.searchParams.get('profil') === 'gerant');
    assert.equal(await page.locator('input[type="radio"][value="gerant"]').isChecked(), true);
    assert.deepEqual(errors, []);
    console.log('✓ Page partenaire, six liens publics, menu mobile, FAQ et inscription gérant vérifiés.');
} catch (e) {
    await page.screenshot({ path: output + '/echec-partenaire.png', fullPage: true }).catch(() => {});
    throw e;
} finally {
    await browser.close();
}
