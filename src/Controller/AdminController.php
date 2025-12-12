<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Repository\ProductRepository;
use App\Repository\OrderRepository;
use App\Repository\ReclamationRepository;
use App\Repository\OrderItemRepository;
use App\Repository\CategoryRepository;
use App\Entity\Product;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;

class AdminController extends AbstractController
{
    #[Route('/admin/dashboard', name: 'admin_dashboard')]
    public function dashboard(
        UserRepository $userRepo,
        ProductRepository $productRepo,
        OrderRepository $orderRepo,
        ReclamationRepository $reclamationRepo
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        // Récupérer les stats
        $totalUsers = $userRepo->count([]);
        $totalProducts = $productRepo->count([]);
        $totalOrders = $orderRepo->count([]);
        $totalReclamations = $reclamationRepo->count([]);
        
        // Produits avec stock faible (< 10)
        $lowStockProducts = $productRepo->createQueryBuilder('p')
            ->where('p.stock < 10')
            ->getQuery()
            ->getResult();
        
        // Dernières commandes (5 plus récentes)
        $recentOrders = $orderRepo->createQueryBuilder('o')
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
        
        // Réclamations récentes
        $recentReclamations = $reclamationRepo->createQueryBuilder('r')
            ->orderBy('r.dateCreation', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
        
        // Tous les produits pour les graphiques
        $allProducts = $productRepo->findAll();
        
        // Préparer les données pour le graphique des quantités
        $productNames = [];
        $productStocks = [];
        $productSales = [];
        
        foreach ($allProducts as $product) {
            $productNames[] = $product->getName();
            $productStocks[] = $product->getStock();
            
            // Calculer le nombre total vendu pour ce produit
            $totalSold = $orderRepo->createQueryBuilder('o')
                ->select('SUM(oi.quantity) as total')
                ->leftJoin('o.orderItems', 'oi')
                ->where('oi.product = :product')
                ->setParameter('product', $product)
                ->getQuery()
                ->getSingleScalarResult();
            
            $productSales[] = $totalSold ?? 0;
        }
        
        // Préparer les données pour le graphique des ventes des 7 derniers jours
        $salesData = [];
        $labels = [];
        $today = new \DateTime();
        
        for ($i = 6; $i >= 0; $i--) {
            $date = (clone $today)->modify("-{$i} days");
            $dateStr = $date->format('Y-m-d');
            $labels[] = $date->format('D'); // Lun, Mar, Mer, etc.
            
            // Calculer les ventes pour ce jour
            $dayStart = (clone $date)->setTime(0, 0, 0);
            $dayEnd = (clone $date)->setTime(23, 59, 59);
            
            $ordersForDay = $orderRepo->createQueryBuilder('o')
                ->where('o.createdAt >= :start AND o.createdAt <= :end')
                ->setParameter('start', $dayStart)
                ->setParameter('end', $dayEnd)
                ->getQuery()
                ->getResult();
            
            $totalSalesDay = 0;
            foreach ($ordersForDay as $order) {
                foreach ($order->getOrderItems() as $item) {
                    $totalSalesDay += ($item->getProduct()->getPrice() / 1000) * $item->getQuantity();
                }
            }
            
            $salesData[] = round($totalSalesDay, 2);
        }
        
        return $this->render('admin/dashboard.html.twig', [
            'totalUsers' => $totalUsers,
            'totalProducts' => $totalProducts,
            'totalOrders' => $totalOrders,
            'totalReclamations' => $totalReclamations,
            'lowStockProducts' => $lowStockProducts,
            'recentOrders' => $recentOrders,
            'recentReclamations' => $recentReclamations,
            'allProducts' => $allProducts,
            'productNames' => $productNames,
            'productStocks' => $productStocks,
            'productSales' => $productSales,
            'salesLabels' => $labels,
            'salesData' => $salesData,
        ]);
    }

    #[Route('/admin/objectifs', name: 'admin_objectifs')]
    public function objectifs(
        OrderRepository $orderRepo,
        ProductRepository $productRepo
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $now = new \DateTime();
        $start30 = (clone $now)->modify('-30 days');
        $start7 = (clone $now)->modify('-7 days');

        // Ventes et commandes des 30 derniers jours
        $orders30 = $orderRepo->createQueryBuilder('o')
            ->where('o.createdAt >= :start')
            ->setParameter('start', $start30)
            ->getQuery()
            ->getResult();

        $totalSales30 = 0;
        foreach ($orders30 as $order) {
            $totalSales30 += $order->getTotalAmount();
        }
        $ordersCount30 = count($orders30);
        $avgOrderValue = $ordersCount30 > 0 ? round($totalSales30 / $ordersCount30, 2) : 0;

        // Top produits (quantité vendue) sur 30 jours
        $qb = $orderRepo->createQueryBuilder('o')
            ->select('p.id as id, p.name as name, p.image as image, SUM(oi.quantity) as qty')
            ->join('o.orderItems', 'oi')
            ->join('oi.product', 'p')
            ->where('o.createdAt >= :start')
            ->setParameter('start', $start30)
            ->groupBy('p.id')
            ->orderBy('qty', 'DESC')
            ->setMaxResults(5);
        $topProducts = $qb->getQuery()->getArrayResult();

        // Ventes 7 jours par produit pour estimer la couverture de stock
        $sales7ByProduct = [];
        $orders7 = $orderRepo->createQueryBuilder('o')
            ->where('o.createdAt >= :start7')
            ->setParameter('start7', $start7)
            ->getQuery()
            ->getResult();

        foreach ($orders7 as $order) {
            foreach ($order->getOrderItems() as $item) {
                $pid = $item->getProduct()->getId();
                $sales7ByProduct[$pid] = ($sales7ByProduct[$pid] ?? 0) + $item->getQuantity();
            }
        }

        $coverage = [];
        $products = $productRepo->findAll();
        foreach ($products as $product) {
            $pid = $product->getId();
            $sold7 = $sales7ByProduct[$pid] ?? 0;
            $salesPerDay = $sold7 / 7;
            $days = $salesPerDay > 0 ? round($product->getStock() / $salesPerDay, 1) : null; // null = pas de conso
            $coverage[] = [
                'name' => $product->getName(),
                'stock' => $product->getStock(),
                'sold7' => $sold7,
                'coverageDays' => $days,
            ];
        }

        return $this->render('admin/objectifs.html.twig', [
            'totalSales30' => round($totalSales30, 2),
            'ordersCount30' => $ordersCount30,
            'avgOrderValue' => $avgOrderValue,
            'topProducts' => $topProducts,
            'coverage' => $coverage,
        ]);
    }
    
    #[Route('/admin/export-objectifs', name: 'admin_export_objectifs')]
    public function exportObjectifs(OrderRepository $orderRepo, ProductRepository $productRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        // Calculer les KPIs des 30 derniers jours
        $date30DaysAgo = new \DateTime('-30 days');
        $orders30 = $orderRepo->createQueryBuilder('o')
            ->where('o.createdAt >= :date')
            ->setParameter('date', $date30DaysAgo)
            ->getQuery()
            ->getResult();
        
        $totalSales30 = 0;
        $productSales = [];
        
        foreach ($orders30 as $order) {
            $totalSales30 += $order->getTotalAmount();
            foreach ($order->getOrderItems() as $item) {
                $productId = $item->getProduct()->getId();
                if (!isset($productSales[$productId])) {
                    $productSales[$productId] = [
                        'name' => $item->getProduct()->getName(),
                        'qty' => 0,
                        'revenue' => 0
                    ];
                }
                $productSales[$productId]['qty'] += $item->getQuantity();
                $productSales[$productId]['revenue'] += $item->getQuantity() * $item->getUnitPrice();
            }
        }
        
        // Trier par quantité
        uasort($productSales, fn($a, $b) => $b['qty'] <=> $a['qty']);
        
        $ordersCount30 = count($orders30);
        $avgOrderValue = $ordersCount30 > 0 ? round($totalSales30 / $ordersCount30, 2) : 0;
        
        // Créer le CSV
        $csv = "Rapport KPI - EcoNutri - " . date('d/m/Y') . "\n\n";
        $csv .= "=== INDICATEURS GLOBAUX (30 JOURS) ===\n";
        $csv .= "Ventes totales;{$totalSales30} TND\n";
        $csv .= "Nombre de commandes;{$ordersCount30}\n";
        $csv .= "Panier moyen;{$avgOrderValue} TND\n\n";
        
        $csv .= "=== TOP PRODUITS (30 JOURS) ===\n";
        $csv .= "Produit;Quantité vendue;Chiffre d'affaires\n";
        foreach ($productSales as $product) {
            $csv .= "\"{$product['name']}\";{$product['qty']};{$product['revenue']} TND\n";
        }
        
        // Produits à réassort
        $date7DaysAgo = new \DateTime('-7 days');
        $orders7 = $orderRepo->createQueryBuilder('o')
            ->where('o.createdAt >= :date')
            ->setParameter('date', $date7DaysAgo)
            ->getQuery()
            ->getResult();
        
        $sold7ByProduct = [];
        foreach ($orders7 as $order) {
            foreach ($order->getOrderItems() as $item) {
                $productId = $item->getProduct()->getId();
                if (!isset($sold7ByProduct[$productId])) {
                    $sold7ByProduct[$productId] = 0;
                }
                $sold7ByProduct[$productId] += $item->getQuantity();
            }
        }
        
        $csv .= "\n=== PRODUITS À RÉASSORT ===\n";
        $csv .= "Produit;Stock actuel;Ventes 7j;Statut\n";
        
        $products = $productRepo->findAll();
        foreach ($products as $product) {
            $stock = $product->getStock();
            $sold7 = $sold7ByProduct[$product->getId()] ?? 0;
            $coverageDays = $sold7 > 0 ? floor(($stock / $sold7) * 7) : null;
            
            if ($stock < 10 || ($sold7 > 0 && $coverageDays !== null && $coverageDays < 14)) {
                $status = $stock < 10 ? 'CRITIQUE' : 'FAIBLE';
                $csv .= "\"{$product->getName()}\";{$stock};{$sold7};{$status}\n";
            }
        }
        
        // Retourner le CSV
        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="objectifs-econutri-'.date('Y-m-d').'.csv"');
        
        return $response;
    }
    
    #[Route('/admin/users', name: 'admin_users')]
    public function users(UserRepository $userRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $users = $userRepo->findAll();
        
        return $this->render('admin/users.html.twig', [
            'users' => $users,
        ]);
    }
    
    #[Route('/admin/products', name: 'admin_products')]
    public function products(ProductRepository $productRepo, CategoryRepository $categoryRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $products = $productRepo->findAll();
        $categories = $categoryRepo->findAll();
        
        return $this->render('admin/products.html.twig', [
            'products' => $products,
            'categories' => $categories,
        ]);
    }
    
    #[Route('/admin/add-product', name: 'admin_add_product', methods: ['POST'])]
    public function addProduct(
        Request $request, 
        EntityManagerInterface $em,
        CategoryRepository $categoryRepo
    ): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        try {
            // Récupérer les données du formulaire
            $name = $request->request->get('name');
            $description = $request->request->get('description');
            $price = $request->request->get('price');
            $stock = $request->request->get('stock');
            $categoryId = $request->request->get('category_id');
            $origin = $request->request->get('origin', 'Tunisie');
            $unit = $request->request->get('unit', 'kg');
            
            // Validation
            if (!$name || !$price || !$stock || !$categoryId) {
                return new JsonResponse(['success' => false, 'message' => 'Données manquantes'], 400);
            }
            
            // Récupérer la catégorie
            $category = $categoryRepo->find($categoryId);
            if (!$category) {
                return new JsonResponse(['success' => false, 'message' => 'Catégorie invalide'], 400);
            }
            
            // Gérer l'upload d'image
            $imageName = 'default.jpg';
            $imageFile = $request->files->get('image');
            
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $originalExtension = pathinfo($imageFile->getClientOriginalName(), PATHINFO_EXTENSION);
                
                // Nettoyer le nom de fichier (remplacer les caractères spéciaux)
                $safeFilename = preg_replace('/[^a-z0-9]+/', '-', strtolower($originalFilename));
                $safeFilename = trim($safeFilename, '-');
                
                // Utiliser l'extension du fichier original au lieu de guessExtension()
                $extension = !empty($originalExtension) ? strtolower($originalExtension) : 'jpg';
                $newFilename = $safeFilename.'-'.uniqid().'.'.$extension;
                
                try {
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/images',
                        $newFilename
                    );
                    $imageName = $newFilename;
                } catch (\Exception $e) {
                    return new JsonResponse(['success' => false, 'message' => 'Erreur upload image: ' . $e->getMessage()], 500);
                }
            }
            
