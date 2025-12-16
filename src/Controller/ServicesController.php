<?php

namespace App\Controller;

use App\Entity\Services;
use App\Form\ServicesType;
use App\Repository\ServicesRepository;
use Symfony\Component\Form\FormError;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/services')]
final class ServicesController extends AbstractController
{
    #[Route('/', name: 'app_services_index')]
    public function index(ServicesRepository $servicesRepository): Response
    {
        $services = $servicesRepository->findAll();

        return $this->render('services/index.html.twig', [
            'services' => $services,
        ]);
    }

    #[Route('/new', name: 'app_services_new')]
    public function new(Request $request, EntityManagerInterface $entityManager, ServicesRepository $servicesRepository): Response
    {
        // Only staff may create services
        $this->denyAccessUnlessGranted('ROLE_STAFF');

        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'You must be logged in as staff to create services.');
            return $this->redirectToRoute('app_login');
        }

        $service = new Services();
        $form = $this->createForm(ServicesType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Prevent duplicate service names
            $existing = $servicesRepository->findOneBy(['name' => $service->getName()]);
            if ($existing) {
                $form->get('name')->addError(new FormError('A service with this name already exists.'));
                return $this->render('services/new.html.twig', [
                    'form' => $form->createView(),
                ]);
            }
            // set owner to current staff user
            if ($user instanceof \App\Entity\User) {
                $service->setOwner($user);
            }

            $entityManager->persist($service);
            $entityManager->flush();

            $this->addFlash('success', 'Service added successfully!');
            return $this->redirectToRoute('app_services_index');
        }

        return $this->render('services/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
