<?php

namespace App\Admin\Command;

use App\Abonnement\Service\Entitlements;
use App\Compte\Entity\User;
use Doctrine\DBAL\Connection;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Contrôle avant (et après) la mise en ligne : liste ce qui manque, en français, avec la marche à suivre.
 * Code de sortie 1 s'il reste un point bloquant.
 */
#[AsCommand(name: 'app:launch:check', description: 'Vérifie que tout est prêt pour la mise en ligne')]
class LaunchCheckCommand extends Command
{
    public function __construct(
        private readonly Connection $db,
        private readonly \App\Contenu\Service\SiteTexts $siteTexts,
        private readonly Entitlements $entitlements,
        private readonly CacheItemPoolInterface $cacheApp,
        #[Autowire('%kernel.environment%')] private readonly string $env,
        #[Autowire('%kernel.debug%')] private readonly bool $debug,
        #[Autowire('%kernel.project_dir%')] private readonly string $dir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('KIINI SAWA : contrôle avant mise en ligne');
        $rows = [];
        $blocking = 0;
        $check = function (string $label, bool $ok, string $advice, bool $isBlocking = true) use (&$rows, &$blocking): void {
            $rows[] = [$ok ? '<info>✓</info>' : ($isBlocking ? '<error>✗</error>' : '<comment>!</comment>'), $label, $ok ? '' : $advice];
            if (!$ok && $isBlocking) {
                ++$blocking;
            }
        };
        $env = fn (string $k) => (string) ($_SERVER[$k] ?? $_ENV[$k] ?? getenv($k) ?: '');

        // Serveur
        $check('Mode production', 'prod' === $this->env && !$this->debug, 'Définir APP_ENV=prod (et APP_DEBUG=0).');
        $secret = $env('APP_SECRET');
        $check('Clé secrète APP_SECRET', \strlen($secret) >= 32 && !str_contains($secret, 'test'), 'Générer une clé : php -r "echo bin2hex(random_bytes(32));" et la mettre dans APP_SECRET.');
        $uri = $env('DEFAULT_URI');
        $check('Adresse publique DEFAULT_URI (https)', str_starts_with($uri, 'https://') && !str_contains($uri, 'localhost'), 'Mettre l\'adresse du site, ex. DEFAULT_URI=https://kiinisawa.com (sert aux liens des emails).');
        $check('Proxy de l\'hébergeur (TRUSTED_PROXIES)', '' !== $env('TRUSTED_PROXIES'), 'Renseigner l\'adresse du proxy de l\'hébergeur, ou REMOTE_ADDR.', false);

        // Base
        try {
            $this->db->executeQuery('SELECT 1');
            $dbOk = true;
        } catch (\Throwable) {
            $dbOk = false;
        }
        $check('Base de données joignable', $dbOk, 'Vérifier DATABASE_URL.');
        if ($dbOk) {
            $applied = (int) $this->db->fetchOne('SELECT COUNT(*) FROM doctrine_migration_versions');
            $files = \count(glob($this->dir.'/migrations/*.php') ?: []);
            $check('Migrations appliquées', $applied >= $files, 'Lancer : php bin/console doctrine:migrations:migrate -n');
            $admins = array_filter($this->db->fetchFirstColumn('SELECT roles FROM `user`'), fn ($r) => str_contains((string) $r, 'ROLE_ADMIN'));
            $check('Compte administrateur', [] !== $admins, 'Lancer : php bin/console app:install --admin-email=vous@domaine.com');
            $check('Citations et questionnaires de départ', (int) $this->db->fetchOne('SELECT COUNT(*) FROM quote') > 0 && (int) $this->db->fetchOne('SELECT COUNT(*) FROM personality_question') > 0, 'Lancer : php bin/console app:install');
            $config = $this->entitlements->config();
            $check('Coordonnées de paiement (virement ou Mobile Money)', (bool) ($config->getBankTransferInstructions() || $config->getMobileMoneyInstructions()), 'Admin › Configuration : renseigner les coordonnées, sinon le paiement reste masqué.');
        }

        // Emails
        $mailer = $env('MAILER_DSN');
        $check('Envoi d\'emails (MAILER_DSN)', '' !== $mailer && !str_starts_with($mailer, 'null://'), 'Créer un compte chez un fournisseur d\'emails (Brevo, Mailjet, Postmark…) et renseigner MAILER_DSN. Sans cela : ni code de vérification, ni rappels.');
        $from = $env('MAILER_FROM');
        $check('Expéditeur des emails (MAILER_FROM)', '' !== $from, 'Définir MAILER_FROM avec une adresse de votre domaine, vérifiée chez le fournisseur.', false);
        $transport = $env('MESSENGER_TRANSPORT_DSN');
        // sync:// (envoi immédiat) convient à un hébergement mutualisé ; doctrine:// demande un worker permanent.
        $check('Mode d\'envoi des emails (MESSENGER_TRANSPORT_DSN)', '' !== $transport, 'sync:// sur un hébergement mutualisé, ou doctrine://default avec le worker : php bin/console messenger:consume async', false);

        // Tâches planifiées
        $lastRun = $this->cacheApp->getItem('reminders_last_run')->get();
        $check('Rappels quotidiens (tâche planifiée)', \is_int($lastRun) && $lastRun > time() - 36 * 3600, 'Planifier chaque jour : php bin/console app:reminders:send (voir docs/mise-en-ligne.md).', false);

        // Fichiers compilés
        $check('Feuilles de style et scripts compilés', is_file($this->dir.'/public/assets/manifest.json'), 'Lancer : php bin/console tailwind:build --minify puis php bin/console asset-map:compile', 'prod' === $this->env);

        // Contenu légal
        $todo = 0;
        // Pages légales : le texte en ligne (modifiable dans Admin › Textes du site), pas seulement le texte d'origine
        foreach (['mentions_legales', 'confidentialite', 'cgu', 'cgv'] as $page) {
            $todo += substr_count($this->siteTexts->publishedDocument('legal.'.$page), '[À COMPLÉTER');
        }
        foreach ([$this->dir.'/templates/abonnement/receipt.html.twig', $this->dir.'/templates/organisation/receipt.html.twig'] as $f) {
            $todo += substr_count((string) file_get_contents($f), '[À COMPLÉTER');
        }
        $check('Pages légales et reçus complétés', 0 === $todo, sprintf('%d passage(s) « [À COMPLÉTER] » : raison sociale, adresse, SIREN, hébergeur, médiateur, TVA (Admin › Textes du site › Pages légales, et reçus). À faire relire par un juriste.', $todo));

        $io->table(['', 'Point', 'À faire'], $rows);
        if ($blocking > 0) {
            $io->error(sprintf('%d point(s) bloquant(s) avant la mise en ligne.', $blocking));

            return Command::FAILURE;
        }
        $io->success('Tout est prêt pour la mise en ligne. Les points marqués « ! » sont recommandés.');

        return Command::SUCCESS;
    }
}
