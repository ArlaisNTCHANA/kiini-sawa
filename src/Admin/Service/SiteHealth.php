<?php

namespace App\Admin\Service;

use Doctrine\DBAL\Connection;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Santé du site au jour le jour (Admin › Réglages › Santé du site, 27/11/2026) : ce qui peut s'arrêter sans bruit sur
 * un hébergement mutualisé. Sauvegarde de la nuit, tournée des rappels, erreurs du journal, place sur le disque,
 * dernière mise à jour, envois d'annonces et paiements qui attendent.
 *
 * Chaque point : {label, state (ok | warning | problem), value, advice}. Rien de secret n'est affiché (pas de chemin de
 * base de données, pas de mot de passe).
 */
final class SiteHealth
{
    public function __construct(
        private readonly Connection $db,
        private readonly CacheItemPoolInterface $cacheApp,
        #[Autowire('%kernel.project_dir%')] private readonly string $dir,
        #[Autowire('%kernel.environment%')] private readonly string $env,
    ) {
    }

    /** @return list<array{label: string, state: string, value: string, advice: string}> */
    public function checks(?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $out = [];
        $add = function (string $label, string $state, string $value, string $advice = '') use (&$out): void {
            $out[] = compact('label', 'state', 'value', 'advice');
        };
        $ago = function (int $ts) use ($now): string {
            $h = (int) floor(($now->getTimestamp() - $ts) / 3600);

            return $h < 1 ? 'il y a moins d\'une heure' : ($h < 48 ? 'il y a '.$h.' h' : 'il y a '.intdiv($h, 24).' jours');
        };

        // Sauvegarde de la nuit (bin/ops/cron-sauvegarde.sh → ~/sauvegardes, à côté du dossier du site)
        $backups = glob(\dirname($this->dir).'/sauvegardes/kiini-sawa-2*.sql.gz') ?: [];
        if ([] === $backups) {
            $add('Sauvegarde de la base', 'prod' === $this->env ? 'problem' : 'warning', 'aucune sauvegarde trouvée',
                'Vérifier la tâche planifiée « cron-sauvegarde.sh » dans hPanel › Avancé › Tâches Cron.');
        } else {
            usort($backups, fn ($a, $b) => filemtime($b) <=> filemtime($a));
            $last = (int) filemtime($backups[0]);
            $fresh = $last > $now->getTimestamp() - 36 * 3600;
            $add('Sauvegarde de la base', $fresh ? 'ok' : 'problem',
                sprintf('%s (%s), %d gardée%s', date('d/m/Y à H\hi', $last), $this->size((int) filesize($backups[0])), \count($backups), \count($backups) > 1 ? 's' : ''),
                $fresh ? '' : 'La sauvegarde de la nuit n\'a pas tourné : vérifier la tâche « cron-sauvegarde.sh » dans hPanel.');
        }

        // Tournée des rappels (app:reminders:send, chaque jour)
        $lastRun = $this->cacheApp->getItem('reminders_last_run')->get();
        $add('Rappels et e-mails du jour', \is_int($lastRun) && $lastRun > $now->getTimestamp() - 36 * 3600 ? 'ok' : 'problem',
            \is_int($lastRun) ? 'dernière tournée '.date('d/m/Y à H\hi', $lastRun).' ('.$ago($lastRun).')' : 'jamais lancée',
            'Sans elle : ni rappels, ni séances de l\'Académie, ni e-mails d\'accueil. Vérifier la tâche « cron-rappels.sh » dans hPanel.');

        // Erreurs du journal : aujourd'hui et hier
        $errors = 0;
        foreach ([$now, $now->modify('-1 day')] as $day) {
            $file = $this->dir.'/var/log/'.$this->env.'-'.$day->format('Y-m-d').'.log';
            if (is_file($file) && filesize($file) < 50 * 1024 * 1024) {
                $errors += preg_match_all('/\.(ERROR|CRITICAL|ALERT|EMERGENCY): /', (string) file_get_contents($file));
            }
        }
        $add('Erreurs du site (hier et aujourd\'hui)', 0 === $errors ? 'ok' : ($errors < 10 ? 'warning' : 'problem'),
            0 === $errors ? 'aucune' : $errors.' erreur'.($errors > 1 ? 's' : '').' au journal',
            0 === $errors ? '' : 'Demander à l\'IA ou au développeur de lire le journal du serveur (var/log).');

        // Place sur le disque
        $free = @disk_free_space($this->dir);
        if (false !== $free) {
            $add('Place libre sur le disque', $free > 2 * 1024 ** 3 ? 'ok' : ($free > 500 * 1024 ** 2 ? 'warning' : 'problem'), $this->size((int) $free),
                'Peu de place : les sauvegardes et les images pourraient ne plus s\'écrire. Vider les vieilles sauvegardes ou agrandir l\'hébergement.');
        }

        // Dernière mise à jour du site (écrite par bin/ops/mise-a-jour.sh)
        $deploy = $this->dir.'/var/derniere-mise-a-jour.txt';
        $add('Dernière mise à jour du site', 'ok', is_file($deploy) ? trim((string) file_get_contents($deploy)) : 'non notée');

        // Ce qui attend
        $one = function (string $sql): int {
            try {
                return (int) $this->db->fetchOne($sql);
            } catch (\Throwable) {
                return 0;
            }
        };
        $sending = $one("SELECT COUNT(*) FROM announcement WHERE status = 'SENDING'");
        $add('Annonces en cours d\'envoi', 0 === $sending ? 'ok' : 'warning', 0 === $sending ? 'aucune' : (string) $sending,
            'Un envoi interrompu se termine avec la tournée des rappels ; sinon, rouvrir l\'annonce dans Écrire aux membres.');
        $since = $now->modify('-48 hours')->format('Y-m-d H:i:s');
        $late = $one("SELECT COUNT(*) FROM `transaction` WHERE status = 'PENDING' AND created_at < '".$since."'")
            + $one("SELECT COUNT(*) FROM seat_order WHERE status = 'PENDING' AND created_at < '".$since."'");
        $add('Paiements en attente depuis plus de 48 h', 0 === $late ? 'ok' : 'warning', 0 === $late ? 'aucun' : (string) $late,
            'Des membres attendent leur accès PRO : Admin › Paiements.');

        return $out;
    }

    /** Le pire état : ok, warning ou problem. */
    public function overall(array $checks): string
    {
        $states = array_column($checks, 'state');

        return \in_array('problem', $states, true) ? 'problem' : (\in_array('warning', $states, true) ? 'warning' : 'ok');
    }

    private function size(int $bytes): string
    {
        return $bytes >= 1024 ** 3 ? number_format($bytes / 1024 ** 3, 1, ',', ' ').' Go' : number_format($bytes / 1024 ** 2, 1, ',', ' ').' Mo';
    }
}
