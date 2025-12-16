<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Form\ContactType;
use App\Repository\ContactRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/staff/contacts')]
final class StaffContactController extends AbstractController
{
    private function ensureStaff(): void
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');
    }

    #[Route('/', name: 'staff_contacts_index', methods: ['GET'])]
    public function index(ContactRepository $contactRepository): Response
    {
        $this->ensureStaff();

        return $this->render('staff/contact/index.html.twig', [
            'contacts' => $contactRepository->findAll(),
        ]);
    }

    #[Route('/{id}', name: 'staff_contacts_show', methods: ['GET'])]
    public function show(Contact $contact, EntityManagerInterface $entityManager): Response
    {
        $this->ensureStaff();

        if ($contact->getStatus() === 'New') {
            $contact->setStatus('Read');
            $entityManager->flush();
        }

        return $this->render('staff/contact/show.html.twig', [
            'contact' => $contact,
        ]);
    }

    #[Route('/{id}/edit', name: 'staff_contacts_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Contact $contact, EntityManagerInterface $entityManager): Response
    {
        $this->ensureStaff();

        $form = $this->createForm(ContactType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Message updated successfully.');
            return $this->redirectToRoute('staff_contacts_index');
        }

        return $this->render('staff/contact/edit.html.twig', [
            'form' => $form->createView(),
            'contact' => $contact,
        ]);
    }

    #[Route('/{id}/delete', name: 'staff_contacts_delete', methods: ['POST'])]
    public function delete(Request $request, Contact $contact, EntityManagerInterface $entityManager): Response
    {
        $this->ensureStaff();

        if ($this->isCsrfTokenValid('delete' . $contact->getId(), $request->request->get('_token'))) {
            $entityManager->remove($contact);
            $entityManager->flush();
            $this->addFlash('success', 'Message deleted successfully.');
        }

        return $this->redirectToRoute('staff_contacts_index');
    }
}
