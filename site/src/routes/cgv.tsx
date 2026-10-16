import { createFileRoute } from '@tanstack/react-router'
import { PageLegale } from '@/components/PageLegale'

export const Route = createFileRoute('/cgv')({
  head: () => ({
    meta: [
      { title: 'Conditions générales de vente' },
      {
        name: 'description',
        content:
          "Conditions de réservation, de paiement, de modification et d'annulation des voyages.",
      },
    ],
  }),
  component: () => (
    <PageLegale
      titre="Conditions générales de vente"
      sections={[
        {
          titre: 'Propositions',
          texte:
            "Les propositions remises par l'assistant sont des estimations. Elles n'engagent l'agence qu'après confirmation des prix et des disponibilités par un conseiller.",
        },
        { titre: 'Réservation et paiement', texte: '[Acompte, solde, moyens de paiement.]' },
        {
          titre: 'Modification et annulation',
          texte: "[Barème de frais selon la date d'annulation, conditions des prestataires.]",
        },
        {
          titre: 'Frais de dossier',
          texte: "Des frais de dossier de 100 à 200 € s'appliquent une fois par dossier.",
        },
      ]}
    />
  ),
})
