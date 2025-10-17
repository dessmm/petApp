<?php

namespace App\Controller;

use App\Entity\Services;
use App\Form\ServicesType;
use App\Repository\ServicesRepository;
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
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $service = new Services();
        $form = $this->createForm(ServicesType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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
