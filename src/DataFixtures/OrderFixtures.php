<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class OrderFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // Noms tunisiens
        $premiersTunisiens = [
            'Ahmed', 'Ali', 'Mohamed', 'Karim', 'Habib', 'Riadh', 'Salim', 'Tarek', 
            'Wassim', 'Adel', 'Jaouad', 'Nabil', 'Hichem', 'Sami', 'Khaled'
        ];

        $premiersFemellesTunisiennes = [
            'Yasmine', 'Leila', 'Fatima', 'Mariem', 'Nadia', 'Amira', 'Sophia', 'Zina',
            'Hana', 'Sara', 'Ines', 'Dina', 'Salma', 'Layla', 'Rihab'
        ];

        $nomsFamiliauxTunisiens = [
            'Ben Ali', 'Trabelsi', 'Ghazi', 'Bouabdallah', 'Makni', 'Akermi', 'Chabane',
            'Hadji', 'Romain', 'Youssef', 'Hamrouni', 'Lassoued', 'Chahed', 'Gafsi', 'Souissi'
        ];

        // Créer des utilisateurs de test
        $users = [];
        for ($i = 0; $i < 10; $i++) {
            $user = new User();
            $isFemale = rand(0, 1);
            
            if ($isFemale) {
                $prenom = $premiersFemellesTunisiennes[array_rand($premiersFemellesTunisiennes)];
            } else {
                $prenom = $premiersTunisiens[array_rand($premiersTunisiens)];
            }
            
            $nom = $nomsFamiliauxTunisiens[array_rand($nomsFamiliauxTunisiens)];
            
            $user->setPrenom($prenom);
            $user->setNom($nom);
            $user->setEmail(strtolower($prenom . '.' . $nom . '@gmail.com'));
            $user->setPassword(password_hash('password123', PASSWORD_BCRYPT));
            $user->setTelephone('+216 ' . rand(20000000, 99999999));
            $user->setAdresse('Rue ' . rand(1, 50) . ', Tunis');
            $user->setObjectif('Bien-être');
            $user->setTaille(rand(155, 195));
            $user->setPoids(rand(50, 100));
            $user->setRole(0); // ROLE_USER
            
            $manager->persist($user);
            $users[] = $user;
        }
        
        $manager->flush();

        // Récupérer les produits existants
        $products = $manager->getRepository(Product::class)->findAll();
        
        if (empty($products)) {
            return; // Pas de produits, on ne peut pas créer de commandes
        }

        // Créer des commandes pour les 7 derniers jours
        $today = new \DateTime();
        $orderCount = 0;

        for ($dayOffset = 6; $dayOffset >= 0; $dayOffset--) {
            $orderDate = (clone $today)->modify("-{$dayOffset} days");
            $orderDate->setTime(rand(9, 20), rand(0, 59), 0);
            
            // 2-4 commandes par jour
            $ordersPerDay = rand(2, 4);
            
            for ($j = 0; $j < $ordersPerDay; $j++) {
                $order = new Order();
                $order->setUser($users[array_rand($users)]);
                $order->setCreatedAt($orderDate);
                
                // Ajouter 2-5 produits par commande
                $numItems = rand(2, 5);
                $selectedProducts = array_rand($products, min($numItems, count($products)));
                
                if (!is_array($selectedProducts)) {
                    $selectedProducts = [$selectedProducts];
                }
                
                $totalAmount = 0;
                
                foreach ($selectedProducts as $productIndex) {
                    $product = $products[$productIndex];
                    $quantity = rand(1, 3);
                    $unitPrice = $product->getPrice() / 1000; // Convertir en TND
                    
                    $orderItem = new OrderItem();
                    $orderItem->setOrder($order);
                    $orderItem->setProduct($product);
                    $orderItem->setQuantity($quantity);
                    $orderItem->setUnitPrice($unitPrice);
                    
                    $totalAmount += $unitPrice * $quantity;
                    
                    $order->addOrderItem($orderItem);
                    $manager->persist($orderItem);
                }
                
                $order->setTotalAmount(round($totalAmount, 2));
                $manager->persist($order);
                $orderCount++;
            }
        }
        
        $manager->flush();
        
        echo "\n✅ {$orderCount} commandes de test créées avec succès!\n";
        echo "📊 Les données vont du jour -6 jusqu'à aujourd'hui\n";
        echo "👥 10 utilisateurs tunisiens créés\n";
    }

    public function getDependencies(): array
    {
        return [AppFixtures::class];
    }
}
