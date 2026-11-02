// Fin du parcours voyageur v3 dans un vrai navigateur : Mon espace, profil, annulation, avis, signalement, reçu.
// Données : tests/Support/preparer-reservation.php puis preparer-espace-voyageur.php (séjours 1 confirmé, 2 terminé, 3 à payer).
// Usage : node espace-voyageur.mjs <runtime> <dossier-captures> [base]
import { createRequire } from 'node:module';
import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
const [runtime, output, base = 'http://127.0.0.1:8123'] = process.argv.slice(2);
assert(['127.0.0.1', 'localhost'].includes(new URL(base).hostname));
const { chromium } = createRequire(runtime + '/package.json')('playwright');
mkdirSync(output, { recursive: true });
const browser = await chromium.launch({ headless: true });
const erreurs = [];
const pages = { compte: '/compte', profil: '/compte/profil', annuler: '/reservation/1/annuler', avis: '/reservation/2/avis', litige: '/reservation/1/probleme', recu: '/reservation/1/recu' };
for (const [w, nom] of [[1440, 'ordinateur'], [390, 'telephone']]) {
    const context = await browser.newContext({ viewport: { width: w, height: 900 }, locale: 'fr-FR', serviceWorkers: 'block' });
    const page = await context.newPage();
    page.on('pageerror', e => erreurs.push(`${nom}: ${e.message}`));
    await page.goto(base + '/connexion');
    await page.locator('#telephone').fill('0799100003');
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
        // Mon espace : le paiement à finir passe avant la pièce d'identité, le séjour à venir est visible
        await page.goto(base + '/compte');
        assert.equal(await page.getByText('À faire en premier').count(), 1);
        assert(await page.getByRole('heading', { name: 'Payez pour garder vos dates' }).isVisible());
        assert((await page.locator('.ev-cols').evaluate(el => getComputedStyle(el).gridTemplateColumns.split(' ').length)) === 3, 'Trois colonnes sur grand écran.');
        // Profil : Afficher / Masquer
        await page.goto(base + '/compte/profil');
        await page.locator('#ancien').fill('secret');
        assert.equal(await page.locator('#ancien').getAttribute('type'), 'password');
        await page.getByRole('button', { name: 'Afficher' }).first().click();
        assert.equal(await page.locator('#ancien').getAttribute('type'), 'text');
        // Avis : une note, un libellé
        await page.goto(base + '/reservation/2/avis');
        await page.getByRole('radio', { name: '4 étoile(s)' }).click();
        await page.getByText('4 / 5 · Très bien').waitFor();
        // Signalement : texte trop court
        await page.goto(base + '/reservation/1/probleme');
        await page.locator('#motif').fill('court');
        await page.getByRole('button', { name: 'Envoyer à l\'équipe Avelis' }).click();
        await page.getByText('10 caractères au minimum').waitFor();
    }
    await context.close();
}
assert.deepEqual(erreurs, [], 'Erreurs JavaScript : ' + erreurs.join(' | '));
await browser.close();
console.log('Parcours espace voyageur : OK');
