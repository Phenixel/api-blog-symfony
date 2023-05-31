<?php

namespace App\Controller;

use App\Entity\Article;
use App\Repository\ArticleRepository;
use App\Repository\AuthorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ArticleController extends AbstractController
{
    #[Route('/api/articles', name: 'app_article', methods: ['GET'])]
    public function index(ArticleRepository $articleRepository, SerializerInterface $serializer): JsonResponse
    {
        $articleList= $articleRepository->findAll();
        $jsonarticleList = $serializer->serialize($articleList, 'json', ['groups' => 'getArticles']);

        return new JsonResponse($jsonarticleList, Response::HTTP_OK, [], true);
    }

    #[Route('/api/article/{id}', name: 'detailArticle', methods: ['GET'])]
    public function getDetailArticle(Article $article, SerializerInterface $serializer): JsonResponse {
        $jsonArticle = $serializer->serialize($article, 'json', ['groups' => 'getArticles']);
        return new JsonResponse($jsonArticle, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    #[Route('/api/article/{id}', name: 'deleteArticle', methods: ['DELETE'])]
    public function deleteArticle(Article $article, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($article);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

//    #[Route('/api/articles', name:"createArticle", methods: ['POST'])]
//    public function createArticle(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, AuthorRepository $authorRepository, ValidatorInterface $validator): JsonResponse
//    {
//        $article = $serializer->deserialize($request->getContent(), Article::class, 'json');
//
//        // On vérifie les erreurs
//        $errors = $validator->validate($article);
//
//        if ($errors->count() > 0) {
//            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
//        }
//
//        $em->persist($article);
//        $em->flush();
//
//        // Récupération de l'ensemble des données envoyées sous forme de tableau
//        $content = $request->toArray();
//
//        // Récupération de l'idAuthor. S'il n'est pas défini, alors on met -1 par défaut.
//        $idAuthor = $content['idAuthor'] ?? -1;
//
//        // On cherche l'auteur qui correspond et on l'assigne au livre.
//        // Si "find" ne trouve pas l'auteur, alors null sera retourné.
//        $article->setAuthor($authorRepository->find($idAuthor));
//        $jsonArticle = $serializer->serialize($article, 'json', ['groups' => 'getArticles']);
//        $location = $urlGenerator->generate('detailArticle', ['id' => $article->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
//
//        return new JsonResponse($jsonArticle, Response::HTTP_CREATED, ["Location" => $location], true);
//    }

    #[Route('/api/articles', name:"createArticle", methods: ['POST'])]
    public function createArticle(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, AuthorRepository $authorRepository, ValidatorInterface $validator): JsonResponse
    {
        $article = $serializer->deserialize($request->getContent(), Article::class, 'json');

        // On vérifie les erreurs
        $errors = $validator->validate($article);

        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $em->persist($article);
        $em->flush();

        $content = $request->toArray();
        $idAuthor = $content['idAuthor'] ?? -1;

        $article->setAuthor($authorRepository->find($idAuthor));
        $jsonArticle = $serializer->serialize($article, 'json', ['groups' => 'getArticles']);
        $location = $urlGenerator->generate('detailArticle', ['id' => $article->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonArticle, Response::HTTP_CREATED, ["Location" => $location], true);

    }

    #[Route('/api/article/{id}', name:"updateArticle", methods:['PUT'])]
    public function updateArticle(Request $request, SerializerInterface $serializer, Article $currentArticle, EntityManagerInterface $em, AuthorRepository $authorRepository): JsonResponse
    {
        $updatedArticle = $serializer->deserialize($request->getContent(),
            Article::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $currentArticle]);
        $content = $request->toArray();
        $idAuthor = $content['idAuthor'] ?? -1;
        $updatedArticle->setAuthor($authorRepository->find($idAuthor));

        $em->persist($updatedArticle);
        $em->flush();
        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
