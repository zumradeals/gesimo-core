/*
 * Application installable, mode hors connexion et mises à jour (Lot 6).
 * Aucun appel réseau n'est jamais rejoué automatiquement : c'est ce qui empêche un double paiement.
 */
// Ce fichier est chargé APRÈS l'affichage de la page (voir app.js) : les événements « page prête » ont déjà eu lieu.
const quandPret = (f) => (document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', f) : f());
const quandCharge = (f) => (document.readyState === 'complete' ? f() : window.addEventListener('load', f));

const sansStockage = (f, defaut = null) => { try { return f(); } catch (e) { return defaut; } };
const lire = () => sansStockage(() => {
  const d = JSON.parse(localStorage.getItem('gesimo:pwa') || '{}');
  return d && typeof d === 'object' && !Array.isArray(d) ? d : {};
}, {});
const ecrire = (d) => sansStockage(() => localStorage.setItem('gesimo:pwa', JSON.stringify(d)));

const CHEMINS_SENSIBLES = /^\/(payer|paiement|reserver|connexion|inscription|installation|invitation)(\/|$)|^\/reservation\/\d+\/(annuler|confirmation)/;
const surPageSensible = () => CHEMINS_SENSIBLES.test(location.pathname);
const installee = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
const ua = navigator.userAgent;
const estIOS = /iPhone|iPad|iPod/.test(ua);
const estSafariIOS = estIOS && /Safari/.test(ua) && !/CriOS|FxiOS|EdgiOS|GSA|Instagram|FBAN|FBAV/.test(ua);

let zone;
function zoneBandeaux() {
  if (!zone) {
    zone = document.createElement('div');
    zone.id = 'pwa-bandeaux';
    zone.className = 'pointer-events-none fixed inset-x-3 bottom-[76px] z-40 mx-auto grid max-w-xl gap-2 lg:bottom-4';
    zone.setAttribute('aria-live', 'polite');
    document.body.appendChild(zone);
  }
  return zone;
}

function bandeau(id, html, ton = 'vert') {
  document.getElementById(id)?.remove();
  const couleurs = { vert: 'border-marque-600', ambre: 'border-amber-400', rouge: 'border-red-600' };
  const el = document.createElement('div');
  el.id = id;
  el.className = `pointer-events-auto rounded-2xl border-2 ${couleurs[ton]} bg-white p-3 text-[15px] shadow-lg`;
  el.innerHTML = html;
  zoneBandeaux().appendChild(el);
  return el;
}
const retirer = (id) => document.getElementById(id)?.remove();
const bouton = (texte, second = false, extra = '') => `<button type="button" class="btn ${second ? 'btn-second' : ''} ${extra}" style="min-height:48px;font-size:16px">${texte}</button>`;

// ------------------------------------------------------------------ service worker et mises à jour
let registration = null;
let rechargementDemande = false;
let miseAJourReportee = sansStockage(() => sessionStorage.getItem('gesimo:pwa:maj-reportee') === '1', false);

function proposerMiseAJour() {
  if (miseAJourReportee || !registration?.waiting || !navigator.serviceWorker.controller) return;
  if (surPageSensible()) return retirer('pwa-maj'); // jamais au milieu d'un paiement ou d'une réservation
  if (document.getElementById('pwa-maj')) return;
  const el = bandeau('pwa-maj', `<p class="mb-2">🔄 <b>Une nouvelle version est disponible.</b></p><div class="grid grid-cols-2 gap-2">${bouton('Mettre à jour', false, 'js-maj')}${bouton('Plus tard', true, 'js-plus-tard')}</div>`);
  el.querySelector('.js-maj').onclick = () => { rechargementDemande = true; registration.waiting.postMessage({ type: 'passer-a-la-nouvelle-version' }); };
  el.querySelector('.js-plus-tard').onclick = () => {
    miseAJourReportee = true;
    sansStockage(() => sessionStorage.setItem('gesimo:pwa:maj-reportee', '1'));
    retirer('pwa-maj');
  };
}

if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
  quandCharge(() => {
    navigator.serviceWorker.register('/sw.js', { scope: '/' }).then((reg) => {
      registration = reg;
      proposerMiseAJour();
      reg.addEventListener('updatefound', () => {
        const nouveau = reg.installing;
        nouveau?.addEventListener('statechange', () => { if (nouveau.state === 'installed') proposerMiseAJour(); });
      });
      document.addEventListener('visibilitychange', () => { if (!document.hidden) reg.update().catch(() => {}); });
      setInterval(proposerMiseAJour, 30000);
    }).catch(() => {});
    navigator.serviceWorker.addEventListener('controllerchange', () => { if (rechargementDemande) location.reload(); });
  });

  // Déconnexion : la copie privée de ce téléphone est effacée (le service worker le refait aussi côté réseau).
  document.addEventListener('submit', (e) => {
    if (e.target?.getAttribute?.('action')?.endsWith('/deconnexion')) {
      navigator.serviceWorker.controller?.postMessage({ type: 'vider-prive' });
      sansStockage(() => { Object.keys(localStorage).filter((k) => k.startsWith('gesimo:vu:')).forEach((k) => localStorage.removeItem(k)); });
    }
  }, true);
}

