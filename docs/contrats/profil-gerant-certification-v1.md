# Profil gérant, certification et badges — contrat v1

Date : 21 octobre 2026.
Statut : règles fondatrices validées en conversation ; maquettes et modalités complémentaires proposées à la validation. Aucune implémentation applicative dans ce lot.

## 1. Vision — pourquoi

Un gérant renseigne son identité une seule fois pour toutes ses résidences. La publication reste accessible aux gérants disposant d'une pièce d'identité validée, même sans certificat de résidence ni justificatif de gestion. Les preuves complémentaires donnent des reconnaissances distinctes, dont la signification doit être compréhensible par le public.

Cette décision du porteur remplace la règle du parcours actuel exigeant une pièce d'identité et un justificatif de gestion pour chaque soumission. Le cahier des charges historique reste une référence pour les autres règles ; ce contrat prévaut sur cette exigence ancienne pour le périmètre décrit ici.

## 2. Conception — règles confirmées

| Objet | Pièce ou contrôle | Obligatoire pour publier | Effet après validation humaine par Avelis |
| --- | --- | --- | --- |
| Gérant | Pièce d'identité principale | Oui | Le gérant peut demander la publication de ses annonces |
| Gérant | Certificat de résidence, délivré par un commissariat en Côte d'Ivoire | Non | Mention « Gérant certifié » sur le profil public |
| Résidence | Validation de l'annonce, des logements, des photos et des tarifs | Oui | Publication sous réserve de l'éligibilité du gérant et des règles existantes de suspension |
| Résidence | Justificatif du droit de gestion de cette résidence | Non | Badge attribué à cette résidence uniquement |

La certification du gérant exige la validation de la pièce d'identité principale ET du certificat de résidence. Le dépôt d'un fichier ne constitue jamais sa validation. Ni l'absence ni le refus d'un justificatif facultatif ne doit empêcher la publication si les exigences obligatoires restent satisfaites.

Une résidence peut être publiée sans badge, et appartenir à un gérant sans certification complémentaire. Un gérant certifié peut proposer une résidence sans badge. Une résidence avec badge peut appartenir à un gérant dont le certificat de résidence n'a pas été fourni. Aucune de ces reconnaissances ne se propage automatiquement à l'autre objet.

## 3. Responsabilité et confidentialité

- L'identité et le certificat de résidence appartiennent au dossier du gérant. Ils sont réutilisés pour ses résidences, sans copie des fichiers par annonce.
- Le justificatif de gestion appartient au dossier de la résidence identifiée. Il ne certifie ni une autre résidence ni l'identité du gérant.
- L'équipe Avelis autorisée contrôle les documents. Les dépôts, consultations, décisions et motifs de refus sont journalisés, sans contenu documentaire dans les journaux.
- Les documents restent privés. Les pages publiques n'exposent ni les fichiers ni leurs liens, ni les références documentaires, ni l'adresse personnelle, ni les coordonnées privées du compte.
- Le profil public rassemble une présentation, un nom d'affichage, une photo ou un logo facultatif, une commune publique et les seules offres publiées du gérant. Le téléphone et l'e-mail du compte ne deviennent pas automatiquement publics.
- Le refus indique au gérant ce qu'il doit corriger, dans le contexte du document concerné.
- L'état de certification et le badge sont déterminés côté serveur par les contrôles acceptés, et non par les informations modifiables du profil public.

## 4. Parcours proposé

1. Le gérant complète son profil privé : nom complet, adresse et coordonnées. L'entreprise reste facultative pour ne pas exclure les personnes exerçant en leur nom.
2. Il fournit sa pièce d'identité et peut joindre son certificat de résidence, maintenant ou plus tard.
3. Il peut préparer des brouillons de résidences pendant le contrôle. La demande de publication attend que l'identité obligatoire soit acceptée.
4. L'assistant de résidence contient : « Ma résidence », « Mes logements », « Vérification et envoi ». La dernière étape montre l'état de l'identité du profil ; elle ne redemande aucun document personnel. Le justificatif de gestion y reste facultatif.
5. Après soumission réussie, retour à « Mes résidences » avec confirmation nommant la résidence, statut « En attente de validation » et action « Suivre mon dossier ». Aucun formulaire de nouvel envoi n'est affiché comme si l'action restait à faire.
6. L'équipe Avelis valide l'annonce indépendamment du badge facultatif. Le gérant peut apporter un justificatif de gestion plus tard.
7. Le profil public n'affiche que les offres effectivement publiées, ainsi que la certification du gérant si elle est acquise. Chaque résidence conserve son propre badge.

