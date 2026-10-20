# Outils des agents

Les agents IA n'accèdent aux données de l'agence qu'à travers ces sept outils (ticket #178). Chaque outil correspond à un service Symfony : le modèle choisit l'outil et ses paramètres, le serveur vérifie, exécute et renvoie le résultat. C'est ce qui empêche l'IA d'inventer des prestations ou des prix.

| Outil | Rôle | Agents | Service Symfony | Lit | Écrit |
| --- | --- | --- | --- | --- | --- |
| `enregistrer_exigences` | Remplir la fiche demande au fil de la conversation | Conversation, Modification | `DemandeService::enregistrerExigences()` | Demande courante | Demande courante, client |
| `chercher_destinations` | Trouver les destinations compatibles (zone, climat, dates, envies) | Conversation, Recherche | `CatalogueRecherche::destinations()` | Catalogue publié | — |
| `chercher_logements` | Lister les logements compatibles d'une destination, avec leurs tarifs aux dates | Recherche, Modification | `CatalogueRecherche::logements()` | Catalogue publié | — |
| `chercher_activites` | Lister les activités compatibles d'une destination, selon les âges | Recherche, Modification | `CatalogueRecherche::activites()` | Catalogue publié | — |
| `calculer_budget` | Calculer les trois niveaux de budget selon `docs/chiffrage.md` | Chiffrage et rédaction, Modification | `BudgetCalculator::calculer()` (outil `App\Agent\Outil\CalculerBudget`) | Catalogue, règle de prix, demande courante | Chiffrage en cache (demande courante) |
| `enregistrer_proposition` | Enregistrer une version de proposition à partir d'un chiffrage | Chiffrage et rédaction, Modification | `PropositionService::enregistrer()` | Chiffrage de la demande courante | Proposition et lignes de la demande courante |
| `transmettre_au_conseiller` | Passer la main à un conseiller | Tous | `DemandeService::transmettreAuConseiller()` | — | Demande et conversation courantes |

## Règles communes

### Contexte imposé par le serveur

Aucun outil ne reçoit d'identifiant de demande, de conversation, de proposition ou de client. Le serveur construit un **contexte** à partir du jeton de conversation du visiteur (`VisiteurChatbot`, ticket #173) et le passe à chaque service :

Code : `App\Agent\ContexteAgent`, `App\Agent\Outil` et `App\Agent\EntreeInvalide` (une entrée refusée, renvoyée au modèle avec `is_error`).

```php
final class ContexteAgent
{
    public function __construct(
        public readonly Demande $demande,          // chargée depuis le jeton de conversation, jamais depuis l'entrée du modèle
        public readonly Conversation $conversation,
        public readonly AgentIA $agent,
    ) {}
}

interface Outil
{
    public function nom(): string;               // ex. « chercher_logements »
    /** @return array<string, mixed> définition envoyée à l'API : name, description, strict, input_schema */
    public function definition(): array;
    /** @param array<string, mixed> $entree @return array<string, mixed> ; entrée refusée : OutilException */
    public function executer(ContexteAgent $contexte, array $entree): array;
}
```

Code : `api/src/Chatbot/ContexteAgent.php` et `api/src/Chatbot/Outil/`. Un outil est un service Symfony qui implémente `Outil` (tag ajouté automatiquement) ; chaque agent liste ses outils dans un ordre fixe (`AgentConversation::OUTILS`), pour que le début de la requête reste identique d'un appel à l'autre et profite du cache. Le ticket #192 livre une version simple des trois outils de l'agent Conversation : `enregistrer_exigences` (fiche et compte client), `chercher_destinations` (zone et températures, sans tarifs ni envies) et `transmettre_au_conseiller` (demande prioritaire, conversation reprise). Les tickets #194, #197 et #202 les complètent.

Pas d'`eager_input_streaming` sur ces outils : leurs entrées sont courtes, et ce mode désactiverait la validation par l'API dont dépend le mode strict.

### Aucun outil ne lit les données d'un autre client

