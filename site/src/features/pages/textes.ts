/**
 * Contenus des pages du site, en français et en anglais : titres, textes, intitulés, boutons et messages, titres et
 * descriptions pour les moteurs. C'est ici, et non dans le backoffice, qu'ils se modifient.
 * Entre astérisques, le passage qui porte l'idée d'un titre ou d'une accroche : son premier « o » fait le saut du O.
 */
export const TEXTES = {
  fr: {
    racine: {
      titre: 'Agence de voyage sur mesure',
      description:
        "Décrivez vos envies à notre assistant : il vous propose un voyage complet et chiffré, qu'un conseiller confirme ensuite.",
    },
    accueil: {
      meta: {
        titre: 'Agence de voyage sur mesure · Votre voyage chiffré en 2 minutes',
        description:
          "Décrivez vos envies à notre assistant : il vous propose un voyage complet et chiffré, qu'un conseiller confirme ensuite.",
      },
      tous: 'Tous',
      themeDesVoyages: 'Thème des voyages',
      themes: {
        chaud: {
          label: 'Voyages chauds',
          titre: 'Où irez-vous chercher le *soleil* ?',
          aucunVoyage:
            'Aucun voyage au soleil à la une pour le moment. Décrivez vos envies à notre assistant : il vous en propose un sur mesure.',
        },
        froid: {
          label: 'Voyages froids',
          titre: 'Où irez-vous chercher le *froid* ?',
          aucunVoyage:
            'Aucun voyage au frais à la une pour le moment. Décrivez vos envies à notre assistant : il vous en propose un sur mesure.',
        },
      },
      tousLesVoyages: 'Découvrez nos meilleurs voyages',
      accroche: 'Prenez le pas de *côté*.',
      // Carte du monde
      carte: 'Carte des voyages, glissez pour explorer',
      glissez: 'Glissez pour explorer',
      des: 'dès',
      parPers: ' / pers.',
      idealEn: (mois: string) => `idéal en ${mois}`,
      temperatureEn: (temperature: number, mois: string) => `${temperature} °C en ${mois}`,
      construire: 'Construire',
      construireCeVoyage: 'Construire ce voyage',
      brouillon: (ville: string, mois: string) => `Un voyage à ${ville} en ${mois}, `,
      // Bandeau
      bandeauSurtitre: 'Le bandeau des envies',
      bandeauTitre: 'Destinations à la une',
      precedents: 'Voyages précédents',
      suivants: 'Voyages suivants',
      // Assistant
      suggestion: (ville: string) => `Comme ${ville}, à mes dates`,
      discussion: "Discussion avec l'assistant",
    },
    agence: {
      meta: {
        titre: "L'agence · Nos conseillers et nos engagements",
        description:
          'Une équipe de conseillers spécialistes de chaque destination, qui confirment chaque proposition avant votre départ.',
      },
      surtitre: "L'agence",
      titre: 'Des conseillers qui *connaissent le terrain*',
      intro: [
        'Depuis plus de quinze ans, nos conseillers construisent des voyages sur mesure. Chacun suit ses destinations, connaît les hébergeurs et les guides sur place, et met à jour les tarifs saison après saison.',
      ],
      engagements: 'Nos engagements',
      listeEngagements: [
        'Une première proposition complète en quelques minutes, à toute heure.',
        'Un conseiller référent qui confirme les prix et les disponibilités sous deux jours ouvrés.',
        'Des prix transparents : le détail de chaque poste et les hypothèses de budget.',
      ],
      photo: "L'équipe de l'agence",
      equipe: "L'équipe",
      equipeTexte: 'Votre proposition est confirmée par le conseiller de votre destination.',
      conseillere: 'Conseillère voyage',
      conseiller: 'Conseiller voyage',
    },
    contact: {
      meta: {
        titre: "Contacter l'agence",
        description: 'Posez vos questions à notre assistant ou demandez à parler à un conseiller.',
      },
      surtitre: 'Contact',
      titre: 'Une question ? *Parlons-en*',
      intro: [
        "Le plus rapide : ouvrez l'assistant en bas de l'écran et décrivez votre projet. Vous pouvez à tout moment lui demander de parler à un conseiller.",
      ],
      infos: [
        {
          titre: 'Horaires des conseillers',
          texte: 'Du lundi au vendredi de 9 h à 18 h 30, le samedi de 10 h à 17 h.',
        },
        {
          titre: 'Adresse',
          texte: '12 rue des Voyageurs, 75000 Paris (adresse fictive de démonstration).',
        },
      ],
      photo: "L'accueil de l'agence",
      assistant: "L'assistant, 24 h sur 24",
      assistantTexte: 'Une proposition complète et chiffrée en moins de 2 minutes.',
      ouvrirAssistant: "Ouvrir l'assistant",
      unConseiller: 'Un conseiller',
      etreRappele: 'Être rappelé par un conseiller',
    },
    types: {
      meta: {
        titre: 'Séjours, circuits, groupes, voyages de noces et entreprise',
        description:
          "Séjours détente, circuits accompagnés, voyages de groupe, voyages de noces et séminaires : l'agence organise tous vos voyages.",
      },
      surtitre: 'Nos voyages',
      titre: '*Tous* les voyages, *sur mesure*',
      intro:
        "Quel que soit votre projet, l'assistant pose les questions propres à votre type de voyage : étapes d'un circuit, taille du groupe, date du mariage ou objectif de l'entreprise.",
      nbDestinations: (n: number) => `${n} destination${n > 1 ? 's' : ''}`,
      fraisDeDossier: (montant: number) => `Frais de dossier : ${montant} € par dossier.`,
      descriptions: {
        sejour:
          'Une destination, un hébergement choisi pour vous et des activités à la carte : plage, ville ou nature.',
        circuit:
          'Plusieurs étapes, un itinéraire pensé jour par jour, avec guide ou en autotour : Islande, Costa Rica…',
        groupe:
          'Amis, associations, scolaires : hébergements adaptés, transferts et visites guidées pour 10 à 60 personnes.',
        noces: 'Suites avec vue, villas avec piscine privée, attentions pour les jeunes mariés.',
        entreprise:
          'Séminaires, conventions et incentives : salles de réunion, hébergement en bloc et activités de cohésion.',
      },
      voirDestinations: 'Voir les destinations',
      brouillons: {
        sejour: 'Un séjour pour deux, ',
        circuit: 'Un circuit en plusieurs étapes, ',
        groupe: 'Un voyage de groupe pour 15 personnes, ',
        noces: 'Notre voyage de noces, ',
        entreprise: 'Un séminaire d’entreprise pour 30 collègues, ',
      },
    },
    destinations: {
      meta: {
        titre: 'Destinations · Où partir, et quand ?',
        description:
          'Toutes nos destinations, filtrables par mois, température moyenne et type de voyage.',
      },
      surtitre: 'Destinations',
      titre: 'Où partir, et *à quel moment* ?',
      intro:
        "Choisissez un mois et une température : nous vous montrons les destinations dont le climat convient, d'après les relevés saisis par nos conseillers.",
      filtres: 'Filtres',
      mois: 'Mois',
      meilleurMois: 'Le meilleur mois',
      temperature: 'Température moyenne',
      peuImporte: 'Peu importe',
      auMoins: (t: number) => `Au moins ${t} °C`,
      typeDeVoyage: 'Type de voyage',
      resultats: (n: number) => `${n} destination${n > 1 ? 's' : ''}`,
      enMois: (mois: string) => ` en ${mois}`,
      aucune: 'Aucune destination ne correspond',
      aucuneTexte:
        "Essayez un autre mois ou une température plus douce, ou décrivez votre envie à l'assistant : il cherche aussi les destinations voisines.",
      effacer: 'Effacer les filtres',
      demander: "Demander à l'assistant",
    },
    destination: {
      metaTitre: (nom: string) => `${nom} · Climat, hébergements et prix`,
      filAriane: "Fil d'Ariane",
      aPartirDe: 'À partir de',
      parPersonne: ' / pers.',
      estimation:
        'Estimation pour 7 nuits à deux, vols depuis Paris et transferts compris, marge et frais de dossier inclus. Votre proposition est calculée sur vos dates exactes.',
      depuis: (ville: string) => `Depuis ${ville}`,
      sousBouton: 'Proposition chiffrée en 2 minutes, confirmée par un conseiller.',
      climat: 'Le climat mois par mois',
      legendeClimat: 'Températures moyennes et pluie par mois',
      mois: 'Mois',
      pluie: 'Pluie',
      meilleurePeriode: (mois: string) =>
        `Températures moyennes saisies par nos conseillers. Meilleure période conseillée : ${mois}.`,
      hebergements: "Exemples d'hébergements",
      activites: "Exemples d'activités",
      pers: (n: number) => `${n} pers.`,
      accessible: 'accessible',
      des: 'dès',
      nuit: 'nuit',
      personne: 'personne',
      prixBas:
        "Prix les plus bas de l'année, marge de l'agence comprise ; ils varient selon la saison.",
    },
    legales: {
      // aValider : modèle fourni par ShortCut, à passer à false quand l'agence a relu et complété le texte.
      'mentions-legales': {
        titre: 'Mentions légales',
        description: "Éditeur du site, hébergement et immatriculation de l'agence.",
        aValider: true,
        sections: [
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
        ],
      },
      cgv: {
        titre: 'Conditions générales de vente',
        description:
          "Conditions de réservation, de paiement, de modification et d'annulation des voyages.",
        aValider: true,
        sections: [
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
        ],
      },
      confidentialite: {
        titre: 'Politique de confidentialité et cookies',
        description:
          "Données collectées par l'assistant et le site, finalités, durée de conservation et vos droits.",
        aValider: true,
        sections: [
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
          {
            titre: 'Vos droits',
            texte: '[Accès, rectification, suppression : contact et délais.]',
          },
          { titre: 'Cookies', texte: '[Liste des cookies et gestion du consentement.]' },
        ],
      },
    },
    introuvable: {
      titre: 'Page introuvable',
      description: "Cette page n'existe pas ou a été déplacée. Retrouvez nos destinations.",
    },
    connexion: {
      meta: {
        titre: 'Connexion',
        description:
          'Connectez-vous pour suivre vos voyages, vos propositions et vos conversations.',
      },
      titre: 'Connexion',
      sousTitre: 'Retrouvez vos voyages, vos propositions et vos conversations.',
      pasDeCompte: 'Pas encore de compte ?',
      creerUnCompte: 'Créer un compte',
      enCours: 'Connexion…',
      seConnecter: 'Se connecter',
    },
    inscription: {
      meta: {
        titre: 'Créer un compte',
        description:
          'Créez votre compte pour retrouver vos propositions de voyage et suivre leur confirmation.',
      },
      titre: 'Créer un compte',
      sousTitre:
        'Suivez vos demandes de voyage et leurs propositions, du premier message à la réservation.',
      dejaUnCompte: 'Déjà un compte ?',
      seConnecter: 'Se connecter',
      aideMotDePasse: 'Au moins 8 caractères.',
      enCours: 'Création…',
      creer: 'Créer mon compte',
    },
    profil: {
      meta: {
        titre: 'Mon profil',
        description: 'Vos coordonnées, votre e-mail et votre mot de passe.',
      },
      titre: 'Mon profil',
      compte: (role: string) => `Compte ${role.toLowerCase()}`,
      roles: { ROLE_CLIENT: 'Client', ROLE_CONSEILLER: 'Conseiller', ROLE_ADMIN: 'Administrateur' },
      misAJour: 'Profil mis à jour.',
      telephone: 'Téléphone',
      changerMotDePasse: 'Changer de mot de passe',
      nouveauMotDePasse: 'Nouveau mot de passe',
      aideNouveau: 'Laissez vide pour garder le mot de passe actuel.',
      motDePasseActuel: 'Mot de passe actuel',
      aideActuel: "Exigé pour changer d'e-mail ou de mot de passe.",
      enCours: 'Enregistrement…',
      enregistrer: 'Enregistrer',
    },
    // Champs des formulaires de compte
    champs: {
      email: 'E-mail',
      motDePasse: 'Mot de passe',
      prenom: 'Prénom',
      nom: 'Nom',
      emailInvalide: 'Saisissez une adresse e-mail valide.',
      motDePasseVide: 'Saisissez votre mot de passe.',
      nomVide: 'Saisissez votre nom.',
      motDePasseCourt: 'Le mot de passe doit faire au moins 8 caractères.',
    },
    proposition: {
      metaTitre: 'Ma proposition de voyage',
      metaDescription: 'Votre proposition de voyage sur mesure, à confirmer par votre conseiller.',
      surtitre: 'Ma proposition',
      introuvable: 'Proposition introuvable',
      introuvableTexte:
        "Ce lien ne correspond à aucune proposition, ou elle a expiré. Décrivez votre voyage à l'assistant pour en recevoir une nouvelle en 2 minutes.",
    },
    construireMonVoyage: 'Construire mon voyage',
  },
  en: {
    racine: {
      titre: 'Tailor-made travel agency',
      description:
        'Describe what you are looking for to our assistant: it suggests a complete, priced trip, which an advisor then confirms.',
    },
    accueil: {
      meta: {
        titre: 'Tailor-made travel agency · Your trip priced in 2 minutes',
        description:
          'Describe what you are looking for to our assistant: it suggests a complete, priced trip, which an advisor then confirms.',
      },
      tous: 'All',
      themeDesVoyages: 'Type of trip',
      themes: {
        chaud: {
          label: 'Warm trips',
          titre: 'Where will you go *looking for the sun*?',
          aucunVoyage:
            'No sunny trips featured right now. Tell our assistant what you are looking for: it will suggest a tailor-made one.',
        },
        froid: {
          label: 'Cool trips',
          titre: 'Where will you go *looking for the cold*?',
          aucunVoyage:
            'No cool-weather trips featured right now. Tell our assistant what you are looking for: it will suggest a tailor-made one.',
        },
      },
      tousLesVoyages: 'Discover our best trips',
      accroche: 'Step *off* the beaten track.',
      carte: 'Map of trips, swipe to explore',
      glissez: 'Swipe to explore',
      des: 'from',
      parPers: ' / person',
      idealEn: (mois: string) => `best in ${mois}`,
      temperatureEn: (temperature: number, mois: string) => `${temperature} °C in ${mois}`,
      construire: 'Plan',
      construireCeVoyage: 'Plan this trip',
      brouillon: (ville: string, mois: string) => `A trip to ${ville} in ${mois}, `,
      bandeauSurtitre: 'Inspiration',
      bandeauTitre: 'Featured destinations',
      precedents: 'Previous trips',
      suivants: 'Next trips',
      suggestion: (ville: string) => `Like ${ville}, on my dates`,
      discussion: 'Conversation with the assistant',
    },
    agence: {
      meta: {
        titre: 'The agency · Our advisors and our commitments',
        description:
          'A team of advisors who specialise in each destination and confirm every proposal before you leave.',
      },
      surtitre: 'The agency',
      titre: 'Advisors who *know their destinations*',
      intro: [
        'For more than fifteen years, our advisors have been building tailor-made trips. Each of them follows their own destinations, knows the hosts and guides on the ground, and updates prices season after season.',
      ],
      engagements: 'Our commitments',
      listeEngagements: [
        'A first complete proposal in a few minutes, at any time of day.',
        'A dedicated advisor who confirms prices and availability within two business days.',
        'Transparent prices: the detail of every item and the budget assumptions.',
      ],
      photo: 'The agency team',
      equipe: 'The team',
      equipeTexte: 'Your proposal is confirmed by the advisor for your destination.',
      conseillere: 'Travel advisor',
      conseiller: 'Travel advisor',
    },
    contact: {
      meta: {
        titre: 'Contact the agency',
        description: 'Ask our assistant your questions or ask to speak to an advisor.',
      },
      surtitre: 'Contact',
      titre: 'A question? *Talk to us*',
      intro: [
        'The quickest way: open the assistant at the bottom of the screen and describe your plans. You can ask it to put you in touch with an advisor at any time.',
      ],
      infos: [
        {
          titre: 'Advisors’ opening hours',
          texte: 'Monday to Friday, 9 am to 6:30 pm; Saturday, 10 am to 5 pm.',
        },
        {
          titre: 'Address',
          texte: '12 rue des Voyageurs, 75000 Paris (fictitious demo address).',
        },
      ],
      photo: 'The agency reception',
      assistant: 'The assistant, 24/7',
      assistantTexte: 'A complete, priced proposal in under 2 minutes.',
      ouvrirAssistant: 'Open the assistant',
      unConseiller: 'An advisor',
      etreRappele: 'Get a call back from an advisor',
    },
    types: {
      meta: {
        titre: 'Stays, tours, groups, honeymoons and corporate travel',
        description:
          'Relaxing stays, guided tours, group trips, honeymoons and seminars: the agency organises all your travel.',
      },
      surtitre: 'Our trips',
      titre: 'All our trips, *tailor-made*',
      intro:
        'Whatever your plans, the assistant asks the questions that matter for your type of trip: the stages of a tour, the size of the group, the wedding date or the company’s goal.',
      nbDestinations: (n: number) => `${n} destination${n > 1 ? 's' : ''}`,
      fraisDeDossier: (montant: number) => `Booking fee: €${montant} per booking.`,
      descriptions: {
        sejour:
          'One destination, accommodation chosen for you and activities to suit you: beach, city or nature.',
        circuit:
          'Several stops, an itinerary planned day by day, with a guide or self-drive: Iceland, Costa Rica…',
        groupe:
          'Friends, associations, school trips: suitable accommodation, transfers and guided tours for 10 to 60 people.',
        noces: 'Suites with a view, villas with a private pool, special touches for newlyweds.',
        entreprise:
          'Seminars, conventions and incentives: meeting rooms, block bookings and team-building activities.',
      },
      voirDestinations: 'See destinations',
      brouillons: {
        sejour: 'A stay for two, ',
        circuit: 'A tour with several stops, ',
        groupe: 'A group trip for 15 people, ',
        noces: 'Our honeymoon, ',
        entreprise: 'A company seminar for 30 colleagues, ',
      },
    },
    destinations: {
      meta: {
        titre: 'Destinations · Where to go, and when?',
        description:
          'All our destinations, filtered by month, average temperature and type of trip.',
      },
      surtitre: 'Destinations',
      titre: '*Where to go*, and when?',
      intro:
        'Choose a month and a temperature: we show you the destinations whose climate suits you, based on the records kept by our advisors.',
      filtres: 'Filters',
      mois: 'Month',
      meilleurMois: 'The best month',
      temperature: 'Average temperature',
      peuImporte: 'Any',
      auMoins: (t: number) => `At least ${t} °C`,
      typeDeVoyage: 'Type of trip',
      resultats: (n: number) => `${n} destination${n > 1 ? 's' : ''}`,
      enMois: (mois: string) => ` in ${mois}`,
      aucune: 'No destination matches',
      aucuneTexte:
        'Try another month or a milder temperature, or describe what you would like to the assistant: it also looks at nearby destinations.',
      effacer: 'Clear filters',
      demander: 'Ask the assistant',
    },
    destination: {
      metaTitre: (nom: string) => `${nom} · Climate, accommodation and prices`,
      filAriane: 'Breadcrumb',
      aPartirDe: 'From',
      parPersonne: ' / person',
      estimation:
        'Estimate for 7 nights for two, flights from Paris and transfers included, agency margin and booking fee included. Your proposal is priced on your exact dates.',
      depuis: (ville: string) => `From ${ville}`,
      sousBouton: 'Priced proposal in 2 minutes, confirmed by an advisor.',
      climat: 'The climate month by month',
      legendeClimat: 'Average temperatures and rainfall by month',
      mois: 'Month',
      pluie: 'Rain',
      meilleurePeriode: (mois: string) =>
        `Average temperatures recorded by our advisors. Best time to go: ${mois}.`,
      hebergements: 'Accommodation examples',
      activites: 'Activity examples',
      pers: (n: number) => `${n} people`,
      accessible: 'accessible',
      des: 'from',
      nuit: 'night',
      personne: 'person',
      prixBas: 'Lowest prices of the year, agency margin included; they vary with the season.',
    },
    legales: {
      'mentions-legales': {
        titre: 'Legal *notice*',
        description: 'Website publisher, hosting and registration of the agency.',
        aValider: true,
        sections: [
          {
            titre: 'Publisher',
            texte:
              '[Company name], [legal form] with a share capital of €[amount], registered with the [city] Trade and Companies Register under number [number]. Registered office: [address]. Publication director: [name].',
          },
          {
            titre: 'Registration',
            texte:
              'Travel operator registered with Atout France under number [IM000000000]. Financial guarantee: [provider]. Professional liability insurance: [insurer].',
          },
          { titre: 'Hosting', texte: '[Hosting provider], [address].' },
        ],
      },
      cgv: {
        titre: 'Terms and *conditions* of sale',
        description: 'Booking, payment, modification and cancellation terms for trips.',
        aValider: true,
        sections: [
          {
            titre: 'Proposals',
            texte:
              'Proposals provided by the assistant are estimates. They only bind the agency once an advisor has confirmed prices and availability.',
          },
          { titre: 'Booking and payment', texte: '[Deposit, balance, payment methods.]' },
          {
            titre: 'Changes and cancellation',
            texte: '[Fee scale depending on the cancellation date, supplier conditions.]',
          },
          {
            titre: 'Booking fee',
            texte: 'A booking fee of €100 to €200 applies once per booking.',
          },
        ],
      },
      confidentialite: {
        titre: 'Privacy and cookies policy',
        description:
          'Data collected by the assistant and the website, purposes, retention period and your rights.',
        aValider: true,
        sections: [
          {
            titre: 'Data collected',
            texte:
              'Your messages to the assistant, your travel requirements and, if you provide them, your name, email address and phone number. They are only used to prepare and confirm your proposal.',
          },
          {
            titre: 'AI assistant',
            texte:
              'The assistant is an artificial intelligence. Your messages are processed by an AI provider to generate the replies.',
          },
          {
            titre: 'Your rights',
            texte: '[Access, rectification, deletion: contact and time limits.]',
          },
          { titre: 'Cookies', texte: '[List of cookies and consent management.]' },
        ],
      },
    },
    introuvable: {
      titre: 'Page not found',
      description: 'This page does not exist or has been moved. Discover our destinations.',
    },
    connexion: {
      meta: {
        titre: 'Log in',
        description: 'Log in to follow your trips, your proposals and your conversations.',
      },
      titre: 'Log in',
      sousTitre: 'Find your trips, your proposals and your conversations.',
      pasDeCompte: 'No account yet?',
      creerUnCompte: 'Create an account',
      enCours: 'Logging in…',
      seConnecter: 'Log in',
    },
    inscription: {
      meta: {
        titre: 'Create an account',
        description:
          'Create your account to find your travel proposals and follow their confirmation.',
      },
      titre: 'Create an account',
      sousTitre:
        'Follow your travel requests and their proposals, from the first message to the booking.',
      dejaUnCompte: 'Already have an account?',
      seConnecter: 'Log in',
      aideMotDePasse: 'At least 8 characters.',
      enCours: 'Creating…',
      creer: 'Create my account',
    },
    profil: {
      meta: {
        titre: 'My profile',
        description: 'Your contact details, email and password.',
      },
      titre: 'My profile',
      compte: (role: string) => `${role} account`,
      roles: { ROLE_CLIENT: 'Client', ROLE_CONSEILLER: 'Advisor', ROLE_ADMIN: 'Administrator' },
      misAJour: 'Profile updated.',
      telephone: 'Phone',
      changerMotDePasse: 'Change password',
      nouveauMotDePasse: 'New password',
      aideNouveau: 'Leave blank to keep your current password.',
      motDePasseActuel: 'Current password',
      aideActuel: 'Required to change your email or password.',
      enCours: 'Saving…',
      enregistrer: 'Save',
    },
    champs: {
      email: 'Email',
      motDePasse: 'Password',
      prenom: 'First name',
      nom: 'Last name',
      emailInvalide: 'Enter a valid email address.',
      motDePasseVide: 'Enter your password.',
      nomVide: 'Enter your last name.',
      motDePasseCourt: 'Your password must be at least 8 characters long.',
    },
    proposition: {
      metaTitre: 'My travel proposal',
      metaDescription: 'Your tailor-made travel proposal, to be confirmed by your advisor.',
      surtitre: 'My proposal',
      introuvable: 'Proposal not found',
      introuvableTexte:
        'This link does not match any proposal, or it has expired. Describe your trip to the assistant to receive a new one in 2 minutes.',
    },
    construireMonVoyage: 'Plan my trip',
  },
}