            // Créer le produit
            $product = new Product();
            $product->setName($name);
            $product->setDescription($description ?: '');
            $product->setPrice($price);
            $product->setStock((int)$stock);
            $product->setCategory($category);
            $product->setImage($imageName);
            $product->setOrigin($origin);
            $product->setUnit($unit);
            $product->setCreatedAt(new \DateTimeImmutable());
            
            // Générer le slug
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
            $product->setSlug($slug);
            
            // Sauvegarder
            $em->persist($product);
            $em->flush();
            
            return new JsonResponse([
                'success' => true, 
                'message' => 'Produit ajouté avec succès',
                'product' => [
                    'id' => $product->getId(),
                    'name' => $product->getName(),
                    'image' => $product->getImage()
                ]
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }
    
    #[Route('/admin/stocks', name: 'admin_stocks')]
    public function stocks(ProductRepository $productRepo, OrderRepository $orderRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $products = $productRepo->findAll();
        
        // Calculer le nombre vendu par produit
        $allOrders = $orderRepo->findAll();
        $salesByProduct = [];
        $productNames = [];
        
        foreach ($products as $product) {
            $salesByProduct[$product->getId()] = 0;
            $productNames[$product->getId()] = $product->getName();
        }
        
        foreach ($allOrders as $order) {
            foreach ($order->getOrderItems() as $orderItem) {
                $productId = $orderItem->getProduct()->getId();
                if (isset($salesByProduct[$productId])) {
                    $salesByProduct[$productId] += $orderItem->getQuantity();
                }
            }
        }
        
        // Trier par ID pour maintenir la cohérence
        ksort($salesByProduct);
        ksort($productNames);
        
        return $this->render('admin/stocks.html.twig', [
            'products' => $products,
            'salesByProduct' => $salesByProduct,
            'productNames' => $productNames,
        ]);
    }
    
    #[Route('/admin/orders', name: 'admin_orders')]
    public function orders(OrderRepository $orderRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $orders = $orderRepo->findAll();
        
        return $this->render('admin/orders.html.twig', [
            'orders' => $orders,
        ]);
    }
    
    #[Route('/admin/reclamations', name: 'admin_reclamations')]
    public function reclamations(ReclamationRepository $reclamationRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $reclamations = $reclamationRepo->findAll();
        
        return $this->render('admin/reclamations.html.twig', [
            'reclamations' => $reclamations,
        ]);
    }
    
    #[Route('/admin/reclamations/reply', name: 'admin_reply_reclamation', methods: ['POST'])]
    public function replyReclamation(Request $request, ReclamationRepository $reclamationRepo, \Symfony\Component\Mailer\MailerInterface $mailer): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $reclamationId = $request->request->get('reclamationId');
        $subject = $request->request->get('subject');
        $message = $request->request->get('message');
        
        $reclamation = $reclamationRepo->find($reclamationId);
        
        if (!$reclamation) {
            $this->addFlash('error', 'Réclamation non trouvée.');
            return $this->redirectToRoute('admin_reclamations');
        }
        
        try {
            $email = (new \Symfony\Component\Mime\Email())
                ->from('ecofood678@gmail.com')
                ->to($reclamation->getEmail())
                ->subject($subject)
                ->html(
                    '<html><body style="font-family: Arial, sans-serif; padding: 20px; background-color: #f0fdf4;">' .
                    '<div style="max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(6, 78, 29, 0.1);">' .
                    '<h2 style="color: #166534; border-bottom: 2px solid #22c55e; padding-bottom: 10px;">Réponse à votre réclamation - Econutri</h2>' .
                    '<p style="color: #374151;">Bonjour <strong>' . htmlspecialchars($reclamation->getNom()) . '</strong>,</p>' .
                    '<p style="color: #374151;">Nous avons bien reçu votre réclamation concernant : <strong>' . htmlspecialchars($reclamation->getSujet()) . '</strong></p>' .
                    '<div style="background: #f0fdf4; padding: 20px; border-left: 4px solid #22c55e; margin: 20px 0;">' .
                    '<p style="color: #166534; margin: 0;">' . nl2br(htmlspecialchars($message)) . '</p>' .
                    '</div>' .
                    '<p style="color: #374151;">Cordialement,<br><strong>L\'équipe Econutri</strong></p>' .
                    '<hr style="border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;">' .
                    '<p style="color: #6b7280; font-size: 12px;">© ' . date('Y') . ' Econutri - Votre partenaire nutrition</p>' .
                    '</div></body></html>'
                );
            
            $mailer->send($email);
            
            // Mettre à jour le statut de la réclamation
            $reclamation->setStatut('resolu');
            $reclamationRepo->save($reclamation, true);
            
            $this->addFlash('success', 'Réponse envoyée avec succès à ' . $reclamation->getEmail());
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'envoi de l\'email : ' . $e->getMessage());
        }
        
