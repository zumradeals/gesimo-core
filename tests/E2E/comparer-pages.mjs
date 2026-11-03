// Capture d'une grande série de pages (public, voyageur, gérant, administration) à deux largeurs, avec l'empreinte de chaque image :
// sert à prouver qu'un nettoyage de styles ne change rien à l'affichage (même empreinte avant et après).
// Données : tests/Support/preparer-reservation.php puis preparer-espace-voyageur.php.
// Usage : node comparer-pages.mjs <runtime> <dossier> <slug-gerant> [base]
import { createRequire } from 'node:module';
import assert from 'node:assert/strict';
import { mkdirSync, writeFileSync, readFileSync } from 'node:fs';
import { createHash } from 'node:crypto';
const [runtime, output, slug, base = 'http://127.0.0.1:8123'] = process.argv.slice(2);
assert(['127.0.0.1', 'localhost'].includes(new URL(base).hostname));
const { chromium } = createRequire(runtime + '/package.json')('playwright');
mkdirSync(output, { recursive: true });
const browser = await chromium.launch({ headless: true });
const MDP = 'mot-de-passe-essai-navigateur';
const espaces = {
    public: { tel: null, pages: ['/', '/recherche', '/logement/1', '/devenir-partenaire', '/a-propos', '/faq', '/contact', '/conditions', '/confidentialite', '/mentions-legales', '/connexion', '/inscription', `/hebergeurs/${slug}`, '/page-introuvable'] },
    voyageur: { tel: '0799100003', pages: ['/compte', '/compte/profil', '/mes-reservations', '/reservation/1', '/reservation/1/venir', '/reservation/1/annuler', '/reservation/2/avis', '/reservation/1/probleme', '/reservation/1/recu', '/reservation/3/venir'] },
    gerant: { tel: '0799100001', pages: ['/gerant/arrivees', '/gerant/reservations', '/gerant/calendrier', '/gerant/telephone', '/gerant/residences', '/gerant/residences/1', '/gerant/residences/1/acces', '/gerant/profil', '/gerant/verification', '/gerant/argent', '/gerant/mise-en-avant', '/gerant/mise-en-avant/nouvelle', '/gerant/personnel', '/gerant/aide'] },
    admin: { tel: '0799100002', pages: ['/admin', '/admin/residences', '/admin/residences/1', '/admin/reservations', '/admin/litiges', '/admin/paiements', '/admin/reversements', '/admin/controle', '/admin/mises-en-avant', '/admin/journal', '/admin/messages', '/admin/reglages', '/admin/reglages/reservation', '/admin/reglages/tests'] },
};
const empreintes = {};
for (const [w, nom] of [[1440, 'ord'], [390, 'tel']]) {
    for (const [espace, { tel, pages }] of Object.entries(espaces)) {
        const context = await browser.newContext({ viewport: { width: w, height: 900 }, locale: 'fr-FR', serviceWorkers: 'block', reducedMotion: 'reduce' });
        const page = await context.newPage();
        if (tel) {
            await page.goto(base + '/connexion');
            await page.locator('#telephone').fill(tel);
            await page.locator('#motDePasse').fill(MDP);
            await page.getByRole('button', { name: 'Me connecter' }).click();
            await page.waitForURL(url => !url.pathname.startsWith('/connexion'));
        }
        for (const chemin of pages) {
            await page.goto(base + chemin);
            await page.waitForLoadState('networkidle');
            await page.addStyleTag({ content: '*,*::before,*::after{animation:none!important;transition:none!important;caret-color:transparent!important}' });
            await page.evaluate(() => document.querySelectorAll('img').forEach(i => { i.loading = 'eager'; }));
            await page.waitForTimeout(150);
            const fichier = `${nom}-${espace}${chemin.replace(/[^a-z0-9]+/gi, '-')}.png`;
            await page.screenshot({ path: `${output}/${fichier}`, fullPage: true });
            empreintes[fichier] = createHash('sha256').update(readFileSync(`${output}/${fichier}`)).digest('hex');
        }
        await context.close();
    }
}
writeFileSync(`${output}/empreintes.json`, JSON.stringify(empreintes, null, 1));
console.log(Object.keys(empreintes).length, 'captures');
await browser.close();
