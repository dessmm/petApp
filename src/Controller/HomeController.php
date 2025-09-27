<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController {
    #[Route('/')]
    public function index():Response {

        return $this->render('home/index.html.twig', [
    'services' => [
        ['title' => 'Bath & Blow Dry', 'desc' => 'Keep your pet fresh and clean', 'link' => '#'],
        ['title' => 'Full Groom', 'desc' => 'Complete haircut and styling', 'link' => '#'],
        ['title' => 'Spa Treatment', 'desc' => 'De-shedding and skin care', 'link' => '#'],
    ],
    'packages' => [
        ['name' => 'Essential Care', 'price' => '₱800', 'features' => ['Bath', 'Nail Trim']],
        ['name' => 'Complete Groom', 'price' => '₱1600', 'features' => ['Haircut', 'Bath', 'Ear Cleaning']],
    ],
    'reviews' => [
        ['author' => 'Maria D.', 'stars' => 5, 'comment' => 'Great service, my dog loved it!'],
        ['author' => 'John P.', 'stars' => 4, 'comment' => 'Easy booking and friendly staff.'],
    ]
]);

    }
}