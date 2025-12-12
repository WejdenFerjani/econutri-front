<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(ProductRepository $productRepository): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer les produits recommandés (6 mieux stockés) et nouveaux (3 plus récents)
        $recommendedEntities = $productRepository->findRecommendedProducts(6);
        $newEntities = $productRepository->findNewProducts(3);

        // Convert entities to simple arrays so templates can safely access optional keys (e.g. season)
        $mapProduct = function($p) {
            return [
                'id' => $p->getId(),
                'name' => $p->getName(),
                'price' => $p->getPrice(),
                'image' => $p->getImage(),
                'description' => $p->getDescription(),
                'stock' => $p->getStock(),
                'origin' => $p->getOrigin(),
                // season is optional / not present on the entity - keep null when unknown
                    // no season key: option C chosen (do not expose season)
            ];
        };

        $recommendedProducts = array_map($mapProduct, $recommendedEntities);
        $newProducts = array_map($mapProduct, $newEntities);

        return $this->render('home/index.html.twig', [
            'recommendedProducts' => $recommendedProducts,
            'newProducts' => $newProducts,
        ]);
    }
}