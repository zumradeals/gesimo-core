// Usage : node tests/E2E/profil-gerant.mjs <répertoire Playwright temporaire> <captures> [URL locale]
// Comptes fictifs préparés par tests/Support/preparer-profil-gerant.php, exclusivement en environnement testing.
import { createRequire } from 'node:module';
import { readFileSync, writeFileSync } from 'node:fs';
import assert from 'node:assert/strict';
const [runtime, output, base = 'http://127.0.0.1:8123'] = process.argv.slice(2);
assert(['127.0.0.1', 'localhost'].includes(new URL(base).hostname), 'Ce parcours doit viser uniquement un serveur local de test.');
const require = createRequire(runtime + '/package.json');
const { chromium } = require('playwright');
const data = JSON.parse(readFileSync(output + '/donnees.json', 'utf8'));
const browser = await chromium.launch({ headless: true });
const errors = [];
const contexts = [];
async function session(telephone) {
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 }, serviceWorkers: 'block' });
    contexts.push(ctx);
    const page = await ctx.newPage();
    page.on('pageerror', e => errors.push(e.message));
    page.on('response', r => { if (r.status() >= 400) console.error('HTTP', r.status(), r.url()); });
    page.on('dialog', d => d.accept());
    await page.goto(base + '/connexion');
    await page.waitForFunction(() => !!window.Livewire);
    await page.locator('#telephone').fill(telephone);
    await page.locator('#motDePasse').fill('mot-de-passe-essai-navigateur');
    await page.getByRole('button', { name: 'Me connecter', exact: true }).click();
    await page.waitForURL(u => !u.pathname.startsWith('/connexion'));
    return page;
}
async function visible(page, text) {
    await page.getByText(text, { exact: false }).first().waitFor({ state: 'visible', timeout: 15000 });
}
try {
    const owner = await session('0799000001');
    await owner.goto(base + '/gerant/profil');
    await owner.waitForFunction(() => !!window.Livewire);
    await owner.locator('#identite').setInputFiles(output + '/document-fictif.png');
    await owner.locator('#certificat_residence').setInputFiles(output + '/document-fictif.png');
    await owner.getByRole('button', { name: 'Envoyer les documents pour contrôle', exact: true }).click();
    await visible(owner, 'Documents envoyés pour contrôle');
    console.log('✓ Envoi réel des deux documents privés par le gérant.');
    const admin = await session('0799000002');
    await admin.goto(base + '/admin/gerants/' + data.profilId);
    const identityCard = admin.locator('.carte-p').filter({ has: admin.getByRole('heading', { name: 'Pièce d’identité', exact: true }) });
    await identityCard.getByRole('button', { name: 'Accepter ce document', exact: true }).click();
    await visible(admin, 'Document validé.');
    await owner.goto(base + '/gerant/profil');
    await visible(owner, 'Prêt à publier');
    await owner.screenshot({ path: output + '/profil-gerant-ordinateur.png', fullPage: true });
    await owner.goto(base + '/gerant/residences/' + data.residenceId);
    await owner.getByRole('button', { name: /3 Vérification et envoi/ }).click();
    await visible(owner, 'Aucune pièce personnelle à renvoyer');
    await owner.setViewportSize({ width: 390, height: 844 });
    await owner.screenshot({ path: output + '/soumission-telephone.png', fullPage: true });
    await owner.getByRole('button', { name: 'Envoyer pour validation →', exact: true }).click();
    await owner.waitForURL(u => u.pathname === '/gerant/residences');
    await visible(owner, 'Votre envoi est terminé.');
    assert.equal(await owner.getByRole('button', { name: 'Envoyer pour validation →', exact: true }).count(), 0);
    await owner.screenshot({ path: output + '/confirmation-telephone.png', fullPage: true });
    console.log('✓ Soumission sans justificatif de gestion, retour à la liste et confirmation.');
    await admin.goto(base + '/admin/residences/' + data.residenceId);
    await admin.getByRole('button', { name: 'Valider et mettre en ligne', exact: true }).click();
    await visible(admin, 'Résidence validée et mise en ligne.');
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 }, serviceWorkers: 'block' });
    contexts.push(ctx);
    const visitor = await ctx.newPage();
    visitor.on('pageerror', e => errors.push(e.message));
    await visitor.goto(base + '/hebergeurs/' + data.slug);
    await visible(visitor, 'Studio Papaye — ESSAI');
    assert.equal(await visitor.getByText('✓ Gérant certifié', { exact: true }).count(), 0);
    assert.equal(await visitor.getByText('✓ Droit de gestion vérifié', { exact: true }).count(), 0);
    const html = await visitor.content();
    assert(!html.includes('Adresse personnelle confidentielle ESSAI'));
    assert(!html.includes('0799000001'));
    assert(!html.includes('gerant@example.test'));
    console.log('✓ Annonce publique sans certification ni badge, sans données privées.');
    await admin.goto(base + '/admin/gerants/' + data.profilId);
    const certificate = admin.locator('.carte-p').filter({ has: admin.getByRole('heading', { name: 'Certificat de résidence', exact: true }) });
    await certificate.getByRole('button', { name: 'Accepter ce document', exact: true }).click();
    await visible(admin, 'Document validé.');
    await visitor.reload();
    await visible(visitor, '✓ Gérant certifié');
    assert.equal(await visitor.getByText('✓ Droit de gestion vérifié', { exact: true }).count(), 0);
    await owner.goto(base + '/gerant/residences/' + data.residenceId);
    await owner.getByRole('button', { name: /3 Vérification et envoi/ }).click();
    await owner.locator('#justif').setInputFiles(output + '/document-fictif.png');
    await owner.getByRole('button', { name: 'Enregistrer le justificatif seul', exact: true }).click();
    await visible(owner, 'Justificatif enregistré pour contrôle.');
    await visitor.reload();
    await visible(visitor, 'Studio Papaye — ESSAI');
    await admin.goto(base + '/admin/residences/' + data.residenceId);
    await admin.getByRole('button', { name: 'Valider le justificatif et accorder le badge', exact: true }).click();
    await visible(admin, 'Justificatif de gestion validé.');
    await visitor.reload();
    await visible(visitor, '✓ Droit de gestion vérifié');
    await visitor.screenshot({ path: output + '/profil-public-ordinateur.png', fullPage: true });
    await visitor.setViewportSize({ width: 390, height: 844 });
    await visitor.screenshot({ path: output + '/profil-public-telephone.png', fullPage: true });
    assert.equal(await visitor.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    assert.deepEqual(errors, [], 'Erreurs JavaScript du parcours : ' + errors.join('\n'));
    console.log('✓ Certification du gérant et badge de résidence attribués séparément, sans interruption de publication.');
} catch (error) {
    for (const [i, ctx] of contexts.entries()) {
        for (const [j, page] of ctx.pages().entries()) {
            await page.screenshot({ path: output + `/echec-${i}-${j}.png`, fullPage: true }).catch(() => {});
            writeFileSync(output + `/echec-${i}-${j}.txt`, await page.locator('body').innerText());
            console.error(await page.locator('body').innerText());
        }
    }
    console.error('Erreurs JavaScript :', errors);
    throw error;
} finally {
    for (const ctx of contexts) await ctx.close();
    await browser.close();
}
