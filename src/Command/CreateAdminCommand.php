<?php
// src/Command/CreateAdminCommand.php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CreateAdminCommand extends Command
{
    protected static $defaultName = 'app:create-admin';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Crée un utilisateur administrateur')
            ->setHelp('Cette commande permet de créer un utilisateur avec le rôle administrateur');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = 'ecofood678@gmail.com';
        $password = 'admin123'; // Changez ce mot de passe

        // Vérifier si l'admin existe déjà
        $existingAdmin = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        
        if ($existingAdmin) {
            // Mettre à jour l'admin existant
            $existingAdmin->setPassword($this->passwordHasher->hashPassword($existingAdmin, $password));
            $existingAdmin->setRole(1);
            $existingAdmin->setNom('Admin');
            $existingAdmin->setPrenom('Econutri');
            
            $io->success('Admin existant mis à jour avec le nouveau mot de passe!');
        } else {
            // Créer un nouvel admin
            $admin = new User();
            $admin->setEmail($email);
            $admin->setPassword($this->passwordHasher->hashPassword($admin, $password));
            $admin->setNom('Admin');
            $admin->setPrenom('Econutri');
            $admin->setRole(1); // ← C'EST ICI QU'ON DÉFINIT LE RÔLE ADMIN

            $this->entityManager->persist($admin);
            $io->success('Nouvel admin créé!');
        }

        $this->entityManager->flush();

        $io->note([
            'Email: ' . $email,
            'Mot de passe: ' . $password,
            'Rôle: ADMIN'
        ]);

        return Command::SUCCESS;
    }
}