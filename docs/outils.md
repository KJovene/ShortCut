# Outils des agents

Les agents IA n'accèdent aux données de l'agence qu'à travers ces dix outils (ticket #178, `retenir_combinaison` ajouté par le ticket #203, `consulter_catalogue` pour les recommandations du chatbot, `choisir_options` pour les options de la proposition). Neuf sont donnés aux trois agents IA (Conversation, Recherche, Chiffrage et rédaction) ; `calculer_budget` n'est appelé que par `retenir_combinaison`. Chaque outil correspond à un service Symfony : le modèle choisit l'outil et ses paramètres, le serveur vérifie, exécute et renvoie le résultat. C'est ce qui empêche l'IA d'inventer des prestations ou des prix.

| Outil | Rôle | Agents | Service Symfony | Lit | Écrit |
| --- | --- | --- | --- | --- | --- |
| `enregistrer_exigences` | Remplir la fiche demande au fil de la conversation | Conversation | `App\Chatbot\Outil\EnregistrerExigences` | Demande courante | Demande courante, client |
| `chercher_destinations` | Trouver les destinations compatibles (zone, climat, dates, envies) | Conversation, Recherche | `App\Chatbot\Outil\ChercherDestinations` | Catalogue publié | — |
| `chercher_logements` | Lister les logements compatibles d'une destination, avec leurs tarifs aux dates | Recherche | `App\Chatbot\Outil\ChercherLogements` | Catalogue publié | — |
| `chercher_activites` | Lister les activités compatibles d'une destination, selon les âges | Recherche | `App\Chatbot\Outil\ChercherActivites` | Catalogue publié | — |
| `consulter_catalogue` | Recommander au client des logements ou des activités d'une destination, sans dates ni prix | Conversation | `App\Chatbot\Outil\ConsulterCatalogue` | Catalogue publié | — |
| `retenir_combinaison` | Retenir la destination, les logements et les activités choisis, vérifiés contre la fiche, et chiffrer les trois niveaux | Recherche (et `NouvelleVersion`, sans modèle) | `App\Chatbot\Outil\RetenirCombinaison` | Catalogue, demande courante | Chiffrage et combinaison en cache (demande courante) |
| `calculer_budget` | Calculer les trois niveaux de budget selon `docs/chiffrage.md` | Aucun : appelé par `retenir_combinaison` | `BudgetCalculator::calculer()` (outil `App\Chatbot\Outil\CalculerBudget`) | Catalogue, règle de prix, demande courante | Chiffrage en cache (demande courante) |
| `enregistrer_proposition` | Enregistrer une version de proposition à partir d'un chiffrage | Chiffrage et rédaction | `PropositionService::enregistrer()` (outil `App\Chatbot\Outil\EnregistrerProposition`) | Chiffrage de la demande courante | Proposition et lignes de la demande courante |
| `transmettre_au_conseiller` | Passer la main à un conseiller | Tous | `App\Chatbot\Outil\TransmettreAuConseiller` (`Demande::transmettre()`) | — | Demande et conversation courantes |
| `choisir_options` | Noter la réponse du client aux options de sa proposition (activités ajoutées, niveau) | Conversation | `App\Chatbot\Outil\ChoisirOptions`, puis `NouvelleVersion::appliquerLesOptions()` | Proposition de la demande courante | — |

## Règles communes

### Contexte imposé par le serveur

Aucun outil ne reçoit d'identifiant de demande, de conversation, de proposition ou de client. Le serveur construit un **contexte** à partir du jeton de conversation du visiteur (`VisiteurChatbot`, ticket #173) et le passe à chaque service :

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

Code : `api/src/Chatbot/ContexteAgent.php` et `api/src/Chatbot/Outil/`. Un outil est un service Symfony qui implémente `Outil` (tag ajouté automatiquement) ; chaque agent liste ses outils dans un ordre fixe (méthode `outils()` de `AgentConversation`, `AgentRecherche` et `AgentRedaction`), pour que le début de la requête reste identique d'un appel à l'autre et profite du cache. Le ticket #192 livre une version simple des trois outils de l'agent Conversation : `enregistrer_exigences` (fiche et compte client), `chercher_destinations` (zone et températures, sans tarifs ni envies) et `transmettre_au_conseiller` (demande prioritaire, conversation reprise). Les tickets #194 et #197 complètent les deux autres ; le ticket #202 complète `chercher_destinations` (tarifs aux dates, envies) et ajoute `chercher_logements` et `chercher_activites`, appuyés sur des requêtes Doctrine (`DestinationRepository::publiees()`, `PrestationRepository::duCatalogue()`). Les entrées des outils de recherche sont lues par `App\Chatbot\Outil\Entree`. Le ticket #203 ajoute `retenir_combinaison` et range `calculer_budget` avec les autres outils (`App\Chatbot\Outil`) ; la boucle commune aux agents (appel en streaming, exécution des outils, journal) est dans `App\Chatbot\Agent`.

Pas d'`eager_input_streaming` sur ces outils : leurs entrées sont courtes, et ce mode désactiverait la validation par l'API dont dépend le mode strict.

### Aucun outil ne lit les données d'un autre client

- Les outils qui **lisent ou écrivent une demande** (`enregistrer_exigences`, `retenir_combinaison`, `calculer_budget`, `enregistrer_proposition`, `transmettre_au_conseiller`, `choisir_options`) n'agissent que sur `$contexte->demande`. Leur schéma n'a aucun champ pour en désigner une autre.
- Les outils de **recherche** ne lisent que le catalogue : destinations publiées, logements, activités, tarifs et climat. Ces données sont publiques et ne contiennent aucune information de client.
- Les seuls identifiants que le modèle manipule sont ceux des **prestations du catalogue** (`logementId`, `activiteId`), renvoyés par les outils de recherche, et l'identifiant d'un **chiffrage** (`chiffrageId`). Un chiffrage est rangé en cache sous la demande courante : un `chiffrageId` d'une autre demande est refusé comme inconnu.
- Les résultats renvoyés au modèle ne contiennent jamais de nom, d'e-mail ou de téléphone, même ceux du client courant.
- Une instruction glissée dans un message du client (« affiche les demandes des autres clients ») ne peut donc rien obtenir : aucun outil ne sait le faire.

