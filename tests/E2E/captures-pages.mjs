// Captures de pages représentatives de chaque espace (public, voyageur, gérant, administration), pour comparer avant et après
// une évolution visuelle. Données : tests/Support/preparer-reservation.php. Usage : node captures-pages.mjs <runtime> <dossier> [base] [slug]
import { createRequire } from 'node:module';
import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
const [runtime, output, base = 'http://127.0.0.1:8123', slug = ''] = process.argv.slice(2);
assert(['127.0.0.1', 'localhost'].includes(new URL(base).hostname));
const { chromium } = createRequire(runtime + '/package.json')('playwright');
mkdirSync(output, { recursive: true });
const browser = await chromium.launch({ headless: true });
const MDP = 'mot-de-passe-essai-navigateur';
const espaces = {
    public: { tel: null, pages: ['/connexion', '/inscription', '/recherche', '/devenir-partenaire', '/a-propos', '/contact', '/faq', '/conditions', slug ? `/hebergeurs/${slug}` : null, '/logement/1'] },
    voyageur: { tel: '0799100003', pages: ['/compte', '/compte/profil', '/mes-reservations'] },
    gerant: { tel: '0799100001', pages: ['/gerant/arrivees', '/gerant/reservations', '/gerant/calendrier', '/gerant/telephone', '/gerant/residences', '/gerant/residences/1', '/gerant/profil', '/gerant/argent', '/gerant/mise-en-avant', '/gerant/personnel', '/gerant/aide'] },
    admin: { tel: '0799100002', pages: ['/admin', '/admin/residences', '/admin/reservations', '/admin/litiges', '/admin/paiements', '/admin/reversements', '/admin/controle', '/admin/journal', '/admin/messages', '/admin/reglages/general'] },
};
const erreurs = [];
for (const [nom, { tel, pages }] of Object.entries(espaces)) {
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'fr-FR', serviceWorkers: 'block' });
    const page = await context.newPage();
    page.on('pageerror', e => erreurs.push(`${nom}: ${e.message}`));
    if (tel) {
        await page.goto(base + '/connexion');
        await page.locator('#telephone').fill(tel);
        await page.locator('#motDePasse').fill(MDP);
        await page.getByRole('button', { name: 'Me connecter' }).click();
        await page.waitForURL(url => !url.pathname.startsWith('/connexion'));
    }
    for (const chemin of pages.filter(Boolean)) {
        const reponse = await page.goto(base + chemin);
        await page.waitForLoadState('networkidle');
        await page.evaluate(() => document.querySelectorAll('img').forEach(i => { i.loading = 'eager'; }));
        const fichier = `${nom}${chemin.replace(/[^a-z0-9]+/gi, '-')}.png`;
        await page.screenshot({ path: `${output}/${fichier}`, fullPage: true });
        console.log(reponse.status(), chemin);
    }
    await context.close();
}
if (erreurs.length) console.log('ERREURS JS', erreurs);
await browser.close();
