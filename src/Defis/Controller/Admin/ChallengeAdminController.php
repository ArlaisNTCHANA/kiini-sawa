<?php

namespace App\Defis\Controller\Admin;

use App\Compte\Entity\User;
use App\Defis\Entity\Challenge;
use App\Defis\Service\ChallengeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;

/** Création et suivi des défis collectifs (publiés sur /defi/{slug}). */
#[Route('/admin/defis', name: 'admin_challenges')]
#[IsGranted('ROLE_ADMIN')]
class ChallengeAdminController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly ChallengeService $challenges)
    {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function index(): Response
    {
        $today = new \DateTimeImmutable('today');
        $list = $this->em->getRepository(Challenge::class)->createQueryBuilder('c')
            ->where('c.slug IS NOT NULL')->orderBy('c.startDate', 'DESC')->getQuery()->getResult();

        return $this->render('defis/admin/index.html.twig', [
            'rows' => array_map(fn (Challenge $c) => ['c' => $c, 'phase' => $this->challenges->phase($c, $today), 'stats' => $this->challenges->stats($c, $today)], $list),
        ]);
    }

    #[Route('/nouveau', name: '_new', methods: ['GET', 'POST'])]
    #[Route('/{id}', name: '_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, ?int $id = null): Response
    {
        $challenge = $id ? ($this->em->find(Challenge::class, $id) ?? throw $this->createNotFoundException()) : null;
        $errors = [];
        $data = $challenge ? [
            'title' => $challenge->getTitle(), 'slug' => $challenge->getSlug(), 'tagline' => $challenge->getTagline(),
            'description' => $challenge->getDescription(), 'habitTitle' => $challenge->getHabitTitle(),
            'startDate' => $challenge->getStartDate()?->format('Y-m-d'), 'duration' => $challenge->getDurationDays(),
            'dailyActions' => implode("\n", $challenge->getDailyActions()), 'published' => $challenge->isPublished(),
        ] : ['title' => '', 'slug' => '', 'tagline' => '', 'description' => '', 'habitTitle' => '', 'startDate' => (new \DateTimeImmutable('next monday'))->format('Y-m-d'), 'duration' => 21, 'dailyActions' => '', 'published' => false];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_challenge', $request->request->getString('_csrf_token'))) {
                $errors[] = 'La page a expiré, merci de réessayer.';
            }
            $p = $request->request;
            $data = [
                'title' => trim($p->getString('title')), 'slug' => trim($p->getString('slug')), 'tagline' => trim($p->getString('tagline')),
                'description' => trim($p->getString('description')), 'habitTitle' => trim($p->getString('habitTitle')),
                'startDate' => $p->getString('startDate'), 'duration' => $p->getInt('duration'),
                'dailyActions' => trim($p->getString('dailyActions')), 'published' => $p->getBoolean('published'),
            ];
            $slug = mb_strtolower((string) (new AsciiSlugger('fr'))->slug('' !== $data['slug'] ? $data['slug'] : $data['title']));
            $start = \DateTimeImmutable::createFromFormat('!Y-m-d', $data['startDate']) ?: null;
            if ('' === $data['title']) { $errors[] = 'Le titre est obligatoire.'; }
            if (!preg_match('/^[a-z0-9-]{3,80}$/', $slug)) { $errors[] = 'Adresse invalide (3 à 80 caractères : lettres, chiffres, tirets).'; }
            if (null === $start) { $errors[] = 'Date de début invalide.'; }
            if ($data['duration'] < 3 || $data['duration'] > 100) { $errors[] = 'Durée : entre 3 et 100 jours.'; }
            if ('' === $data['habitTitle'] && '' === $data['dailyActions']) { $errors[] = 'Indiquez l\'action quotidienne ou les actions jour par jour.'; }
            $other = $this->em->getRepository(Challenge::class)->findOneBy(['slug' => $slug]);
            if ($other && $other !== $challenge) { $errors[] = 'Cette adresse est déjà utilisée par un autre défi.'; }

            if ([] === $errors) {
                /** @var User $admin */
                $admin = $this->getUser();
                $challenge ??= (new Challenge())->setCreator($admin);
                $challenge->setTitle($data['title'])->setSlug($slug)->setTagline($data['tagline'] ?: null)
                    ->setDescription($data['description'] ?: null)->setHabitTitle($data['habitTitle'] ?: null)
                    ->setStartDate(\DateTime::createFromImmutable($start))
                    ->setEndDate(\DateTime::createFromImmutable($start->modify('+'.($data['duration'] - 1).' days')))
                    ->setDailyActions(preg_split('/\R/', $data['dailyActions']) ?: [])
                    ->setPublished($data['published'])->setVisibility('PUBLIC')->setFrequency('DAILY');
                $this->em->persist($challenge);
                $this->em->flush();
                $this->addFlash('success', 'Défi enregistré.'.($challenge->isPublished() ? ' Il est en ligne.' : ' Il n\'est pas encore publié.'));

                return $this->redirectToRoute('admin_challenges_edit', ['id' => $challenge->getId()]);
            }
        }

        return $this->render('defis/admin/edit.html.twig', [
            'challenge' => $challenge, 'data' => $data, 'errors' => $errors,
            'publicUrl' => $challenge?->getSlug() ? $this->generateUrl('app_challenge', ['slug' => $challenge->getSlug()], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL) : null,
            'stats' => $challenge ? $this->challenges->stats($challenge, new \DateTimeImmutable('today')) : null,
        ]);
    }
}
