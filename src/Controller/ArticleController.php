<?php

namespace App\Controller;

use App\Repository\ArticleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

class ArticleController extends AbstractController
{
    #[Route('/api/articles', name: 'app_article', methods: ['GET'])]
    public function index(ArticleRepository $articleRepository, SerializerInterface $serializer): JsonResponse
    {
        $articleList= $articleRepository->findAll();
        $jsonarticleList = $serializer->serialize($articleList, 'json');

        return new JsonResponse($jsonarticleList, Response::HTTP_OK, [], true);
    }

    #[Route('/api/article/{id}', name: 'detailArticle', methods: ['GET'])]
    public function getDetailarticle(int $id, SerializerInterface $serializer, ArticleRepository $articleRepository): JsonResponse {

        $article = $articleRepository->find($id);
        if ($article) {
            $jsonArticle = $serializer->serialize($article, 'json');
            return new JsonResponse($jsonArticle, Response::HTTP_OK, [], true);
        }
        return new JsonResponse(null, Response::HTTP_NOT_FOUND);
    }
}
