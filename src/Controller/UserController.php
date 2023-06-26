<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;

class UserController extends AbstractController
{
    /**
     * Récupère l'ensemble des utilisateurs avec pagination.
     *
     * @OA\Get(
     *     path="/api/users",
     *     tags={"Users"},
     *     summary="Récupère l'ensemble des utilisateurs",
     *     description="Retourne la liste des utilisateurs avec pagination",
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="La page à récupérer (par défaut, la première)",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Le nombre d'utilisateurs par page (par défaut, 3)",
     *         @OA\Schema(type="integer", default=3)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des utilisateurs",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref=@Model(type=User::class, groups={"getUsers"}))
     *         )
     *     )
     * )
     *
     * @param UserRepository $userRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     */
    #[Route('/api/users', name: 'app_user', methods: ['GET'])]
    public function index(UserRepository $userRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cache): JsonResponse
    {
        // Récupérer les utilisateurs avec pagination
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $idCache = "getAllUsers-" . $page . "-" . $limit;

        // Utiliser le cache pour stocker la liste des utilisateurs
        $jsonUserList = $cache->get($idCache, function (ItemInterface $item) use ($userRepository, $page, $limit, $serializer) {
            $item->tag("usersCache");
            $userList = $userRepository->findAllWithPagination($page, $limit);
            return $serializer->serialize($userList, 'json', ['groups' => 'getUsers']);
        });

        return new JsonResponse($jsonUserList, Response::HTTP_OK, [], true);
    }

    /**
     * Récupère les détails d'un utilisateur spécifique.
     *
     * @OA\Get(
     *     path="/api/user/{id}",
     *     tags={"Users"},
     *     summary="Récupère les détails d'un utilisateur spécifique",
     *     description="Retourne les détails de l'utilisateur avec l'ID spécifié",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de l'utilisateur",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Détails de l'utilisateur",
     *         @OA\JsonContent(ref=@Model(type=User::class, groups={"getUser"}))
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Utilisateur non trouvé"
     *     )
     * )
     *
     * @param User $user
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    #[Route('/api/user/{id}', name: 'app_user_detail', methods: ['GET'])]
    public function getDetailUser(User $user, SerializerInterface $serializer): JsonResponse
    {
        $jsonUser = $serializer->serialize($user, 'json', ['groups' => 'getUser']);
        return new JsonResponse($jsonUser, Response::HTTP_OK, [], true);
    }

    /**
     * Supprime un utilisateur existant.
     *
     * @OA\Delete(
     *     path="/api/user/{id}",
     *     tags={"Users"},
     *     summary="Supprime un utilisateur existant",
     *     description="Supprime un utilisateur existant en fonction de l'ID spécifié",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de l'utilisateur",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Utilisateur supprimé avec succès"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Utilisateur non trouvé"
     *     )
     * )
     *
     * @param User $user
     * @param EntityManagerInterface $entityManager
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     */
    #[Route('/api/user/{id}', name: 'app_user_delete', methods: ['DELETE'])]
    public function deleteUser(User $user, EntityManagerInterface $entityManager, TagAwareCacheInterface $cache): JsonResponse
    {
        $entityManager->remove($user);
        $entityManager->flush();

        // Invalidater le cache pour les utilisateurs
        $cache->invalidateTags(["usersCache"]);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Crée un nouvel utilisateur.
     *
     * @OA\Post(
     *     path="/api/user",
     *     tags={"Users"},
     *     summary="Crée un nouvel utilisateur",
     *     description="Crée un nouvel utilisateur avec les informations fournies",
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="username", type="string"),
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="password", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Utilisateur créé avec succès",
     *         @OA\JsonContent(ref=@Model(type=User::class, groups={"getUser"}))
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Données de l'utilisateur non valides"
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
    #[Route('/api/user', name: 'app_user_create', methods: ['POST'])]
    public function createUser(Request $request, EntityManagerInterface $entityManager, SerializerInterface $serializer, ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse
    {
        $requestData = json_decode($request->getContent(), true);

        // Valider les données de l'utilisateur
        $user = $serializer->deserialize($request->getContent(), User::class, 'json');
        $errors = $validator->validate($user);

        if (count($errors) > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), Response::HTTP_BAD_REQUEST, [], true);
        }

        $entityManager->persist($user);
        $entityManager->flush();

        // Invalidater le cache pour les utilisateurs
        $cache->invalidateTags(["usersCache"]);

        $jsonUser = $serializer->serialize($user, 'json', ['groups' => 'getUser']);
        return new JsonResponse($jsonUser, Response::HTTP_CREATED, [], true);
    }

    /**
     * Met à jour un utilisateur existant.
     *
     * @OA\Put(
     *     path="/api/user/{id}",
     *     tags={"Users"},
     *     summary="Met à jour un utilisateur existant",
     *     description="Met à jour les informations d'un utilisateur existant en fonction de l'ID spécifié",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de l'utilisateur",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="username", type="string"),
     *             @OA\Property(property="email", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Utilisateur mis à jour avec succès",
     *         @OA\JsonContent(ref=@Model(type=User::class, groups={"getUser"}))
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Données de l'utilisateur non valides"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Utilisateur non trouvé"
     *     )
     * )
     *
     * @param User $user
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param SerializerInterface $serializer
     * @param ValidatorInterface $validator
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     */
    #[Route('/api/user/{id}', name: 'app_user_update', methods: ['PUT'])]
    public function updateUser(User $user, Request $request, EntityManagerInterface $entityManager, SerializerInterface $serializer, ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse
    {
        $requestData = json_decode($request->getContent(), true);

        // Valider les données de l'utilisateur
        $updatedUser = $serializer->deserialize($request->getContent(), User::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $user]);
        $errors = $validator->validate($updatedUser);

        if (count($errors) > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), Response::HTTP_BAD_REQUEST, [], true);
        }

        $entityManager->flush();

        // Invalidater le cache pour les utilisateurs
        $cache->invalidateTags(["usersCache"]);

        $jsonUser = $serializer->serialize($updatedUser, 'json', ['groups' => 'getUser']);
        return new JsonResponse($jsonUser, Response::HTTP_OK, [], true);
    }
}
