<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/panier')]
class CartController extends AbstractController
{
    public function __construct(
        
        private ProductRepository $productRepository,
        private CartService $cartService

    ) {}

    #[Route('', name: 'app_cart_index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $cartItems = $this->cartService->getCartItems($user);
        $cartTotal = $this->cartService->getCartTotal($user);
        $shippingCost = 5.00;
        $totalWithShipping = $cartTotal + $shippingCost;

        return $this->render('cart/index.html.twig', [
            'cartItems' => $cartItems,
            'cartTotal' => $cartTotal,
            'shippingCost' => $shippingCost,
            'totalWithShipping' => $totalWithShipping,
        ]);
    }

    #[Route('/ajouter/{id}', name: 'app_cart_add', methods: ['GET', 'POST'])]
    public function add(string $id, Request $request): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $productId = filter_var($id, FILTER_VALIDATE_INT);
        
        if ($productId === false) {
            $this->addFlash('error', 'ID produit invalide');
            return $this->redirectToRoute('app_home');
        }

        $product = $this->productRepository->find($productId);

        if (!$product) {
            $this->addFlash('error', 'Produit non trouvé dans la base de données');
            return $this->redirectToRoute('app_home');
        }

        // Read quantity from POST first (form submit), then fallback to query param
        $quantity = $request->request->getInt('quantity', $request->query->getInt('quantity', 1));

        if ($quantity < 1) {
            $quantity = 1;
        }

        $this->cartService->addToCart($user, $product, $quantity);

        $this->addFlash('success', 'Produit ajouté au panier avec succès!');

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/supprimer/{id}', name: 'app_cart_remove', methods: ['POST'])]
    public function remove(string $id): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $productId = filter_var($id, FILTER_VALIDATE_INT);
        
        if ($productId === false) {
            $this->addFlash('error', 'ID produit invalide');
            return $this->redirectToRoute('app_cart_index');
        }

        $product = $this->productRepository->find($productId);

        if (!$product) {
            $this->addFlash('error', 'Produit non trouvé');
            return $this->redirectToRoute('app_cart_index');
        }

        $this->cartService->removeFromCart($user, $product);

        $this->addFlash('success', 'Produit supprimé du panier!');

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/mettre-a-jour/{id}', name: 'app_cart_update', methods: ['POST'])]
    public function updateQuantity(string $id, Request $request): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $productId = filter_var($id, FILTER_VALIDATE_INT);
        
        if ($productId === false) {
            $this->addFlash('error', 'ID produit invalide');
            return $this->redirectToRoute('app_cart_index');
        }

        $product = $this->productRepository->find($productId);

        if (!$product) {
            $this->addFlash('error', 'Produit non trouvé');
            return $this->redirectToRoute('app_cart_index');
        }

        $quantity = $request->request->getInt('quantity', 1);

        if ($quantity < 1) {
            $quantity = 1;
        }

        $this->cartService->updateQuantity($user, $product, $quantity);

        $this->addFlash('success', 'Quantité mise à jour!');

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/vider', name: 'app_cart_clear', methods: ['POST'])]
    public function clear(): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $this->cartService->clearCart($user);

        $this->addFlash('success', 'Panier vidé!');

        return $this->redirectToRoute('app_cart_index');
    }
}
