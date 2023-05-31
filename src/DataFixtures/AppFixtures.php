<?php
// src\DataFixtures\AppFixtures.php

namespace App\DataFixtures;

use App\Entity\Article;
use App\Entity\Author;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $listAuthor = [];
        for ($i = 0; $i < 10; $i++) {
            // Création de l'auteur lui-même.
            $author = new Author();
            $author->setFirstName("Prénom " . $i);
            $author->setLastName("Nom " . $i);
            $manager->persist($author);
            // On sauvegarde l'auteur créé dans un tableau.
            $listAuthor[] = $author;
        }

        // Création d'une vingtaine d'articles ayant pour titre
        for ($i = 1; $i <= 20; $i++) {
            $article = new Article;
            $article->setTitle('Article N° ' . $i);
            $article->setDescription('Cette article est le numéro : ' . $i);

            $article->setAuthor($listAuthor[array_rand($listAuthor)]);
            $manager->persist($article);
        }

        $manager->flush();
    }
}