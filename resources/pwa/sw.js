/* Avelis Séjours — service worker (version __VERSION__).
 *
 * Règles de sécurité (Lot 6) :
 *  - on n'enregistre QUE les pages que le serveur marque « X-Gesimo-Cache: public » (visiteur) ou « prive » (client connecté) ;
 *  - jamais le paiement, les reçus, les pièces d'identité, l'argent, les écrans gérant/admin (liste NE_JAMAIS, en plus du serveur) ;
 *  - la copie « privée » est effacée à la déconnexion, après 7 jours, et dès que le serveur demande de se reconnecter ;
 *  - aucune requête qui modifie quelque chose (POST…) n'est jamais mise en attente ni rejouée : pas de double paiement possible.
 */
'use strict';

const VERSION = '__VERSION__';
const C_STATIQUE = 'statique-' + VERSION;
const C_PUBLIC = 'public-' + VERSION;
const C_PRIVE = 'prive-' + VERSION;
const PAGE_HORS_CONNEXION = '/hors-connexion';
const AGE_MAX_PRIVE_MS = 7 * 24 * 3600 * 1000;
const DELAI_RESEAU_FAIBLE_MS = 4000;
const MAX_PAGES = 40;
const MAX_IMAGES = 60;
// Le serveur compresse (« Vary: Accept-Encoding ») : sans cette option, une copie enregistrée ne serait jamais retrouvée.
const OPTIONS_MATCH = { ignoreVary: true };

