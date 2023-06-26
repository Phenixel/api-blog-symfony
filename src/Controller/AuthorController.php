<?php

namespace App\Controller;

use App\Entity\Author;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Annotations as OA;

class AuthorController extends AbstractController
{
    /**
     * Crée un nouvel auteur.
     *
     * @OA\Post(
     *     path="/api/author",
     *     tags={"Authors"},
     *     summary="Crée un nouvel auteur",
     *     description="Crée un nouvel auteur avec les informations spécifiées",
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="firstName", type="string"),
     *             @OA\Property(property="lastName", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Auteur créé avec succès",
     *         @OA\JsonContent(ref="#/components/schemas/Author")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Données de l'auteur non valides"
     *     )
     * )
     *
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param SerializerInterface $serializer
     * @param ValidatorInterface $validator
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     */
    #[Route('/api/author', name: 'app_author_create', methods: ['POST'])]
    public function createAuthor(Request $request, EntityManagerInterface $entityManager, SerializerInterface $serializer, ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse
    {
        $requestData = json_decode($request->getContent(), true);

        // Valider les données de l'auteur
        $author = $serializer->deserialize($request->getContent(), Author::class, 'json');
        $errors = $validator->validate($author);

        if (count($errors) > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), Response::HTTP_BAD_REQUEST, [], true);
        }

        $entityManager->persist($author);
        $entityManager->flush();

        // Invalidater le cache pour les auteurs
        $cache->invalidateTags(["authorsCache"]);

        $jsonAuthor = $serializer->serialize($author, 'json');
        return new JsonResponse($jsonAuthor, Response::HTTP_CREATED, [], true);
    }

    /**
     * Met à jour un auteur existant.
     *
     * @OA\Put(
     *     path="/api/author/{id}",
     *     tags={"Authors"},
     *     summary="Met à jour un auteur existant",
     *     description="Met à jour les informations d'un auteur existant en fonction de l'ID spécifié",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de l'auteur",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="firstName", type="string"),
     *             @OA\Property(property="lastName", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Auteur mis à jour avec succès",
     *         @OA\JsonContent(ref="#/components/schemas/Author")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Données de l'auteur non valides"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Auteur non trouvé"
     *     )
     * )
     *
     * @param int $id
     * @param Request $request
     * @param AuthorRepository $authorRepository
     * @param EntityManagerInterface $entityManager
     * @param SerializerInterface $serializer
     * @param ValidatorInterface $validator
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     */
    #[Route('/api/author/{id}', name: 'app_author_update', methods: ['PUT'])]
    public function updateAuthor(int $id, Request $request, AuthorRepository $authorRepository, EntityManagerInterface $entityManager, SerializerInterface $serializer, ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse
    {
        $author = $authorRepository->find($id);

        if (!$author) {
            return new JsonResponse('Author not found', Response::HTTP_NOT_FOUND);
        }

        $requestData = json_decode($request->getContent(), true);

        // Mettre à jour les informations de l'auteur
        $serializer->deserialize($request->getContent(), Author::class, 'json', ['object_to_populate' => $author]);
        $errors = $validator->validate($author);

        if (count($errors) > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), Response::HTTP_BAD_REQUEST, [], true);
        }

        $entityManager->flush();

        // Invalidater le cache pour les auteurs
        $cache->invalidateTags(["authorsCache"]);

        $jsonAuthor = $serializer->serialize($author, 'json');
        return new JsonResponse($jsonAuthor, Response::HTTP_OK, [], true);
    }
}
