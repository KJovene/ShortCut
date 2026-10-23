# Demandes test

Dix demandes de clients en langage naturel, avec le résultat attendu du chatbot (ticket #175).
Elles servent de référence pour mesurer les critères de US05 (recueil des exigences), US07 (proposition) et US08 (chiffrage), et pour l'évaluation du chatbot (#195).

| Fichier | Type | Cas particuliers |
| --- | --- | --- |
| `01-sejour-famille-tenerife.json` | Séjour | Âge minimum des activités (enfants de 6 et 9 ans) |
| `02-sejour-lisbonne-mobilite-reduite.json` | Séjour | Fauteuil roulant : logements et activités accessibles uniquement |
| `03-circuit-costa-rica.json` | Circuit | Trois étapes, deux chambres quand la capacité ne suffit pas |
| `04-circuit-islande-sans-tarif.json` | Circuit | **Période sans données** : aucun tarif en janvier, passage au conseiller |
| `05-groupe-rome-association.json` | Groupe | 35 personnes, visite limitée à 30 places |
| `06-groupe-marrakech-budget-insuffisant.json` | Groupe | **Budget insuffisant** : arbitrages chiffrés attendus |
| `07-noces-santorin.json` | Noces | Allergie alimentaire signalée au conseiller |
| `08-noces-envie-floue.json` | Noces | **Envie floue** : trois pistes avant la proposition |
| `09-entreprise-seminaire-marrakech.json` | Entreprise | 40 personnes, salle plénière, activités en plusieurs sessions |
| `10-entreprise-convention-rome-changement.json` | Entreprise | **Changement en cours** : nombre et ville de départ modifiés, version 2 |

## Format

Chaque fichier suit `demande.schema.json` :

- `conversation` : les messages du client, dans l'ordre. `moment` indique quand les envoyer : `debut`, `reponse` (aux questions du chatbot), `apres_pistes` (après les trois pistes d'une envie floue), `apres_proposition` (changement demandé dès que le chatbot présente un récapitulatif ou une proposition, avant de confirmer quoi que ce soit).
- `exigencesAttendues` : la fiche demande que le chatbot doit remplir, avec les noms des champs de l'entité `Demande` (budget en euros ici, en centimes en base). Pour le cas 10, c'est l'état final après le changement.
- `attendu` : destinations, logements compatibles, prestations à exclure et pourquoi, passage au conseiller, budget insuffisant, points de contrôle à vérifier à la lecture de la conversation, et budgets par niveau détaillés poste par poste.

## Budgets attendus

Les budgets sont calculés à partir du jeu de démo (#174) avec la formule de [`docs/chiffrage.md`](../../../docs/chiffrage.md), **au centime près** (`formule.tolerance` vaut 0) :

```text
total = (hébergements + transports + activités) + marge de 20 % + frais de dossier selon le type de voyage
```

- `attendu.candidats` : les étapes et logements compatibles, les activités demandées et les suggestions, à partir desquels les trois niveaux sont choisis.
- `attendu.budgetsParNiveau` : pour chaque niveau, les logements et activités retenus, la position dans la fourchette de transport et le détail poste par poste, en centimes.
- Cas 10 : `attendu.versionInitiale` donne aussi les budgets de la première version (55 personnes depuis Paris).

`tests/DemandesTestTest.php` vérifie que les fichiers restent cohérents avec le catalogue de démo et avec la formule : types de voyage, cas difficiles, noms des prestations, ordre des niveaux, somme des postes, marge, frais de dossier et respect du budget.

Les fichiers servent aussi à :

- `make evaluer` (recueil des exigences par l'agent Conversation, #195) et `make evaluer-proposition` (recherche et rédaction de la proposition, voir `docs/outils.md`) ;
- `tests/Chiffrage/ChiffrageDesDemandesTestTest.php` : `BudgetCalculator` retrouve les budgets attendus au centime près, poste par poste ;
- `tests/Chatbot/Outil/OutilsDeRechercheTest.php` : les outils de recherche retrouvent destinations, logements et activités attendus sur le jeu de démo ;
- `tests/Chatbot/Outil/RetenirCombinaisonTest.php` : `retenir_combinaison` retient la combinaison attendue, chaque niveau garde ses logements et activités, et tout choix hors contrainte est refusé.