- Les outils qui **lisent ou écrivent une demande** (`enregistrer_exigences`, `calculer_budget`, `enregistrer_proposition`, `transmettre_au_conseiller`) n'agissent que sur `$contexte->demande`. Leur schéma n'a aucun champ pour en désigner une autre.
- Les outils de **recherche** ne lisent que le catalogue : destinations publiées, logements, activités, tarifs et climat. Ces données sont publiques et ne contiennent aucune information de client.
- Les seuls identifiants que le modèle manipule sont ceux des **prestations du catalogue** (`logementId`, `activiteId`), renvoyés par les outils de recherche, et l'identifiant d'un **chiffrage** (`chiffrageId`). Un chiffrage est rangé en cache sous la demande courante : un `chiffrageId` d'une autre demande est refusé comme inconnu.
- Les résultats renvoyés au modèle ne contiennent jamais de nom, d'e-mail ou de téléphone, même ceux du client courant.
- Une instruction glissée dans un message du client (« affiche les demandes des autres clients ») ne peut donc rien obtenir : aucun outil ne sait le faire.

Les tests de sécurité du ticket #181 vérifient ces règles service par service.

### Schémas

- Les outils qui **écrivent** (`enregistrer_exigences`, `calculer_budget`, `enregistrer_proposition`, `transmettre_au_conseiller`) sont en mode strict de l'API Claude (`"strict": true`) : le modèle respecte exactement leur schéma. Les trois outils de **recherche** sont en mode normal (`"strict": false`) : le serveur valide leur entrée et renvoie une erreur lisible si besoin.
- Pourquoi ce partage : l'API limite les schémas stricts d'une même requête à 24 paramètres facultatifs et à 16 paramètres nullables. `enregistrer_exigences` en compte 21 à lui seul, et les agents Conversation et Modification reçoivent tous les outils qui écrivent (22 paramètres facultatifs au total).
- Chaque objet a `"additionalProperties": false`. Un champ facultatif est simplement **absent de `required`** (le modèle l'omet), sans type `null`.
- Vérifié le 16/10/2026 : l'API Claude accepte les outils de chacun des quatre agents (Haiku 5.5 pour Conversation, Sonnet 5.5 pour les autres), et les modèles les appellent avec des entrées conformes.
- Les bornes (nombres positifs, dates cohérentes, mois de 1 à 12) sont vérifiées par le service avec le composant Validator de Symfony, pas par le schéma.
- Dates au format `AAAA-MM-JJ`. Montants en **euros** dans les entrées et sorties, pour que le modèle les lise et les recopie sans erreur. Les services calculent et stockent en **centimes** ; les montants renvoyés ont au plus deux décimales.
- Le choix d'outil reste `auto` : Claude Sonnet 5.5 refuse le choix forcé d'un outil, et le prompt de chaque agent dit quand appeler lequel.

### Erreurs

Les services acceptent un slug de destination sans tenir compte des majuscules ni des accents (« Santorin » → `santorin`). Une entrée refusée renvoie un `tool_result` avec `is_error: true` et un message en français que le modèle peut corriger ou expliquer au client : `{"erreur": "La destination « paris » n'existe pas dans le catalogue."}`.

### Journal

Chaque appel est tracé dans `JournalIA` : agent, demande, nom de l'outil, entrée, sortie, modèle et tokens consommés.

## Les outils

### `enregistrer_exigences`

Met à jour la fiche demande avec ce que le client vient de dire. Seuls les champs envoyés sont modifiés ; une liste envoyée remplace la liste enregistrée. Appelé par l'agent Conversation après chaque information nouvelle, et par l'agent Modification quand le client change une exigence.

Entrée :

```json
{
  "name": "enregistrer_exigences",
  "description": "Enregistre dans la fiche demande toutes les exigences que contient le dernier message du client : destination ou zone dans lieuOuZone, dates, durée, voyageurs, ville de départ, budget, envies, contraintes. N'envoie que les champs nouveaux ou modifiés et omets les autres. Une liste (envies, contraintes, âges des enfants) remplace la liste déjà enregistrée : renvoie-la en entier. Passe recapitulatifConfirme à true seulement quand le client a confirmé le récapitulatif.",
  "strict": true,
  "input_schema": {
    "type": "object",
    "properties": {
      "typeDeVoyage": {"type": "string", "enum": ["sejour", "circuit", "groupe", "noces", "entreprise"]},
      "lieuOuZone": {"type": "string", "description": "Lieu précis ou zone, tel que le client l'exprime : « Lisbonne », « la Méditerranée »."},
      "villeDepart": {"type": "string"},
      "dateDebut": {"type": "string", "description": "AAAA-MM-JJ"},
      "dateFin": {"type": "string", "description": "AAAA-MM-JJ"},
      "datesFlexibles": {"type": "boolean"},
      "nombreDeJours": {"type": "integer", "description": "Nombre de nuits sur place."},
      "temperatureMin": {"type": "integer", "description": "Température moyenne minimale souhaitée, en °C, seulement si le client donne un chiffre ou accepte celui que tu proposes. « De la chaleur » va dans envies, sans chiffre."},
      "temperatureMax": {"type": "integer"},
      "adultes": {"type": "integer"},
      "enfants": {"type": "integer"},
      "agesEnfants": {"type": "array", "items": {"type": "integer"}},
      "budgetEuros": {"type": "integer", "description": "Le montant tel que le client le donne, sans calcul : « 900 € par personne » donne 900 avec budgetParPersonne à true."},
      "budgetParPersonne": {"type": "boolean", "description": "true : le budget est par personne ; false : c'est un total."},
      "transportInclus": {"type": "boolean"},
      "envies": {"type": "array", "items": {"type": "string"}, "description": "Activités, ambiance, rythme (« plage », « gastronomie »), y compris l'ambiance qui découle du projet : « romantique » pour un voyage de noces, « famille » avec des enfants, « nature » pour des paysages ou des aurores boréales, « détente » si le client veut se reposer. Pas la destination : elle va dans lieuOuZone."},
      "contraintes": {"type": "array", "items": {"type": "string"}, "description": "Mobilité réduite (avec le type de besoin), régime, allergie (avec sa gravité), santé, animaux."},
      "client": {"type": "object", "properties": {"prenom": {"type": "string"}, "email": {"type": "string"}, "telephone": {"type": "string"}}, "required": [], "additionalProperties": false},
      "recapitulatifConfirme": {"type": "boolean"}
    },
    "required": ["recapitulatifConfirme"],
    "additionalProperties": false
  }
}
```

Sortie : la fiche telle qu'enregistrée et ce qui manque encore.

```json
{
  "fiche": {
    "typeDeVoyage": "noces", "lieuOuZone": "Santorin", "villeDepart": "Paris",
    "dateDebut": "2027-06-12", "dateFin": "2027-06-19", "datesFlexibles": false, "nombreDeJours": 7,
    "temperatureMin": null, "temperatureMax": null, "adultes": 2, "enfants": 0, "agesEnfants": [],
    "budgetEuros": 5000, "budgetParPersonne": false, "transportInclus": true,
    "envies": ["romantique", "piscine privée", "bateau"], "contraintes": ["allergie sévère aux fruits de mer"]
  },
  "exigencesManquantes": ["températures"],
  "recapitulatifConfirme": false
}
```

Service : `DemandeService::enregistrerExigences(ContexteAgent, ExigencesDto)` convertit le budget en centimes, valide la fiche (`Demande` et ses contraintes), crée ou met à jour le `Client` lié à la demande, met à jour `modifieLe`. Quand `recapitulatifConfirme` passe à `true`, l'orchestrateur enchaîne sur l'agent Recherche. La confirmation est refusée (erreur renvoyée au modèle, exigences du message gardées) tant que la fiche n'a pas de type de voyage, de lieu ou de dates (#193).

### `chercher_destinations`

Trouve les destinations publiées qui correspondent à une zone, des dates, un climat et des envies. Sert à l'agent Recherche, et à l'agent Conversation pour proposer trois ambiances quand l'envie est floue.

Entrée :

```json
{
  "name": "chercher_destinations",
  "description": "Cherche dans le catalogue de l'agence les destinations publiées compatibles avec les critères donnés. Ne cite jamais au client une destination qui n'est pas dans ce résultat.",
  "strict": false,
  "input_schema": {
    "type": "object",
    "properties": {
      "zone": {"type": "string", "description": "Pays, région, ville ou zone large (« Méditerranée ») ; omettre pour tout le catalogue."},
      "dateDebut": {"type": "string", "description": "AAAA-MM-JJ"},
      "dateFin": {"type": "string", "description": "AAAA-MM-JJ"},
      "temperatureMin": {"type": "integer"},
      "temperatureMax": {"type": "integer"},
      "typeDeVoyage": {"type": "string", "enum": ["sejour", "circuit", "groupe", "noces", "entreprise"]},
      "envies": {"type": "array", "items": {"type": "string"}},
      "villeDepart": {"type": "string", "description": "Pour renvoyer le temps de trajet."},
      "limite": {"type": "integer", "description": "Nombre maximum de résultats (5 par défaut)."}
    },
    "required": [],
    "additionalProperties": false
  }
}
```

Sortie :

```json
{
  "destinations": [
    {
      "slug": "santorin", "ville": "Santorin", "pays": "Grèce", "region": "Cyclades",
      "pointsForts": ["Couchers de soleil à Oia", "Suites avec piscine privée"],
      "description": "Villages blancs accrochés à la caldeira…",
      "climatAuxDates": [{ "debut": "2027-06-01", "fin": "2027-06-30", "temperatureMoyenne": 25, "pluieMm": 2 }],
      "tempsDeTrajetMinutes": 240,
      "tarifsAuxDates": "complets",
      "aPartirDeParNuitEuros": 150
    }
  ],
  "ecartees": [{ "slug": "islande", "raison": "Aucun tarif saisi aux dates demandées." }]
}
```

`tarifsAuxDates` vaut `complets`, `partiels` ou `aucun` : une destination sans tarif aux dates reste visible dans `ecartees`, pour que l'agent puisse proposer d'autres dates ou passer la main au conseiller.

### `chercher_logements`

Liste les logements d'une destination compatibles avec le séjour : capacité (avec le nombre de chambres ou d'unités nécessaires), accessibilité, séjour minimum et tarifs nuit par nuit.

