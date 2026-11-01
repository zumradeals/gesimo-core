// Parcours de réservation dans un vrai navigateur : fiche (arrivée + nuits), connexion sans perdre les dates,
// étape « Vos informations », absence de débordement de 320 à 2 560 px. Données : tests/Support/preparer-reservation.php.
import { createRequire } from 'node:module';
import assert from 'node:assert/strict';
const [runtime, output, base = 'http://127.0.0.1:8123', uniteId = '1'] = process.argv.slice(2);
assert(['127.0.0.1', 'localhost'].includes(new URL(base).hostname));
const { chromium } = createRequire(runtime + '/package.json')('playwright');
const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: { width: 1366, height: 768 }, locale: 'fr-FR', serviceWorkers: 'block' });
const page = await context.newPage();
const errors = [];
page.on('pageerror', e => errors.push(e.message));
const debordement = async w => { await page.setViewportSize({ width: w, height: 900 }); return page.evaluate(() => document.documentElement.scrollWidth > innerWidth); };
// Livewire répond en quelques instants : on attend la fin des échanges, puis on laisse le navigateur se stabiliser.
const livewire = async () => { await page.waitForLoadState('networkidle'); await page.waitForTimeout(400); };
const compteur = (zone, n) => page.waitForFunction(([sel, motif]) => new RegExp(motif).test(document.querySelector(sel)?.innerText ?? ''), [`${zone} .rv-compteur output`, `^${n}\\s*nuit`]);

const vendredi = new Date(); vendredi.setDate(vendredi.getDate() + 30); vendredi.setDate(vendredi.getDate() + ((5 - vendredi.getDay() + 7) % 7));
const arrivee = vendredi.toISOString().slice(0, 10);

