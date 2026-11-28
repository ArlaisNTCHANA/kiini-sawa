<?php

namespace App\Admin\Twig;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Menu de l'administration, défini une seule fois (ordinateur et téléphone), regroupé par usage.
 * Chaque page : [libellé, route, icône, préfixes de route qui la rendent active (« =nom » : ce nom exact), compteur « à traiter » éventuel].
 * Ajouter une page d'administration : l'inscrire ici ; tests/Admin/AdminNavigationTest.php vérifie que chaque entrée s'ouvre.
 */
final class AdminNavigationExtension extends AbstractExtension
{
    /**
     * Réorganisé le 27/11/2026 (demande du fondateur) : d'abord ce qui attend une décision chaque jour, puis les
     * personnes, ce que l'on écrit, les tests, les chiffres, enfin les réglages. L'atelier de schémas, caché dans le
     * studio de l'Académie, a son entrée ; « Qualité des tests » rejoint les autres pages des tests.
     */
    public const GROUPS = [
        'home' => ['label' => 'Accueil', 'items' => [
            ['Tableau de bord', 'admin_dashboard', 'layout-dashboard', ['admin_dashboard']],
        ]],
        'todo' => ['label' => 'À traiter', 'items' => [
            ['Paiements', 'admin_payments', 'wallet', ['admin_payments', 'admin_seat_order'], 'payments'],
            ['Avis reçus', 'admin_app_feedback', 'message-square-heart', ['admin_app_feedback'], 'feedback'],
        ]],
        'members' => ['label' => 'Les membres', 'items' => [
            ['Membres', 'admin_users', 'users', ['admin_users']],
            ['Organisations', 'admin_organisations', 'building-2', ['admin_organisations']],
            ['Écrire aux membres', 'admin_announcements', 'megaphone', ['admin_announcements']],
        ]],
        'content' => ['label' => 'Les contenus', 'items' => [
            ['L\'Académie', 'admin_academie', 'graduation-cap', ['admin_academie_new', 'admin_academie_course', 'admin_academie_status', 'admin_academie_cover', 'admin_academie_image', 'admin_academie_module', 'admin_academie_lesson', 'admin_academie_markdown', 'admin_academie_blocks', 'admin_academie_question', 'admin_academie_preview', 'admin_academie_export', '=admin_academie']],
            ['Atelier de schémas', 'admin_academie_diagrams', 'shapes', ['admin_academie_diagrams', 'admin_academie_diagram']],
            ['Textes du site', 'admin_site_texts', 'file-pen-line', ['admin_site_texts', 'admin_site_text'], 'drafts'],
            ['Citations du jour', 'admin_quotes', 'quote', ['admin_quotes']],
            ['Défis collectifs', 'admin_challenges', 'trophy', ['admin_challenges']],
        ]],
        'tests' => ['label' => 'Les tests', 'items' => [
            ['Questions et profils', 'admin_personality_dashboard', 'brain', ['admin_personality']],
            ['Textes des tests', 'admin_content_index', 'pencil-line', ['admin_content']],
            ['Qualité des tests', 'admin_psychometrics', 'gauge', ['admin_psychometrics']],
        ]],
        'figures' => ['label' => 'Les chiffres', 'items' => [
            ['D\'où viennent les inscrits', 'admin_origins', 'radar', ['admin_origins']],
            ['Du visiteur au membre', 'admin_funnel', 'filter', ['admin_funnel']],
            ['Qui revient', 'admin_retention', 'repeat', ['admin_retention']],
            ['Suivi de l\'Académie', 'admin_academy_stats', 'chart-no-axes-column', ['admin_academy_stats']],
            ['La Boussole 360', 'admin_boussole_stats', 'compass', ['admin_boussole_stats']],
            ['Exports pour la comptabilité', 'admin_exports', 'file-spreadsheet', ['admin_exports']],
        ]],
        'settings' => ['label' => 'Réglages', 'items' => [
            ['Réglages de l\'application', 'admin_config', 'settings', ['admin_config']],
            ['Santé du site', 'admin_health', 'heart-pulse', ['admin_health'], 'health'],
            ['Aperçu des e-mails', 'admin_emails', 'mails', ['admin_emails']],
            ['Administrateurs et journal', 'admin_admins', 'shield-check', ['admin_admins']],
            ['Documentation technique', 'admin_doc', 'book-open', ['admin_doc']],
        ]],
    ];