Entrée :

```json
{
  "name": "chercher_logements",
  "description": "Liste les logements compatibles d'une destination du catalogue pour un séjour donné, avec le prix de chaque période tarifaire. Utilise l'identifiant renvoyé (logementId) pour calculer le budget.",
  "strict": false,
  "input_schema": {
    "type": "object",
    "properties": {
      "destination": {"type": "string", "description": "Slug renvoyé par chercher_destinations, en minuscules (ex. « santorin », « costa-rica »)."},
      "dateDebut": {"type": "string", "description": "Première nuit, AAAA-MM-JJ."},
      "nombreDeNuits": {"type": "integer"},
      "voyageurs": {"type": "integer", "description": "Adultes et enfants."},
      "accessibleObligatoire": {"type": "boolean", "description": "true si un voyageur a besoin d'un logement accessible."},
      "envies": {"type": "array", "items": {"type": "string"}},
      "categorie": {"type": "string", "description": "Ex. « Villa », « Hôtel 4 étoiles » ; omettre pour toutes."}
    },
    "required": ["destination", "dateDebut", "nombreDeNuits", "voyageurs", "accessibleObligatoire"],
    "additionalProperties": false
  }
}
```

Sortie :

```json
{
  "logements": [
    {
      "logementId": 12, "nom": "Suite Caldera Oia, piscine privée", "prestataire": "Hôtels Azur Méditerranée",
      "categorie": "Suite de luxe", "capacite": 2, "unitesNecessaires": 1,
      "equipements": ["piscine privée", "vue caldeira", "petit-déjeuner"], "accessible": false,
      "envies": ["romantique", "détente"], "photos": ["villa.jpg"], "sejourMinimumNuits": 3,
      "tarifs": [{ "debut": "2027-04-01", "fin": "2027-06-30", "prixParNuitEuros": 420 }],
      "nuitsSansTarif": []
    }
  ],
  "ecartes": [{ "nom": "Villa Imerovigli", "raison": "Séjour minimum de 4 nuits pour un séjour de 3 nuits." }]
}
```