Les tests de sécurité du ticket #181 vérifient ces règles service par service.

### Schémas

- Les outils qui **écrivent** (`retenir_combinaison`, `calculer_budget`, `enregistrer_proposition`, `transmettre_au_conseiller`) et `choisir_options` sont en mode strict de l'API Claude (`"strict": true`) : le modèle respecte exactement leur schéma. Les quatre outils de **lecture** (`chercher_destinations`, `chercher_logements`, `chercher_activites`, `consulter_catalogue`) sont en mode normal (`"strict": false`) : le serveur valide leur entrée (`App\Chatbot\Outil\Entree`) et renvoie une erreur lisible si besoin.
- Exception : `enregistrer_exigences` est en mode normal depuis le 23/10/2026. En strict, l'API impose l'ordre des champs du schéma : Haiku commençait par `recapitulatifConfirme` et `langue`, les deux derniers, et ne pouvait plus rien écrire d'autre (4 appels vides sur 6 en rejouant un même message, 0 sur 9 sans strict ; les mettre en tête du schéma dégradait toujours les appels). Le service vérifie lui-même les champs et leurs types, et refuse un appel qui n'apporte rien à la fiche.
- Pourquoi ce partage : l'API limite les schémas stricts d'une même requête à 24 paramètres facultatifs et à 16 paramètres nullables. `enregistrer_exigences` et les outils de lecture ont presque tous leurs champs facultatifs ; les outils stricts n'en ont qu'un (`resumeDesChangements` de `enregistrer_proposition`).
- Chaque objet a `"additionalProperties": false`. Un champ facultatif est simplement **absent de `required`** (le modèle l'omet), sans type `null`.
- Vérifié le 16/10/2026 : l'API Claude accepte les outils de chacun des trois agents (Haiku 5.5 pour Conversation, Sonnet 5.5 pour Recherche et pour Chiffrage et rédaction), et les modèles les appellent avec des entrées conformes.
- Les bornes (nombres positifs, dates cohérentes, mois de 1 à 12) sont vérifiées par le service avec le composant Validator de Symfony, pas par le schéma.
- Dates au format `AAAA-MM-JJ`. Montants en **euros** dans les entrées et sorties, pour que le modèle les lise et les recopie sans erreur. Les services calculent et stockent en **centimes** ; les montants renvoyés ont au plus deux décimales.
- Le choix d'outil reste `auto` : Claude Sonnet 5.5 refuse le choix forcé d'un outil, et le prompt de chaque agent dit quand appeler lequel.

### Erreurs

Les services acceptent un slug de destination sans tenir compte des majuscules ni des accents (« Santorin » → `santorin`). Une entrée refusée renvoie un `tool_result` avec `is_error: true` et un message en français que le modèle peut corriger ou expliquer au client : `{"erreur": "La destination « paris » n'existe pas dans le catalogue."}`.

### Journal

Chaque appel à Claude est tracé dans `JournalIA`, une ligne par appel : agent, demande, modèle, entrée (le message du tour), sortie (réponse du modèle, avec les outils appelés et leurs résultats), tokens d'entrée et de sortie, durée (`dureeMs`). Un appel qui échoue garde l'erreur dans la sortie. Les appels de `retenir_combinaison` par `NouvelleVersion`, sans modèle, n'y figurent pas.

## Les outils

### `enregistrer_exigences`

Met à jour la fiche demande avec ce que le client vient de dire. Seuls les champs envoyés sont modifiés ; une liste envoyée remplace la liste enregistrée. Appelé par l'agent Conversation après chaque information nouvelle, y compris quand le client change une exigence après sa proposition (`NouvelleVersion` recalcule ensuite la version suivante).

Entrée :

```json
{
  "name": "enregistrer_exigences",
  "description": "Enregistre dans la fiche demande toutes les exigences que contient le dernier message du client : destination ou zone dans lieuOuZone, dates, durée, voyageurs, ville de départ, budget, envies, contraintes. N'envoie que les champs nouveaux ou modifiés et omets les autres. Une liste (envies, contraintes, âges des enfants) remplace la liste déjà enregistrée : renvoie-la en entier. Passe recapitulatifConfirme à true seulement quand le client a confirmé le récapitulatif. Les textes de la fiche (lieu, ville de départ, envies, contraintes) s'écrivent toujours en français, même si le client écrit en anglais : « Lisbonne », « plage ».",
  "strict": false,
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
      "adultes": {"type": "integer"},
      "enfants": {"type": "integer"},
      "agesEnfants": {"type": "array", "items": {"type": "integer"}},
      "budgetEuros": {"type": "integer", "description": "Le montant tel que le client le donne, sans calcul : « 900 € par personne » donne 900 avec budgetParPersonne à true."},
      "budgetParPersonne": {"type": "boolean", "description": "true : le budget est par personne ; false : c'est un total."},
      "transportInclus": {"type": "boolean"},
      "envies": {"type": "array", "items": {"type": "string"}, "description": "Activités, ambiance, rythme (« plage », « gastronomie »), y compris l'ambiance qui découle du projet : « romantique » pour un voyage de noces, « famille » avec des enfants, « nature » pour des paysages ou des aurores boréales, « détente » si le client veut se reposer. Une préférence de climat y va aussi, avec les mots du client : « de la chaleur », « au moins 25 °C », « pas trop chaud ». Pas la destination : elle va dans lieuOuZone."},
      "contraintes": {"type": "array", "items": {"type": "string"}, "description": "Mobilité réduite (avec le type de besoin), régime, allergie (avec sa gravité), santé, animaux."},
      "client": {"type": "object", "properties": {"prenom": {"type": "string"}, "email": {"type": "string"}, "telephone": {"type": "string"}}, "required": [], "additionalProperties": false},
      "recapitulatifConfirme": {"type": "boolean"},
      "langue": {"type": "string", "enum": ["fr", "en"], "description": "Langue dans laquelle le client écrit : « en » s'il écrit en anglais, « fr » sinon. À envoyer dès son premier message, puis s'il change de langue."}
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
    "adultes": 2, "enfants": 0, "agesEnfants": [],
    "budgetEuros": null, "budgetParPersonne": false, "transportInclus": true,
    "envies": ["romantique", "piscine privée", "bateau"], "contraintes": ["allergie sévère aux fruits de mer"]
  },
  "exigencesManquantes": ["budget"],
  "recapitulatifConfirme": false,
  "langue": "fr"
}
```

La langue (US15) n'est pas dans la fiche : elle règle la langue des réponses, de la proposition (page « Ma proposition » et PDF) et des textes fixes du site, mais la fiche reste en français pour les outils de recherche et le backoffice.

`exigencesManquantes` reprend `FicheDemande::manquantes()` : type de voyage, lieu ou zone, dates, nombre de jours, ville de départ, budget, envies.

Code : `EnregistrerExigences::executer()` vérifie les champs et leurs types (mode normal), refuse un appel qui n'apporte rien à la fiche, convertit le budget en centimes, valide la fiche (`Demande` et ses contraintes ; rien n'est gardé si elle est refusée), rattache la demande au compte client de l'e-mail donné (créé au besoin, jamais modifié depuis le chatbot) et met à jour `modifieLe`. La confirmation est refusée (erreur renvoyée au modèle, exigences du message gardées) tant que la fiche n'a pas de type de voyage, de lieu, ni de date de début ou de nombre de jours (#193). Quand `recapitulatifConfirme` passe à `true`, `AgentConversation` enchaîne sur l'agent Recherche, ou sur `NouvelleVersion` si le client a déjà une proposition.

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
      "zone": {"type": "string", "description": "Pays, région, ville ou zone large (« Méditerranée », « Asie du Sud-Est », « Afrique », « Caraïbes », « Europe du Nord ») ; omettre pour tout le catalogue."},
      "dateDebut": {"type": "string", "description": "Jour du départ, AAAA-MM-JJ."},
      "dateFin": {"type": "string", "description": "Jour du retour, AAAA-MM-JJ."},
      "temperatureMin": {"type": "integer", "description": "Température moyenne minimale, en °C ; demande les dates."},
      "temperatureMax": {"type": "integer"},
      "typeDeVoyage": {"type": "string", "enum": ["sejour", "circuit", "groupe", "noces", "entreprise"]},
      "envies": {"type": "array", "items": {"type": "string"}, "description": "Les destinations qui y répondent le mieux sont listées en premier."},
      "villeDepart": {"type": "string", "description": "Pour renvoyer le temps de trajet."},
      "voyageurs": {"type": "integer", "description": "Adultes et enfants : écarte les destinations sans logement pour le groupe."},
      "accessibleObligatoire": {"type": "boolean", "description": "true si un voyageur a besoin d'un logement accessible."},
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
      "enviesCorrespondantes": ["romantique"],
      "aPartirDeParNuitEuros": 150
    }
  ],
  "ecartees": [
    {
      "slug": "islande",
      "raison": "Aucun logement n'a de tarif pour chaque nuit du 16/01/2027 au 22/01/2027 : proposer d'autres dates ou passer la main au conseiller.",
      "climatAuxDates": [{ "debut": "2027-01-01", "fin": "2027-01-31", "temperatureMoyenne": 0, "pluieMm": 90 }],
      "datesPossibles": [{ "dateDebut": "2027-05-01", "dateFin": "2027-05-07" }]
    }
  ],
  "autresDestinations": [{ "slug": "costa-rica", "ville": "La Fortuna", "pays": "Costa Rica", "enviesCorrespondantes": ["nature"], "aPartirDeParNuitEuros": 144 }]
}
```

Règles :

- **Zone** : rapprochée de la ville, du pays, de la région, du slug et des zones larges saisies dans le backoffice (`Destination::zones` : « Europe », « Méditerranée », « Asie du Sud-Est », « Caraïbes »…), sans tenir compte des accents. Les destinations qui portent exactement ce nom passent seules si elles existent (« Afrique du Sud » donne Le Cap, pas toute l'Afrique) ; sinon, un nom contenu dans la zone ou qui la contient suffit (« la Méditerranée », « Santorin, Grèce »). Une zone qui ne correspond à rien (« soleil, plage ») renvoie tout le catalogue avec une `remarque`.
- **Groupe** : avec `voyageurs` (et `accessibleObligatoire`), seuls comptent les logements qui accueillent le groupe, selon les règles de `chercher_logements`. Une destination qui n'en a aucun est écartée (« Aucun logement pour 60 voyageurs »), sans autres dates à proposer.
- **Température** : chaque période climatique qui chevauche les dates doit respecter `temperatureMin` et `temperatureMax`. Un voyage à cheval sur un mois trop frais est écarté. Sans données de climat aux dates, la destination est écartée, car la température ne peut pas être garantie. Une température sans dates est refusée.
- **Tarifs aux dates** : une destination n'est retenue que si au moins un de ses logements a un tarif pour chaque nuit, du départ à la veille du retour. Sinon, elle va dans `ecartees`, pour que l'agent propose d'autres dates ou passe la main au conseiller. `aPartirDeParNuitEuros` est le prix de nuit le plus bas parmi ces logements : c'est un prix d'achat, sans marge. Plus de 60 nuits entre les deux dates sont refusées, car ce sont les dates du voyage, pas une saison.
- **Ajustements** (ticket #204) : quand une zone est demandée et trouvée, chaque destination écartée aux dates demandées donne son climat saisi (`climatAuxDates`) et ce qui la rendrait possible, calculé sur les données, jamais par le modèle ; sur tout le catalogue, une destination écartée ne donne que sa raison, pour ne pas envoyer au modèle des dizaines d'ajustements inutiles. `datesPossibles` : les départs les plus proches, avant et après, où le même voyage convient (températures et tarifs), dans les six mois et jamais avant aujourd'hui. `nuitsPossibles` : la durée la plus longue qui convient aux dates demandées, si elle existe. Quand une zone est demandée et que toutes ses destinations sont écartées, `autresDestinations` donne jusqu'à trois destinations du catalogue qui conviennent aux mêmes dates et critères.
- **Envies** : `enviesCorrespondantes` liste les envies du client présentes dans les logements ou activités de la destination, selon `App\Texte\Correspondance` : leurs envies, équipements, catégories et noms (`Prestation::motsCles()`, « piscine privée », « dauphins »). Les logements et activités calculent leurs `enviesCorrespondantes` de la même façon. Les destinations qui en ont le plus viennent en premier ; à égalité, celles qui proposent le `typeDeVoyage` demandé, puis celles dont le meilleur mois (`moisIdeal`) est le plus proche du départ, puis par ville. Les envies classent les résultats sans les filtrer : une envie floue garde tout le catalogue.

### `chercher_logements`

Liste les logements d'une destination compatibles avec le séjour : capacité (avec le nombre de chambres ou d'unités nécessaires), accessibilité, catégorie, séjour minimum et tarifs nuit par nuit.

Entrée :

```json
{
  "name": "chercher_logements",
  "description": "Liste les logements compatibles d'une destination du catalogue pour un séjour donné, avec le prix de chaque nuit. Utilise l'identifiant renvoyé (logementId) pour calculer le budget. Les prix sont des prix d'achat, hors marge : ne les annonce jamais au client, seuls les montants de calculer_budget le sont.",
  "strict": false,
  "input_schema": {
    "type": "object",
    "properties": {
      "destination": {"type": "string", "description": "Slug renvoyé par chercher_destinations, en minuscules (ex. « santorin », « costa-rica »)."},
      "dateDebut": {"type": "string", "description": "Première nuit, AAAA-MM-JJ."},
      "nombreDeNuits": {"type": "integer"},
      "voyageurs": {"type": "integer", "description": "Adultes et enfants."},
      "accessibleObligatoire": {"type": "boolean", "description": "true si un voyageur a besoin d'un logement accessible."},
      "envies": {"type": "array", "items": {"type": "string"}, "description": "Les logements qui y répondent le mieux sont listés en premier."},
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
      "categorie": "Suite de luxe", "description": "Suite troglodyte face au coucher du soleil…",
      "capacite": 2, "chambres": 1, "unitesNecessaires": 1,
      "equipements": ["piscine privée", "vue caldeira", "petit-déjeuner"], "accessible": false,
      "envies": ["romantique", "détente"], "enviesCorrespondantes": ["romantique"],
      "photos": ["villa.jpg"], "sejourMinimumNuits": 3,
      "tarifs": [{ "du": "2027-06-12", "au": "2027-06-19", "nuits": 7, "prixParNuitEuros": 420 }]
    }
  ],
  "ecartes": [{ "nom": "Villa Imerovigli", "raison": "Séjour minimum de 4 nuits pour un séjour de 3 nuits." }]
}
```

Règles, dans l'ordre où elles écartent un logement :

1. **Accessibilité** : avec `accessibleObligatoire`, seuls les logements accessibles restent.
2. **Capacité** : `unitesNecessaires = ⌈ voyageurs ÷ capacité ⌉`, comme pour le chiffrage. Un logement de plusieurs chambres (riad, villa, bloc de chambres) se loue entier, une seule fois. Une chambre se réserve jusqu'à 4 fois (`ChercherLogements::UNITES_MAX`) ; au-delà, il faut un tarif de groupe : 35 personnes à l'Hôtel Colosseo, en chambres doubles, sont écartées (« il faudrait 18 chambres »).
3. **Tarifs** : chaque nuit doit avoir un tarif. Le logement est écarté sinon, avec la première nuit sans tarif.
4. **Séjour minimum** : celui de la période de la première nuit, comme pour le chiffrage.

Une `categorie` filtre sans écarter : les autres catégories ne sont pas demandées, elles n'apparaissent pas dans `ecartes`. Les logements sont classés par nombre d'envies correspondantes, puis du moins cher au plus cher pour le séjour (nuits × unités). `tarifs` regroupe les nuits de même prix ; `au` est le lendemain de la dernière nuit.

### `chercher_activites`

Liste les activités d'une destination avec les voyageurs qui ont l'âge d'y participer, et le nombre de sessions si la capacité ne suffit pas. Un enfant trop jeune n'écarte pas l'activité : elle n'est proposée qu'aux autres voyageurs.

Entrée :

```json
{
  "name": "chercher_activites",
  "description": "Liste les activités compatibles d'une destination du catalogue, avec les voyageurs qui ont l'âge d'y participer et le prix par personne aux dates. Une activité qui exclut un enfant ne se propose qu'aux autres voyageurs, en le disant. Utilise l'identifiant renvoyé (activiteId) pour calculer le budget. Les prix sont des prix d'achat, hors marge : ne les annonce jamais au client, seuls les montants de calculer_budget le sont.",
  "strict": false,
  "input_schema": {
    "type": "object",
    "properties": {
      "destination": {"type": "string", "description": "Slug renvoyé par chercher_destinations, en minuscules (ex. « santorin »)."},
      "date": {"type": "string", "description": "Premier jour du voyage, AAAA-MM-JJ."},
      "adultes": {"type": "integer"},
      "agesEnfants": {"type": "array", "items": {"type": "integer"}},
      "accessibleObligatoire": {"type": "boolean"},
      "envies": {"type": "array", "items": {"type": "string"}, "description": "Les activités qui y répondent le mieux sont listées en premier."},
      "dureeMaxMinutes": {"type": "integer", "description": "Durée maximale d'une activité ; omettre si le client n'en a pas donné."}
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
      "activiteId": 41, "nom": "Cours de cuisine marocaine et tajine", "prestataire": "Saveurs & Ateliers",
      "categorie": "Atelier culinaire", "description": null, "dureeMinutes": 240, "ageMinimum": null,
      "participants": 40, "exclus": [], "capacite": 16, "sessions": 3, "accessible": true,
      "envies": ["gastronomie"], "enviesCorrespondantes": [], "photos": ["cuisine.jpg"],
      "prixParPersonneEuros": 54
    }
  ],
  "ecartees": [
    { "nom": "Hammam traditionnel et massage", "raison": "Non accessible : un voyageur a besoin d'une activité accessible." }
  ]
}
```

Règles, dans l'ordre où elles écartent une activité : accessibilité, durée maximale, puis tarif au premier jour du voyage (celui que retient le chiffrage). L'âge minimum n'écarte pas l'activité : elle est proposée aux seuls voyageurs qui ont l'âge (`participants`), les enfants trop jeunes sont listés dans `exclus`, et le chiffrage ne compte que les participants. C'est le cas de la randonnée au Teide dans la demande test de Tenerife : exclue pour les enfants de 6 et 9 ans, retenue pour les parents au niveau confort. Une capacité trop petite n'écarte pas l'activité : `sessions = ⌈ participants ÷ capacité ⌉`. Les activités que toute la famille peut faire viennent en premier, puis elles sont classées par nombre d'envies correspondantes et par nom.

### `consulter_catalogue`

Outil de l'agent Conversation : le client demande des recommandations (où dormir, que faire sur place) avant sa proposition. Mêmes fiches du backoffice que `chercher_logements` et `chercher_activites`, mais sans dates ni prix, car le chatbot ne donne ni disponibilité ni prix avant la proposition.

Entrée :

```json
{
  "name": "consulter_catalogue",
  "strict": false,
  "input_schema": {
    "type": "object",
    "properties": {
      "destination": {"type": "string", "description": "Slug ou ville renvoyé par chercher_destinations (ex. « santorin »)."},
      "type": {"type": "string", "enum": ["logement", "activite"]},
      "envies": {"type": "array", "items": {"type": "string"}},
      "voyageurs": {"type": "integer", "description": "Logements : écarte ceux qui ne peuvent pas accueillir le groupe."},
      "accessibleObligatoire": {"type": "boolean"},
      "limite": {"type": "integer", "description": "Nombre de fiches (3 par défaut, 6 au plus)."}
    },
    "required": ["destination", "type"],
    "additionalProperties": false
  }
}
```

Sortie :

```json
{
  "destination": {"slug": "marrakech", "ville": "Marrakech", "pays": "Maroc"},
  "type": "logement",
  "prestations": [
    {
      "id": 12, "nom": "Riad Dar Yasmine, riad entier", "categorie": "Riad privatisé", "description": "Six chambres autour d'un patio avec piscine…", "capacite": 12, "accessible": false,
      "equipements": ["wifi", "piscine", "terrasse", "cuisinière sur place", "petit-déjeuner"], "envies": ["culture", "détente", "famille"], "enviesCorrespondantes": ["piscine"], "chambres": 6
    }
  ],
  "ecartees": 1
}
```

Un logement est écarté par les mêmes règles d'accueil que `chercher_logements` (accessibilité, capacité) ; une activité, par l'accessibilité seule. Les fiches sont classées par nombre d'envies correspondantes, puis par nom ; une activité donne `dureeMinutes` et `ageMinimum` au lieu de `chambres`.

Après la réponse du modèle, `AgentConversation` envoie au site l'événement `suggestions` (`App\Chatbot\Suggestions`) : les fiches renvoyées pendant le tour, relues en base (8 au plus), avec leurs photos, leur destination, leurs équipements, leur durée et leur âge minimum, sans prix. Le site les affiche en cartes sous le message ; chaque carte ouvre une fenêtre avec la fiche complète. La carte est gardée avec le message de l'agent (`Conversation::messages`) et réaffichée quand la conversation est reprise.

### `retenir_combinaison`

Outil de l'agent Recherche (ticket #203) : il retient la combinaison choisie pour la demande courante. Le modèle donne la destination, les logements de chaque étape et les activités, pris dans les résultats des trois outils de recherche. Le serveur ne lui fait pas confiance : il refait les recherches avec les critères de la fiche demande, refuse tout choix qu'elles n'auraient pas renvoyé, puis chiffre les trois niveaux.

Entrée :

```json
{
  "name": "retenir_combinaison",
  "strict": true,
  "input_schema": {
    "type": "object",
    "properties": {
      "destination": {"type": "string"},
      "etapes": {"type": "array", "items": {"type": "object", "properties": {"dateDebut": {"type": "string"}, "nombreDeNuits": {"type": "integer"}, "logementIds": {"type": "array", "items": {"type": "integer"}}}, "required": ["dateDebut", "nombreDeNuits", "logementIds"], "additionalProperties": false}},
      "activitesDemandees": {"type": "array", "items": {"type": "integer"}},
      "activitesSuggerees": {"type": "array", "items": {"type": "integer"}},
      "pourquoi": {"type": "string", "description": "Pourquoi cette destination et ces prestations répondent au client, en deux ou trois phrases, sans prix."}
    },
    "required": ["destination", "etapes", "activitesDemandees", "activitesSuggerees", "pourquoi"],
    "additionalProperties": false
  }
}
```

Contrôles, avec les critères de la fiche demande et jamais ceux du modèle :

1. **Séjour** : les étapes se suivent sans trou, totalisent le nombre de nuits de la fiche et commencent à sa date de début, sauf dates flexibles.
2. **Destination** : `chercher_destinations` avec la zone, les dates, les envies, la ville de départ, les voyageurs et l'accessibilité de la fiche, sans température. Une destination hors de la zone, sans logement pour le groupe ou sans tarif aux dates est refusée. Les envies de climat (« au moins 18 °C ») ne filtrent pas : elles sont rappelées dans `justification.enviesDeClimat`, face au `climatAuxDates`.
3. **Logements** : `chercher_logements` pour chaque étape, avec tous les voyageurs et l'accessibilité déduite des contraintes (`Demande::aBesoinDAccessibilite()` : fauteuil, mobilité réduite, PMR, plain-pied…).
4. **Activités** : `chercher_activites` avec les âges de la fiche (`Demande::agesDesEnfants()`, 0 an pour un âge inconnu) et la même accessibilité.
5. **Chiffrage** : `calculer_budget`, appelé par le serveur, choisit pour chaque niveau ses logements et ses activités parmi les candidats retenus.

Un choix refusé renvoie toutes les raisons à la fois, celles des outils de recherche : `Combinaison refusée : - Étape 1 : Appartement Bairro Alto, non accessible : un voyageur a besoin d'un logement accessible.` L'agent corrige et rappelle l'outil.

