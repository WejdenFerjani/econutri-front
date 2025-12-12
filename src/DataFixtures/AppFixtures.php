<?php
// src/DataFixtures/AppFixtures.php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Création des catégories
        $categories = [
            'Légumes' => ['desc' => 'Nos légumes bio frais et locaux', 'slug' => 'legumes', 'image' => 'legumes.jpg'],
            'Fruits' => ['desc' => 'Fruits biologiques de saison', 'slug' => 'fruits', 'image' => 'fruits.jpg'],
            'Laitiers' => ['desc' => 'Produits laitiers bio', 'slug' => 'laitiers', 'image' => 'laitiers.jpg'],
            'Grains' => ['desc' => 'Grains et céréales biologiques', 'slug' => 'grains', 'image' => 'grains.jpg']
        ];

        $categoryObjects = [];
        
        foreach ($categories as $name => $info) {
            $category = new Category();
            $category->setName($name);
            $category->setDescription($info['desc']);
            $category->setImage($info['image']);
            $category->setSlug($info['slug']);
            $manager->persist($category);
            $categoryObjects[$name] = $category;
        }

        // Création de produits
        $products = [
            ['Tomates Bio', 'Tomates rouges biologiques fraîches', 4.50, 'tomates.jpg', 50, 'Légumes'],
            ['Carottes Bio', 'Carottes fraîches biologiques', 3.20, 'carottes.jpg', 30, 'Légumes'],
            ['Pommes Golden', 'Pommes golden biologiques', 2.80, 'pommes.jpg', 40, 'Fruits'],
            ['Lait Bio', 'Lait entier biologique 1L', 1.50, 'lait.jpg', 20, 'Laitiers'],
            ['Riz Complet Bio', 'Riz complet biologique 1kg', 5.00, 'riz.jpg', 25, 'Grains'],
            ['Bananes Bio', 'Bananes biologiques', 3.50, 'bananes.jpg', 35, 'Fruits'],
            ['Fromage Bio', 'Fromage de chèvre biologique', 6.80, 'fromage.jpg', 15, 'Laitiers'],
            ['Concombres Bio', 'Concombres frais biologiques', 2.20, 'concombres.jpg', 25, 'Légumes'],
            ['Poivrons Bio', 'Poivrons rouges et jaunes biologiques', 3.80, 'poivrons.jpg', 20, 'Légumes'],
            ['Salade Verte', 'Salade verte biologique croquante', 1.80, 'salade.jpg', 40, 'Légumes'],
            ['Oignons Bio', 'Oignons jaunes biologiques', 2.50, 'oignons.jpg', 60, 'Légumes'],
            ['Ail Bio', 'Ail frais biologique', 4.00, 'ail.jpg', 45, 'Légumes'],
            ['Oranges Bio', 'Oranges juteuses biologiques', 3.20, 'oranges.jpg', 30, 'Fruits'],
            ['Fraises Bio', 'Fraises sucrées biologiques', 5.50, 'fraises.jpg', 15, 'Fruits'],
            ['Raisins Bio', 'Raisins noirs biologiques', 4.80, 'raisins.jpg', 20, 'Fruits'],
            ['Poires Bio', 'Poires Williams biologiques', 3.40, 'poires.jpg', 25, 'Fruits'],
            ['Pêches Bio', 'Pêches juteuses biologiques', 4.20, 'peches.jpg', 18, 'Fruits'],
            ['Yaourt Nature Bio', 'Yaourt nature biologique', 0.80, 'yaourt.jpg', 50, 'Laitiers'],
            ['Beurre Bio', 'Beurre doux biologique', 3.50, 'beurre.jpg', 30, 'Laitiers'],
            ['Crème Fraîche Bio', 'Crème fraîche épaisse biologique', 2.20, 'creme.jpg', 25, 'Laitiers'],
            ['Fromage Blanc Bio', 'Fromage blanc biologique', 2.00, 'fromageblanc.jpg', 35, 'Laitiers'],
            ['Quinoa Bio', 'Quinoa biologique 500g', 4.50, 'quinoa.jpg', 20, 'Grains'],
            ['Lentilles Vertes Bio', 'Lentilles vertes biologiques', 3.80, 'lentilles.jpg', 30, 'Grains'],
            ['Pâtes Complètes Bio', 'Pâtes complètes biologiques', 2.50, 'pates.jpg', 40, 'Grains'],
            ['Flocons d\'Avoine Bio', 'Flocons d\'avoine biologiques', 3.20, 'avoine.jpg', 35, 'Grains'],
            ['Pois Chiches Bio', 'Pois chiches biologiques', 2.80, 'poischiches.jpg', 25, 'Grains'],
            ['Amandes Bio', 'Amandes biologiques', 7.50, 'amandes.jpg', 20, 'Grains'],
            ['Noix Bio', 'Noix biologiques', 6.80, 'noix.jpg', 18, 'Grains'],
            ['Miel Bio', 'Miel toutes fleurs biologique', 8.00, 'miel.jpg', 15, 'Grains'],
            ['Chocolat Noir Bio', 'Chocolat noir 70% biologique', 4.20, 'chocolat.jpg', 30, 'Grains'],
        ];

        foreach ($products as $productData) {
            $product = new Product();
            $product->setName($productData[0]);
            $product->setDescription($productData[1]);
            $product->setPrice($productData[2]);
            $product->setImage($productData[3]);
            $product->setStock($productData[4]);
            $product->setCategory($categoryObjects[$productData[5]]);
            $manager->persist($product);
        }

        $manager->flush();
    }
}