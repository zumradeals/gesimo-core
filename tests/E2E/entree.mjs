// Entrée du visiteur dans un vrai navigateur : connexion (afficher le mot de passe, erreur, succès), inscription (cartes du profil),
// lien expiré, invitation expirée, page 404, absence de débordement de 320 à 2 560 px. Données : tests/Support/preparer-reservation.php.
import { createRequire } from 'node:module';
import assert from 'node:assert/strict';
const [runtime, output, base = 'http://127.0.0.1:8123'] = process.argv.slice(2);
assert(['127.0.0.1', 'localhost'].includes(new URL(base).hostname));
const { chromium } = createRequire(runtime + '/package.json')('playwright');
const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'fr-FR', serviceWorkers: 'block' });
const page = await context.newPage();
const errors = [];
page.on('pageerror', e => errors.push(e.message));
const debordement = async w => { await page.setViewportSize({ width: w, height: 900 }); return page.evaluate(() => document.documentElement.scrollWidth > innerWidth); };
const LARGEURS = [320, 390, 768, 1024, 1366, 1920, 2560];
try {
    // Connexion
    await page.goto(`${base}/connexion`);
    await page.getByRole('heading', { name: 'Se connecter' }).waitFor();
    assert.equal(await page.locator('.en-panneau').isVisible(), true);
    assert.equal(await page.locator('.en-photo').isVisible(), true, 'La photo d\'ambiance est visible sur ordinateur.');
    const mdp = page.locator('#motDePasse');
    await mdp.fill('un-mot-de-passe');
    assert.equal(await mdp.getAttribute('type'), 'password');
    await page.getByRole('button', { name: 'Afficher' }).click();
    assert.equal(await mdp.getAttribute('type'), 'text', 'Le bouton Afficher montre le mot de passe.');
    await page.getByRole('button', { name: 'Masquer' }).click();
    assert.equal(await mdp.getAttribute('type'), 'password');
    await page.getByRole('button', { name: 'Me connecter', exact: true }).click();
    await page.getByText('Indiquez votre numéro de téléphone.').waitFor();
    await page.locator('#telephone').fill('0799100003');
    await page.getByRole('button', { name: 'Me connecter', exact: true }).click();
    await page.getByText('Téléphone ou mot de passe incorrect.').waitFor();
    await page.screenshot({ path: output + '/entree-connexion-erreur-ordinateur.png', fullPage: true });
    for (const w of LARGEURS) assert.equal(await debordement(w), false, `Débordement de la connexion à ${w} px`);
    await page.setViewportSize({ width: 390, height: 844 });
    const formu = await page.locator('.en-formu').boundingBox(); const panneau = await page.locator('.en-panneau').boundingBox();
    assert(formu.y < panneau.y, 'Sur téléphone, le formulaire passe avant le panneau.');
    await page.screenshot({ path: output + '/entree-connexion-telephone.png', fullPage: true });

    // Inscription
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto(`${base}/inscription`);
    await page.getByRole('heading', { name: 'Créer un compte' }).waitFor();
    assert.equal(await page.getByText('contrôlée avant la publication').count(), 0);
    await page.getByLabel('Proposer ma résidence', { exact: false }).check();
    await page.getByText('contrôlée avant la publication de votre première annonce').waitFor();
    await page.getByRole('button', { name: 'Afficher' }).click();
    assert.equal(await page.locator('#motDePasse').getAttribute('type'), 'text');
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await page.locator('.erreur-champ').first().waitFor();
    await page.screenshot({ path: output + '/entree-inscription-ordinateur.png', fullPage: true });
    for (const w of LARGEURS) assert.equal(await debordement(w), false, `Débordement de l'inscription à ${w} px`);

    // Lien et invitation expirés, page introuvable
    for (const [chemin, titre, statut] of [['/connexion/lien/jeton-invalide', 'Lien expiré', 200], ['/invitation/jeton-invalide', 'Invitation expirée', 200], ['/cette-page-nexiste-pas', 'Cette page n\'existe pas', 404]]) {
        await page.setViewportSize({ width: 1440, height: 900 });
        const reponse = await page.goto(base + chemin);
        assert.equal(reponse.status(), statut);
        await page.getByRole('heading', { name: titre }).waitFor();
        await page.screenshot({ path: output + `/entree${chemin.replace(/[^a-z0-9]+/gi, '-')}-ordinateur.png`, fullPage: true });
        for (const w of LARGEURS) assert.equal(await debordement(w), false, `Débordement de ${chemin} à ${w} px`);
    }

    // Connexion réussie
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto(`${base}/connexion`);
    await page.locator('#telephone').fill('0799100003');
    await page.locator('#motDePasse').fill('mot-de-passe-essai-navigateur');
    await page.getByRole('button', { name: 'Me connecter', exact: true }).click();
    await page.waitForURL(url => !url.pathname.startsWith('/connexion'));
    assert.deepEqual(errors, [], 'Aucune erreur JavaScript.');
    console.log('Entrée du visiteur : OK');
} finally {
    await browser.close();
}
