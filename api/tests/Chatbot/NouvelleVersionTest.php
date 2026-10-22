<?php

namespace App\Tests\Chatbot;

use App\Chatbot\AgentRedaction;
use App\Chatbot\Outil\EnregistrerExigences;
use App\Entity\Conversation;
use App\Entity\Demande;
use App\Entity\Prestation;
use App\Entity\Proposition;
use App\Entity\Utilisateur;
use App\Enum\NiveauBudget;
use App\Enum\StatutDemande;
use App\Enum\StatutProposition;
use App\Enum\TypeDeVoyage;
use App\Proposition\PropositionService;
use Doctrine\Bundle\FixturesBundle\Loader\SymfonyFixturesLoader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Modification après une proposition (#211), dans le chat, sur le catalogue de démo : le client a reçu la version 1
 * (Lisbonne, du 14 au 17 mai 2027) et change sa demande. L'API Claude est remplacée par FausseApiClaude.
 * Nécessite `make db-test`.
 */
final class NouvelleVersionTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private FausseApiClaude $claude;
    private string $jeton;
    private Proposition $version1;
    /** @var list<int> */
    private array $logements;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
        $this->claude = static::getContainer()->get(FausseApiClaude::class);

        /** @var SymfonyFixturesLoader $loader */
        $loader = static::getContainer()->get('doctrine.fixtures.loader');
        (new ORMExecutor($this->em, new ORMPurger($this->em)))->execute($loader->getFixtures());
        foreach (['chatbot_messages', 'chatbot_conversations'] as $limiteur) {
            /** @var RateLimiterFactoryInterface $fabrique */
            $fabrique = static::getContainer()->get(\sprintf('limiter.%s', $limiteur));
            $fabrique->create('127.0.0.1')->reset();
        }

        $this->logements = [$this->id('Hôtel Alfama Vista, chambre double'), $this->id('Tejo Palace, suite vue fleuve')];
        $this->recevoirLaVersion1();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testDeNouvellesDatesRecalculentLaMemeCombinaisonEtPreparentLaVersion2PourLeConseiller(): void
    {
        $this->claude->prevoir(
            FausseApiClaude::appelOutil('enregistrer_exigences', ['dateDebut' => '2027-05-21', 'dateFin' => '2027-05-24', 'recapitulatifConfirme' => true]),
            FausseApiClaude::texte('C’est noté, je mets votre proposition à jour.'),
            // Pas d'agent Recherche : la même combinaison est recalculée. La rédaction écrit la version 2.
            $this->claude->appelOutilSelon('enregistrer_proposition', fn (array $requete): array => $this->proposition($requete, 4)),
        );

        $evenements = $this->envoyer('Finalement, décalez d’une semaine : du 21 au 24 mai.');

        self::assertSame(
            ['outil', 'demande', 'texte', 'texte', 'outil', 'texte', 'piste', 'outil', 'texte', 'fin'],
            array_column($evenements, 0),
        );
        // Une version déjà envoyée : la suivante attend le conseiller, la conversation n'attend plus rien du client.
        self::assertSame(['terminee' => true], end($evenements)[1]);
        self::assertCount(3, $this->claude->requetes, 'Conversation (2 appels) puis rédaction, sans recherche.');
        $redaction = $this->claude->requetes[2]['corps']['messages'][0]['content'][0]['text'];
        self::assertStringContainsString('Rédige la version suivante', $redaction);
        self::assertStringContainsString('C\'est la version 2', $redaction);
        self::assertStringContainsString('Programme de la version 1', $redaction);
        self::assertStringContainsString('Jour 1, vendredi 21 mai 2027 : arrivée', $redaction);

        $conversation = $this->conversation();
        $messages = array_column($conversation->getMessages(), 'contenu');
        self::assertSame('Voici votre proposition mise à jour (dates : du 21/05/2027 au 24/05/2027, 3 nuits).', $messages[\count($messages) - 3]);
        $piste = $messages[\count($messages) - 2];
        self::assertStringStartsWith('Lisbonne (Portugal), du 21 mai 2027 au 24 mai 2027, 3 nuits, 2 voyageurs.', $piste);
        self::assertStringContainsString("\n- Essentiel : Hôtel Alfama Vista, chambre double, ", $piste);
        self::assertMatchesRegularExpression('/\nPrix estimés le .+ à \d+ h( \d{2})? d\'après nos tarifs : votre conseiller vérifie chaque prix et chaque place avant de vous les confirmer\.$/', $piste);
        self::assertSame(\sprintf(AgentRedaction::MESSAGE_NOUVELLE_VERSION, 2, 1), end($messages));

        // La version 2 attend le conseiller, en brouillon : le client garde la version 1.
        $demande = $conversation->getDemande() ?? throw new \LogicException();
        [$v1, $v2] = $this->versions($demande);
        self::assertSame(StatutProposition::Envoyee, $v1->getStatut());
        self::assertSame(StatutProposition::Brouillon, $v2->getStatut());
        self::assertNull($v2->getEnvoyeeLe());
        self::assertSame('2027-05-21', $v2->getChoix()['etapes'][0]['dateDebut'] ?? null);
        self::assertSame('2027-05-21', $v2->getExigences()['dateDebut']);
        self::assertSame(StatutDemande::PropositionEnvoyee, $demande->getStatut());
        $this->client->request('GET', '/api/propositions/'.$v1->getJetonPublic());
        self::assertSame(1, json_decode((string) $this->client->getResponse()->getContent(), true)['version']);

        // Le conseiller l'envoie : la version 1 est remplacée mais reste consultable.
        $em = static::getContainer()->get(EntityManagerInterface::class);
        static::getContainer()->get(PropositionService::class)->envoyer($em->find(Proposition::class, $v2->getId()) ?? throw new \LogicException());
        $em->flush();
        $em->clear();
        self::assertSame(StatutProposition::Remplacee, $em->find(Proposition::class, $v1->getId())?->getStatut());
        self::assertSame(StatutProposition::Envoyee, $em->find(Proposition::class, $v2->getId())?->getStatut());

        $this->client->request('GET', '/api/propositions/'.$v1->getJetonPublic());
        $page = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(2, $page['version']);
        self::assertSame([1, 2], array_column($page['versions'], 'version'));
        $this->client->request('GET', '/api/propositions/'.$v2->getJetonPublic().'/versions/1');
        self::assertResponseIsSuccessful();
        $precedente = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(['version' => 1, 'statut' => 'remplacee'], ['version' => $precedente['version'], 'statut' => $precedente['statut']]);
        self::assertSame('2027-05-14', $precedente['programme'][0]['date']);
    }

    public function testDeNouvellesEnviesRepartentDeLaCombinaisonPrecedente(): void
    {
        $this->claude->prevoir(
            FausseApiClaude::appelOutil('enregistrer_exigences', ['envies' => ['gastronomie', 'culture'], 'recapitulatifConfirme' => true]),
            FausseApiClaude::texte('Je mets votre proposition à jour.'),
            FausseApiClaude::appelOutil('retenir_combinaison', ['destination' => 'lisbonne', 'etapes' => [['dateDebut' => '2027-05-14', 'nombreDeNuits' => 3, 'logementIds' => $this->logements]], 'activitesDemandees' => [], 'activitesSuggerees' => [], 'pourquoi' => 'Lisbonne, avec la culture en plus.']),
            FausseApiClaude::texte('Même Lisbonne, avec plus de culture.'),
            $this->claude->appelOutilSelon('enregistrer_proposition', fn (array $requete): array => $this->proposition($requete, 4)),
        );

        $this->envoyer('Ajoutez la culture à nos envies.');

        $recherche = $this->claude->requetes[2]['corps']['messages'][0]['content'][0]['text'];
        self::assertStringStartsWith('Le client a modifié sa demande après sa proposition (version 1)', $recherche);
        self::assertStringContainsString('"envies":{"avant":["gastronomie"],"apres":["gastronomie","culture"]}', $recherche);
        self::assertStringContainsString('{"id":'.$this->logements[0].',"nom":"Hôtel Alfama Vista, chambre double"}', $recherche);
        self::assertStringNotContainsString('a été refusée', $recherche);
        [, $v2] = $this->versions($this->conversation()->getDemande() ?? throw new \LogicException());
        self::assertSame(StatutProposition::Brouillon, $v2->getStatut());
    }

    public function testUneCombinaisonQuiNeConvientPlusPasseParLaRecherche(): void
    {
        // Aucun tarif saisi en 2028 : la même combinaison est refusée, la recherche en connaît la raison.
        $this->claude->prevoir(
            FausseApiClaude::appelOutil('enregistrer_exigences', ['dateDebut' => '2028-05-14', 'dateFin' => '2028-05-17', 'recapitulatifConfirme' => true]),
            FausseApiClaude::texte('Je mets votre proposition à jour.'),
            FausseApiClaude::texte('L’agence n’a pas encore les tarifs de mai 2028.'),
        );

        $this->envoyer('Et plutôt en mai 2028 ?');

        self::assertCount(3, $this->claude->requetes);
        $recherche = $this->claude->requetes[2]['corps']['messages'][0]['content'][0]['text'];
        self::assertStringContainsString('Recalculée telle quelle avec la demande modifiée, cette combinaison a été refusée : Lisbonne ne convient pas', $recherche);
        self::assertCount(1, $this->versions($this->conversation()->getDemande() ?? throw new \LogicException()));
    }

    public function testUneConfirmationSansChangementGardeLaProposition(): void
    {
        $this->claude->prevoir(
            FausseApiClaude::appelOutil('enregistrer_exigences', ['recapitulatifConfirme' => true]),
            FausseApiClaude::texte('Très bien.'),
        );

        $this->envoyer('C’est toujours bon pour moi.');

        self::assertCount(2, $this->claude->requetes);
        $messages = array_column($this->conversation()->getMessages(), 'contenu');
        self::assertSame('Votre demande n\'a pas changé : votre proposition (version 1) reste valable.', end($messages));
    }

    /**
     * Avant tout envoi du conseiller, une option ajoutée par le client met à jour son récapitulatif : la combinaison
     * est recalculée avec l'option en activité demandée, la rédaction écrit la version 2, toujours consultable.
     */
    public function testUneOptionAjouteeMetLeRecapitulatifAJour(): void
    {
        $atelier = $this->id('Atelier pastéis de nata');
        $this->preparerSansEnvoi($atelier);
        $this->claude->prevoir(
            FausseApiClaude::appelOutil('choisir_options', ['activitesAjoutees' => [$atelier], 'niveau' => 'inchange']),
            FausseApiClaude::texte('Je l’ajoute à votre voyage.'),
            $this->claude->appelOutilSelon('enregistrer_proposition', fn (array $requete): array => $this->proposition($requete, 4, [$atelier])),
        );

        $evenements = $this->envoyer('J’ajoute l’option « Atelier pastéis de nata ».');

        // La balise donne au modèle les options et leur prix pour le groupe.
        $balise = $this->claude->requetes[0]['corps']['messages'][0]['content'][2]['text'];
        self::assertStringContainsString(\sprintf('Options : %d = Atelier pastéis de nata, +', $atelier), $balise);
        self::assertStringContainsString('récapitulatif pas encore relu par le conseiller', $balise);
        self::assertCount(3, $this->claude->requetes, 'Conversation (2 appels) puis rédaction, sans recherche.');
        self::assertStringContainsString('Le client vient d\'ajouter ces options, déjà comprises dans la combinaison : Atelier pastéis de nata.', $this->claude->requetes[2]['corps']['messages'][0]['content'][0]['text']);

        $parType = array_column($evenements, 1, 0);
        self::assertContains('Atelier pastéis de nata', $parType['piste']['niveaux']['essentiel']['activites']);
        self::assertSame(\sprintf(AgentRedaction::MESSAGE_A_JOUR, ', avec Atelier pastéis de nata').' '.AgentRedaction::MESSAGE_SUITE, $parType['recapitulatif']['texte']);
        self::assertSame([], $parType['recapitulatif']['options']);
        self::assertSame(['terminee' => true], $parType['fin']);

        [$v1, $v2] = $this->versions($this->conversation()->getDemande() ?? throw new \LogicException());
        self::assertSame(StatutProposition::Remplacee, $v1->getStatut());
        self::assertSame(StatutProposition::Brouillon, $v2->getStatut());
        self::assertNotNull($v2->getOptionsChoisiesLe());
        self::assertSame([$atelier], $v2->getChoix()['activitesDemandees'] ?? null);
        self::assertNotContains($atelier, array_column($v2->getOptions(), 'id'));

        // Le client consulte la version 2, même par le lien de la version 1.
        $this->client->request('GET', '/api/propositions/'.$v1->getJetonPublic());
        $page = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(['version' => 2, 'etat' => 'preparee', 'mention' => Proposition::MENTION_PREPAREE], ['version' => $page['version'], 'etat' => $page['etat'], 'mention' => $page['mention']]);
        self::assertSame([2], array_column($page['versions'], 'version'));
        self::assertSame(['demanderConfirmation' => false, 'accepter' => false], $page['actions']);
    }

    /** Le client ne veut pas d'option : rien n'est recalculé, son récapitulatif lui est confirmé tel quel. */
    public function testLeClientGardeSonVoyageTelQuel(): void
    {
        $this->preparerSansEnvoi($this->id('Atelier pastéis de nata'));
        $this->claude->prevoir(
            FausseApiClaude::appelOutil('choisir_options', ['activitesAjoutees' => [], 'niveau' => 'inchange']),
            FausseApiClaude::texte('Votre voyage reste tel quel.'),
        );

        $evenements = $this->envoyer('Je garde le voyage tel quel, sans option.');

        self::assertCount(2, $this->claude->requetes);
        $parType = array_column($evenements, 1, 0);
        self::assertSame(AgentRedaction::MESSAGE_PRET.' '.AgentRedaction::MESSAGE_SUITE, $parType['recapitulatif']['texte']);
        self::assertSame($this->version1->getJetonPublic(), $parType['recapitulatif']['jeton']);
        self::assertSame(['terminee' => true], $parType['fin']);
        $versions = $this->versions($this->conversation()->getDemande() ?? throw new \LogicException());
        self::assertCount(1, $versions);
        self::assertNotNull($versions[0]->getOptionsChoisiesLe());

        // Au message suivant, l'assistant sait que le client a répondu : il ne repropose rien.
        $this->claude->prevoir(FausseApiClaude::texte('Avec plaisir.'));
        $this->envoyer('Merci !');
        $messages = $this->claude->requetes[2]['corps']['messages'];
        $balise = end($messages)['content'][2]['text'];
        self::assertStringContainsString('le client a déjà répondu aux options : ne les repropose pas', $balise);
    }

    /** Une option qui n'a pas été proposée est refusée au modèle, avec les options possibles. */
    public function testUneOptionInconnueEstRefusee(): void
    {
        $atelier = $this->id('Atelier pastéis de nata');
        $this->preparerSansEnvoi($atelier);
        $this->claude->prevoir(
            FausseApiClaude::appelOutil('choisir_options', ['activitesAjoutees' => [999999], 'niveau' => 'inchange']),
            FausseApiClaude::texte('Laquelle souhaitez-vous ?'),
        );

        $this->envoyer('Ajoutez une croisière.');

        $resultat = end($this->claude->requetes[1]['corps']['messages'])['content'][0];
        self::assertTrue($resultat['is_error']);
        self::assertStringContainsString(\sprintf('Options possibles : %d (Atelier pastéis de nata).', $atelier), json_decode($resultat['content'], true)['erreur']);
        self::assertCount(1, $this->versions($this->conversation()->getDemande() ?? throw new \LogicException()));
    }

    /**
     * La version 1 n'est encore que le récapitulatif du client : brouillon jamais envoyé, demande sans conseiller,
     * avec une option à son prix pour le groupe.
     */
    private function preparerSansEnvoi(int $option): void
    {
        $this->version1->setStatut(StatutProposition::Brouillon)->setEnvoyeeLe(null)
            ->setOptions([['id' => $option, 'nom' => 'Atelier pastéis de nata', 'prixCentimes' => 9000, 'parPersonneCentimes' => 4500, 'participants' => 2]]);
        $this->version1->getDemande()?->setStatut(StatutDemande::Nouvelle)->setConseiller(null);
        $this->em->flush();
    }

    /** Le client a confirmé Lisbonne en fauteuil et reçu la version 1, chiffrée sur l'hôtel et le palace. */
    private function recevoirLaVersion1(): void
    {
        $this->client->request('POST', '/api/chat/conversations');
        $this->jeton = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR)['jeton'];

        $demande = $this->em->getRepository(Conversation::class)->findOneBy(['jeton' => $this->jeton])?->getDemande() ?? throw new \LogicException();
        $demande->setTypeDeVoyage(TypeDeVoyage::Sejour)->setLieuOuZone('Lisbonne')->setVilleDepart('Lyon')
            ->setDateDebut(new \DateTimeImmutable('2027-05-14'))->setDateFin(new \DateTimeImmutable('2027-05-17'))->setNombreDeJours(3)
            ->setAdultes(2)->setBudgetCentimes(130000)->setEnvies(['gastronomie'])->setContraintes(['fauteuil roulant'])
            ->setStatut(StatutDemande::PropositionEnvoyee)
            // Une proposition n'est envoyée que par le conseiller à qui la demande est attribuée.
            ->setConseiller($this->em->getRepository(Utilisateur::class)->findOneBy(['email' => 'lea@shortcut.test']));
        $this->version1 = (new Proposition())
            ->setVersion(1)
            ->setStatut(StatutProposition::Envoyee)
            ->setEnvoyeeLe(new \DateTimeImmutable('-1 hour'))
            ->setNiveauBudget(NiveauBudget::Equilibre)
            ->setChoix(['destination' => 'lisbonne', 'etapes' => [['dateDebut' => '2027-05-14', 'nombreDeNuits' => 3, 'logementIds' => $this->logements]], 'activitesDemandees' => [], 'activitesSuggerees' => [], 'pourquoi' => 'Lisbonne à deux, de plain-pied.'])
            ->setExigences(EnregistrerExigences::fiche($demande))
            ->setProgramme(array_map(static fn (int $jour): array => ['jour' => $jour, 'date' => \sprintf('2027-05-%d', 13 + $jour), 'titre' => 'Jour '.$jour, 'description' => 'Lisbonne.', 'activiteIds' => []], range(1, 4)));
        $demande->addProposition($this->version1);
        $this->em->persist($this->version1);
        $this->em->flush();
    }

    /**
     * @return list<array{0: string, 1: mixed}>
     */
    private function envoyer(string $message): array
    {
        $this->client->request(
            'POST',
            '/api/chat',
            server: ['HTTP_X_CONVERSATION_TOKEN' => $this->jeton, 'CONTENT_TYPE' => 'application/json'],
            content: json_encode(['message' => $message], \JSON_THROW_ON_ERROR),
        );
        self::assertResponseIsSuccessful();

        $evenements = [];
        foreach (explode("\n\n", trim((string) $this->client->getInternalResponse()->getContent())) as $bloc) {
            if (preg_match('/^event: (\S+)\ndata: (.*)$/s', $bloc, $m)) {
                $evenements[] = [$m[1], json_decode($m[2], true, flags: \JSON_THROW_ON_ERROR)];
            }
        }

        return $evenements;
    }

    /**
     * Ce que l'agent Rédaction enverrait, sur le chiffrage donné dans son premier message.
     *
     * @param array<string, mixed> $requete   corps de la requête reçue par l'API
     * @param list<int>            $activites activités du niveau détaillé, placées le jour 2
     *
     * @return array<string, mixed>
     */
    private function proposition(array $requete, int $jours, array $activites = []): array
    {
        preg_match('/chiffrageId : (chf_[0-9a-f]+).*niveauDetaille\) : (\w+)/s', $requete['messages'][0]['content'][0]['text'], $m);

        return [
            'chiffrageId' => $m[1],
            'niveauDetaille' => $m[2],
            'pourquoi' => 'Lisbonne se visite très bien en fauteuil.',
            'argumentaire' => [['exigence' => 'bien manger', 'reponse' => 'Les quartiers gourmands de l\'Alfama.']],
            'prestations' => array_map(static fn (int $id): array => ['id' => $id, 'pointsForts' => ['Plain-pied', 'Chambre adaptée']], [...$this->logements, ...$activites]),
            'programme' => array_map(static fn (int $jour): array => ['jour' => $jour, 'titre' => 'Jour '.$jour, 'description' => 'Découverte de Lisbonne.', 'activiteIds' => 2 === $jour ? $activites : []], range(1, $jours)),
        ];
    }

    /** @return list<Proposition> */
    private function versions(Demande $demande): array
    {
        return $this->em->getRepository(Proposition::class)->findBy(['demande' => $demande], ['version' => 'ASC']);
    }

    private function id(string $nom): int
    {
        return (int) $this->em->getRepository(Prestation::class)->findOneBy(['nom' => $nom])?->getId();
    }

    private function conversation(): Conversation
    {
        $this->em->clear();

        return $this->em->getRepository(Conversation::class)->findOneBy(['jeton' => $this->jeton]) ?? throw new \LogicException();
    }
}
