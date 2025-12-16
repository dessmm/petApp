<?php

namespace App\Controller;

use App\Form\ChangePasswordType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/admin/account')]
final class AdminAccountController extends AbstractController
{
    #[Route('/', name: 'app_admin_account_profile', methods: ['GET'])]
    public function profile(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = $this->getUser();

        return $this->render('admin_account/profile.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/change-password', name: 'app_admin_account_change_password', methods: ['GET','POST'])]
    public function changePassword(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = $this->getUser();

        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = $form->get('newPassword')->getData();
            if (!($user instanceof \App\Entity\User)) {
                $this->addFlash('error', 'Unable to update password.');
                return $this->redirectToRoute('app_admin_account_profile');
            }

            $hashed = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashed);
            $em->flush();

            $this->addFlash('success', 'Password updated successfully.');
            return $this->redirectToRoute('app_admin_account_profile');
        }

        return $this->render('admin_account/change_password.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