// ------------------------------------------------------------------ hors connexion / retour du réseau
function majEtatReseau() {
  const hors = !navigator.onLine;
  document.documentElement.classList.toggle('hors-ligne', hors);
  if (hors) {
    retirer('pwa-retour');
    const vu = sansStockage(() => localStorage.getItem('gesimo:vu:' + location.pathname));
    const quand = vu ? new Date(Number(vu)).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }).replace(':', ' h ') : null;
    bandeau('pwa-hors', `<p>📡 <b>Hors connexion.</b> Vous voyez la dernière version enregistrée${quand ? ` (aujourd'hui à ${quand})` : ''}. Payer, réserver ou annuler demande une connexion.</p>`, 'ambre');
  } else if (document.getElementById('pwa-hors')) {
    retirer('pwa-hors');
    const el = bandeau('pwa-retour', `<div class="flex items-center justify-between gap-2"><span>✔ <b>Connexion rétablie.</b></span>${bouton('Actualiser', true, 'js-actu')}</div>`);
    el.querySelector('.js-actu').onclick = () => location.reload();
    if (location.pathname === '/mes-reservations') setTimeout(() => location.reload(), 6000); // simple liste de lecture : mise à jour toute seule
    else setTimeout(() => retirer('pwa-retour'), 15000);
  }
}
window.addEventListener('offline', majEtatReseau);
window.addEventListener('online', majEtatReseau);
quandPret(() => {
  if (navigator.onLine) sansStockage(() => localStorage.setItem('gesimo:vu:' + location.pathname, String(Date.now())));
  majEtatReseau();
});

// Une action (Livewire) qui échoue faute de réseau ne se perd jamais en silence, et n'est jamais renvoyée toute seule.
document.addEventListener('livewire:init', () => {
  if (!window.Livewire?.interceptMessage) return;
  window.Livewire.interceptMessage(({ message, onSend, onFailure, onSuccess }) => {
    // Les rafraîchissements automatiques (wire:poll) ne sont pas des actions de la personne : on ne l'alarme pas pour eux.
    if ([...(message?.actions || [])].length > 0 && [...message.actions].every((a) => a.metadata?.type === 'poll')) return;
    let horsLigneAvantEnvoi = false;
    onSend(() => { horsLigneAvantEnvoi = !navigator.onLine; });
    onSuccess(() => retirer('pwa-echec'));
    onFailure(() => {
      const texte = horsLigneAvantEnvoi || !navigator.onLine
        ? `<p class="mb-2">📡 <b>Pas de connexion.</b> Votre demande n'a <b>pas</b> été envoyée et vous n'avez <b>pas</b> été débité. Vos choix sont conservés : touchez de nouveau le bouton quand le réseau est de retour.</p>`
        : `<p class="mb-2">⚠️ <b>La connexion s'est coupée pendant l'envoi.</b> Nous ne savons pas si votre demande est arrivée. Vérifiez dans « Mes réservations » avant de recommencer.</p>`;
      const el = bandeau('pwa-echec', texte + bouton('Réessayer', true, 'js-ferme') + '<p class="aide mt-1">Rien n\'est renvoyé automatiquement, pour éviter un double paiement.</p>', 'rouge');
      el.querySelector('.js-ferme').onclick = () => retirer('pwa-echec');
    });
  });
});

