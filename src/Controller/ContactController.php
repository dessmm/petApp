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

#[Route('/contact')]
final class ContactController extends AbstractController
{
    // 🟡 Public-facing contact page (must be FIRST)
    #[Route('/contact-us', name: 'app_contact_public', methods: ['GET', 'POST'])]
    public function publicContact(Request $request, EntityManagerInterface $entityManager): Response
    {
        $contact = new Contact();
        $form = $this->createForm(ContactType::class, $contact);
        $form->remove('status'); // hide status from public
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contact->setSubmittedAt(new \DateTime());
            $contact->setStatus('New');
            $entityManager->persist($contact);
            $entityManager->flush();

            $this->addFlash('success', 'Thank you for reaching out! We’ll get back to you soon.');
            return $this->redirectToRoute('app_contact_public');
        }

        return $this->render('contact/public_contact.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/', name: 'app_contact_index', methods: ['GET'])]
    public function index(ContactRepository $contactRepository): Response
    {
        return $this->render('contact/index.html.twig', [
            'contacts' => $contactRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_contact_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $contact = new Contact();
        $form = $this->createForm(ContactType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contact->setSubmittedAt(new \DateTime());
            $contact->setStatus('New');
            $entityManager->persist($contact);
            $entityManager->flush();

            $this->addFlash('success', 'Message created successfully!');
            return $this->redirectToRoute('app_contact_index');
        }

        return $this->render('contact/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    // 🟢 Admin: view one contact
    #[Route('/{id}', name: 'app_contact_show', methods: ['GET'])]
    public function show(int $id, ContactRepository $contactRepository): Response
    {
        $contact = $contactRepository->find($id);
        if (!$contact) {
            throw $this->createNotFoundException('Contact not found.');
        }

        return $this->render('contact/show.html.twig', [
            'contact' => $contact,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_contact_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id, ContactRepository $contactRepository, EntityManagerInterface $entityManager): Response
    {
        $contact = $contactRepository->find($id);
        if (!$contact) {
            throw $this->createNotFoundException('Contact not found.');
        }

        $form = $this->createForm(ContactType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Message updated successfully.');
            return $this->redirectToRoute('app_contact_index');
        }

        return $this->render('contact/edit.html.twig', [
            'contact' => $contact,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_contact_delete', methods: ['POST'])]
    public function delete(Request $request, int $id, ContactRepository $contactRepository, EntityManagerInterface $entityManager): Response
    {
        $contact = $contactRepository->find($id);
        if (!$contact) {
            throw $this->createNotFoundException('Contact not found.');
        }

        if ($this->isCsrfTokenValid('delete'.$contact->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($contact);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_contact_index');
    }
}