Sortie (extrait) :

```json
{
  "chiffrageId": "chf_7f3a2c",
  "destination": { "slug": "tenerife", "ville": "Tenerife", "pays": "Espagne", "fiche": "/api/destinations/4" },
  "etapes": [{ "dateDebut": "2027-02-13", "nombreDeNuits": 7, "logements": [{ "id": 12, "nom": "Apartamentos Los Cristianos", "fiche": "/api/logements/12" }] }],
  "justification": {
    "climatAuxDates": [{ "debut": "2027-02-01", "fin": "2027-02-28", "temperatureMoyenne": 19.0, "pluieMm": 30 }],
    "enviesDeClimat": ["au moins 18 °C"],
    "villeDepart": "Bordeaux", "tempsDeTrajetMinutes": 270,
    "enviesCorrespondantes": ["plage", "famille"], "enviesSansReponse": ["animaux"],
    "pourquoi": "…"
  },
  "niveaux": {
    "essentiel": { "logements": [{ "id": 12, "nom": "Apartamentos Los Cristianos", "fiche": "/api/logements/12" }], "activites": [{ "id": 41, "nom": "Observation des baleines et dauphins", "fiche": "/api/activites/41" }], "transport": "minimum de la fourchette", "totalEuros": 2622.4, "parPersonneEuros": 655.6 },
    "equilibre": { "…": "même structure" },
    "confort": { "…": "même structure" }
  },
  "niveauConseille": "equilibre",
  "options": [{ "id": 44, "nom": "Randonnée guidée au Teide", "prixEuros": 187.2, "parPersonneEuros": 93.6, "participants": 2, "totalAvecEuros": 3553.6 }],
  "budgetClient": { "montantEuros": 3500, "parPersonne": false },
  "budgetInsuffisant": false,
  "arbitrages": [],
  "avertissement": "Prix estimés le lundi 19 octobre 2026 à 15 h 30 d'après nos tarifs : votre conseiller vérifie chaque prix et chaque place avant de vous les confirmer."
}
```

