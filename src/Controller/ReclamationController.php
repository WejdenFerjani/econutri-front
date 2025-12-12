<?php

namespace App\Controller;

use App\Entity\Reclamation;
use App\Form\ReclamationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ReclamationController extends AbstractController
{
    #[Route('/reclamation', name: 'app_reclamation')]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $reclamation = new Reclamation();
        $form = $this->createForm(ReclamationType::class, $reclamation);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Enregistrer la réclamation
            $entityManager->persist($reclamation);
            $entityManager->flush();

            // Message de succès
            $this->addFlash('success', 'Votre réclamation a été envoyée avec succès ! Nous vous répondrons dans les plus brefs délais.');

            // Rediriger pour éviter la resoumission du formulaire
            return $this->redirectToRoute('app_reclamation');
        }

        return $this->render('reclamation/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/reclamation/success', name: 'app_reclamation_success')]
    public function success(): Response
    {
        return $this->render('reclamation/success.html.twig');
    }
}