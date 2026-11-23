<?php

namespace App\Mobile\Controller;

use App\Boussole\Entity\BoussoleResult;
use App\Boussole\Repository\BoussoleResultRepository;
use App\Boussole\Service\BoussoleContent;
use App\Boussole\Service\BoussoleEngine;
use App\Boussole\Service\BoussoleRecorder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Ma Boussole 360 dans l'application mobile : la même que sur le site (24 affirmations, même calcul, fait ici
 * côté serveur). Chaque passation est enregistrée dans le compte. Routes et clés JSON en français.
 */
#[Route('/api/mobile/v1/boussole')]
#[IsGranted('ROLE_USER')]
final class MobileBoussoleController extends AbstractController
{
    use MobileJsonTrait;

    public function __construct(
        private readonly BoussoleRecorder $recorder,
        private readonly BoussoleResultRepository $results,
    ) {
    }

    /** Questionnaire : 24 affirmations dans l'ordre, échelle de réponse et les 6 plans (noms de PlanCatalog). */
    #[Route('/questions', name: 'mobile_boussole_questions', methods: ['GET'])]
    public function questions(): JsonResponse
    {
        return $this->jsonNoStore([
            'questions' => array_map(fn (array $item, int $i) => [
                'numero' => $i + 1,
                'texte' => $item['text'],
                'plan' => $item['plan'],
            ], BoussoleContent::ITEMS, array_keys(BoussoleContent::ITEMS)),
            'echelle' => array_map(fn (array $s) => ['valeur' => $s['val'], 'libelle' => $s['label']], BoussoleContent::SCALE),
            'plans' => BoussoleEngine::plans(),
        ]);
    }

    /** Envoi des 24 réponses : calcul serveur, enregistrement, résultat complet. */
    #[Route('/reponses', name: 'mobile_boussole_submit', methods: ['POST'])]
    public function submit(Request $request): JsonResponse
    {
        $answers = BoussoleEngine::normalizeAnswers(($this->decodeJson($request) ?? [])['reponses'] ?? null);
        if (null === $answers) {
            return $this->jsonError('Les 24 réponses (de 1 à 5) sont attendues dans « reponses ».', Response::HTTP_UNPROCESSABLE_ENTITY, 'reponses_invalides');
        }
        $result = $this->recorder->record($this->currentMobileUser(), $answers, BoussoleResult::SOURCE_MOBILE);

        return $this->jsonNoStore(['resultat' => $this->serialize($result, true)], Response::HTTP_CREATED);
    }

    /** Dernière passation (lecture complète), ou null. */
    #[Route('/dernier', name: 'mobile_boussole_latest', methods: ['GET'])]
    public function latest(): JsonResponse
    {
        $result = $this->results->latestFor($this->currentMobileUser());

        return $this->jsonNoStore(['resultat' => null === $result ? null : $this->serialize($result, true)]);
    }

    /** Historique des passations (résumé), de la plus récente à la plus ancienne. */
    #[Route('/historique', name: 'mobile_boussole_history', methods: ['GET'])]
    public function history(): JsonResponse
    {
        return $this->jsonNoStore([
            'passations' => array_map(fn (BoussoleResult $r) => $this->serialize($r, false), $this->results->historyFor($this->currentMobileUser())),
        ]);
    }

    /** Une passation précise (lecture complète). 404 si elle appartient à un autre compte. */
    #[Route('/passations/{id}', name: 'mobile_boussole_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $result = $this->results->findOneForUser($id, $this->currentMobileUser());
        if (null === $result) {
            return $this->jsonError('Passation introuvable.', Response::HTTP_NOT_FOUND);
        }

        return $this->jsonNoStore(['resultat' => $this->serialize($result, true)]);
    }

    /** @return array<string, mixed> */
    private function serialize(BoussoleResult $result, bool $full): array
    {
        $data = [
            'id' => $result->getId(),
            'date' => $result->getCreatedAt()->format(\DATE_ATOM),
            'source' => $result->getSource(),
            'coherence' => $result->getCoherence(),
            'scores' => $result->getScores(),
            'planFort' => ['cle' => $result->getStrongestKey(), 'nom' => BoussoleEngine::planName($result->getStrongestKey())],
            'planPrioritaire' => ['cle' => $result->getPriorityKey(), 'nom' => BoussoleEngine::planName($result->getPriorityKey())],
        ];
        if (!$full) {
            return $data;
        }
        $r = $this->recorder->evaluate($result);

        return $data + [
            'reponses' => $result->getAnswers(),
            'ecart' => $r['spread'],
            'lecture' => ['principale' => $r['readingMain'], 'complement' => $r['readingSub']],
            'appui' => $r['showStrongCard'] ? ['libelle' => $r['strongLabel'], 'titre' => $r['strongTitle'], 'texte' => $r['strongText']] : null,
            'priorite' => ['libelle' => $r['prioLabel'], 'titre' => $r['prioTitle'], 'texte' => $r['prioText']],
            'signaux' => $r['signals'],
            'pistes' => ['mode' => $r['stepsMode'], 'etapes' => $r['steps']],
        ];
    }
}
