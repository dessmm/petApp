<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Form\ContactType;
use App\Repository\ContactRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/contacts')]
final class AdminContactController extends AbstractController
{
    #[Route('/', name: 'app_admin_contacts_index', methods: ['GET'])]
    public function index(ContactRepository $contactRepository): Response
    {
        return $this->render('admin_dashboard/contact/index.html.twig', [
            'contacts' => $contactRepository->findAll(),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_contacts_show', methods: ['GET'])]
    public function show(Contact $contact, EntityManagerInterface $entityManager): Response
    {
        if ($contact->getStatus() === 'New') {
            $contact->setStatus('Read');
            $entityManager->flush();
        }

        return $this->render('admin_dashboard/contact/show.html.twig', [
            'contact' => $contact,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_contacts_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Contact $contact, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ContactType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Message updated successfully.');
            return $this->redirectToRoute('app_admin_contacts_index');
        }

        return $this->render('admin_dashboard/contact/edit.html.twig', [
            'form' => $form->createView(),
            'contact' => $contact,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_contacts_delete', methods: ['POST'])]
    public function delete(Request $request, Contact $contact, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $contact->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($contact);
            $entityManager->flush();
            $this->addFlash('success', 'Message deleted successfully.');
        }

        return $this->redirectToRoute('app_admin_contacts_index');
    }
}
