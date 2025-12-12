<?php

namespace App\EventListener;

use App\Entity\User;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: LoginSuccessEvent::class)]
class AuthenticationSuccessListener
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        
        // Vérifier si l'utilisateur a le rôle ADMIN (role = 1)
        if ($user instanceof User && $user->isAdmin()) {
            $response = new \Symfony\Component\HttpFoundation\RedirectResponse(
                $this->urlGenerator->generate('admin_dashboard')
            );
            $event->setResponse($response);
        }
        // Pour les utilisateurs normaux (role = 0), la redirection par défaut vers app_home sera utilisée
    }
}