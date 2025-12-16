<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\ActivityLogger;
use Symfony\Component\Form\FormError;

#[Route('/admin/user')]
final class AdminUserController extends AbstractController
{
    #[Route('/', name: 'app_admin_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        return $this->render('admin_user/index.html.twig', [
            'users' => $userRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_admin_user_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request, 
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        ActivityLogger $activityLogger
    ): Response {
        
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = new User();
        $user->setIsActive(true);
        $user->setCreatedAt(new \DateTime());

        $form = $this->createForm(UserType::class, $user, [
            'is_new' => true
        ]);

        $form->handleRequest($request);

        // Debug logging to help track down missing username: log POST keys and sanitized payload
        try {
            $post = $request->request->all();
            $sanitized = $post;
            if (isset($sanitized['plainPassword'])) {
                $sanitized['plainPassword'] = '[REDACTED]';
            }
            // If form uses nested 'user' array, redact nested plainPassword too
            if (isset($sanitized['user']) && is_array($sanitized['user']) && isset($sanitized['user']['plainPassword'])) {
                $sanitized['user']['plainPassword'] = '[REDACTED]';
            }
            error_log('[AdminUserController] POST keys: '.json_encode(array_keys($post)));
            error_log('[AdminUserController] Sanitized POST payload: '.json_encode($sanitized));
            $submittedFlag = $form->isSubmitted() ? '1' : '0';
            $validFlag = $form->isSubmitted() ? ($form->isValid() ? '1' : '0') : 'U';
            error_log('[AdminUserController] form->isSubmitted='.$submittedFlag.', isValid='.$validFlag.', entity username='.json_encode($user->getUsername()));
        } catch (\Throwable $e) {
            error_log('[AdminUserController] Error while logging POST payload: '.$e->getMessage());
        }

        // Defensive server-side check: ensure username is present before persisting
        if ($form->isSubmitted()) {
            $usernameValue = $form->has('username') ? $form->get('username')->getData() : null;
            if (empty($usernameValue)) {
                $form->get('username')->addError(new FormError('Please enter a username.'));
            }
        }

        // If mapping somehow failed, try to pull username directly from POST payload and set it on the entity.
        if ($form->isSubmitted() && (null === $user->getUsername() || '' === trim((string)$user->getUsername()))) {
            $post = $request->request->all();
            // common Symfony form name is the lower-cased short class name 'user'
            if (isset($post['user']['username'])) {
                $raw = trim((string)$post['user']['username']);
                if ($raw !== '') {
                    $user->setUsername($raw);
                }
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {

            $plainPassword = $form->get('plainPassword')->getData();
            $hashed = $passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashed);

            // `roles` is not mapped in the form (mapped => false), so copy it to the entity
            if ($form->has('roles')) {
                $selectedRole = $form->get('roles')->getData();
                if ($selectedRole) {
                    // setRoles accepts string or array in the entity
                    $user->setRoles($selectedRole);
                }
            }

            // Defensive: ensure username is set before persisting to avoid DB constraint errors
            if (null === $user->getUsername() || '' === trim((string) $user->getUsername())) {
                // Log raw request payload (avoid logging raw password)
                $payload = $request->request->all();
                if (isset($payload['plainPassword'])) {
                    $payload['plainPassword'] = '[REDACTED]';
                }
                error_log('[AdminUserController] Missing username on create. POST payload: '.json_encode($payload));

                $form->get('username')->addError(new FormError('Please provide a username.')); 

                return $this->render('admin_user/new.html.twig', [
                    'form' => $form,
                ]);
            }

            // Log current username before persisting to help debug null username issues
            error_log('[AdminUserController] About to persist user. username=' . json_encode($user->getUsername()));

            try {
                $em->persist($user);
                $em->flush();
            } catch (\Throwable $e) {
                // Log full payload (redacting password) and the exception for debugging
                $payload = $request->request->all();
                if (isset($payload['plainPassword'])) {
                    $payload['plainPassword'] = '[REDACTED]';
                }
                error_log('[AdminUserController] Exception during user persist: ' . $e->getMessage() . ' POST: ' . json_encode($payload));

                $form->addError(new FormError('There was a problem saving the user. Please check the data and try again.'));

                return $this->render('admin_user/new.html.twig', [
                    'form' => $form,
                ]);
            }

            // Record activity (two-arg API)
            $actor = $this->getUser();
            $actorName = null;
            if (is_object($actor) && method_exists($actor, 'getUserIdentifier')) {
                $actorName = $actor->getUserIdentifier();
            }
            $createdIdentifier = method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : (method_exists($user, 'getUsername') ? $user->getUsername() : null);
            $activityMsg = 'Created new user ' . ($createdIdentifier ?? 'ID '.$user->getId());
            if ($actorName) {
                $activityMsg .= ' by ' . $actorName;
            }
            $activityLogger->logActivity('User Creation', $activityMsg);

            $this->addFlash('success', 'User created successfully.');
            return $this->redirectToRoute('app_admin_user_index');
        }

        return $this->render('admin_user/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_user_show', methods: ['GET'])]
    public function show(User $user): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        return $this->render('admin_user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_user_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        User $user,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        ActivityLogger $activityLogger
    ): Response {

        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(UserType::class, $user, [
            'is_new' => false
        ]);

        $form->handleRequest($request);

        // capture archive state before processing changes
        $wasArchived = $user->getArchivedAt() !== null;

        // If mapping somehow failed on edit, try to pull username from POST payload and set it
        if ($form->isSubmitted() && (null === $user->getUsername() || '' === trim((string)$user->getUsername()))) {
            $post = $request->request->all();
            if (isset($post['user']['username'])) {
                $raw = trim((string)$post['user']['username']);
                if ($raw !== '') {
                    $user->setUsername($raw);
                }
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {

            $plainPassword = $form->get('plainPassword')->getData();
            if (!empty($plainPassword)) {
                $hashed = $passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashed);
            }

            // If the roles field is present (it's unmapped), apply the selected role
            if ($form->has('roles')) {
                $selectedRole = $form->get('roles')->getData();
                if ($selectedRole) {
                    $user->setRoles($selectedRole);
                }
            }

            // Handle archive/unarchive flag from the form (mapped => false)
            if ($form->has('archived')) {
                $archived = $form->get('archived')->getData();
                if ($archived) {
                    if ($user->getArchivedAt() === null) {
                        $user->setArchivedAt(new \DateTime());
                    }
                } else {
                    $user->setArchivedAt(null);
                }
            }

            // Defensive check for edit as well
            if (null === $user->getUsername() || '' === trim((string) $user->getUsername())) {
                $payload = $request->request->all();
                if (isset($payload['plainPassword'])) {
                    $payload['plainPassword'] = '[REDACTED]';
                }
                error_log('[AdminUserController] Missing username on edit. POST payload: '.json_encode($payload));
                $form->get('username')->addError(new FormError('Please provide a username.'));
                return $this->render('admin_user/edit.html.twig', [
                    'form' => $form,
                    'user' => $user,
                ]);
            }

            try {
                $em->flush();
                // Log admin user update and archive/unarchive events
                try {
                    $updatedIdentifier = method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : (method_exists($user, 'getUsername') ? $user->getUsername() : null);
                    $activityLogger->logActivity('User Update', 'Updated user ' . ($updatedIdentifier ?? 'ID '.$user->getId()));
                    $nowArchived = $user->getArchivedAt() !== null;
                    if ($nowArchived && !$wasArchived) {
                        $activityLogger->logActivity('User Archived', 'Archived user ' . ($updatedIdentifier ?? 'ID '.$user->getId()));
                    } elseif (!$nowArchived && $wasArchived) {
                        $activityLogger->logActivity('User Unarchived', 'Unarchived user ' . ($updatedIdentifier ?? 'ID '.$user->getId()));
                    }
                } catch (\Throwable $inner) {}
            } catch (\Throwable $e) {
                $payload = $request->request->all();
                if (isset($payload['plainPassword'])) {
                    $payload['plainPassword'] = '[REDACTED]';
                }
                error_log('[AdminUserController] Exception during user update: ' . $e->getMessage() . ' POST: ' . json_encode($payload));

                $form->addError(new FormError('There was a problem updating the user. Please check the data and try again.'));
                return $this->render('admin_user/edit.html.twig', [
                    'form' => $form,
                    'user' => $user,
                ]);
            }

            $this->addFlash('success', 'User updated successfully.');
            return $this->redirectToRoute('app_admin_user_index');
        }

        return $this->render('admin_user/edit.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $em, \App\Service\ActivityLogger $activityLogger): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if ($this->isCsrfTokenValid('delete-user-'.$user->getId(), $request->request->get('_token'))) {

            // Capture identifying info before removal
            $deletedId = $user->getId();
            $deletedIdentifier = method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : (method_exists($user, 'getUsername') ? $user->getUsername() : null);

            $em->remove($user);
            $em->flush();

            // Log deletion activity using username/identifier when available
            if ($deletedIdentifier) {
                $message = 'Deleted user ' . $deletedIdentifier . ' (ID ' . $deletedId . ')';
            } else {
                $message = 'Deleted user ID ' . $deletedId;
            }
            $activityLogger->logActivity('User Deletion', $message);

            $this->addFlash('success', 'User deleted successfully.');
        }

        return $this->redirectToRoute('app_admin_user_index');
    }

}
