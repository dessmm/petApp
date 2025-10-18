<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Form\ContactType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/contact')]
final class ContactController extends AbstractController
{
    #[Route('/contact-us', name: 'app_contact_public', methods: ['GET', 'POST'])]
    public function publicContact(Request $request, EntityManagerInterface $entityManager): Response
    {
        $contact = new Contact();
        $form = $this->createForm(ContactType::class, $contact);
        $form->remove('status'); 
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
}
