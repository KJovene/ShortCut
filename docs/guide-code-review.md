# ShortCut — guide complet pour la code review

Analyse en lecture seule de la branche `dev` (d3873b3), au 23/10/2026. Les références de fichiers sont relatives à la racine du dépôt.

Notation des points d'attention :
- **[C]** : certain, vérifié dans le code.
- **[S]** : suspect, à confirmer ou à tester.

---

## 1. Vue d'ensemble

ShortCut est le site d'une agence de voyage. Un chatbot IA y recueille les envies du client, cherche dans le catalogue, chiffre le voyage en trois niveaux et rédige un récapitulatif. Des conseillers reprennent ensuite le dossier dans un backoffice.

| Couche | Techno | Rôle |
| --- | --- | --- |
| `api/` | Symfony 7.4 / PHP 8.3, API Platform, EasyAdmin, Doctrine, PostgreSQL 16, Workflow, Dompdf | API JSON, backoffice `/admin`, appels à Claude, PDF |
| `site/` | React 19, TS, Vite, TanStack Router / Query / Form, Zod, Tailwind | Site public, widget chatbot, espace client, page « Ma proposition » |
| IA | Claude (via `api/src/Chatbot`) | Haiku 5.5 : conversation et traduction. Sonnet 5.5 : recherche et rédaction. Opus 5.5 : juge des évaluations |

Le navigateur ne parle jamais à Claude. La clé est uniquement dans `api/.env.local`, qui n'est pas versionné : aucune clé n'apparaît dans l'historique git.

### Chiffres

- 187 commits du 15 au 23/10/2026, PR jusqu'à #289.
- `main` n'a reçu **aucun** code : 2 commits, contre 185 d'avance pour `dev`.
- Tests : 277 côté API (PHPUnit 12 strict), 131 côté site (Vitest).
- **Il n'y a pas de CI** : la qualité repose sur `make check`, lancé à la main.

---

## 2. Infra et lancement

`compose.yaml` définit cinq conteneurs :

| Conteneur | Image | Port | Rôle |
| --- | --- | --- | --- |
| `php` | php:8.3-fpm-alpine | — | Code en bind mount ; extensions intl, pdo_pgsql, gd, opcache, pcov |
| `nginx` | 1.27 | 8000 | Buffering FastCGI coupé pour le SSE, timeout de 300 s |
| `front` | node:24-alpine | 5173 | `npm run dev` (Vite) ; proxy `/api`, `/uploads`, `/admin`, `/bundles`, `/sitemap.xml`, `/robots.txt` vers nginx |
| `database` | postgres:16 | 5433 | Identifiants `shortcut/shortcut` |
| `adminer` | — | 8080 | Démarré en permanence, sans profil |

Commandes principales :
- `make install`, puis `make up` / `make dev` pour lancer.
- `make check` enchaîne lint (php-cs-fixer, eslint, prettier, tsc), PHPStan niveau 6, phpunit, vitest et le build du site.
- `make db-test` prépare la base de test ; il est séparé de `make check`.
- Sous Windows : `compose.windows.yaml` → `compose.override.yaml` (vendor/ et var/ en volumes).

---

## 3. Modèle de données (`api/src/Entity`)

Conventions :
- Montants en **centimes**.
- Périodes `debut`/`fin` en dates immuables.
- Listes libres en JSON.
- Pas de lifecycle callbacks ni de verrou optimiste.

