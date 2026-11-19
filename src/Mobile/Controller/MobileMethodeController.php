<?php

namespace App\Mobile\Controller;

use App\Mobile\Service\MobileMethodeSerializer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class MobileMethodeController extends AbstractController
{
    use MobileJsonTrait;

    public function __construct(
        private readonly MobileMethodeSerializer $serializer,
    ) {
    }

    #[Route('/api/mobile/v1/methode/chapitres', name: 'api_mobile_v1_methode_catalog', methods: ['GET'])]
    public function catalog(): JsonResponse
    {
        $user = $this->currentMobileUser();

        return $this->jsonNoStore($this->serializer->serializeCatalog($user));
    }

    #[Route('/api/mobile/v1/methode/chapitres/{slug}', name: 'api_mobile_v1_methode_chapter', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function chapter(string $slug): JsonResponse
    {
        $user = $this->currentMobileUser();
        $chapter = $this->serializer->serializeChapter($slug, $user);

        if (null === $chapter) {
            return $this->jsonError('Chapitre introuvable.', Response::HTTP_NOT_FOUND, 'CHAPTER_NOT_FOUND');
        }

        return $this->jsonNoStore($chapter);
    }
}
