<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CategoryController extends AbstractController
{
    #[Route('/categorie/{id}', name: 'app_category')]
    public function index(
        int $id,
        Request $request,
        CategoryRepository $categoryRepository,
        ProductRepository $productRepository
    ): Response {
        // Find category by ID from database
        $category = $categoryRepository->find($id);
        
        if (!$category) {
            throw $this->createNotFoundException('Catégorie non trouvée');
        }

        // Get filter parameters from request
        $filters = [
            'minPrice' => $request->query->get('minPrice'),
            'maxPrice' => $request->query->get('maxPrice'),
            'origin' => $request->query->get('origin'),
            'inStock' => $request->query->get('inStock'),
            'sort' => $request->query->get('sort', 'newest')
        ];

        // Handle price ranges - THIS MUST BE INSIDE THE METHOD
        if ($request->query->get('priceRange')) {
            $priceRange = $request->query->get('priceRange');
            list($minPrice, $maxPrice) = explode('-', $priceRange);
            $filters['minPrice'] = $minPrice;
            $filters['maxPrice'] = $maxPrice;
        }

        // Find products with filters
        $products = $productRepository->findByCategoryWithFilters($category->getId(), $filters);

        return $this->render('category.html.twig', [
            'category' => [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'description' => $category->getDescription(),
                'image' => $category->getImage()
            ],
            'products' => $products,
            'currentFilters' => $filters
        ]);
    }
}