| Entité | Points clés |
| --- | --- |
| `Utilisateur` | Un seul modèle pour client, conseiller et admin (`role` enum). `motDePasse` vaut null pour les comptes créés par le chatbot |
| `Demande` | La fiche du voyage, remplie par le chatbot : type, lieu ou zone, dates, durée, voyageurs, âges, budget, envies, contraintes, statut (workflow), conseiller, `prioritaire`, motif et résumé de transmission, langue. Au plus 3 en cours par client (`VOYAGES_EN_COURS_MAX`) |
| `Conversation` | OneToOne avec la demande. `jeton` de 64 caractères hex, sans expiration. `messages` (vue lisible, avec cartes), `historique` (JSON brut au format de l'API Claude, jamais compacté), `repriseParConseiller` |
| `Proposition` | Versions, unique sur `(demande, version)`. `jetonPublic` sert d'accès. Type `premiere`/`confirmee`, statut (workflow), `choix` (combinaison retenue), `exigences` (fiche du moment), `options`, `programme`, `budgetsParNiveau`, `hypotheses`, totaux, frais |
| `LigneDePrestation` | Poste chiffré. `prixEstimeCentimes` et `calcul` contiennent des **prix d'achat** ; `prixConfirmeCentimes` |
| `Destination` | Catalogue : climat (`PeriodeClimatique`), zones, types de voyage, temps de trajet, publiée / mise en avant |
| `Prestation` | Logement **ou** activité, même entité exposée par deux ressources API. Tarifs par période (`TarifParPeriode`, prix d'achat) |
| `Prestataire` | Hébergeur, transporteur (fourchettes de vols par ville), guide, activités |
| `RegleDePrix` | Marge de 20 % et frais de dossier par type (100 à 200 €). **Aucune UI** pour la modifier |
| `PageDuSite` | Contenus éditables (HTML Trix) |
| `Traduction` | Cache des traductions EN, clé sha1 du texte FR |
| `JournalIA` | Une ligne par appel à Claude : agent, modèle, tokens, durée, sortie |

`docs/schema.md` est **périmé** : il manque `traduction`, `demande.langue`, `proposition.options`… Il faut relancer `make schema`. Le diagramme du README montre encore une entité `CLIENT`, qui a été supprimée.

---

## 4. Sécurité et droits

### Pare-feu (`api/config/packages/security.yaml`)

- **`chatbot`** :
  - sans état, pattern `^/api/chat(?!/conversations$)` ;
  - authentifié par l'en-tête `X-Conversation-Token` (`JetonConversationAuthenticator`) ;
  - utilisateur virtuel `VisiteurChatbot` avec `ROLE_CHATBOT`.
- **`main`** :
  - session, `json_login` sur `/api/connexion`, logout sur `/api/deconnexion` en GET ou POST ;
  - point d'entrée : 401 JSON sous `/api`, sinon redirection vers `/connexion` du site.
- **Rôles** : `ROLE_ADMIN` hérite de `ROLE_CONSEILLER`. Les clients ont `ROLE_CLIENT` et `ROLE_USER`.

### Voter `DemandeVoter`

Attributs `VOIR`, `MODIFIER` et `ATTRIBUER`, valables sur une Demande, une Conversation ou une Proposition.

| Profil | Droits |
| --- | --- |
| Chatbot | Sa propre demande uniquement |
| Admin | Tout |
| Conseiller | Ses demandes. Pour une demande non attribuée : `VOIR` et `ATTRIBUER`, pas `MODIFIER` |

### Accès publics et limites

- Accès publics :
  - `POST /api/chat/conversations` ;
  - connexion et inscription ;
  - `/api/propositions/{jeton}…` (le jeton de 256 bits tient lieu d'accès) ;
  - `/api/public/destinations*` et `/api/pages*` ;
  - sitemap et robots ;
  - la doc API Platform.
- Limite de débit (`LimiteChatbotSubscriber` + `rate_limiter.yaml`) : 10 conversations par heure et 20 requêtes par minute sur `/api/chat`, **par IP**.

---

## 5. Inventaire des endpoints

### Site et client

| Méthode et chemin | Accès | Effet |
| --- | --- | --- |
| `POST /api/connexion`, `/api/inscription` | public | Session, puis profil |
| `GET`/`PATCH /api/moi` | connecté | Profil. Changer l'e-mail ou le mot de passe exige le mot de passe actuel |
| `GET /api/moi/demandes` | connecté | Voyages du client, avec conversation (jeton compris), messages, fiche et proposition visible |
| `DELETE /api/moi/demandes/{id}` | propriétaire | Suppression ; 409 si le voyage est accepté |
| `POST /api/chat/conversations` | public | Crée une demande et sa conversation, renvoie le jeton. Préférences du questionnaire, langue |
| `POST /api/chat` | jeton | Message (2 000 caractères au plus), réponse en SSE |
| `GET /api/chat/demandes/{id}` | jeton | Résumé de la demande |
| `GET /api/propositions/{jeton}` (`/versions/{n}`, `/pdf`) | public | Proposition qui fait foi, une version, ou le PDF |
| `POST …/demande-de-confirmation`, `…/acceptation` | public | Transitions du workflow ; 409 si impossible |

### Catalogue (API Platform)

- Routes : `/api/destinations`, `/logements`, `/activites`, `/prestataires`, `/tarifs`.
- Accès : conseiller ; suppression réservée à l'admin, **sauf pour les tarifs**.
- Groupes de sérialisation `public` et `interne`.
- `/api/public/destinations` est enrichie par `DestinationPubliqueNormalizer` (prix « à partir de »).

### Backoffice

- `/admin` (EasyAdmin), plus :
  - `POST /admin/propositions/{id}/envoyer` ;
  - `POST /admin/demandes/{id}/attribuer` ;
  - `GET`/`POST /admin/demande/{id}/confirmation` (+ `/retirer`, `/remplacer`, `/perdre`) ;
  - `/admin/lieux` (autocomplétion via Photon).

---

## 6. Workflows (`api/config/packages/workflow.yaml`)

```
Proposition : brouillon ─envoyer→ envoyee ─(client demande confirmation)…
              brouillon ─confirmer→ confirmee ─accepter→ acceptee
              {brouillon, envoyee, confirmee} ─remplacer→ remplacee
              {envoyee, confirmee} ─refuser→ refusee      ← jamais utilisée

Demande :     nouvelle ─envoyer_proposition→ proposition_envoyee ─demander_confirmation→ a_confirmer
              ─confirmer→ confirmee ─accepter→ acceptee
              * ─perdre→ perdue   (aucune sortie de perdue/acceptee)
```

Où chaque transition est déclenchée :
- `PropositionService::envoyer`, depuis le backoffice : `envoyer` et `remplacer`.
- `PropositionClient`, depuis les routes publiques : `demander_confirmation` et `accepter`.
- `ConfirmationService::confirmer`, depuis le backoffice : `confirmer` et `remplacer`.
- `DemandeCrudController::perdre` : `perdre`.

---

## 7. Le chatbot (le cœur de l'application)

### 7.1 Mécanique commune (`api/src/Chatbot/Agent.php`)

- **Appel à Claude** :
  - en streaming via `ClientClaude` (client HTTP `claude.client`, timeout de 60 s, **aucun retry**) ;
  - `max_tokens` 16000, effort adaptatif, prompt système tiré de `api/prompts/*.md`, cache de prompt.
- **Boucle d'outils** :
  - le modèle demande un outil, le serveur l'exécute, le résultat repart à Claude, jusqu'à la réponse finale ou `appelsMax` ;
  - une `OutilException` revient au modèle en `is_error`.
- **Arrêts** : `refusal`, `max_tokens` ou plafond d'appels déclenchent un événement `erreur`.
- **Journal** : chaque appel est tracé dans `JournalIA`.

### 7.2 Les agents

| Agent | Modèle | Appels max | Outils |
| --- | --- | --- | --- |
| Conversation | Haiku 5.5 | 8 | `enregistrer_exigences`, `chercher_destinations`, `consulter_catalogue`, `choisir_options`, `transmettre_au_conseiller` |
| Recherche | Sonnet 5.5 | 12 | `chercher_destinations`, `chercher_logements`, `chercher_activites`, `retenir_combinaison`, `transmettre_au_conseiller` |
| Rédaction | Sonnet 5.5 | 3 | `enregistrer_proposition` (outil final), `transmettre_au_conseiller` |

`calculer_budget` n'est exposé à aucun modèle : `retenir_combinaison` l'appelle en interne. `docs/outils.md` est périmé sur ce point.

### 7.3 Parcours d'un message (`POST /api/chat` → `AgentConversation::repondre`)

1. Si la conversation est reprise par un conseiller : réponse fixe, puis `fin`.
2. Haiku dialogue. Il appelle `enregistrer_exigences` **à chaque message**, ce qui émet l'événement `demande` (fiche mise à jour en direct dans le widget).
3. Quand le client confirme le récapitulatif (`recapitulatifConfirme=true`, qui exige type, lieu et dates) :
   - **options choisies** : `NouvelleVersion::appliquerLesOptions` ;
   - **proposition déjà existante** : `NouvelleVersion::recalculer` :
     - même combinaison recalculée sans LLM si seuls dates, voyageurs, budget, ville ou contraintes changent ;
     - sinon nouvelle Recherche ;
   - **sinon, sans conseiller** : `AgentRecherche::proposer` dans la même réponse HTTP ;
   - **sinon, dossier suivi par un conseiller** : la fiche est seulement mise à jour.
4. La Recherche choisit destination, logements et activités, puis appelle `retenir_combinaison`. Le serveur re-vérifie tout et chiffre les 3 niveaux. Événement `piste`.
5. La Rédaction écrit `pourquoi`, l'argumentaire et le programme, puis appelle `enregistrer_proposition` (brouillon). Événement `recapitulatif` avec le lien vers « Ma proposition », en état `preparee`.
6. Toute erreur de Recherche ou de Rédaction déclenche une transmission automatique au conseiller.

### 7.4 Événements SSE

| Événement | Contenu |
| --- | --- |
| `texte` | Morceau de texte |
| `outil` | Nom de l'outil en cours d'exécution |
| `demande` | Fiche mise à jour |
| `piste` | Carte de la piste retenue |
| `suggestions` | Logements et activités recommandés |
| `recapitulatif` | Carte du récapitulatif détaillé |
| `erreur` | Message déjà rédigé pour le client |
| `fin` | `{terminee}` : la conversation n'attend plus rien du client |

Côté site, le flux est lu avec `fetch` + `getReader`, car c'est une requête POST.

### 7.5 Chiffrage (`api/src/Chiffrage/BudgetCalculator.php`)

- **Formule** : total = coût d'achat (hébergements + transports + activités) + marge (20 %, arrondie au centime) + frais de dossier.
- **Hébergement** : tarif de chaque nuit × ⌈voyageurs ÷ capacité⌉. Une nuit sans tarif rend la demande « non chiffrable ».
- **Transport** : (vol + transfert) × voyageurs, enfants au prix adulte. Minimum, milieu ou maximum de la fourchette selon le niveau.
- **Activités** : tarif du **premier jour du voyage** × participants ayant l'âge minimum.
- **Niveaux** :
  - essentiel : le moins cher ;
  - confort : le plus cher, toutes les suggestions ;
  - équilibré : meilleure combinaison sous le budget (recherche exhaustive, plafonnée à 20 000 combinaisons).
- **Arbitrages** (`ArbitrageCalculator`) si le budget est insuffisant : moins de nuits, ou autre période (±182 jours).
- **Options** : jusqu'à 3 activités en plus, au prix de vente.

### 7.6 Propositions et confirmation

- **Version** : `max + 1`. Un nouveau brouillon remplace les brouillons précédents ; l'envoi par le conseiller remplace les autres versions.
- **Page client** (`PropositionClient::presenter`) : jamais de prix d'achat, lignes sans prix. Le lien d'une version remplacée mène à la version qui fait foi.
- **Confirmation** (`ConfirmationService`) :
  - le conseiller ouvre l'écran : une copie « confirmée » est créée en brouillon ;
  - il saisit les prix réels, peut retirer une activité ou remplacer un logement ;
  - l'écart est signalé au-delà de 10 % ;
  - la confirmation remplace l'estimation, et la demande passe « confirmée » ;
  - le client accepte ensuite.

---

## 8. Backoffice (EasyAdmin, `api/src/Controller/Admin`)

- **Tableau de bord** : compteurs par statut, prioritaires, « à aiguiller », « à traiter ». Un conseiller ne voit que ses chiffres.
- **Demandes** :
  - un conseiller voit ses demandes, plus la vue « à aiguiller » ;
  - actions : M'attribuer, Confirmer, Marquer perdue, Journal IA (réservé à l'admin) ;
  - envoi de la proposition depuis la fiche.
- **Clients** : lecture seule, avec l'historique.
- **Catalogue** : destinations (climat mensuel), logements et activités (tarifs en collection), prestataires.
- **Réservé à l'admin** : pages du site, traductions, comptes, journal IA.

---

## 9. Traduction EN (US15)

- Le site appelle l'API avec `?langue=en`.
- `TraductionSubscriber` (`kernel.response`) collecte les champs textuels de `/api/pages` et `/api/public`. `Traducteur` traduit les inconnus avec Haiku, par lots, et les garde dans `traduction`.
- L'admin peut corriger les traductions.
- Les textes fixes du site sont dans les fichiers `textes.ts` (`TEXTES[langue].cle`).
- Le chatbot démarre dans la langue du site, puis suit celle du client.

---

## 10. Front (`site/src`)

### Routes

| Route | Contenu |
| --- | --- |
| `/` | Carte, section assistant |
| `/agence`, `/contact`, `/types-de-voyages` | Pages de présentation |
| `/destinations` et `/destinations/$slug` | Catalogue public |
| `/cgv`, `/confidentialite`, `/mentions-legales` | Pages légales |
| `/connexion`, `/inscription` | Comptes |
| `/profil`, `/espace` | Réservées aux utilisateurs connectés (`beforeLoad`) |
| `/proposition/$id` | « Ma proposition » (jeton de 64 caractères hex) |
| `/$` | 404 |

### Organisation

- **Chat** (`features/chat`) :
  - `ChatProvider` est le contexte global (état, jeton, `AbortController`, persistance dans `sessionStorage`) ;
  - `api.ts` contient le parseur SSE et les schémas Zod ;
  - composants `ChatFenetre`, `ChatWorkspace`, `Messages` (cartes), `Recapitulatif`.
- **Espace** (`routes/espace.tsx`, `features/espace`) : étapes du voyage, reprise de la conversation, suppression, Calendly.
- **Proposition** (`features/proposition`) : affichage, comparaison de versions (`comparaison.ts`), actions, PDF.
- **Données** : TanStack Query (langue dans les clés), validation Zod partout. Aucun `dangerouslySetInnerHTML` : pas de risque XSS côté chat.

---

## 11. Diff local non commité

- **`api/src/Controller/Admin/DashboardController.php`** : favicon versionnée `?v=filemtime`, avec `@` pour masquer les erreurs. Le même motif existe déjà dans `configureAssets` : à factoriser.
- **`site/src/features/chat/components/ChatAccueil.tsx`** : suppression des puces de suggestion. Ce changement laisse du code mort :
  - la prop `suggestion` dans le type ;
  - `SectionAssistant.tsx:79` qui la passe toujours ;
  - les clés `suggestions` dans `features/chat/textes.ts` et `accueil.suggestion` dans `features/pages/textes.ts`.

---

## 12. Points d'attention pour la review

### 12.1 Sécurité (priorité haute)

1. **[C] Rattachement d'une demande à n'importe quel compte par e-mail.** `api/src/Chatbot/Outil/EnregistrerExigences.php:185-213` (`lierClient`).
   - Un visiteur anonyme qui donne l'e-mail d'un tiers rattache sa demande au compte de ce tiers, y compris un compte conseiller ou admin.
   - Si le compte n'existe pas, il crée un compte **sans mot de passe** : le vrai titulaire ne peut plus s'inscrire, et il n'existe pas de réinitialisation du mot de passe.
   - Conséquences : un tiers peut remplir le quota de 3 voyages de quelqu'un, et le message d'erreur permet d'énumérer les comptes.
   - Le comportement est voulu et testé, mais c'est une faille.
2. **[C] Prix d'achat exposés aux modèles.** `aPartirDeParNuitEuros`, `prixParNuitEuros` et `prixParPersonneEuros` sont renvoyés dans les résultats d'outils et persistés dans l'historique. Seul le prompt les protège : une injection de prompt peut les faire fuiter. `recherche.md` n'a aucune consigne anti-injection.
3. **[C] Texte arbitraire publié sous la charte de l'agence.** Les messages bruts du client vont à la Rédaction. Son texte s'affiche sur la page publique et dans le PDF (état `preparee`) **avant toute relecture du conseiller**.
4. **[C] Un conseiller peut changer le client d'une demande.** `DemandeCrudController.php:249` : la conversation et le jeton apparaissent alors dans l'espace d'un autre client.
5. **[C] Pas de protection contre la force brute.** Ni `login_throttling` ni limiteur sur connexion, inscription et `PATCH /api/moi`. Le logout est accepté en GET, donc vulnérable au CSRF.
6. **[C] Limitation de débit globale en pratique.** Il n'y a pas de `trusted_proxies` et Vite ne transmet pas `X-Forwarded-For` : tous les visiteurs partagent une IP, donc **10 conversations par heure pour tout le site**.
7. **[C] CORS incomplet.** `allow_headers` n'inclut pas `X-Conversation-Token`. Ça ne fonctionne que grâce au proxy Vite (même origine).
8. **[C] Jetons sans expiration.** Ni le jeton de conversation ni le jeton public de proposition ne périment, et aucune révocation n'existe. La doc API est publique.
9. **[C] Docker.** Postgres et Adminer sont publiés sur toutes les interfaces avec `shortcut/shortcut`, et les Dockerfiles font des `chmod 777`.
10. **[C] Données personnelles.** E-mail, téléphone et santé sont conservés en clair dans `JournalIA` et dans le `sessionStorage` du navigateur.

### 12.2 Bugs avérés

1. **[C] Questionnaire, mauvais budget.** « Plus de 3 000 € » vaut 300000 centimes, comme la tranche 1 500 – 3 000 € (`ChatController.php:131-132`). Même chose côté front dans `demande.ts:62-69`.
2. **[C] Boucle non bornée.** `NouvelleVersion::choixAdapte` avance d'une nuit à la fois, et `nombreDeJours` n'a pas de maximum : un client peut figer le serveur (déni de service).
3. **[C] Concurrence sur une conversation.** Aucun verrou :
   - deux messages simultanés font perdre des tours ;
   - deux versions simultanées violent la contrainte unique ;
   - après cette exception Doctrine, l'EntityManager est fermé : le flux SSE se coupe sans `erreur` ni `fin`.
4. **[C] Un GET modifie l'état.** `GET /admin/demande/{id}/confirmation` crée et enregistre une version. Deux ouvertures simultanées provoquent une erreur 500 (contrainte unique), et une précharge du navigateur peut aussi créer la version.
5. **[C] Aiguillage non atomique.** Lecture puis écriture sans verrou : en cas de concurrence, le dernier conseiller qui enregistre gagne.
6. **[C] Échec temporaire, transmission définitive.** Un 529 ou un timeout de Claude pendant la Recherche ou la Rédaction transmet la demande au conseiller, et `repriseParConseiller` est irréversible.
7. **[C] Correspondance par nom au lieu d'identifiant.** `BudgetCalculator`, `RetenirCombinaison::parNom` et `PropositionService::lignes` rattachent les prestations par leur nom : deux homonymes donnent un mauvais rattachement ou une `LogicException`.
8. **[C] Validations manquantes (500 au lieu de 422).** Pas de `Length` sur `Utilisateur.prenom`, `telephone`, `email`, sur `PageDuSite.seo*`, ni sur `destination` du questionnaire. 0 adulte est accepté, et les âges saisis dans le backoffice arrivent en chaînes.
9. **[C] `NoPeriodOverlap` ne compare qu'à la base.** Deux tarifs qui se chevauchent dans la même soumission passent. Le climat saisi dans le backoffice n'est pas validé et prend l'année courante.
10. **[C] Traçabilité des tarifs incomplète.** `PrestationCrudController` ne met à jour `saisiPar` et `modifieLe` que sur les nouveaux tarifs.
11. **[C] Bugs front** :
    - la barre de progression du récapitulatif compte jusqu'à 9 sur 8 critères, donc l'état « complet » n'est jamais atteint (`Recapitulatif.tsx:62,72`) ;
    - `destinationInvite` est préfixé à **chaque** message (`ChatProvider.tsx:350`) ;
    - l'année 2027 est codée en dur (`demande.ts:128`) ;
    - `budgetIndicatif` reste figé après que le client a précisé son budget ;
    - l'espace n'est pas invalidé après une acceptation ;
    - la reprise d'une conversation affiche les notes conseiller comme des messages de l'assistant ;
    - le parseur SSE ne gère pas un `\r\n` coupé entre deux morceaux, et perd le dernier bloc s'il n'est pas terminé.
12. **[C] Marge non déterministe.** `RegleDePrix` est lue avec `findOneBy([])` sans ordre. Les tarifs qui se chevauchent sont lus par `tarifLe`, qui prend le premier trouvé.

### 12.3 Performance et coûts

- **Appels Claude par message.** Un POST peut enchaîner jusqu'à 23 appels Claude (8 + 12 + 3). L'historique de la conversation n'est jamais compacté, et `chercher_destinations` peut renvoyer tout le catalogue.
- **Traduction publique non protégée.** `?langue=en` déclenche un appel Claude **synchrone** dans `kernel.response`, sans limitation de débit et sans cache négatif : il est rejoué à chaque requête en cas d'échec.
- **`/api/public/destinations`** : non paginée, prix « à partir de » recalculé pour chaque destination, N+1 sur le climat, aucun cache.
- **N+1 dans le backoffice** : `Demande::conversation` (OneToOne côté inverse), plus des `findAll()` dans `TexteSuggereType` et `LieuxDuCatalogue`.
- **Front** :
  - chaque token SSE réécrit tout l'état dans `sessionStorage` et re-rend tous les consommateurs de `useChat` ;
  - c'est la cause probable du test instable `chat-fenetre.test.tsx:96`.
- **Journal IA faussé** : `tokensEntree` additionne le cache et l'entrée, et l'entrée ne garde que le dernier message.

### 12.4 Dette, code mort, docs

- **Code mort** :
  - `PeriodeClimatiqueController`, avec ses formulaires et son template ;
  - la transition `refuser`, jamais utilisée ;
  - `apiFetch` : le README demande de l'utiliser, mais `fetch` est réécrit dans 6 modules.
- **Duplications** :
  - `destination()` en trois exemplaires ;
  - la normalisation de texte ;
  - le calcul de l'état d'une proposition (`EspaceClientController` / `PropositionClient`) ;
  - `VOYAGES_EN_COURS_MAX` côté front ;
  - le setup transactionnel des tests, copié dans environ 15 classes.
- **Gros fichiers** : `PropositionEnLigne.tsx` (797 lignes), `espace.tsx` (677), `ChatProvider.tsx` (492), `Messages.tsx` (494).
- **i18n incomplet** : bloc de suppression de l'espace, messages auto du chat, fallback d'erreur et format des euros en FR sur la page EN.
- **Documentation et config périmées** :
  - `docs/schema.md` ;
  - `docs/outils.md` ;
  - `docs/chiffrage.md` ;
  - le diagramme ER du README ;
  - `prompts/conversation.md:40` (puce cassée) ;
  - `.gitignore` (commentaires sans `#`, coquille `.env..local`).
- **Tests manquants** :
  - les branches d'arrêt de `Agent::boucle` ;
  - le parseur SSE serveur ;
  - la concurrence ;
  - les bornes extrêmes ;
  - les contrôleurs EasyAdmin Utilisateur, Traduction, Logement, Activité et Aiguillage ;
  - `NoPeriodOverlapValidator`, `Traducteur`, `Suggestions`.
- **Écarts au cahier des charges** :
  - US14 (écart moyen sur le tableau de bord) absente ;
  - TanStack Router au lieu de React Router ;
  - pas de VichUploader.
- **Livraison** : pas de configuration de prod (Vite en dev, `APP_ENV=dev`), pas de CI, `main` vide.

### 12.5 Ce qui est bien fait

- Voter cohérent, appliqué partout. CSRF sur les actions sensibles du backoffice.
- Groupes de sérialisation public et interne. Prix d'achat exclus de la page client.
- `retenir_combinaison` re-vérifie côté serveur tout ce que propose le modèle.
- Calculs en centimes, idempotence des traductions (`ON CONFLICT`).
- Front :
  - validation Zod systématique ;
  - aucun HTML injecté ;
  - effets correctement nettoyés ;
  - bonne accessibilité de base : lien d'évitement, `aria-live`, focus.
- Tests fonctionnels riches. Faux client Claude en test, donc aucun appel réseau.
