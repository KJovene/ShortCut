import { createFileRoute } from '@tanstack/react-router'
import { PageLegale } from '@/components/PageLegale'

export const Route = createFileRoute('/mentions-legales')({
  head: () => ({
    meta: [
      { title: 'Mentions légales' },
      {
        name: 'description',
        content: "Éditeur du site, hébergement et immatriculation de l'agence.",
      },
    ],
  }),
  component: () => (
    <PageLegale
      titre="Mentions légales"
      sections={[
        {
          titre: 'Éditeur',
          texte:
            '[Raison sociale], [forme juridique] au capital de [montant] €, immatriculée au RCS de [ville] sous le numéro [numéro]. Siège : [adresse]. Directeur de la publication : [nom].',
        },
        {
          titre: 'Immatriculation',
          texte:
            'Opérateur de voyages immatriculé au registre Atout France sous le numéro [IM000000000]. Garantie financière : [organisme]. Assurance responsabilité civile professionnelle : [assureur].',
        },
        { titre: 'Hébergement', texte: '[Hébergeur], [adresse].' },
      ]}
    />
  ),
})
