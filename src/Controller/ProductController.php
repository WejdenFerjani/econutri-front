<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProductController extends AbstractController
{
    #[Route('/produit/{id}', name: 'app_product')]
    public function index(
        int $id, 
        ProductRepository $productRepository
    ): Response {
        // Find product by ID
        $product = $productRepository->find($id);
        
        if (!$product) {
            throw $this->createNotFoundException('Produit non trouvé');
        }

        // Get related products from same category
        $relatedProducts = $productRepository->findBy(
            ['category' => $product->getCategory()],
            ['createdAt' => 'DESC'],
            4
        );

        // Prepare product data for template
        $productData = [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'price' => $product->getPrice(),
            'image' => $product->getImage(),
            'category' => [
                'name' => $product->getCategory()->getName(),
                'id' => $product->getCategory()->getId()
            ],
            'origin' => $product->getOrigin(),
            'stock' => $product->getStock(),
            'unit' => $product->getUnit(),
            // 'season' removed per option C: do not include season in product data
            'description' => $product->getDescription(),
            'fullDescription' => $product->getDescription(),
            'createdAt' => $product->getCreatedAt(),
            'nutrition' => [
                'calories' => 100,
                'proteins' => 5,
                'carbs' => 15,
                'fiber' => 2
            ]
        ];

        // Prepare related products data
        $relatedProductsData = [];
        foreach ($relatedProducts as $relatedProduct) {
            if ($relatedProduct->getId() !== $product->getId()) {
                $relatedProductsData[] = [
                    'id' => $relatedProduct->getId(),
                    'name' => $relatedProduct->getName(),
                    'price' => $relatedProduct->getPrice(),
                    'image' => $relatedProduct->getImage(),
                    'description' => $relatedProduct->getDescription(),
                    // 'unit' removed per UI decision
                ];
            }
        }

        // Limit to 4 products maximum
        $relatedProductsData = array_slice($relatedProductsData, 0, 4);

        return $this->render('product.html.twig', [
            'product' => $productData,
            'relatedProducts' => $relatedProductsData
        ]);
    }
}