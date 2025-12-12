<?php

namespace App\Twig;

use App\Repository\CartItemRepository;
use App\Repository\CategoryRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function __construct(
        private Security $security,
        private CartItemRepository $cartItemRepository,
        private CategoryRepository $categoryRepository
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_cart_item_count', [$this, 'getCartItemCount']),
            new TwigFunction('get_categories', [$this, 'getCategories']),
        ];
    }

    public function getCartItemCount(): int
    {
        $user = $this->security->getUser();
        
        if (!$user) {
            return 0;
        }

        return $this->cartItemRepository->getCartItemCount($user);
    }

    public function getCategories(): array
    {
        return $this->categoryRepository->findAll();
    }
}