// ------------------------------------------------------------------ invitation à installer
let invitationAndroid = null;
let installationProposee = false;
const estAppareilMobile = () => (navigator.userAgentData?.mobile === true || /Android|iPhone|iPad|iPod/.test(ua)
  || (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1))
  && window.matchMedia('(pointer: coarse) and (max-width: 1023px)').matches;
window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); invitationAndroid = e; proposerInstallation(); });
window.addEventListener('appinstalled', () => { const d = lire(); d.installee = true; ecrire(d); retirer('pwa-installer'); });

function proposerInstallation() {
  // La largeur et le tactile seuls peuvent aussi décrire un ordinateur hybride.
  if (!estAppareilMobile() || installationProposee) return;
  // Si le stockage de session est indisponible, ne pas risquer une relance à chaque page.
  if (sansStockage(() => sessionStorage.getItem('gesimo:pwa:installation-proposee'), '1')) return;
  const d = lire();
  if (installee() || d.installee || surPageSensible() || document.getElementById('pwa-installer')) return;
  if (d.refuse_le && Date.now() - d.refuse_le < 30 * 24 * 3600 * 1000) return;
  if ((d.visites || 0) < 2 && (d.pages || 0) < 3) return; // pas à la première visite
  if (!invitationAndroid && !estIOS) return;

  installationProposee = true;
  sansStockage(() => sessionStorage.setItem('gesimo:pwa:installation-proposee', '1'));
  const aide = estIOS
    ? (estSafariIOS
      ? `<ol class="mt-2 grid gap-1 text-[15px]"><li><b>1.</b> Touchez <b>Partager</b> (le carré avec une flèche) en bas de Safari ⬆️</li><li><b>2.</b> Choisissez <b>« Sur l'écran d'accueil »</b> ➕</li><li><b>3.</b> Touchez <b>« Ajouter »</b> ✔️</li></ol>`
      : `<p class="mt-2">Ouvrez cette page dans <b>Safari</b> pour installer l'application.</p>`)
    : '';
  const corps = `<div class="flex items-center gap-3"><div class="flex h-[52px] w-[52px] shrink-0 items-center justify-center rounded-2xl bg-marque-600 text-[28px] font-extrabold text-white">A</div><div><b>Installer Avelis sur votre téléphone</b><div class="aide">Un accès direct depuis l'écran d'accueil, et vos réservations même avec un réseau faible.</div></div></div>
    <div id="pwa-aide-ios"></div>
    <div class="mt-2 grid grid-cols-2 gap-2">${bouton('Installer', false, 'js-installer')}${bouton('Plus tard', true, 'js-plus-tard')}</div>`;
  const el = bandeau('pwa-installer', corps);
  el.querySelector('.js-plus-tard').onclick = () => { const x = lire(); x.refuse_le = Date.now(); ecrire(x); retirer('pwa-installer'); };
  el.querySelector('.js-installer').onclick = async () => {
    if (invitationAndroid) {
      const invitation = invitationAndroid;
      invitationAndroid = null;
      let choix = null;
      try { await invitation.prompt(); choix = await invitation.userChoice; } catch {}
      const x = lire();
      if (choix?.outcome === 'accepted') x.installee = true; else x.refuse_le = Date.now();
      ecrire(x);
      retirer('pwa-installer');
    } else {
      el.querySelector('#pwa-aide-ios').innerHTML = aide;
      el.querySelector('.js-installer').textContent = "J'ai compris";
      el.querySelector('.js-installer').onclick = () => { const x = lire(); x.refuse_le = Date.now(); ecrire(x); retirer('pwa-installer'); };
    }
  };
}

quandPret(() => {
  const d = lire();
  d.pages = (d.pages || 0) + 1;
  if (!sansStockage(() => sessionStorage.getItem('gesimo:session'))) { sansStockage(() => sessionStorage.setItem('gesimo:session', '1')); d.visites = (d.visites || 0) + 1; }
  ecrire(d);
  setTimeout(proposerInstallation, 2500); // jamais dès l'ouverture de la page
});
