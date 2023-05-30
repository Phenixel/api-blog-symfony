<?php
// src\DataFixtures\AppFixtures.php

namespace App\DataFixtures;

use App\Entity\Article;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Création d'une vingtaine d'articles ayant pour titre
        for ($i = 1; $i <= 20; $i++) {
            $article = new Article;
            $article->setTitle('Article N° ' . $i);
            $article->setDescription('Cette article est le numéro : ' . $i);
            $manager->persist($article);
        }

        $manager->flush();
    }
}