- Chaque prestation renvoie vers sa **fiche** : l'adresse de sa ressource dans l'API du catalogue (`/api/logements/{id}`, `/api/activites/{id}`, `/api/destinations/{id}`), consultable par les conseillers.
- La **justification** ne reprend que des données : climat aux dates, temps de trajet, envies satisfaites ou non. Seul `pourquoi` est écrit par le modèle.
- La combinaison est gardée 24 heures avec le chiffrage, sous le même `chiffrageId` (clé `combinaison`) : l'agent de rédaction la retrouve pour écrire la proposition.
- **`niveauConseille`** : l'équilibré, sauf s'il dépasse le budget du client (l'essentiel) ; c'est le niveau que la rédaction détaille.
- **`options`** : jusqu'à trois activités compatibles que le niveau conseillé ne contient pas (les suggestions de l'agent d'abord, puis celles qui répondent aux envies), à leur prix de vente pour leurs seuls participants, marge comprise, et le total du niveau si le client l'ajoute. La randonnée au Teide (12 ans minimum) n'est comptée que pour les deux parents. Aucune option si `budgetInsuffisant`.
- **`arbitrages`** : ceux de `calculer_budget`, réduits à `libelle`, `totalEuros` et `tientDansLeBudget`, sans étapes : la carte de la piste les affiche. Si le client en accepte un, l'agent Conversation enregistre les nouvelles dates ou la nouvelle durée avec `enregistrer_exigences`, et la confirmation relance le calcul (`NouvelleVersion`).
- Le site reçoit une carte de la piste (`Chatbot/Piste`) dans l'événement `piste` du chat, après le message de l'agent.

### `calculer_budget`

Calcule les trois niveaux de budget avec la formule de `docs/chiffrage.md`, à partir des logements et activités compatibles. Aucun agent ne reçoit cet outil : `retenir_combinaison` l'appelle avec les candidats qu'il a vérifiés, et `RetenirCombinaison` s'en sert aussi pour chiffrer les options. Le modèle ne fait **aucun calcul** : le serveur choisit les combinaisons de chaque niveau et chiffre au centime près. Les voyageurs, la ville de départ, le budget du client et le type de voyage viennent de la demande courante.

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
        { "type": "transport", "libelle": "Transferts aéroport – hébergement à Santorin", "calcul": "2 voyageurs × 25 €", "montantEuros": 50, "estime": true }
      ],
      "coutPrestationsEuros": 1780, "margeEuros": 356, "fraisDeDossierEuros": 150,
      "totalEuros": 2286, "parPersonneEuros": 1143,
      "hypotheses": ["Hôtel Fira Blue, chambre triple : Hôtel 3 étoiles, 1 chambre, 7 nuits du 12/06/2027 au 19/06/2027, tarif d'avril à juin 2027", "Transport inclus : vols et transferts estimés au minimum de la fourchette, pour 2 voyageurs", "Frais de dossier voyage de noces : 150 €"]
    },
    "equilibre": { "…": "même structure" },
    "confort": { "…": "même structure" }
  },
  "nonChiffrable": null
}
```

La sortie contient aussi `avertissement`, la mention datée que l'agent recopie après les totaux (« Prix estimés le lundi 19 octobre 2026 à 15 h 30 d'après nos tarifs : votre conseiller vérifie chaque prix et chaque place avant de vous les confirmer. ») et `exclusions` : les candidats écartés et pourquoi (pas de tarif à une des nuits, séjour minimum plus long que l'étape, suggestion sans tarif au premier jour). Si plus de 20 000 combinaisons sont à comparer pour l'équilibré, l'entrée est refusée : `retenir_combinaison` renvoie l'erreur, et l'agent Recherche doit retenir moins de candidats.

Si même l'essentiel dépasse le budget (`budgetInsuffisant`), la sortie contient aussi `arbitrages` (ticket #206, voir `docs/chiffrage.md`), chacun avec ses `etapes` ; `retenir_combinaison` n'en transmet que le libellé, le total et s'il tient dans le budget.

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

Si un tarif manque, `niveaux` vaut `null` et `nonChiffrable` explique pourquoi : `{"prestation": "Guesthouse Reykjavik Centre", "date": "2027-01-16", "raison": "Aucun tarif saisi à cette date."}`. `retenir_combinaison` refuse alors la combinaison (« Combinaison non chiffrable : … ») ; l'agent Recherche propose un autre logement ou d'autres dates, ou appelle `transmettre_au_conseiller`.

Le résultat est gardé en cache 24 heures sous la demande courante, avec son `chiffrageId`.

### `enregistrer_proposition`

Enregistre une nouvelle version de la proposition à partir d'un chiffrage retenu (`retenir_combinaison`) : programme jour par jour, points forts des prestations, argumentaire, lignes de prestation et budgets des trois niveaux. Les montants viennent uniquement du chiffrage, jamais du modèle. La version est enregistrée en **brouillon** : le conseiller la relit sur la fiche demande du backoffice, puis l'envoie au client.

Ticket #207 : l'agent Chiffrage et rédaction (`App\Chatbot\AgentRedaction`, `prompts/redaction.md`) prend la suite de l'agent Recherche dès qu'une combinaison est retenue. Son premier message contient tout ce qu'il faut : `chiffrageId`, niveau à détailler (équilibré, ou l'essentiel si l'équilibré dépasse le budget du client), calendrier jour par jour, fiche et messages du client, faits vérifiés, niveaux et fiche de chaque prestation retenue. Il n'écrit rien dans la conversation : le tour s'arrête dès que l'outil a réussi, et le serveur dit au client que son conseiller vérifie sa proposition avant de la lui envoyer. S'il échoue, le serveur transmet la demande au conseiller et prévient le client.

Entrée :

```json
{
  "name": "enregistrer_proposition",
  "description": "Enregistre la proposition de la demande en cours, que le conseiller relira avant de l'envoyer au client, à partir du chiffrage retenu : programme jour par jour, points forts de chaque logement et activité, argumentaire. Les prix viennent du chiffrage : n'en écris aucun. Si la proposition est refusée, corrige chaque point signalé et rappelle l'outil.",
  "strict": true,
  "input_schema": {
    "type": "object",
    "properties": {
      "chiffrageId": {"type": "string", "description": "Identifiant du chiffrage donné dans le premier message."},
      "niveauDetaille": {"type": "string", "enum": ["essentiel", "equilibre", "confort"], "description": "Niveau que décrit le programme : celui conseillé dans le premier message."},
      "pourquoi": {"type": "string", "description": "Deux ou trois phrases au client : pourquoi ce voyage lui correspond."},
      "argumentaire": {"type": "array", "items": {"type": "object", "properties": {"exigence": {"type": "string"}, "reponse": {"type": "string"}}, "required": ["exigence", "reponse"], "additionalProperties": false}},
      "prestations": {"type": "array", "items": {"type": "object", "properties": {"id": {"type": "integer"}, "pointsForts": {"type": "array", "items": {"type": "string"}}}, "required": ["id", "pointsForts"], "additionalProperties": false}},
      "programme": {"type": "array", "items": {"type": "object", "properties": {"jour": {"type": "integer"}, "titre": {"type": "string"}, "description": {"type": "string"}, "activiteIds": {"type": "array", "items": {"type": "integer"}}}, "required": ["jour", "titre", "description", "activiteIds"], "additionalProperties": false}},
      "resumeDesChangements": {"type": "string", "description": "Pour une nouvelle version : ce qui a changé par rapport à la précédente."}
    },
    "required": ["chiffrageId", "niveauDetaille", "pourquoi", "argumentaire", "prestations", "programme"],
    "additionalProperties": false
  }
}
```

Sortie :

```json
{
  "version": 1,
  "statut": "brouillon",
  "nombreDeJours": 8,
  "niveauDetaille": "equilibre",
  "totalEuros": { "essentiel": 4554, "equilibre": 4956, "confort": 5502 }
}
```

Service : `App\Proposition\PropositionService`.

- `lire()` dénormalise l'entrée avec le Serializer (`PropositionRedigee`, sans champ inconnu) et la valide avec le Validator : textes non vides et bornés, un à dix arguments, un à quatre points forts par prestation, jours numérotés 1, 2, 3… dans l'ordre.
- `enregistrer()` vérifie ensuite la rédaction contre le chiffrage de la demande courante : un jour de programme par jour de voyage (**nuits + 1**, du jour d'arrivée au jour du départ), des points forts pour chaque prestation retenue par au moins un niveau et pour elles seules, chaque activité du niveau détaillé placée dans un jour (`activiteIds`) et aucune autre. Toutes les raisons d'un refus sont renvoyées d'un coup au modèle.
- Il crée la `Proposition` (type « première », statut « brouillon », version suivante), avec la destination, l'argumentaire, les prestations présentées, le programme daté et les budgets des trois niveaux, puis les `LigneDePrestation` du niveau détaillé (logement daté par étape, transports estimés, activités, détail du calcul). Un brouillon précédent passe à « remplacée » ; une version déjà envoyée reste visible du client. La demande garde son statut.
- `envoyer()` est appelé par le conseiller (bouton « Envoyer la proposition au client » sur la fiche demande, route `admin_proposition_envoyer`, réservée à qui peut modifier la demande) : le brouillon passe à « envoyée », les autres versions à « remplacée », la demande à « proposition envoyée ».

`make evaluer-proposition [d="01 07"]` rejoue les demandes test à partir de leur fiche attendue avec les agents Recherche et Rédaction réels, et mesure le délai entre la confirmation et la proposition enregistrée (objectif : moins de 2 minutes) ainsi que le nombre de jours du programme. Les programmes sont écrits dans `api/evaluations/` pour relecture. Résultat du 20/10/2026 : 9 propositions sur 9 en 21 à 38 secondes, durée exacte ; la demande 04 (période sans tarif) passe au conseiller comme attendu.

### `transmettre_au_conseiller`

Passe la main à un conseiller : à la demande du client, quand une donnée manque (période sans tarif ni climat), pour une demande hors de ce que l'agent sait faire, ou pour une destination absente du catalogue sur laquelle le client insiste.

Entrée :

```json
{
  "name": "transmettre_au_conseiller",
  "description": "Transmet la demande en cours à un conseiller de l'agence, qui reprendra la conversation. Écris pour lui un résumé utile ; préviens ensuite le client qu'un conseiller va le recontacter.",
  "strict": true,
  "input_schema": {
    "type": "object",
    "properties": {
      "motif": {"type": "string", "enum": ["demande_du_client", "donnees_manquantes", "hors_perimetre", "hors_catalogue", "autre"]},
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

Code : `TransmettreAuConseiller` passe `Conversation::repriseParConseiller` à `true` et appelle `Demande::transmettre(motif, resume)` : la demande devient prioritaire, arrive dans l'onglet « À aiguiller » du backoffice et sa fiche affiche le motif, le résumé et la conversation (US06). Ensuite, le chatbot ne répond plus au client : chaque nouveau message est gardé pour le conseiller et reçoit une réponse fixe (`AgentConversation::MESSAGE_REPRISE`). Un conseiller peut aussi reprendre lui-même une conversation depuis la fiche (« Reprendre la conversation »).

Destination absente du catalogue (motif `hors_catalogue`) : `chercher_destinations` répond par une `remarque` et la liste complète du catalogue (`catalogue`), l'agent Conversation propose des destinations qui ressemblent au lieu demandé. Si le client insiste, il enregistre ce lieu, recueille le reste des exigences et, au récapitulatif confirmé, transmet la demande : dans ce tour, `AgentConversation` ne lance pas l'agent Recherche.

`chercher_destinations` transmet aussi la demande, sans intervention du modèle, quand des dates sont données et qu'aucune destination cherchée n'a de climat saisi sur tout le séjour : motif `donnees_manquantes`, résumé généré, et la sortie contient `donneesManquantes` (`destinations`, `consigne`). La conversation n'est pas reprise : le client peut continuer avec d'autres dates.

### `choisir_options`

Outil de l'agent Conversation : la réponse du client aux options de sa proposition. Le message du client est suivi d'une balise `<proposition_preparee>` (version, niveau détaillé et son total, totaux des trois niveaux, options avec leur identifiant et leur prix pour le groupe, ou « le client a déjà répondu »).

Entrée :

```json
{
  "name": "choisir_options",
  "strict": true,
  "input_schema": {
    "type": "object",
    "properties": {
      "activitesAjoutees": {"type": "array", "items": {"type": "integer"}, "description": "Identifiants des options que le client ajoute ; vide s'il n'en veut aucune."},
      "niveau": {"type": "string", "enum": ["essentiel", "equilibre", "confort", "inchange"]}
    },
    "required": ["activitesAjoutees", "niveau"],
    "additionalProperties": false
  }
}
```

Sortie :

```json
{
  "activitesAjoutees": [1662],
  "niveau": null,
  "ajouts": ["Cérémonie du thé en kimono (+85 €)"],
  "consigne": "Dis en une phrase courte que tu mets sa proposition à jour avec son choix ; les nouveaux montants s'affichent après ton message : n'en écris aucun."
}
```

Un identifiant qui n'est pas une option de la proposition est refusé, avec la liste des options possibles. L'outil n'écrit rien : après la réponse du modèle, `NouvelleVersion::appliquerLesOptions` garde le voyage tel quel (et note que le client a répondu), ou recalcule la combinaison avec les options en activités demandées et fait rédiger la version suivante.

## Quel agent voit quels outils

| Outil | Conversation (Haiku 5.5) | Recherche (Sonnet 5.5) | Chiffrage et rédaction (Sonnet 5.5) |
| --- | --- | --- | --- |
| `enregistrer_exigences` | ✓ | | |
| `chercher_destinations` | ✓ | ✓ | |
| `chercher_logements` | | ✓ | |
| `chercher_activites` | | ✓ | |
| `consulter_catalogue` | ✓ | | |
| `retenir_combinaison` | | ✓ | |
| `enregistrer_proposition` | | | ✓ |
| `transmettre_au_conseiller` | ✓ | ✓ | ✓ |
| `choisir_options` | ✓ | | |

Chaque agent ne reçoit que ses outils dans sa requête : l'agent Conversation ne peut ni chiffrer ni enregistrer de proposition. `calculer_budget` n'est donné à aucun agent : `retenir_combinaison` l'appelle, et l'agent Chiffrage et rédaction reçoit le chiffrage déjà fait.

Il n'y a pas d'agent Modification : `AgentIA::Modification` n'est que l'étiquette du contexte que `NouvelleVersion` passe à `retenir_combinaison` quand elle recalcule elle-même, sans modèle, une proposition après une modification ou un choix d'options. Quand le changement demande une nouvelle recherche, elle relance l'agent Recherche ; l'agent Chiffrage et rédaction écrit ensuite la version suivante.
