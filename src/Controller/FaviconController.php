<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FaviconController extends AbstractController
{
    #[Route('/favicon.ico', name: 'favicon')]
    public function favicon(): Response
    {
        $path = $this->getParameter('kernel.project_dir') . '/public/favicon.ico';
        if (file_exists($path)) {
            return new BinaryFileResponse($path);
        }

        // Return 204 No Content if no favicon is present to avoid routing errors in dev logs
        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
