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

- `conversation` : les messages du client, dans l'ordre. `moment` indique quand les envoyer : `debut`, `reponse` (aux questions du chatbot), `apres_pistes` (après les trois pistes d'une envie floue), `apres_proposition` (changement demandé une fois la proposition reçue).
- `exigencesAttendues` : la fiche demande que le chatbot doit remplir, avec les noms des champs de l'entité `Demande` (budget en euros ici, en centimes en base). Pour le cas 10, c'est l'état final après le changement.
- `attendu` : destinations, logements compatibles, prestations à exclure et pourquoi, passage au conseiller, budget insuffisant, points de contrôle à vérifier à la lecture de la conversation, et budgets par niveau détaillés poste par poste.

## Budgets attendus

Les budgets sont calculés à partir du jeu de démo (#174) avec une **formule provisoire**, en attendant `docs/chiffrage.md` (#176) et la règle des frais de dossier (#165) :

```text
budget = (hébergements + transports + activités) × 1,20 + 150 € de frais de dossier
```

- **Essentiel** : la combinaison la moins chère, vols au bas de la fourchette.
- **Équilibré** : la meilleure combinaison qui tient dans le budget du client.
- **Confort** : logement supérieur, vols au haut de la fourchette, activités en plus.

Une proposition est jugée correcte si son total est à 15 % près du budget de référence (`formule.tolerance`). Une fois la formule définitive écrite, il faudra recalculer ces budgets à l'euro près.

`tests/DemandesTestTest.php` vérifie que les fichiers restent cohérents avec le catalogue de démo : types de voyage, cas particuliers, noms des prestations, ordre des niveaux et respect du budget.