La navigation de fin de parcours est une proposition de conception approuvée dans son principe ; les maquettes en montrent le comportement attendu. Les sauvegardes intermédiaires restent sur leur formulaire et confirment ce qui a été enregistré : toutes les sauvegardes ne doivent pas provoquer une redirection.

## 5. États et cas d'acceptation

Chaque contrôle documentaire distingue au minimum : absent, en attente de contrôle, accepté, refusé. L'état de validation de l'annonce reste distinct de celui de ses documents.

| Identité | Certificat de résidence | Annonce | Justificatif de gestion | Résultat attendu |
| --- | --- | --- | --- | --- |
| Acceptée | Absent | Validée | Absent | Publication ; aucune reconnaissance complémentaire |
| Acceptée | Accepté | Validée | Absent | Publication ; gérant certifié ; résidence sans badge |
| Acceptée | Absent | Validée | Accepté | Publication ; gérant sans certification complémentaire ; résidence avec badge |
| Acceptée | Accepté | Validée | Accepté | Publication ; gérant certifié ; résidence avec badge |
| Absente, en attente ou refusée | Quelconque | Quelconque | Quelconque | Pas de publication initiale ; brouillon conservé |
| Acceptée | Quelconque | En attente ou refusée | Quelconque | Pas de publication de l'annonce ; le gérant peut rester certifié |
| Acceptée | Refusé | Validée | Absent | Publication ; certification complémentaire non accordée |
| Acceptée | Absent | Validée | Refusé | Publication ; badge non accordé |

## 6. Maquettes proposées à valider

Fichier autonome, sans dépendance ni envoi de données : `docs/maquettes/profil-gerant-v1/index.html`.
Les valeurs, noms et offres sont des exemples fictifs. Les illustrations ne sont pas des photos de résidences réelles.

| Écran | But et contenu | Actions proposées | Erreurs ou états présentés |
| --- | --- | --- | --- |
| Mon profil gérant | Informations privées, présentation publique, pièce principale et certificat facultatif | Enregistrer ; fournir/remplacer un document ; ouvrir son profil public | Identité manquante ou en contrôle ; champs obligatoires ; fichier supérieur à 5 Mo |
| Vérification et envoi | Récapitulatif de l'annonce et état de l'identité déjà renseignée | Modifier ; joindre un justificatif facultatif ; conserver le brouillon ; envoyer | Demande d'envoi indisponible si identité non acceptée ; absence de justificatif facultatif non bloquante |
| Mes résidences après envoi | Confirmation explicite et suivi d'une soumission terminée | Suivre le dossier ; ajouter une résidence | En attente de validation ; aucune nouvelle soumission requise |
| Profil public du gérant | Présentation, commune, certification éventuelle et offres publiées | Consulter les offres | Mode sans certification : aucune mention « Gérant certifié » ; badges propres aux résidences |

Les sélecteurs « Simulation pour la revue » permettent d'inspecter plusieurs états ; ils ne font pas partie de l'application à implémenter.

## 7. Implémentation — prochaine étape après validation des maquettes

Aucun changement de code métier, de base, de compte ou de production dans ce lot.

La réalisation devra rester dans la pile existante et couvrir : profil gérant et contrôles documentaires centralisés ; contrôle de publication ; décisions administratives indépendantes ; parcours de soumission et profil public ; migrations réversibles ; tests de droits, de confidentialité et des combinaisons du tableau ci-dessus.

### Reprise des données existantes

Ne pas supprimer ni déplacer silencieusement les pièces déjà fournies. Inventorier les pièces par gérant, leur historique et leurs validations ; conserver les références traçables. Si plusieurs résidences portent des pièces d'identité différentes pour un même compte, ne pas en choisir arbitrairement une : prévoir un contrôle administratif.
Les justificatifs de gestion existants restent liés à leur résidence. Une résidence déjà vérifiée et son badge doivent être examinés selon la signification historique du contrôle ; ne pas convertir automatiquement un badge ancien en preuve de gestion si celle-ci n'a pas été explicitement contrôlée.

### Modalités à fixer avant modification des données existantes

- Effet du remplacement ou du retrait d'une pièce d'identité sur les annonces déjà publiées ; éviter une suspension involontaire lors d'un simple renouvellement tout en conservant le contrôle d'identité.
- Reprise administrative des gérants et badges existants, notamment en cas de documents divergents ou d'anciennes validations ne distinguant pas les contrôles.
- Règles de conservation, retrait, purge et éventuelle expiration des documents. Aucun délai de validité de certificat n'est inventé dans ce lot.

Ces points ne remettent pas en cause les règles fondatrices validées ; ils doivent être résolus avant d'activer la nouvelle logique sur des données réelles.