### `chercher_activites`

Liste les activités d'une destination, avec le nombre de participants éligibles selon l'âge minimum et le nombre de sessions si la capacité ne suffit pas.

Entrée :

```json
{
  "name": "chercher_activites",
  "description": "Liste les activités compatibles d'une destination du catalogue, avec les participants éligibles selon les âges et le prix par personne aux dates. Utilise l'identifiant renvoyé (activiteId) pour calculer le budget.",
  "strict": false,
  "input_schema": {
    "type": "object",
    "properties": {
      "destination": {"type": "string", "description": "Slug renvoyé par chercher_destinations, en minuscules (ex. « santorin »)."},
      "date": {"type": "string", "description": "Premier jour du voyage, AAAA-MM-JJ."},
      "adultes": {"type": "integer"},
      "agesEnfants": {"type": "array", "items": {"type": "integer"}},
      "accessibleObligatoire": {"type": "boolean"},
      "envies": {"type": "array", "items": {"type": "string"}}
    },
    "required": ["destination", "date", "adultes", "agesEnfants", "accessibleObligatoire"],
    "additionalProperties": false
  }
}
```

Sortie :

```json
{
  "activites": [
    {
      "activiteId": 41, "nom": "Randonnée guidée au Teide", "prestataire": "Explora Nature",
      "categorie": "Randonnée", "dureeMinutes": 360, "ageMinimum": 12,
      "participantsEligibles": 2, "exclus": ["enfant de 6 ans", "enfant de 9 ans"],
      "capacite": 15, "sessions": 1, "accessible": false, "envies": ["nature", "sport"],
      "prixParPersonneEuros": 78, "photos": ["randonnee.jpg"]
    }
  ],
  "ecartees": [{ "nom": "Baptême de plongée", "raison": "Non accessible : un voyageur se déplace en fauteuil roulant." }]
}
```