        return $this->redirectToRoute('admin_reclamations');
    }
    
    #[Route('/admin/profile', name: 'admin_profile')]
    public function profile(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        return $this->render('admin/profile.html.twig');
    }
    
    #[Route('/admin/update-product', name: 'admin_update_product', methods: ['POST'])]
    public function updateProduct(
        Request $request,
        ProductRepository $productRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $data = json_decode($request->getContent(), true);
        $productId = $data['productId'] ?? null;
        
        if (!$productId) {
            return new JsonResponse(['success' => false, 'message' => 'Produit non trouvé'], 404);
        }
        
        $product = $productRepo->find($productId);
        if (!$product) {
            return new JsonResponse(['success' => false, 'message' => 'Produit non trouvé'], 404);
        }
        
        if (isset($data['name'])) $product->setName($data['name']);
        if (isset($data['description'])) $product->setDescription($data['description']);
        if (isset($data['price'])) $product->setPrice($data['price']);
        if (isset($data['stock'])) $product->setStock((int)$data['stock']);
        
        $em->persist($product);
        $em->flush();
        
        return new JsonResponse(['success' => true, 'message' => 'Produit mis à jour']);
    }
    
    #[Route('/admin/delete-product', name: 'admin_delete_product', methods: ['POST'])]
    public function deleteProduct(
        Request $request,
        ProductRepository $productRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $data = json_decode($request->getContent(), true);
        $productId = $data['productId'] ?? null;
        
        if (!$productId) {
            return new JsonResponse(['success' => false, 'message' => 'Produit non trouvé'], 404);
        }
        
        $product = $productRepo->find($productId);
        if (!$product) {
            return new JsonResponse(['success' => false, 'message' => 'Produit non trouvé'], 404);
        }
        
        $em->remove($product);
        $em->flush();
        
        return new JsonResponse(['success' => true, 'message' => 'Produit supprimé']);
    }
    
    #[Route('/admin/update-stock', name: 'admin_update_stock', methods: ['POST'])]
    public function updateStock(
        Request $request,
        ProductRepository $productRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $data = json_decode($request->getContent(), true);
        $productId = $data['productId'] ?? null;
        $newStock = $data['newStock'] ?? null;
        
        if (!$productId || $newStock === null) {
            return new JsonResponse(['success' => false, 'message' => 'Données invalides'], 400);
        }
        
        $product = $productRepo->find($productId);
        if (!$product) {
            return new JsonResponse(['success' => false, 'message' => 'Produit non trouvé'], 404);
        }
        
        $product->setStock((int)$newStock);
        $em->persist($product);
        $em->flush();
        
        return new JsonResponse(['success' => true, 'message' => 'Stock mis à jour']);
    }

    #[Route('/admin/alerts', name: 'admin_alerts')]
    public function alerts(ProductRepository $productRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        // Produits avec stock < 10
        $lowStockProducts = $productRepo->createQueryBuilder('p')
            ->where('p.stock < 10')
            ->orderBy('p.stock', 'ASC')
            ->getQuery()
            ->getResult();
        
        // Calculer le stock critique (< 5) et le total
        $criticalCount = 0;
        $totalStock = 0;
        
        foreach ($lowStockProducts as $product) {
            if ($product->getStock() < 5) {
                $criticalCount++;
            }
            $totalStock += $product->getStock();
        }
        
        return $this->render('admin/alerts.html.twig', [
            'lowStockProducts' => $lowStockProducts,
            'alertCount' => count($lowStockProducts),
            'criticalCount' => $criticalCount,
            'totalStock' => $totalStock,
        ]);
    }
}