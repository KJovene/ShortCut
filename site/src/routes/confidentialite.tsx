import { createFileRoute } from '@tanstack/react-router'
import { PageLegale } from '@/components/PageLegale'

export const Route = createFileRoute('/confidentialite')({
  head: () => ({
    meta: [
      { title: 'Confidentialité et cookies' },
      {
        name: 'description',
        content:
          "Données collectées par l'assistant et le site, finalités, durée de conservation et vos droits.",
      },
    ],
  }),
  component: () => (
    <PageLegale
      titre="Politique de confidentialité et cookies"
      sections={[
        {
          titre: 'Données collectées',
          texte:
            "Vos messages à l'assistant, vos exigences de voyage et, si vous les donnez, vos nom, e-mail et téléphone. Ils servent uniquement à préparer et confirmer votre proposition.",
        },
        {
          titre: 'Assistant IA',
          texte:
            "L'assistant est une intelligence artificielle. Vos messages sont traités par un prestataire d'IA pour générer les réponses.",
        },
        { titre: 'Vos droits', texte: '[Accès, rectification, suppression : contact et délais.]' },
        { titre: 'Cookies', texte: '[Liste des cookies et gestion du consentement.]' },
      ]}
    />
  ),
})
