<?php

namespace App\Tests\Security;

use App\Entity\Conversation;
use App\Entity\Demande;
use App\Entity\Proposition;
use App\Entity\Utilisateur;
use App\Enum\Role;
use App\Security\VisiteurChatbotProvider;
use App\Security\Voter\DemandeVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Droits des trois profils sur les demandes : conseiller, administrateur, chatbot.
 * Nécessite la base de test : `make db-test`.
 */
final class DemandeVoterTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private AccessDecisionManagerInterface $decisions;
    private Utilisateur $conseiller;
    private Utilisateur $autreConseiller;
    private Utilisateur $admin;
    private Demande $demandeDuConseiller;
    private Demande $demandeNonAttribuee;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->decisions = self::getContainer()->get(AccessDecisionManagerInterface::class);
        $this->em->getConnection()->beginTransaction();

        $this->conseiller = $this->utilisateur('lea@shortcut.test', Role::Conseiller);
        $this->autreConseiller = $this->utilisateur('hugo@shortcut.test', Role::Conseiller);
        $this->admin = $this->utilisateur('admin@shortcut.test', Role::Administrateur);
        $this->demandeDuConseiller = $this->demande($this->conseiller);
        $this->demandeNonAttribuee = $this->demande(null);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testLeConseillerAccedeAuxDemandesQuiLuiSontAttribuees(): void
    {
        self::assertTrue($this->peut($this->conseiller, DemandeVoter::VOIR, $this->demandeDuConseiller));
        self::assertTrue($this->peut($this->conseiller, DemandeVoter::MODIFIER, $this->demandeDuConseiller));
        self::assertTrue($this->peut($this->conseiller, DemandeVoter::VOIR, $this->demandeDuConseiller->getConversation()));
        self::assertTrue($this->peut($this->conseiller, DemandeVoter::ATTRIBUER, $this->demandeDuConseiller));
    }

    public function testLeConseillerVoitEtAiguilleLesDemandesNonAttribuees(): void
    {
        self::assertTrue($this->peut($this->conseiller, DemandeVoter::VOIR, $this->demandeNonAttribuee));
        self::assertTrue($this->peut($this->autreConseiller, DemandeVoter::ATTRIBUER, $this->demandeNonAttribuee));
        // Il doit se l'attribuer avant de la traiter.
        self::assertFalse($this->peut($this->conseiller, DemandeVoter::MODIFIER, $this->demandeNonAttribuee));
    }

    public function testLeConseillerNAccedePasAuxAutresDemandes(): void
    {
        self::assertFalse($this->peut($this->autreConseiller, DemandeVoter::VOIR, $this->demandeDuConseiller));
        self::assertFalse($this->peut($this->autreConseiller, DemandeVoter::MODIFIER, $this->demandeDuConseiller));
        self::assertFalse($this->peut($this->autreConseiller, DemandeVoter::ATTRIBUER, $this->demandeDuConseiller));
    }

    public function testLAdministrateurAccedeAToutesLesDemandesEtHeriteDuRoleConseiller(): void
    {
        self::assertTrue($this->peut($this->admin, DemandeVoter::MODIFIER, $this->demandeDuConseiller));
        self::assertTrue($this->peut($this->admin, DemandeVoter::VOIR, $this->demandeNonAttribuee));
        self::assertTrue($this->peut($this->admin, 'ROLE_CONSEILLER'));
        self::assertFalse($this->peut($this->conseiller, 'ROLE_ADMIN'));
    }

    public function testLeChatbotNAccedeQuALaDemandeDeSaConversation(): void
    {
        $jeton = $this->demandeNonAttribuee->getConversation()?->getJeton();
        self::assertNotNull($jeton);
        $visiteur = self::getContainer()->get(VisiteurChatbotProvider::class)->loadUserByIdentifier($jeton);

        $proposition = new Proposition();
        $this->demandeNonAttribuee->addProposition($proposition);

        self::assertTrue($this->peut($visiteur, DemandeVoter::VOIR, $this->demandeNonAttribuee));
        self::assertTrue($this->peut($visiteur, DemandeVoter::VOIR, $proposition));
        self::assertFalse($this->peut($visiteur, DemandeVoter::ATTRIBUER, $this->demandeNonAttribuee));
        self::assertFalse($this->peut($visiteur, DemandeVoter::VOIR, $this->demandeDuConseiller));
        self::assertFalse($this->peut($visiteur, 'ROLE_CONSEILLER'));
    }

    private function peut(UserInterface $user, string $attribut, mixed $sujet = null): bool
    {
        $token = new UsernamePasswordToken($user, 'test', $user->getRoles());

        return $this->decisions->decide($token, [$attribut], $sujet);
    }

    private function utilisateur(string $email, Role $role): Utilisateur
    {
        $utilisateur = (new Utilisateur())->setEmail($email)->setNom($email)->setRole($role);
        $this->em->persist($utilisateur);

        return $utilisateur;
    }

    private function demande(?Utilisateur $conseiller): Demande
    {
        $demande = (new Demande())->setConseiller($conseiller);
        $conversation = new Conversation();
        $demande->setConversation($conversation);
        $this->em->persist($demande);
        $this->em->persist($conversation);

        return $demande;
    }
}
