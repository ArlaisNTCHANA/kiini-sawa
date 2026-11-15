<?php

namespace App\Shared\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Crée une entité et son dépôt dans un module existant
 * (make:entity range le dépôt hors du module, d'où cette commande).
 */
#[AsCommand(
    name: 'app:module:entity',
    description: 'Crée une entité et son dépôt dans un module (src/<Module>/Entity et src/<Module>/Repository)',
)]
class CreateModuleEntityCommand extends Command
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('module', InputArgument::REQUIRED, 'Module existant (ex. : PlanDeVie)')
            ->addArgument('entite', InputArgument::REQUIRED, 'Nom de l\'entité en PascalCase (ex. : Objectif)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $module = (string) $input->getArgument('module');
        $entity = (string) $input->getArgument('entite');

        if (!is_dir($this->projectDir.'/src/'.$module)) {
            $io->error(sprintf('Le module "%s" n\'existe pas. Créez-le avec : bin/console app:module:create %s', $module, $module));

            return Command::FAILURE;
        }
        if (!preg_match('/^[A-Z][A-Za-z0-9]+$/', $entity)) {
            $io->error('Le nom de l\'entité doit être en PascalCase, sans espace ni accent.');

            return Command::FAILURE;
        }

        $entityPath = "src/$module/Entity/$entity.php";
        $repoPath = "src/$module/Repository/{$entity}Repository.php";
        foreach ([$entityPath, $repoPath] as $path) {
            if ($this->filesystem->exists($this->projectDir.'/'.$path)) {
                $io->error(sprintf('%s existe déjà.', $path));

                return Command::FAILURE;
            }
        }

        $this->filesystem->dumpFile($this->projectDir.'/'.$entityPath, <<<PHP
            <?php

            namespace App\\{$module}\\Entity;

            use App\\{$module}\\Repository\\{$entity}Repository;
            use Doctrine\\ORM\\Mapping as ORM;

            #[ORM\\Entity(repositoryClass: {$entity}Repository::class)]
            class {$entity}
            {
                #[ORM\\Id]
                #[ORM\\GeneratedValue]
                #[ORM\\Column]
                private ?int \$id = null;

                public function getId(): ?int
                {
                    return \$this->id;
                }
            }

            PHP);

        $this->filesystem->dumpFile($this->projectDir.'/'.$repoPath, <<<PHP
            <?php

            namespace App\\{$module}\\Repository;

            use App\\{$module}\\Entity\\{$entity};
            use Doctrine\\Bundle\\DoctrineBundle\\Repository\\ServiceEntityRepository;
            use Doctrine\\Persistence\\ManagerRegistry;

            /**
             * @extends ServiceEntityRepository<{$entity}>
             */
            class {$entity}Repository extends ServiceEntityRepository
            {
                public function __construct(ManagerRegistry \$registry)
                {
                    parent::__construct(\$registry, {$entity}::class);
                }
            }

            PHP);

        // Le dossier Entity n'est plus vide : on retire le marqueur
        $this->filesystem->remove([
            $this->projectDir."/src/$module/Entity/.gitkeep",
            $this->projectDir."/src/$module/Repository/.gitkeep",
        ]);

        $io->success(sprintf('Entité %s créée dans le module %s.', $entity, $module));
        $io->listing([$entityPath, $repoPath]);
        $io->text([
            'Étapes suivantes :',
            '  1. bin/console cache:clear',
            sprintf('  2. Ajouter des champs : bin/console make:entity \'\\App\\%s\\Entity\\%s\'', $module, $entity),
            '  3. bin/console make:migration',
        ]);

        return Command::SUCCESS;
    }
}
