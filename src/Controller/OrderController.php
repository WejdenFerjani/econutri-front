<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Repository\CartItemRepository;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class OrderController extends AbstractController
{
    #[Route('/commander', name: 'app_order_checkout')]
    public function checkout(CartItemRepository $cartItemRepository): Response
    {
        $user = $this->getUser();
        $cartItems = $cartItemRepository->findByUser($user);

        if (empty($cartItems)) {
            $this->addFlash('error', 'Votre panier est vide');
            return $this->redirectToRoute('app_cart_index');
        }

        $subtotal = 0;
        foreach ($cartItems as $item) {
            $subtotal += (float)$item->getProduct()->getPrice() * $item->getQuantity();
        }

        $shippingCost = 5.00;
        $total = $subtotal + $shippingCost;

        return $this->render('order/checkout.html.twig', [
            'cartItems' => $cartItems,
            'user' => $user,
            'subtotal' => number_format($subtotal, 2),
            'shippingCost' => number_format($shippingCost, 2),
            'total' => number_format($total, 2),
        ]);
    }

    #[Route('/commander/confirmer', name: 'app_order_confirm', methods: ['POST'])]
    public function confirm(
        Request $request,
        CartItemRepository $cartItemRepository,
        EntityManagerInterface $em,
        MailerInterface $mailer
    ): Response {
        $user = $this->getUser();
        $cartItems = $cartItemRepository->findByUser($user);

        if (empty($cartItems)) {
            $this->addFlash('error', 'Votre panier est vide');
            return $this->redirectToRoute('app_cart_index');
        }

        $subtotal = 0;
        foreach ($cartItems as $item) {
            $subtotal += (float)$item->getProduct()->getPrice() * $item->getQuantity();
        }

        $shippingCost = 5.00;

        // Créer une nouvelle commande
        $order = new Order();
        $order->setUser($user);
        $order->setTotalAmount((string)$subtotal);
        $order->setShippingCost((string)$shippingCost);
        $order->setStatus('pending');
        $order->setShippingAddress($request->request->get('address'));
        $order->setShippingCity($request->request->get('city'));
        $order->setShippingZipCode($request->request->get('zipcode'));

        // Ajouter les articles à la commande
        foreach ($cartItems as $cartItem) {
            $orderItem = new OrderItem();
            $orderItem->setProduct($cartItem->getProduct());
            $orderItem->setQuantity($cartItem->getQuantity());
            $orderItem->setUnitPrice($cartItem->getProduct()->getPrice());

            // Décrémenter le stock du produit
            $product = $cartItem->getProduct();
            $newStock = $product->getStock() - $cartItem->getQuantity();
            if ($newStock < 0) {
                $this->addFlash('error', 'Stock insuffisant pour ' . $product->getName());
                return $this->redirectToRoute('app_cart_index');
            }
            $product->setStock($newStock);
            $em->persist($product);

            $order->addOrderItem($orderItem);
        }

        $em->persist($order);
        $cartItemRepository->clearUserCart($user);
        $em->flush();

        // Envoyer email de confirmation de commande
        try {
            $userEmail = $user->getUserIdentifier();
            $email = (new TemplatedEmail())
                ->from(new Address('no-reply@econutri.tn', 'Econutri'))
                ->to(new Address($userEmail))
                ->subject('Confirmation de votre commande')
                ->htmlTemplate('emails/order_confirmation.html.twig')
                ->context([
                    'order' => $order,
                    'user' => $user,
                ]);
            $mailer->send($email);
            $this->addFlash('success', 'Commande créée avec succès. Email de confirmation envoyé.');
        } catch (\Throwable $e) {
            // Ne pas bloquer l'utilisateur si l'email échoue
            error_log('Erreur email: ' . $e->getMessage());
            $this->addFlash('success', 'Commande créée avec succès. Email: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_order_confirmation', ['id' => $order->getId()]);
    }

    #[Route('/commande/{id}/confirmation', name: 'app_order_confirmation')]
    public function confirmation(
        $id,
        OrderRepository $orderRepository
    ): Response {
        $order = $orderRepository->find($id);

        if (!$order || $order->getUser() !== $this->getUser()) {
            $this->addFlash('error', 'Commande non trouvée');
            return $this->redirectToRoute('app_home');
        }

        return $this->render('order/confirmation.html.twig', [
            'order' => $order,
        ]);
    }
// liste des commandes de l'utilisateurs 
    #[Route('/mes-commandes', name: 'app_my_orders')]
    public function myOrders(OrderRepository $orderRepository): Response
    {
        $user = $this->getUser();
        $orders = $orderRepository->findByUser($user);

        return $this->render('order/my-orders.html.twig', [
            'orders' => $orders,
        ]);
    }
     // détails d'une commande
    #[Route('/commande/{id}', name: 'app_order_details')]
    public function details(
        $id,
        OrderRepository $orderRepository
    ): Response {
        $order = $orderRepository->find($id);

        if (!$order || $order->getUser() !== $this->getUser()) {
            $this->addFlash('error', 'Commande non trouvée');
            return $this->redirectToRoute('app_home');
        }

        return $this->render('order/details.html.twig', [
            'order' => $order,
        ]);
    }
}