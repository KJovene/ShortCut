# Formule de chiffrage

Règles appliquées par le chatbot et par le service de chiffrage pour calculer le budget d'une proposition (ticket #176).
Elles s'appuient uniquement sur les données du backoffice : tarifs par période, fourchettes de transport et règle de prix.

## Principes

- **Règles de l'agence** : marge de 20 % sur le coût des prestations, aucune commission, frais de dossier ajoutés une fois par dossier selon le type de voyage (décision du 16/10/2026, ticket #165).
- **Montants en centimes** dans tous les calculs. L'arrondi à l'euro ne se fait **qu'à l'affichage**.
- **Prix estimés** : la première proposition affiche toujours « Première proposition, prix estimés, à confirmer par votre conseiller ».
- **Aucune donnée inventée** : si un tarif manque pour une nuit ou une activité, le poste ne peut pas être chiffré (voir [Données manquantes](#données-manquantes)).

## Formule

```text
coût des prestations = hébergements + transports + activités
marge                = 20 % du coût des prestations, arrondie au centime
total                = coût des prestations + marge + frais de dossier
par personne         = total ÷ nombre de voyageurs (adultes + enfants)
```

### Frais de dossier

| Type de voyage | Frais de dossier |
| --- | --- |
| Séjour | 100 € |
| Voyage de noces | 150 € |
| Circuit | 150 € |
| Groupe | 200 € |
| Entreprise | 200 € |

Le barème est modifiable par l'administrateur, dans les bornes de 100 à 200 €.

## Calcul par poste

### Hébergement

```text
hébergement = Σ sur chaque nuit (prix de la nuit) × nombre d'unités
```

- **Prix de la nuit** : le tarif par période qui couvre la date de cette nuit. Un séjour à cheval sur deux saisons est donc payé nuit par nuit au bon tarif.
- **Nombre de nuits** : date de fin moins date de début. Pour un circuit, chaque étape a son propre logement et son nombre de nuits.
- **Nombre d'unités** : `⌈ voyageurs ÷ capacité du logement ⌉` (chambres, villas…). Les enfants comptent comme des voyageurs.
- **Séjour minimum** : un logement dont le séjour minimum dépasse le nombre de nuits de l'étape n'est pas proposé.

### Transport

```text
transport = (vol aller-retour + transfert aéroport) × voyageurs
```

- Prix par personne pris dans la **fourchette** du transporteur pour la ville de départ et la destination : **minimum** au niveau essentiel, **milieu** au niveau équilibré (arrondi au centime supérieur), **maximum** au niveau confort.
- Si plusieurs transporteurs desservent le trajet, la fourchette au minimum le plus bas est retenue. Les villes sont rapprochées sans tenir compte des majuscules, des accents ni d'une petite faute.
- Sans fourchette pour le vol ou le transfert, la demande n'est pas chiffrable (voir [Données manquantes](#données-manquantes)).
- Les enfants paient le même prix que les adultes, faute de tarif enfant saisi.
- Le poste est toujours affiché comme **estimé** (fourchette, pas de tarif précis).
- Si le client ne veut pas que le transport soit inclus, ce poste vaut 0 et l'hypothèse « transport non inclus » est affichée.

### Activités

```text
activités = Σ prix par personne × participants
```

- **Participants** : les voyageurs dont l'âge atteint l'âge minimum de l'activité. Les autres ne la font pas et ne la paient pas. Les adultes ont toujours l'âge requis ; un enfant dont l'âge n'est pas encore connu compte comme le plus jeune.
- Une activité dont la capacité est inférieure au nombre de participants est organisée en plusieurs sessions, sans surcoût.
- Prix : le tarif de la période qui couvre le premier jour du voyage.

## Les trois niveaux

Chaque proposition présente trois niveaux. Le chatbot ne retient que des prestations **compatibles** avec la demande : climat aux dates, capacité, accessibilité, âges, envies et contraintes.

| Niveau | Hébergement | Activités | Transport |
| --- | --- | --- | --- |
| **Essentiel** | Le logement compatible le moins cher (par étape) | Seulement celles que le client a demandées | Minimum de la fourchette |
| **Équilibré** | Voir ci-dessous : vise le budget du client | Demandées, plus les suggestions qui tiennent dans le budget | Milieu de la fourchette |
| **Confort** | Le logement compatible le plus cher (par étape) | Demandées et toutes les suggestions | Maximum de la fourchette |

« Le moins cher » et « le plus cher » se comparent sur le coût du logement pour l'étape : ses nuits aux tarifs des dates, multipliées par le nombre d'unités.

### Écart au budget du client

Le budget du client peut être un total ou un montant par personne : la comparaison se fait sur la même base.

- **Budget donné et suffisant** (l'essentiel tient dans le budget) : l'équilibré est la combinaison **la plus complète qui tient dans le budget**, parmi tous les choix de logements et de suggestions, transport au milieu de la fourchette. « La plus complète » est celle au **total le plus élevé** sans dépasser le budget ; à total égal, celle qui a le plus d'activités. Une meilleure catégorie de logement peut donc l'emporter sur une activité de plus (demande test 07 : la suite avec la croisière plutôt que l'hôtel avec deux activités). Si aucune ne tient ainsi, on reprend la même recherche avec le transport au minimum. L'essentiel et le confort encadrent l'équilibré.
- **Budget absent** : le chatbot calcule les trois niveaux, l'équilibré prenant le logement de prix médian (le moins cher des deux du milieu si leur nombre est pair) et les seules activités demandées. Il présente les **trois fourchettes** et demande laquelle convient avant de détailler la proposition.
- **Budget insuffisant** (même l'essentiel dépasse le budget) : le chatbot le dit clairement, avec les chiffres, et propose des **arbitrages chiffrés avec la même formule** : moins de nuits, une autre période (saison moins chère), une autre catégorie d'hébergement, une destination voisine. Il ne baisse jamais les prix des prestataires pour tenir un budget. Les trois niveaux restent calculés pour information, l'équilibré prenant alors la combinaison la moins chère avec le transport au milieu de la fourchette.

## Hypothèses affichées au client

Chaque niveau liste ses hypothèses de budget (ticket #206), pour que le client comprenne d'où vient chaque montant :

- pour chaque logement : catégorie, nombre de chambres (ou d'unités si le nombre de chambres n'est pas saisi), nuits et dates, saison(s) tarifaire(s) appliquée(s) ;
- pour une activité : les participants si certains n'ont pas l'âge minimum, les sessions si la capacité ne suffit pas ;
- transport inclus ou non, et position dans la fourchette (estimé) ;
- frais de dossier appliqués.

Exemple (essentiel de l'exemple 2) :

```text
Riad Dar Yasmine, riad entier : Riad privatisé, 6 chambres, 4 nuits du 21/10/2027 au 25/10/2027, tarif d'octobre à décembre 2027
Transport inclus : vols et transferts estimés au minimum de la fourchette, pour 12 voyageurs
Frais de dossier groupe : 200 €
```

## Arbitrages quand le budget est insuffisant

Le service `ArbitrageCalculator` chiffre, au niveau essentiel et avec la même formule, les mêmes prestations autrement :

1. **Moins de nuits** : une nuit de moins à la fois, à l'étape la plus longue (chaque étape garde au moins une nuit), jusqu'au séjour le plus long qui tient dans le budget ; si aucun ne tient, une nuit de moins, chiffrée.
2. **Une autre période** : les mêmes nuits aux dates les moins chères, à six mois au plus des dates demandées et jamais dans le passé ; à prix égal, les dates les plus proches.
3. **Les deux à la fois**, seulement si ni l'un ni l'autre ne suffit seul.

Chaque arbitrage donne ses dates, son total, son prix par personne, s'il tient dans le budget et l'écart restant. Changer de catégorie d'hébergement ou de destination demande d'autres candidats : l'agent les cherche, puis rappelle `calculer_budget`.

Pour l'exemple 2 (demande test 06), trois arbitrages, aucun ne suffisant : 3 nuits (448,67 € par personne), fin septembre en basse saison (422,67 €), 3 nuits fin septembre (370,67 €). Le chatbot le dit clairement au lieu de proposer un voyage hors budget.

## Données manquantes

Si une nuit d'un logement retenu, ou une activité demandée, n'a aucun tarif aux dates du voyage, le poste n'est pas chiffrable. Le chatbot cherche un autre logement compatible. S'il n'en trouve pas, il le dit au client, ne remet pas de proposition chiffrée et transmet la demande à un conseiller. C'est le cas des circuits en Islande d'octobre à avril dans le jeu de démo.

## Exemples calculés

Les montants viennent du jeu de démo (#174). Les trois exemples correspondent aux demandes test `02`, `06` et `07` de `api/tests/demandes`.

### Exemple 1 : budget donné et suffisant

Week-end à Lisbonne, 2 adultes dont une personne en fauteuil roulant, du 14 au 17 mai 2027 (3 nuits, saison d'avril à juin), départ de Lyon, **budget 1 300 € au total**. Séjour : frais de dossier 100 €.
Logements accessibles : Hôtel Alfama Vista (110 € la nuit) et Tejo Palace (290 € la nuit). Activité suggérée : atelier pastéis de nata (55 € par personne). Vol Lyon – Lisbonne : 140 à 360 € ; transfert : 20 à 45 €.

| Poste | Essentiel | Équilibré | Confort |
| --- | --- | --- | --- |
| Hébergement | Alfama Vista : 3 × 110 = 330 € | Alfama Vista : 3 × 110 = 330 € | Tejo Palace : 3 × 290 = 870 € |
| Vols | 2 × 140 = 280 € | 2 × 250 = 500 € | 2 × 360 = 720 € |
| Transferts | 2 × 20 = 40 € | 2 × 32,50 = 65 € | 2 × 45 = 90 € |
| Activités | — | — | Atelier : 2 × 55 = 110 € |
| Coût des prestations | 650 € | 895 € | 1 790 € |
| Marge 20 % | 130 € | 179 € | 358 € |
| Frais de dossier | 100 € | 100 € | 100 € |
| **Total** | **880 €** | **1 174 €** | **2 248 €** |
| Par personne | 440 € | 587 € | 1 124 € |

Équilibré : avec l'atelier, le total serait 1 174 + 2 × 55 × 1,20 = 1 306 €, au-dessus des 1 300 € ; le chatbot le suggère en option sans l'inclure.

### Exemple 2 : budget insuffisant

Douze amis dans le Riad Dar Yasmine à Marrakech, du 21 au 25 octobre 2027 (4 nuits, haute saison : 650 € × 1,2 = 780 € la nuit pour le riad entier), départ de Paris, **budget 300 € par personne**. Groupe : frais de dossier 200 €.

| Poste | Essentiel |
| --- | --- |
| Hébergement | 4 × 780 = 3 120 € (1 riad pour 12, capacité 12) |
| Vols | 12 × 150 = 1 800 € |
| Transferts | 12 × 15 = 180 € |
| Coût des prestations | 5 100 € |
| Marge 20 % | 1 020 € |
| Frais de dossier | 200 € |
| **Total** | **6 320 €**, soit **526,67 € par personne** |

Même l'essentiel dépasse les 300 € par personne : **budget insuffisant**. Arbitrage chiffré : 3 nuits du 16 au 19 septembre (basse saison, 520 € la nuit).

```text
hébergement 3 × 520 = 1 560 € ; vols 1 800 € ; transferts 180 €
coût 3 540 € + marge 708 € + frais 200 € = 4 448 €, soit 370,67 € par personne
```

Le chatbot l'annonce : même avec ces arbitrages, il faut compter environ 371 € par personne. Voir [Arbitrages quand le budget est insuffisant](#arbitrages-quand-le-budget-est-insuffisant) : le service retient les dates du 28 septembre au 1er octobre, les plus proches des dates demandées en basse saison, pour le même montant.

### Exemple 3 : budget absent

Voyage de noces à Santorin, du 12 au 19 juin 2027 (7 nuits, saison d'avril à juin), départ de Paris, **aucun budget donné**. Noces : frais de dossier 150 €.
Logements : Hôtel Fira Blue (150 € la nuit) et Suite Caldera Oia (420 € la nuit). Activité demandée : croisière en catamaran (120 € par personne) ; suggérée : dégustation de vins (60 € par personne). Vol Paris – Santorin : 220 à 520 € ; transfert : 25 à 60 €.

| Poste | Essentiel | Équilibré | Confort |
| --- | --- | --- | --- |
| Hébergement | Fira Blue : 7 × 150 = 1 050 € | Fira Blue (médian) : 1 050 € | Suite Caldera : 7 × 420 = 2 940 € |
| Vols | 2 × 220 = 440 € | 2 × 370 = 740 € | 2 × 520 = 1 040 € |
| Transferts | 2 × 25 = 50 € | 2 × 42,50 = 85 € | 2 × 60 = 120 € |
| Activités | Croisière : 240 € | Croisière : 240 € | Croisière 240 € + dégustation 120 € |
| Coût des prestations | 1 780 € | 2 115 € | 4 460 € |
| Marge 20 % | 356 € | 423 € | 892 € |
| Frais de dossier | 150 € | 150 € | 150 € |
| **Total** | **2 286 €** | **2 688 €** | **5 502 €** |

Le chatbot présente les trois fourchettes (environ 2 300 €, 2 700 € et 5 500 € pour deux) et demande laquelle convient avant de détailler la proposition.

## Implémentation

Service `App\Chiffrage\BudgetCalculator` (ticket #205), sans état et sans accès à la base : il reçoit une `DemandeDeChiffrage` (voyageurs, budget, étapes et candidats, fourchettes, marge et frais) et rend les trois niveaux poste par poste, ou la raison pour laquelle la demande n'est pas chiffrable. L'outil `calculer_budget` (`App\Agent\Outil\CalculerBudget`) le met à la disposition des agents.

## Tests

Les budgets attendus des dix demandes test (`api/tests/demandes`) sont calculés avec exactement cette formule, au centime près : `tests/Chiffrage/ChiffrageDesDemandesTestTest.php` vérifie que le service les retrouve poste par poste, ainsi que les trois exemples ci-dessus et l'arbitrage de l'exemple 2. `make coverage` mesure la couverture des tests (plus de 99 % des lignes du service).