### `calculer_budget`

Calcule les trois niveaux de budget avec la formule de `docs/chiffrage.md`, à partir des logements et activités compatibles. Le modèle ne fait **aucun calcul** : il fournit les candidats, le serveur choisit les combinaisons de chaque niveau et chiffre au centime près. Les voyageurs, la ville de départ, le budget du client et le type de voyage viennent de la demande courante.

Pour tester un arbitrage (autres dates, moins de nuits), l'agent rappelle l'outil avec d'autres étapes : la demande n'est pas modifiée tant que le client n'a pas accepté.

Entrée :

```json
{
  "name": "calculer_budget",
  "description": "Calcule les budgets essentiel, équilibré et confort de la demande en cours, selon les règles de prix de l'agence. Donne pour chaque étape les logements compatibles, puis les activités demandées par le client et celles que tu suggères. Ne calcule jamais de prix toi-même : reprends les montants renvoyés.",
  "strict": true,
  "input_schema": {
    "type": "object",
    "properties": {
      "destination": {"type": "string", "description": "Slug renvoyé par chercher_destinations, en minuscules (ex. « santorin »)."},
      "etapes": {"type": "array", "description": "Une étape pour un séjour, plusieurs pour un circuit, dans l'ordre du voyage.", "items": {"type": "object", "properties": {"dateDebut": {"type": "string", "description": "Première nuit de l'étape, AAAA-MM-JJ."}, "nombreDeNuits": {"type": "integer"}, "logementIds": {"type": "array", "items": {"type": "integer"}, "description": "Logements compatibles pour cette étape."}}, "required": ["dateDebut", "nombreDeNuits", "logementIds"], "additionalProperties": false}},
      "activitesDemandees": {"type": "array", "items": {"type": "integer"}, "description": "Activités que le client a explicitement demandées (incluses à tous les niveaux)."},
      "activitesSuggerees": {"type": "array", "items": {"type": "integer"}, "description": "Activités en accord avec ses envies (ajoutées selon le niveau et le budget)."}
    },
    "required": ["destination", "etapes", "activitesDemandees", "activitesSuggerees"],
    "additionalProperties": false
  }
}
```

