<?php

/*
 * Déclare automatiquement le mapping Doctrine de chaque module :
 * tout dossier src/<Module>/Entity est pris en compte, sans rien modifier ici.
 * Après la création d'un nouveau module : bin/console cache:clear
 */

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $mappings = [];
    foreach (glob(dirname(__DIR__, 2).'/src/*/Entity', GLOB_ONLYDIR) ?: [] as $dir) {
        $module = basename(dirname($dir));
        $mappings[$module] = [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => '%kernel.project_dir%/src/'.$module.'/Entity',
            'prefix' => 'App\\'.$module.'\\Entity',
            'alias' => $module,
        ];
    }

    $container->extension('doctrine', ['orm' => ['mappings' => $mappings]]);
};
