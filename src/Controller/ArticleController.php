<?php

namespace App\Controller;

use App\Entity\Article;
use App\Repository\ArticleRepository;
use App\Repository\AuthorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;

class ArticleController extends AbstractController
{
    /**
     * Récupère l'ensemble des articles avec pagination.
     *
     * @OA\Get(
     *     path="/api/articles",
     *     tags={"Articles"},
     *     summary="Récupère l'ensemble des articles",
     *     description="Retourne la liste des articles avec pagination",
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="La page à récupérer (par défaut, la première)",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Le nombre d'articles par page (par défaut, 3)",
     *         @OA\Schema(type="integer", default=3)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des articles",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref=@Model(type=Article::class, groups={"getArticles"}))
     *         )
     *     )
     * )
     *
     * @param ArticleRepository $articleRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     */
    #[Route('/api/articles', name: 'app_article', methods: ['GET'])]
    public function index(ArticleRepository $articleRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cache): JsonResponse
    {
        // Récupérer les articles avec pagination
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $idCache = "getAllArticles-" . $page . "-" . $limit;

        // Utiliser le cache pour stocker la liste des articles
        $jsonArticleList = $cache->get($idCache, function (ItemInterface $item) use ($articleRepository, $page, $limit, $serializer) {
            $item->tag("articlesCache");
            $articleList = $articleRepository->findAllWithPagination($page, $limit);
            return $serializer->serialize($articleList, 'json', ['groups' => 'getArticles']);
        });

        return new JsonResponse($jsonArticleList, Response::HTTP_OK, [], true);
    }

    /**
     * Récupère les détails d'un article spécifique.
     *
     * @OA\Get(
     *     path="/api/article/{id}",
     *     tags={"Articles"},
     *     summary="Récupère les détails d'un article spécifique",
     *     description="Retourne les détails d'un article spécifié par son ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de l'article",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Détails de l'article",
     *         @OA\JsonContent(
     *             ref=@Model(type=Article::class, groups={"getArticles"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Article non trouvé"
     *     )
     * )
     *
     * @param Article $article
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    #[Route('/api/article/{id}', name: 'detailArticle', methods: ['GET'])]
    public function getDetailArticle(Article $article, SerializerInterface $serializer): JsonResponse
    {
        // Récupérer les détails d'un article spécifique
        $jsonArticle = $serializer->serialize($article, 'json', ['groups' => 'getArticles']);
        return new JsonResponse($jsonArticle, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    /**
     * Supprime un article existant.
     *
     * @OA\Delete(
     *     path="/api/article/{id}",
     *     tags={"Articles"},
     *     summary="Supprime un article existant",
     *     description="Supprime un article spécifié par son ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de l'article",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Article supprimé avec succès"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Accès refusé. L'utilisateur n'a pas les droits suffisants pour supprimer un article"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Article non trouvé"
     *     )
     * )
     *
     * @param Article $article
     * @param EntityManagerInterface $em
     * @param TagAwareCacheInterface $cachePool
     * @return JsonResponse
     */
    #[Route('/api/article/{id}', name: 'deleteArticle', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour supprimer un article')]
    public function deleteArticle(Article $article, EntityManagerInterface $em, TagAwareCacheInterface $cachePool): JsonResponse
    {
        // Supprimer un article existant
        $cachePool->invalidateTags(["articlesCache"]);
        $em->remove($article);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Crée un nouvel article.
     *
     * @OA\Post(
     *     path="/api/articles",
     *     tags={"Articles"},
     *     summary="Crée un nouvel article",
     *     description="Crée un nouvel article avec les informations fournies",
     *     @OA\RequestBody(
     *         description="Données de l'article",
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="idAuthor", type="integer")
     *             ),
     *             example={
     *                 "title": "L'IA dans la photographie",
     *                 "description": "Nous allons voir comment l'IA a une importance primordiale dans la photo.",
     *                 "idAuthor": 5
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Article créé avec succès",
     *         @OA\JsonContent(
     *             ref=@Model(type=Article::class, groups={"getArticles"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Données de l'article non valides"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Accès refusé. L'utilisateur n'a pas les droits suffisants pour créer un article"
     *     )
     * )
     *
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param UrlGeneratorInterface $urlGenerator
     * @param AuthorRepository $authorRepository
     * @param ValidatorInterface $validator
     * @return JsonResponse
     */
    #[Route('/api/articles', name: 'createArticle', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour créer un article')]
    public function createArticle(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, AuthorRepository $authorRepository, ValidatorInterface $validator): JsonResponse
    {
        // Créer un nouvel article
        $article = $serializer->deserialize($request->getContent(), Article::class, 'json');

        // Valider les erreurs
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

    /**
     * Met à jour un article existant.
     *
     * @OA\Put(
     *     path="/api/article/{id}",
     *     tags={"Articles"},
     *     summary="Met à jour un article existant",
     *     description="Met à jour un article spécifié par son ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de l'article",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         description="Données de l'article",
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 ref=@Model(type=Article::class, groups={"updateArticle"})
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Article mis à jour avec succès",
     *         @OA\JsonContent(
     *             ref=@Model(type=Article::class, groups={"getArticles"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Données de l'article non valides"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Accès refusé. L'utilisateur n'a pas les droits suffisants pour mettre à jour un article"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Article non trouvé"
     *     )
     * )
     *
     * @param Article $article
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param ValidatorInterface $validator
     * @return JsonResponse
     */
    #[Route('/api/article/{id}', name: 'updateArticle', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour mettre à jour un article')]
    public function updateArticle(Article $article, Request $request, SerializerInterface $serializer, EntityManagerInterface $em, ValidatorInterface $validator): JsonResponse
    {
        // Mettre à jour un article existant
        $updatedArticle = $serializer->deserialize($request->getContent(), Article::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $article]);

        // Valider les erreurs
        $errors = $validator->validate($updatedArticle);

        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $em->flush();

        $jsonArticle = $serializer->serialize($updatedArticle, 'json', ['groups' => 'getArticles']);

        return new JsonResponse($jsonArticle, Response::HTTP_OK, [], true);
    }
}
