<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request, 
        UserPasswordHasherInterface $userPasswordHasher, 
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        MailerInterface $mailer
    ): Response {
        // Si l'utilisateur est déjà connecté, rediriger vers l'accueil
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();
            
            // Vérifier si l'email existe déjà
            $existingUser = $userRepository->findOneBy(['email' => $email]);
            if ($existingUser) {
                $this->addFlash('error', 'Un compte avec cet email existe déjà.');
                return $this->render('registration/register.html.twig', [
                    'registrationForm' => $form->createView(),
                ]);
            }

            $plainPassword = $form->get('mot_de_passe')->getData();
            $confirmPassword = $form->get('confirmer_mot_de_passe')->getData();
            
            if ($plainPassword !== $confirmPassword) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
                return $this->render('registration/register.html.twig', [
                    'registrationForm' => $form->createView(),
                ]);
            }

            try {
                // Encoder le mot de passe
                $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));
                $entityManager->persist($user);
                $entityManager->flush();

                // Envoi de l'email (optionnel)
                $this->sendConfirmationEmail($user, $plainPassword, $mailer);

                $this->addFlash('success', 'Inscription effectuée avec succès ! Vous pouvez maintenant vous connecter.');

                return $this->redirectToRoute('app_login');
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de l\'inscription: '.$e->getMessage());
            }
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    private function sendConfirmationEmail(User $user, string $plainPassword, MailerInterface $mailer): void
    {
        try {
            $email = (new Email())
                ->from('ecofood678@gmail.com')
                ->to($user->getEmail())
                ->subject('🎉 Bienvenue sur Econutri - Inscription Confirmée !')
                ->html($this->renderView('emails/registration_confirmation.html.twig', [
                    'user' => $user,
                    'plainPassword' => $plainPassword
                ]));

            $mailer->send($email);
        } catch (\Exception $e) {
            // Log l'erreur mais ne bloque pas l'inscription
            // Vous pouvez logger cette erreur
        }
    }
}