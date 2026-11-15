<?php

namespace App\Shared\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

use function Symfony\Component\String\u;

/**
 * Crée le squelette d'un nouveau module : dossiers src/<Module>/*, contrôleur,
 * template, test fonctionnel et fiche de documentation.
 */
#[AsCommand(
    name: 'app:module:create',
    description: 'Crée un nouveau module (src/<Module>, templates/<module>, tests/<Module>, docs/modules/<module>.md)',
)]
class CreateModuleCommand extends Command
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
            ->addArgument('nom', InputArgument::REQUIRED, 'Nom du module en PascalCase, sans accent (ex. : Finances, PlanDeCarriere)')
            ->addOption('route', null, InputOption::VALUE_REQUIRED, 'Préfixe d\'URL (par défaut : nom en kebab-case, ex. /plan-de-carriere)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $module = (string) $input->getArgument('nom');

        if (!preg_match('/^[A-Z][A-Za-z0-9]+$/', $module)) {
            $io->error('Le nom doit être en PascalCase, sans espace ni accent (ex. : PlanDeCarriere).');

            return Command::FAILURE;
        }

        $srcDir = $this->projectDir.'/src/'.$module;
        if ($this->filesystem->exists($srcDir)) {
            $io->error(sprintf('Le module "%s" existe déjà (%s).', $module, 'src/'.$module));

            return Command::FAILURE;
        }

        $snake = u($module)->snake()->toString();
        $route = '/'.ltrim((string) ($input->getOption('route') ?? str_replace('_', '-', $snake)), '/');

        $files = [
            "src/$module/Controller/{$module}Controller.php" => $this->controller($module, $snake, $route),
            "src/$module/Entity/.gitkeep" => '',
            "src/$module/Repository/.gitkeep" => '',
            "src/$module/Form/.gitkeep" => '',
            "src/$module/Service/.gitkeep" => '',
            "templates/$snake/index.html.twig" => $this->template($module),
            "tests/$module/Controller/{$module}ControllerTest.php" => $this->test($module, $route),
            "docs/modules/$snake.md" => $this->doc($module, $snake, $route),
        ];

        foreach ($files as $path => $content) {
            $this->filesystem->dumpFile($this->projectDir.'/'.$path, $content);
        }

        $io->success(sprintf('Module "%s" créé.', $module));
        $io->listing(array_keys($files));
        $io->text([
            'Étapes suivantes :',
            sprintf('  1. bin/console cache:clear  (pour que Doctrine voie src/%s/Entity)', $module),
            sprintf('  2. bin/console make:entity \'App\\%s\\Entity\\MonEntite\'', $module),
            sprintf('  3. Compléter docs/modules/%s.md et la carte docs/modules.md', $snake),
        ]);

        return Command::SUCCESS;
    }

    private function controller(string $module, string $snake, string $route): string
    {
        return <<<PHP
            <?php

            namespace App\\{$module}\\Controller;

            use Symfony\\Bundle\\FrameworkBundle\\Controller\\AbstractController;
            use Symfony\\Component\\HttpFoundation\\Response;
            use Symfony\\Component\\Routing\\Attribute\\Route;
            use Symfony\\Component\\Security\\Http\\Attribute\\IsGranted;

            #[Route('{$route}', name: 'app_{$snake}_')]
            #[IsGranted('ROLE_USER')]
            class {$module}Controller extends AbstractController
            {
                #[Route('', name: 'index', methods: ['GET'])]
                public function index(): Response
                {
                    return \$this->render('{$snake}/index.html.twig');
                }
            }

            PHP;
    }

    private function template(string $module): string
    {
        return <<<TWIG
            {% extends 'base.html.twig' %}

            {% block title %}{$module} — KIINI SAWA{% endblock %}

            {% block body %}
                <h1>{$module}</h1>
            {% endblock %}

            TWIG;
    }

    private function test(string $module, string $route): string
    {
        return <<<PHP
            <?php

            namespace App\\Tests\\{$module}\\Controller;

            use App\\Compte\\Repository\\UserRepository;
            use Symfony\\Bundle\\FrameworkBundle\\Test\\WebTestCase;

            class {$module}ControllerTest extends WebTestCase
            {
                public function testAnonymousIsRedirectedToLogin(): void
                {
                    \$client = static::createClient();
                    \$client->request('GET', '{$route}');

                    \$this->assertResponseRedirects('/login');
                }

                public function testIndexRendersForLoggedUser(): void
                {
                    \$client = static::createClient();
                    \$user = static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com');
                    \$client->loginUser(\$user);
                    \$client->request('GET', '{$route}');

                    \$this->assertResponseIsSuccessful();
                }
            }

            PHP;
    }

    private function doc(string $module, string $snake, string $route): string
    {
        return <<<MD
            # Module {$module}

            **Statut** : en construction

            ## Rôle

            _À compléter : ce que le module apporte à l'utilisateur._

            ## Emplacements

            | Élément | Chemin |
            |---|---|
            | Code | `src/{$module}/` |
            | Templates | `templates/{$snake}/` |
            | Tests | `tests/{$module}/` |
            | URL | `{$route}` |

            ## Entités

            _Aucune pour l'instant._

            MD;
    }
}
