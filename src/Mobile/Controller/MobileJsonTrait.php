<?php

namespace App\Mobile\Controller;

use App\Compte\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Outils communs aux contrôleurs de l'API mobile (réponses JSON no-store, lecture JSON, utilisateur courant).
 */
trait MobileJsonTrait
{
    /**
     * @param array<string, mixed> $data
     */
    private function jsonNoStore(array $data, int $status = Response::HTTP_OK): JsonResponse
    {
        $response = new JsonResponse($data, $status);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    private function jsonError(string $message, int $status = Response::HTTP_BAD_REQUEST, ?string $code = null): JsonResponse
    {
        $payload = ['error' => $message];
        if (null !== $code) {
            $payload['code'] = $code;
        }

        return $this->jsonNoStore($payload, $status);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(Request $request): ?array
    {
        $raw = trim($request->getContent());
        if ('' === $raw) {
            return null;
        }
        $data = json_decode($raw, true);

        return \is_array($data) ? $data : null;
    }

    private function currentMobileUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentification requise.');
        }

        return $user;
    }
}