const NE_JAMAIS = [
  /^\/(payer|paiement|reserver|admin|gerant|webhooks|installation|connexion|inscription|invitation|compte|deconnexion)(\/|$)/,
  /^\/reservation\/\d+\/(confirmation|annuler|recu|avis|probleme)(\/|$)/,
  /^\/livewire(?!-[^/]+\/livewire(\.min)?\.js)/,
];
const STATIQUES = [/^\/build\/assets\//, /^\/icons\//, /^\/livewire-[^/]+\/livewire(\.min)?\.js$/];
const IMAGES = [/^\/storage\/(photos|logo)\//];

function classer(url, methode) {
  const u = new URL(url, 'https://exemple.invalid');
  const chemin = u.pathname;
  if (methode && methode !== 'GET') return 'jamais';
  if (NE_JAMAIS.some((r) => r.test(chemin))) return 'jamais';
  if (STATIQUES.some((r) => r.test(chemin))) return 'statique';
  if (IMAGES.some((r) => r.test(chemin))) return 'image';
  return 'page';
}

/** Adresse sans le paramètre « source=app » du lancement de l'application : « / » et « /?source=app » sont la même page. */
function cle(url) {
  const u = new URL(url);
  u.searchParams.delete('source');
  u.hash = '';
  return u.toString();
}

async function viderPrive() {
  const noms = await caches.keys();
  await Promise.all(noms.filter((n) => n.startsWith('prive-')).map((n) => caches.delete(n)));
}

async function limiter(nomCache, max) {
  const cache = await caches.open(nomCache);
  const cles = await cache.keys();
  for (let i = 0; i < cles.length - max; i++) await cache.delete(cles[i]);
}

async function avecHorodatage(reponse) {
  const entetes = new Headers(reponse.headers);
  entetes.set('X-Gesimo-Enregistre', String(Date.now()));
  return new Response(await reponse.clone().blob(), { status: reponse.status, statusText: reponse.statusText, headers: entetes });
}

async function copieEnregistree(url) {
  const k = cle(url);
  const prive = await (await caches.open(C_PRIVE)).match(k, OPTIONS_MATCH);
  if (prive) {
    const age = Date.now() - Number(prive.headers.get('X-Gesimo-Enregistre') || 0);
    if (age < AGE_MAX_PRIVE_MS) return prive;
    await (await caches.open(C_PRIVE)).delete(k);
  }
  return (await (await caches.open(C_PUBLIC)).match(k, OPTIONS_MATCH)) || null;
}

async function pageHorsConnexion() {
  const r = await (await caches.open(C_STATIQUE)).match(PAGE_HORS_CONNEXION, OPTIONS_MATCH);
  return r || new Response('Vous êtes hors connexion.', { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
}

/** Enregistre une réponse de page SI le serveur l'autorise ; efface la copie privée si le serveur demande de se reconnecter. */
async function memoriser(requete, reponse) {
  const marque = reponse.headers.get('X-Gesimo-Cache');
  const k = cle(requete.url);

  const cheminDemande = new URL(requete.url).pathname;
  if (reponse.type === 'opaqueredirect' && /^\/(mes-reservations|reservation\/\d+)\/?$/.test(cheminDemande)) {
    await viderPrive(); // le serveur renvoie ailleurs (reconnexion) : la session est finie
    return;
  }
  if (reponse.redirected && /\/connexion(\/|$|\?)/.test(new URL(reponse.url).pathname)) {
    await viderPrive(); // session expirée : plus aucune donnée personnelle sur ce téléphone
    return;
  }
  // Le marquage du serveur est l'autorisation explicite (Livewire ajoute « no-store » à toutes les pages : ce n'est pas un critère).
  if (!reponse.ok || reponse.status !== 200 || classer(reponse.url || requete.url) !== 'page') return;
  if (marque === 'public') {
    const cache = await caches.open(C_PUBLIC);
    await cache.put(k, reponse.clone());
    await limiter(C_PUBLIC, MAX_PAGES);
  } else if (marque === 'prive') {
    const cache = await caches.open(C_PRIVE);
    await cache.put(k, await avecHorodatage(reponse));
    await limiter(C_PRIVE, MAX_PAGES);
  } else {
    // Page non autorisée à être enregistrée : si une ancienne copie privée existe pour cette adresse, on l'efface.
    await (await caches.open(C_PRIVE)).delete(k);
  }
}

async function traiterNavigation(evenement) {
  const requete = evenement.request;
  const type = classer(requete.url, requete.method);
  if (type === 'jamais') {
    try {
      return await fetch(requete);
    } catch (e) {
      return pageHorsConnexion(); // jamais de copie pour ces pages
    }
  }

  const copie = await copieEnregistree(requete.url);
  const reseau = fetch(requete).then(async (reponse) => {
    evenement.waitUntil(memoriser(requete, reponse.clone()).catch(() => {}));
    return reponse;
  });

  try {
    if (copie) {
      // Réseau faible : après 4 secondes, on montre la copie enregistrée pendant que la page fraîche finit de charger.
      return await Promise.race([reseau, new Promise((res) => setTimeout(() => res(copie), DELAI_RESEAU_FAIBLE_MS))]);
    }
    return await reseau;
  } catch (e) {
    return copie || (await pageHorsConnexion());
  }
}

async function traiterRessource(evenement, type) {
  const nom = type === 'statique' ? C_STATIQUE : C_STATIQUE; // images et fichiers du site dans le même cache versionné
  const cache = await caches.open(nom);
  const connue = await cache.match(evenement.request, OPTIONS_MATCH);
  const reseau = fetch(evenement.request).then(async (reponse) => {
    if (reponse.ok && reponse.status === 200) {
      await cache.put(evenement.request, reponse.clone());
      if (type === 'image') await limiter(C_STATIQUE, MAX_IMAGES + 30);
    }
    return reponse;
  });
  if (connue) {
    evenement.waitUntil(reseau.catch(() => {})); // mise à jour discrète
    return connue;
  }
  return reseau;
}

self.addEventListener('install', (evenement) => {
  // On ne prend PAS la main tout de suite : la mise à jour attend que la personne la demande (bouton « Mettre à jour »).
  evenement.waitUntil(
    caches.open(C_STATIQUE).then((cache) => cache.addAll([PAGE_HORS_CONNEXION, '/icons/icone-192.png'])),
  );
});

self.addEventListener('activate', (evenement) => {
  evenement.waitUntil((async () => {
    const noms = await caches.keys();
    await Promise.all(noms.filter((n) => ![C_STATIQUE, C_PUBLIC, C_PRIVE].includes(n)).map((n) => caches.delete(n)));
    await self.clients.claim();
  })());
});

self.addEventListener('fetch', (evenement) => {
  const requete = evenement.request;
  const url = new URL(requete.url);
  if (url.origin !== self.location.origin) return;

  // Déconnexion : on efface la copie privée AVANT d'envoyer la demande (même si le réseau est coupé).
  if (requete.method === 'POST' && url.pathname === '/deconnexion') {
    evenement.respondWith((async () => { await viderPrive(); return fetch(requete); })());
    return;
  }
  if (requete.method !== 'GET') return; // POST, etc. : toujours directement au serveur, jamais rejoués

  const type = classer(requete.url, requete.method);
  if (requete.mode === 'navigate' || (requete.headers.get('Accept') || '').includes('text/html')) {
    evenement.respondWith(traiterNavigation(evenement));
  } else if (type === 'statique' || type === 'image') {
    evenement.respondWith(traiterRessource(evenement, type));
  }
});

self.addEventListener('message', (evenement) => {
  const donnees = evenement.data || {};
  if (donnees.type === 'vider-prive') {
    evenement.waitUntil(viderPrive().then(() => evenement.source && evenement.source.postMessage({ type: 'prive-vide' })));
  } else if (donnees.type === 'passer-a-la-nouvelle-version') {
    self.skipWaiting();
  } else if (donnees.type === 'version') {
    evenement.source && evenement.source.postMessage({ type: 'version', version: VERSION });
  }
});

if (typeof module !== 'undefined' && module.exports) module.exports = { classer, cle, VERSION };