try {
    // 1. Fiche : date d'arrivée puis nombre de nuits.
    await page.goto(`${base}/logement/${uniteId}`);
    await page.locator('#arrivee').fill(arrivee);
    await livewire();
    const encadre = page.locator('#resa');
    assert.match(await encadre.locator('.rv-compteur output').first().innerText(), /^1\s*nuit/);
    await encadre.getByRole('button', { name: 'Une nuit de plus' }).click(); await compteur('#resa', 2);
    await encadre.getByRole('button', { name: 'Une nuit de plus' }).click(); await compteur('#resa', 3);
    assert.match(await encadre.locator('.rv-compteur output').first().innerText(), /^3\s*nuits/);
    await encadre.getByRole('button', { name: '1 semaine' }).click(); await compteur('#resa', 7);
    assert.match(await encadre.locator('.rv-compteur output').first().innerText(), /^7\s*nuits/);
    assert.match(await encadre.locator('.rv-depart').innerText(), /avant 11 h/);
    assert.match(await encadre.innerText(), /Total, sans frais cachés/);
    assert.match(await encadre.innerText(), /Annulation gratuite jusqu'au/);
    const plus = encadre.getByRole('button', { name: 'Un voyageur de plus' });
    assert.equal(await plus.isDisabled(), true, '2 voyageurs au maximum : le bouton + est désactivé.');
    await encadre.getByRole('button', { name: 'Un voyageur de moins' }).click();
    await page.waitForFunction(() => /^1\s*personne\b/.test(document.querySelectorAll('#resa .rv-compteur output')[1]?.innerText ?? ''));
    assert.equal(await plus.isDisabled(), false);
    await plus.click();
    await page.waitForFunction(() => /^2\s*personnes/.test(document.querySelectorAll('#resa .rv-compteur output')[1]?.innerText ?? ''));
    await page.screenshot({ path: output + '/reservation-fiche-ordinateur.png', fullPage: true });

    // 2. Pas de débordement, de 320 à 2 560 px.
    for (const w of [320, 390, 768, 1024, 1366, 1920, 2560]) assert.equal(await debordement(w), false, `Débordement de la fiche à ${w} px`);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.screenshot({ path: output + '/reservation-fiche-telephone.png', fullPage: true });

    // 3. « Réserver » sans compte : connexion avec les dates conservées, puis retour sur la réservation.
    await page.setViewportSize({ width: 1366, height: 768 });
    await page.locator('#resa a.btn-pl').click();
    await page.waitForURL(/\/connexion/);
    assert.match(await page.locator('main').innerText(), /Vos dates sont conservées/);
    await page.locator('#telephone').fill('0799100003');
    await page.locator('#motDePasse').fill('mot-de-passe-essai-navigateur');
    await page.getByRole('button', { name: 'Me connecter' }).click();
    await page.waitForURL(/\/reserver\/\d+/);
    assert.match(page.url(), new RegExp(`arrivee=${arrivee}`));

    // 4. Étape « Vos informations ».
    await page.getByRole('heading', { name: 'Quelques informations avant de payer' }).waitFor();
    assert.equal(await page.locator('#nom').inputValue(), 'ESSAI Voyageur', 'Le nom est prérempli.');
    const continuer = page.getByRole('button', { name: 'Continuer vers le paiement' });
    assert.equal(await continuer.isDisabled(), true, 'Le bouton reste grisé tant que la case d\'accord n\'est pas cochée.');
    await page.locator('.rv-case input').check(); await livewire();
    assert.equal(await continuer.isDisabled(), false);
    await continuer.click(); await livewire();
    assert.match(await page.locator('.rv-formulaire').innerText(), /Choisissez le motif de votre séjour/);
    await page.getByLabel('Événement (mariage, fête, cérémonie)').check(); await livewire();
    assert.match(await page.locator('.rv-formulaire').innerText(), /Lisez le règlement de la résidence/);
    await page.getByLabel('Autre', { exact: true }).check(); await livewire();
    await continuer.click(); await livewire();
    assert.match(await page.locator('.rv-formulaire').innerText(), /Précisez en quelques mots/);
    await page.locator('#precision').fill('Entretien d\'embauche');
    for (const w of [320, 390, 768, 1024, 1366, 1920, 2560]) assert.equal(await debordement(w), false, `Débordement de l'étape informations à ${w} px`);
    await page.setViewportSize({ width: 1366, height: 768 });
    await page.screenshot({ path: output + '/reservation-informations-ordinateur.png', fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.screenshot({ path: output + '/reservation-informations-telephone.png', fullPage: true });
    const boite = await continuer.boundingBox();
    assert(boite && boite.y + boite.height <= 844, 'Sur téléphone, le bouton reste visible en bas de l\'écran.');
    await page.setViewportSize({ width: 1366, height: 768 });
    await continuer.click();
    // Le paiement en ligne n'est pas configuré dans cet environnement : les dates sont gardées et la liste des réservations s'ouvre.
    await page.waitForURL(/\/(mes-reservations|payer\/\d+)/);
    assert.match(await page.locator('main').innerText(), /Vos dates sont gardées|Payez pour confirmer/);
    await page.screenshot({ path: output + '/reservation-apres-gardes.png', fullPage: true });

    // 5. Mes réservations : bandeau « À faire en premier », espace client à la charte orange, aucun débordement.
    await page.goto(`${base}/mes-reservations`);
    await page.getByRole('heading', { name: 'Payez pour garder vos dates' }).waitFor();
    assert.equal(await page.locator('body.espace-client').count(), 1);
    assert.match(await page.locator('.mr-liste').innerText(), /En attente de paiement/);
    assert.match(await page.locator('.mr-liste').innerText(), /Annuler cette réservation/);
    assert.equal(await page.locator('.adm-side').evaluate(el => getComputedStyle(el).backgroundColor), 'rgb(23, 36, 59)', 'Le menu latéral est bleu nuit.');
    for (const w of [320, 390, 768, 1024, 1366, 1920, 2560]) assert.equal(await debordement(w), false, `Débordement de Mes réservations à ${w} px`);
    await page.setViewportSize({ width: 1366, height: 768 });
    await page.screenshot({ path: output + '/mes-reservations-ordinateur.png', fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.screenshot({ path: output + '/mes-reservations-telephone.png', fullPage: true });

    // 6. Détail d'une réservation en attente.
    await page.setViewportSize({ width: 1366, height: 768 });
    await page.locator('.mr-liste h3 a').first().click();
    await page.waitForURL(/\/reservation\/\d+$/);
    await page.getByText('Dates gardées pour vous').waitFor();
    assert.doesNotMatch(await page.locator('main').innerText(), /Adresse/, 'Pas d\'adresse avant la confirmation.');
    for (const w of [320, 390, 768, 1024, 1366, 1920, 2560]) assert.equal(await debordement(w), false, `Débordement du détail à ${w} px`);
    await page.setViewportSize({ width: 1366, height: 768 });
    await page.screenshot({ path: output + '/mes-reservations-detail-ordinateur.png', fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.screenshot({ path: output + '/mes-reservations-detail-telephone.png', fullPage: true });

    // 7. Annuler depuis la liste (réservation non payée) : confirmation puis disparition.
    await page.setViewportSize({ width: 1366, height: 768 });
    await page.goto(`${base}/mes-reservations`);
    page.once('dialog', d => d.accept());
    await page.getByRole('button', { name: 'Annuler cette réservation' }).first().click();
    await page.getByText('Réservation annulée.').waitFor();
    assert.deepEqual(errors, [], 'Aucune erreur JavaScript.');
    console.log('Parcours de réservation : OK');
} finally {
    await browser.close();
}