Sortie (extrait, niveau essentiel seul) :

```json
{
  "chiffrageId": "chf_7f3a2c",
  "budgetClient": { "montantEuros": 5000, "parPersonne": false },
  "budgetInsuffisant": false,
  "niveaux": {
    "essentiel": {
      "logements": ["Hôtel Fira Blue, chambre triple"],
      "activites": ["Croisière en catamaran dans la caldeira"],
      "transport": "minimum de la fourchette",
      "postes": [
        { "type": "hebergement", "libelle": "Hôtel Fira Blue, chambre triple", "calcul": "7 nuits × 150 €", "montantEuros": 1050, "estime": false },
        { "type": "activite", "libelle": "Croisière en catamaran dans la caldeira", "calcul": "2 participants × 120 €", "montantEuros": 240, "estime": false },
        { "type": "transport", "libelle": "Vol aller-retour Paris – Santorin", "calcul": "2 voyageurs × 220 €", "montantEuros": 440, "estime": true },
        { "type": "transport", "libelle": "Transferts aéroport – hébergement", "calcul": "2 voyageurs × 25 €", "montantEuros": 50, "estime": true }
      ],
      "coutPrestationsEuros": 1780, "margeEuros": 356, "fraisDeDossierEuros": 150,
      "totalEuros": 2286, "parPersonneEuros": 1143,
      "hypotheses": ["Saison d'avril à juin", "Hôtel 3 étoiles, 1 chambre", "Vols estimés au bas de la fourchette", "Frais de dossier voyage de noces : 150 €"]
    },
    "equilibre": { "…": "même structure" },
    "confort": { "…": "même structure" }
  },
  "nonChiffrable": null
}
```

