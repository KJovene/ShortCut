<?php

namespace App\Controller;

use App\Entity\Destination;
use App\Entity\PeriodeClimatique;
use App\Entity\TarifParPeriode;
use App\Form\PeriodeClimatiqueType;
use App\Repository\DestinationRepository;
use App\Repository\PeriodeClimatiqueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/destinations')]
final class PeriodeClimatiqueController extends AbstractController
{
    #[Route('', name: 'admin_destinations', methods: ['GET'])]
    public function index(DestinationRepository $repository): Response
    {
        return $this->render('admin/destinations.html.twig', [
            'destinations' => $repository->findBy([], ['ville' => 'ASC']),
        ]);
    }

    #[Route('/{id}/periode', name: 'admin_destination_periode', methods: ['GET', 'POST'])]
    public function edit(
        Destination $destination,
        Request $request,
        EntityManagerInterface $entityManager,
        PeriodeClimatiqueRepository $repository,
        SluggerInterface $slugger,
    ): Response {
        $source = null;
        $sourceId = $request->query->getInt('source');
        if ($sourceId > 0) {
            $source = $repository->find($sourceId);
            if (null === $source || $source->getDestination() !== $destination) {
                throw $this->createNotFoundException('La période à dupliquer est introuvable.');
            }
        }

        $periode = new PeriodeClimatique();
        if (null !== $source) {
            $periode->setDestination($destination);
            if (null !== $source->getDebut() && null !== $source->getFin() && null !== $source->getTemperatureMoyenne()) {
                $periode
                    ->setDebut($source->getDebut())
                    ->setFin($source->getFin())
                    ->setTemperatureMoyenne($source->getTemperatureMoyenne())
                    ->setPluieMm($source->getPluieMm());
            }
        }
        $tarifs = [];
        foreach ($destination->getPrestations() as $prestation) {
            $tarif = new TarifParPeriode();
            $tarif->setPrestation($prestation);
            if (null !== $source) {
                foreach ($prestation->getTarifs() as $existing) {
                    if ($existing->getDebut() == $source->getDebut() && $existing->getFin() == $source->getFin()) {
                        $tarif
                            ->setDebut($existing->getDebut())
                            ->setFin($existing->getFin())
                            ->setPrixCentimes($existing->getPrixCentimes())
                            ->setUnite($existing->getUnite())
                            ->setSejourMinimumNuits($existing->getSejourMinimumNuits());
                        break;
                    }
                }
            }
            $tarifs[] = $tarif;
        }

        $form = $this->createForm(PeriodeClimatiqueType::class, $periode, ['data' => $periode]);
        $form->get('tarifs')->setData($tarifs);
        foreach ($form->get('tarifs') as $index => $tarifForm) {
            $prestation = $tarifs[$index]->getPrestation();
            $tarifForm->get('nom')->setData($prestation?->getNom());
            $tarifForm->get('description')->setData($prestation?->getDescription());
            $tarifForm->get('photo')->setData($prestation?->getPhotos()[0] ?? null);
        }
        $form->handleRequest($request);

        if ($form->isSubmitted() && $request->request->has($form->getName().'_duplicate')) {
            return $this->redirectToRoute('admin_destination_periode', [
                'id' => $destination->getId(),
                'source' => $periode->getId(),
            ]);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $periode->setDestination($destination);
            $entityManager->persist($periode);
            $tarifsSoumis = $form->get('tarifs')->getData();
            foreach (is_iterable($tarifsSoumis) ? $tarifsSoumis : [] as $index => $tarif) {
                if (!$tarif instanceof TarifParPeriode) {
                    continue;
                }
                $prestation = $destination->getPrestations()->filter(
                    static fn ($prestation): bool => $prestation->getId() === $tarif->getPrestation()?->getId(),
                )->first() ?: $tarif->getPrestation();
                $tarif->setPrestation($prestation);
                $tarif->setSaisiDepuisAdmin(true);
                if ($prestation instanceof \App\Entity\Prestation) {
                    $prestationForm = $form->get('tarifs')->get((string) $index);
                    $nom = $prestationForm->get('nom')->getData();
                    $description = $prestationForm->get('description')->getData();
                    $photo = $prestationForm->get('photo')->getData();
                    $photoUpload = $prestationForm->get('photoUpload')->getData();
                    if (is_string($nom) && '' !== trim($nom)) {
                        $prestation->setNom(trim($nom));
                    }
                    $prestation->setDescription(is_string($description) && '' !== trim($description) ? trim($description) : null);
                    if (is_string($photo) && '' !== trim($photo)) {
                        $prestation->setPhotos([basename(trim($photo))]);
                    }
                    if ($photoUpload instanceof UploadedFile) {
                        $nomImage = $slugger->slug((string) pathinfo($photoUpload->getClientOriginalName(), \PATHINFO_FILENAME));
                        $fichier = $nomImage.'-'.bin2hex(random_bytes(4)).'.'.$photoUpload->guessExtension();
                        $photoUpload->move($this->getParameter('kernel.project_dir').'/public/uploads/prestations', $fichier);
                        $prestation->setPhotos([$fichier]);
                    }
                    $entityManager->persist($prestation);
                }
                $entityManager->persist($tarif);
            }
            $entityManager->flush();

            $this->addFlash('success', 'La période et ses tarifs ont été enregistrés.');

            return $this->redirectToRoute('admin_destination_periode', ['id' => $destination->getId()]);
        }

        return $this->render('admin/periode_climatique.html.twig', [
            'destination' => $destination,
            'form' => $form,
            'periodes_non_couvertes' => $repository->findUncoveredMonths(
                $destination,
                new \DateTimeImmutable('today'),
            ),
        ]);
    }
}
