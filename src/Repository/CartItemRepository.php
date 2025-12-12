<?php

namespace App\Repository;

use App\Entity\CartItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CartItem>
 */
class CartItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CartItem::class);
    }

    /**
     * Récupère tous les articles du panier pour un utilisateur
     */
    public function findByUser($user): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.user = :user')
            ->setParameter('user', $user)
            ->orderBy('c.addedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un produit est déjà dans le panier
     */
    public function findOneByUserAndProduct($user, $product): ?CartItem
    {
        return $this->createQueryBuilder('c')
            ->where('c.user = :user')
            ->andWhere('c.product = :product')
            ->setParameter('user', $user)
            ->setParameter('product', $product)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Vide le panier d'un utilisateur
     */
    public function clearUserCart($user): void
    {
        $this->createQueryBuilder('c')
            ->delete()
            ->where('c.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    /**
     * Compte le nombre total d'articles dans le panier d'un utilisateur
     */
    public function getCartItemCount($user): int
    {
        $result = $this->createQueryBuilder('c')
            ->select('SUM(c.quantity) as total')
            ->where('c.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }
    /**
 * Count cart items for a user (alias for getCartItemCount)
 */
public function countCartItems($user): int
{
    return $this->getCartItemCount($user);
}
/**
     * Calcule le total du panier pour un utilisateur
     */
    public function getCartTotal($user): float
{
    $cartItems = $this->findByUser($user);
    $total = 0;
    
    foreach ($cartItems as $cartItem) {
        $product = $cartItem->getProduct();
        $total += $cartItem->getQuantity() * (float) $product->getPrice();
    }
    
    return $total;
}

}