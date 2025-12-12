<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\OrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class AccountController extends AbstractController
{
    #[Route('/mon-compte', name: 'app_account')]
    public function index(
        #[CurrentUser] User $user = null,
        OrderRepository $orderRepository
    ): Response {
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Get user's orders
        $orders = $orderRepository->findByUser($user);

        return $this->render('account/index.html.twig', [
            'user' => $user,
            'orders' => $orders 
        ]);
    }
}