La sortie contient aussi `avertissement` (« Première proposition, prix estimés, à confirmer par votre conseiller. ») et `exclusions` : les candidats écartés et pourquoi (pas de tarif à une des nuits, séjour minimum plus long que l'étape, suggestion sans tarif au premier jour). Si plus de 20 000 combinaisons sont à comparer pour l'équilibré, l'entrée est refusée : l'agent doit donner moins de candidats.

Si même l'essentiel dépasse le budget (`budgetInsuffisant`), la sortie contient aussi `arbitrages` (ticket #206, voir `docs/chiffrage.md`) : l'agent les présente au client et, s'il en accepte un, rappelle l'outil avec ses `etapes`.

```json
"arbitrages": [
  {
    "type": "moins_de_nuits_et_autre_periode",
    "libelle": "Réduire à 3 nuits, du 28/09/2027 au 01/10/2027, à une période moins chère",
    "etapes": [{ "dateDebut": "2027-09-28", "nombreDeNuits": 3 }],
    "totalEuros": 4448, "parPersonneEuros": 370.67,
    "tientDansLeBudget": false, "ecartAuBudgetEuros": 848,
    "hypotheses": ["Riad Dar Yasmine, riad entier : Riad privatisé, 6 chambres, 3 nuits du 28/09/2027 au 01/10/2027, tarif de juin à septembre 2027", "…"]
  }
]
```

Si un tarif manque, `niveaux` vaut `null` et `nonChiffrable` explique pourquoi : `{"prestation": "Guesthouse Reykjavik Centre", "date": "2027-01-16", "raison": "Aucun tarif saisi à cette date."}`. L'agent propose alors un autre logement ou d'autres dates, ou appelle `transmettre_au_conseiller`.

Le résultat est gardé en cache 24 heures sous la demande courante, avec son `chiffrageId`.

### `enregistrer_proposition`

Enregistre une nouvelle version de la proposition à partir d'un chiffrage : programme jour par jour, lignes de prestation et budgets des trois niveaux. Les montants viennent uniquement du chiffrage, jamais du modèle. La version précédente passe à « remplacée ».

Entrée :

```json
{
  "name": "enregistrer_proposition",
  "description": "Enregistre la proposition de la demande en cours à partir d'un chiffrage déjà calculé, avec le programme jour par jour que tu as rédigé. Renvoie le lien que le client peut ouvrir.",
  "strict": true,
  "input_schema": {
    "type": "object",
    "properties": {
      "chiffrageId": {"type": "string"},
      "niveauDetaille": {"type": "string", "enum": ["essentiel", "equilibre", "confort"], "description": "Niveau dont les lignes de prestation sont détaillées ; équilibré par défaut."},
      "pourquoi": {"type": "string", "description": "Pourquoi cette destination répond aux exigences : températures aux dates, temps de trajet, ambiance."},
      "programme": {"type": "array", "items": {"type": "object", "properties": {"jour": {"type": "integer"}, "titre": {"type": "string"}, "description": {"type": "string"}}, "required": ["jour", "titre", "description"], "additionalProperties": false}},
      "resumeDesChangements": {"type": "string", "description": "Pour une nouvelle version : ce qui a changé par rapport à la précédente."}
    },
    "required": ["chiffrageId", "niveauDetaille", "pourquoi", "programme"],
    "additionalProperties": false
  }
}
```

Sortie :

```json
{
  "version": 2,
  "statut": "envoyee",
  "lienClient": "/ma-proposition/3b9d…e41f",
  "totalEuros": { "essentiel": 2286, "equilibre": 4956, "confort": 5502 },
  "mention": "Première proposition, prix estimés, à confirmer par votre conseiller"
}
```

Service : `PropositionService::enregistrer(ContexteAgent, chiffrageId, …)` vérifie que le chiffrage appartient à la demande courante, que le programme compte autant de jours que le séjour, crée la `Proposition` (type « première », statut « envoyée ») et ses `LigneDePrestation`, et passe la demande au statut « proposition envoyée ».

### `transmettre_au_conseiller`

Passe la main à un conseiller : à la demande du client, quand une donnée manque (période sans tarif ni climat), ou pour une demande hors de ce que l'agent sait faire.

Entrée :

```json
{
  "name": "transmettre_au_conseiller",
  "description": "Transmet la demande en cours à un conseiller de l'agence, qui reprendra la conversation. Écris pour lui un résumé utile ; préviens ensuite le client qu'un conseiller va le recontacter.",
  "strict": true,
  "input_schema": {
    "type": "object",
    "properties": {
      "motif": {"type": "string", "enum": ["demande_du_client", "donnees_manquantes", "hors_perimetre", "autre"]},
      "resume": {"type": "string", "description": "Pour le conseiller : où en est la demande et ce qui bloque."}
    },
    "required": ["motif", "resume"],
    "additionalProperties": false
  }
}
```

Sortie :

```json
{
  "transmise": true,
  "delaiAnnonce": "2 jours ouvrés",
  "messagePourLeClient": "Un conseiller de l'agence reprend votre demande et vous recontacte sous 2 jours ouvrés."
}
```

Service : `DemandeService::transmettreAuConseiller(ContexteAgent, motif, resume)` passe `Conversation::repriseParConseiller` à `true`, ajoute le résumé au journal et rend la demande visible comme « à reprendre » dans le pipeline du backoffice.

## Quel agent voit quels outils

| Outil | Conversation (Haiku 5.5) | Recherche (Sonnet 5.5) | Chiffrage et rédaction (Sonnet 5.5) | Modification (Sonnet 5.5) |
| --- | --- | --- | --- | --- |
| `enregistrer_exigences` | ✓ | | | ✓ |
| `chercher_destinations` | ✓ | ✓ | | ✓ |
| `chercher_logements` | | ✓ | | ✓ |
| `chercher_activites` | | ✓ | | ✓ |
| `calculer_budget` | | | ✓ | ✓ |
| `enregistrer_proposition` | | | ✓ | ✓ |
| `transmettre_au_conseiller` | ✓ | ✓ | ✓ | ✓ |

Chaque agent ne reçoit que ses outils dans sa requête : l'agent Conversation ne peut ni chiffrer ni enregistrer de proposition.
