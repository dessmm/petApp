<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\SecurityAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, Security $security, EntityManagerInterface $entityManager): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // Log form data for debugging
            $this->addFlash('debug', 'Form data: ' . json_encode($form->getData()));
        }

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // encode the plain password
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            // Defensive: ensure username is set before persisting to avoid DB constraint errors
            if (null === $user->getUsername() || '' === trim((string) $user->getUsername())) {
                // Try to recover username from raw POST payload (common form names)
                $post = $request->request->all();
                $recovered = null;
                if (isset($post['registration_form']['username'])) {
                    $recovered = trim((string)$post['registration_form']['username']);
                } elseif (isset($post['user']['username'])) {
                    $recovered = trim((string)$post['user']['username']);
                } elseif (isset($post['username'])) {
                    $recovered = trim((string)$post['username']);
                }

                if ($recovered) {
                    $user->setUsername($recovered);
                } else {
                    // Log payload to help debugging (do not log raw password values)
                    $payload = $request->request->all();
                    if (isset($payload['plainPassword'])) {
                        $payload['plainPassword'] = '[REDACTED]';
                    }
                    error_log('[RegistrationController] Missing username on register. POST payload: '.json_encode($payload));

                    $form->get('username')->addError(new \Symfony\Component\Form\FormError('Please provide a username.'));

                    return $this->render('registration/register.html.twig', [
                        'registrationForm' => $form,
                    ]);
                }
            }

            // Defensive: ensure email is set before persisting to avoid DB constraint errors
            if (null === $user->getEmail() || '' === trim((string) $user->getEmail())) {
                // Try to recover email from raw POST payload (common form names)
                $post = $request->request->all();
                $recoveredEmail = null;
                if (isset($post['registration_form']['email'])) {
                    $recoveredEmail = trim((string)$post['registration_form']['email']);
                } elseif (isset($post['user']['email'])) {
                    $recoveredEmail = trim((string)$post['user']['email']);
                } elseif (isset($post['email'])) {
                    $recoveredEmail = trim((string)$post['email']);
                }

                if ($recoveredEmail) {
                    $user->setEmail($recoveredEmail);
                } else {
                    $payload = $request->request->all();
                    if (isset($payload['plainPassword'])) {
                        $payload['plainPassword'] = '[REDACTED]';
                    }
                    error_log('[RegistrationController] Missing email on register. POST payload: '.json_encode($payload));

                    $form->get('username')->addError(new \Symfony\Component\Form\FormError('Please provide an email.'));

                    return $this->render('registration/register.html.twig', [
                        'registrationForm' => $form,
                    ]);
                }
            }

            $entityManager->persist($user);
            $entityManager->flush();

            // do anything else you need here, like send an email

            return $security->login($user, SecurityAuthenticator::class, 'main');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