    /** Pages d'administration hors menu : [préfixe de route => [groupe, libellé]] pour le fil d'Ariane. */
    private const HIDDEN = [
        'admin_plan_de_vie' => ['settings', 'Plan de Vie de l\'administrateur (ancienne page)'],
    ];

    private ?array $counts = null;

    public function __construct(private readonly RequestStack $requests, private readonly Connection $db, private readonly \App\Admin\Service\SiteHealth $health)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('admin_nav', $this->groups(...)),
            new TwigFunction('admin_current', $this->current(...)),
            new TwigFunction('admin_todo', $this->counts(...)),
        ];
    }

    /** Le menu, avec la page active et les compteurs. */
    public function groups(): array
    {
        $route = (string) $this->requests->getCurrentRequest()?->attributes->get('_route');
        $counts = $this->counts();
        $out = [];
        foreach (self::GROUPS as $key => $group) {
            $items = [];
            foreach ($group['items'] as $it) {
                $items[] = [
                    'label' => $it[0], 'route' => $it[1], 'icon' => $it[2],
                    'active' => $this->matches($route, $it[3]),
                    'count' => isset($it[4]) ? ($counts[$it[4]] ?? 0) : 0,
                ];
            }
            $out[$key] = ['label' => $group['label'], 'items' => $items];
        }

        return $out;
    }

    /** Groupe et page de la route courante, pour le fil d'Ariane : {group, label, route} ou null. */
    public function current(): ?array
    {
        $route = (string) $this->requests->getCurrentRequest()?->attributes->get('_route');
        foreach (self::GROUPS as $group) {
            foreach ($group['items'] as $it) {
                if ($this->matches($route, $it[3])) {
                    return ['group' => $group['label'], 'label' => $it[0], 'route' => $it[1], 'home' => 'admin_dashboard' === $it[1]];
                }
            }
        }
        foreach (self::HIDDEN as $prefix => [$group, $label]) {
            if (str_starts_with($route, $prefix)) {
                return ['group' => self::GROUPS[$group]['label'], 'label' => $label, 'route' => $prefix, 'home' => false];
            }
        }

        return null;
    }

    /** Ce qui attend l'administrateur : paiements et commandes de places à vérifier, avis à lire, brouillons non publiés. */
    public function counts(): array
    {
        if (null !== $this->counts) {
            return $this->counts;
        }
        $one = function (string $sql): int {
            try {
                return (int) $this->db->fetchOne($sql);
            } catch (\Throwable) {
                return 0;
            }
        };
        $transactions = $one("SELECT COUNT(*) FROM `transaction` WHERE status = 'PENDING'");
        $orders = $one("SELECT COUNT(*) FROM seat_order WHERE status = 'PENDING'");

        return $this->counts = [
            'transactions' => $transactions,
            'orders' => $orders,
            'payments' => $transactions + $orders,
            'feedback' => $one("SELECT COUNT(*) FROM app_feedback WHERE status = 'PENDING'"),
            'drafts' => $one('SELECT COUNT(*) FROM site_text WHERE draft IS NOT NULL'),
            // Santé du site : sauvegarde ou rappels arrêtés, erreurs, disque plein (SiteHealth)
            'health' => $this->health->problemCount(),
        ];
    }

    /** Un préfixe commençant par « = » ne vaut que pour ce nom exact (l'Académie et son atelier partagent un début de nom). */
    private function matches(string $route, array $prefixes): bool
    {
        foreach ($prefixes as $p) {
            if (str_starts_with($p, '=')) {
                if ($route === substr($p, 1)) {
                    return true;
                }
                continue;
            }
            if ($route === $p || str_starts_with($route, $p.'_')) {
                return true;
            }
        }

        return false;
    }
}
