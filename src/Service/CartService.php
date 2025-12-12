<?php

namespace App\Service;

use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\CartItemRepository;
use Doctrine\ORM\EntityManagerInterface;

class CartService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CartItemRepository $cartItemRepository
    ) {}

    public function addToCart(User $user, Product $product, int $quantity = 1): CartItem
    {
        $cartItem = $this->cartItemRepository->findOneByUserAndProduct($user, $product);

        if ($cartItem) {
            $cartItem->setQuantity($cartItem->getQuantity() + $quantity);
        } else {
            $cartItem = new CartItem();
            $cartItem->setUser($user);
            $cartItem->setProduct($product);
            $cartItem->setQuantity($quantity);
            $this->entityManager->persist($cartItem);
        }

        $this->entityManager->flush();

        return $cartItem;
    }

    public function removeFromCart(User $user, Product $product): void
    {
        $cartItem = $this->cartItemRepository->findOneByUserAndProduct($user, $product);

        if ($cartItem) {
            $this->entityManager->remove($cartItem);
            $this->entityManager->flush();
        }
    }

    public function updateQuantity(User $user, Product $product, int $quantity): CartItem
    {
        $cartItem = $this->cartItemRepository->findOneByUserAndProduct($user, $product);

        if ($cartItem) {
            if ($quantity <= 0) {
                $this->removeFromCart($user, $product);
            } else {
                $cartItem->setQuantity($quantity);
                $this->entityManager->flush();
            }
        }

        return $cartItem;
    }

    public function clearCart(User $user): void
    {
        $cartItems = $this->cartItemRepository->findByUser($user);

        foreach ($cartItems as $cartItem) {
            $this->entityManager->remove($cartItem);
        }

        $this->entityManager->flush();
    }

    public function getCartItems(User $user)
    {
        return $this->cartItemRepository->findByUser($user);
    }

    public function getCartTotal(User $user): float 
    {
        return $this->cartItemRepository->getCartTotal($user);
    }

    public function getCartCount(User $user): int
    {
        return $this->cartItemRepository->countCartItems($user);
    }
}
