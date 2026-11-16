<?php

namespace App\Admin\Command;

use App\Abonnement\Service\Entitlements;
use App\Compte\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Installation d'un nouveau serveur, après les migrations :
 *   php bin/console doctrine:migrations:migrate -n
 *   php bin/console app:install --admin-email=vous@domaine.com
 *
 * Crée la configuration par défaut, les citations du jour, les questionnaires d'aptitudes
 * et d'hygiène de vie, et le premier compte administrateur (mot de passe demandé, jamais affiché).
 * Sans danger si relancée : rien n'est dupliqué.
 */
#[AsCommand(name: 'app:install', description: 'Prépare un nouveau serveur (données de départ et premier administrateur)')]
class InstallCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly Entitlements $entitlements)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('admin-email', null, InputOption::VALUE_REQUIRED, 'Email du premier administrateur');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $app = $this->getApplication();

        $this->entitlements->config();
        $io->writeln('✓ Configuration par défaut');

        $app->find('app:quotes:seed')->run(new ArrayInput([]), $output);

        $hasQuestions = (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM personality_question') > 0;
        if (!$hasQuestions) {
            $app->find('app:seed:personality')->run(new ArrayInput([]), $output);
        } else {
            $io->writeln('✓ Questionnaires d\'aptitudes déjà présents');
        }

        $email = $input->getOption('admin-email');
        if ($email) {
            if ($this->em->getRepository(User::class)->findOneBy(['email' => mb_strtolower($email)])) {
                $io->writeln('✓ Administrateur déjà présent : '.$email);
            } else {
                $password = $io->askHidden('Mot de passe de l\'administrateur (10 caractères minimum)', function (?string $v) {
                    if (null === $v || mb_strlen($v) < 10) {
                        throw new \RuntimeException('10 caractères minimum.');
                    }

                    return $v;
                });
                $app->find('app:create-user')->run(new ArrayInput(['email' => $email, 'password' => $password, '--admin' => true]), $output);
            }
        }

        $io->success('Installation terminée. Lancez maintenant : php bin/console app:launch:check');

        return Command::SUCCESS;
    }
}
