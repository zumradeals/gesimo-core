// Catalogue Logements v3 dans un vrai navigateur : cartes pleine largeur, communes rapides, filtres et puces, type de logement,
// arrivée + nuits, états vides, dialogue des filtres sur téléphone, formulaire de l'accueil.
// Données : tests/Support/preparer-reservation.php puis tests/Support/preparer-catalogue.php (9 logements, dont 8 pour 2 voyageurs : Cocody 4, Marcory 2, Bingerville 1, Plateau 1).
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
const cards = page.locator('.lo-card[data-logement]');
const attendreCartes = n => page.waitForFunction(k => document.querySelectorAll('.lo-card[data-logement]').length === k, n);
const colonnes = () => page.locator('.lo-grid:not(.lo-squelette)').first().evaluate(el => getComputedStyle(el).gridTemplateColumns.split(' ').length);
const date = delta => { const d = new Date(); d.setUTCDate(d.getUTCDate() + delta); return d.toISOString().slice(0, 10); };
try {
    await page.goto(base + '/recherche');
    await page.waitForFunction(() => !!document.querySelector('[x-data="filtresLogements"]')._x_dataStack);
    assert.equal(await cards.count(), 8, 'Le catalogue présente les logements publiés pour 2 voyageurs (la chambre pour 1 personne est exclue).');
    assert.equal(await page.getByRole('heading', { name: 'Trouvez votre logement' }).count(), 1);
    assert.equal(await page.locator('.lo-ambiance').count(), 0, 'Plus de grand bandeau : les logements apparaissent tout de suite.');
    const premiere = await cards.first().boundingBox();
    assert(premiere.y < 1000, 'Les premiers logements sont visibles sans défilement sur ordinateur.');
    assert.equal(await page.getByText('Tarif de référence', { exact: true }).first().isVisible(), true);
    assert(await page.locator('#budget').isDisabled());

    // Toute la largeur, sur toutes les tailles d'écran
    const attendu = { 320: [1, 1], 390: [1, 1], 768: [2, 3], 1024: [2, 4], 1366: [3, 4], 1920: [5, 7], 2560: [6, 10] };
    for (const [w, [min, max]] of Object.entries(attendu)) {
        await page.setViewportSize({ width: +w, height: 900 });
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `Débordement à ${w} px`);
        const c = await colonnes();
        assert(c >= min && c <= max, `${c} colonnes de cartes à ${w} px (attendu de ${min} à ${max}).`);
    }
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.screenshot({ path: output + '/logements-ordinateur.png', fullPage: true });

    // Communes rapides
    await page.getByRole('button', { name: /^Marcory/ }).click();
    await attendreCartes(2);
    assert.equal(await page.getByRole('button', { name: /^Marcory/ }).getAttribute('aria-pressed'), 'true');
    assert.equal(await page.locator('.lo-location').filter({ hasNotText: 'Marcory' }).count(), 0);
    await page.getByRole('button', { name: /^Toutes/ }).click();
    await attendreCartes(8);

    // Filtres, puces et type de logement
    await page.getByLabel('Wi-Fi', { exact: true }).check();
    await page.locator('.lo-puce').filter({ hasText: 'Wi-Fi' }).waitFor();
    await page.getByLabel('Chambre', { exact: true }).check();
    await page.locator('.lo-puce').filter({ hasText: 'Chambre' }).waitFor();
    assert.equal(await page.locator('.lo-filter-button').innerText().then(t => /Filtres/.test(t)), true);
    await page.locator('.lo-puce').filter({ hasText: 'Wi-Fi' }).getByRole('button').click();
    await page.waitForFunction(() => ![...document.querySelectorAll('.lo-puce')].some(p => p.textContent.includes('Wi-Fi')));
    await attendreCartes(1); // une seule chambre accueille 2 voyageurs
    await page.getByRole('button', { name: 'Tout effacer' }).click();
    await attendreCartes(8);
    assert.equal(await page.locator('.lo-puce').count(), 0);

    // Aucun résultat : des boutons précis
    await page.getByRole('button', { name: /^Plateau/ }).click();
    await attendreCartes(1);
    await page.getByLabel('Chambre', { exact: true }).check();
    await page.getByRole('heading', { name: 'Essayons d’autres critères.', exact: true }).waitFor();
    assert.equal(await page.getByRole('button', { name: 'Retirer « Chambre »' }).count(), 1);
    await page.getByRole('button', { name: 'Toutes les communes' }).click();
    await attendreCartes(1);
    await page.getByRole('button', { name: 'Tout effacer' }).click();
    await attendreCartes(8);

    // Arrivée + nombre de nuits
    await page.locator('#arrivee').fill(date(40));
    await page.waitForFunction(() => /1 nuit/.test(document.querySelector('.lo-cpt output').innerText));
    await page.getByRole('button', { name: 'Une nuit de plus' }).click();
    await page.getByRole('button', { name: 'Une nuit de plus' }).click();
    await page.waitForFunction(() => /3 nuits/.test(document.querySelector('.lo-cpt output').innerText));
    await page.waitForFunction(() => /Départ : .*avant 11 h/.test(document.querySelector('.lo-depart').innerText));
    await page.getByRole('button', { name: 'Rechercher', exact: true }).click();
    await page.getByText('Montant du séjour', { exact: true }).first().waitFor();
    assert.equal(await page.getByText('Tarif de référence', { exact: true }).count(), 0);
    assert(await page.getByText('en moyenne').count() > 0, 'Chaque carte donne aussi le prix moyen par nuit.');
    assert(await page.locator('#budget').isEnabled());
    await page.locator('#budget').fill('1');
    await page.getByRole('heading', { name: 'Essayons d’autres critères.', exact: true }).waitFor();
    assert.equal(await page.getByRole('button', { name: 'Changer les dates' }).count(), 1);
    await page.locator('#budget').fill('');
    await attendreCartes(8);
    const lien = new URL(await page.locator('.lo-details').first().getAttribute('href'), base);
    assert.equal(lien.searchParams.get('arrivee'), date(40));
    assert.equal(lien.searchParams.get('depart'), date(43));
    assert.equal(lien.searchParams.get('personnes'), '2');
    await page.screenshot({ path: output + '/logements-avec-dates-ordinateur.png', fullPage: true });

    // Dates incorrectes : message simple, le catalogue reste là
    await page.locator('#arrivee').fill(date(-3));
    await page.getByRole('button', { name: 'Rechercher', exact: true }).click();
    await page.getByText('La date d\'arrivée ne peut pas être dans le passé.').waitFor();

    // Téléphone : les filtres s'ouvrent dans un dialogue
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(base + '/recherche');
    await page.waitForFunction(() => !!document.querySelector('[x-data="filtresLogements"]')._x_dataStack);
    await page.locator('.lo-filter-button').click();
    assert(await page.locator('.lo-panel').evaluate(el => el.matches(':modal')));
    await page.locator('.lo-panel').getByLabel('Studio', { exact: true }).check();
    await attendreCartes(3);
    assert(await page.locator('.lo-panel').evaluate(el => el.matches(':modal')), 'Les mises à jour gardent le dialogue ouvert.');
    assert.match(await page.locator('.lo-apply').innerText(), /Afficher 3 logements/);
    await page.screenshot({ path: output + '/logements-filtres-telephone.png' });
    await page.locator('.lo-apply').click();
    assert(await page.locator('.lo-panel').evaluate(el => !el.open));
    await page.screenshot({ path: output + '/logements-telephone.png', fullPage: true });

    // Formulaire de l'accueil : arrivée + nuits
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto(base + '/');
    await page.locator('#accueil-commune').selectOption('Cocody');
    await page.locator('#accueil-arrivee').fill(date(50));
    await page.getByRole('button', { name: 'Une nuit de plus' }).click();
    await page.getByRole('button', { name: 'Une nuit de plus' }).click();
    assert.equal(await page.locator('#accueil-nuits').inputValue(), '3');
    await page.getByRole('button', { name: 'Rechercher un logement', exact: true }).click();
    await page.waitForURL(u => u.pathname === '/recherche' && u.searchParams.get('nuits') === '3');
    await page.getByText('Montant du séjour', { exact: true }).first().waitFor();
    assert.match(await page.locator('.lo-depart').innerText(), /Départ :/);
    assert.deepEqual(errors, [], 'Aucune erreur JavaScript.');
    console.log('Catalogue Logements v3 : OK');
} catch (e) {
    await page.screenshot({ path: output + '/echec-logements.png', fullPage: true }).catch(() => {});
    throw e;
} finally {
    await browser.close();
}
