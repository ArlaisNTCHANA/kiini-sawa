<?php

namespace App\Compte\Service;

use App\Compte\Entity\AccountToken;
use App\Compte\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Export RGPD (droit d'accès et de portabilité) : toutes les données liées à un utilisateur.
 *
 * Le parcours est générique : on part de l'utilisateur, puis on suit dans les métadonnées
 * Doctrine toutes les relations « enfant → parent » (ManyToOne / OneToOne) vers des lignes
 * déjà exportées, jusqu'à ne plus rien trouver. Un nouveau module est donc inclus
 * automatiquement dans l'export, sans rien modifier ici.
 */
final class UserDataExporter
{
    /** Champs jamais exportés. */
    private const SECRET_FIELDS = ['password', 'secretHash'];
    /** Entités techniques exclues de l'export. */
    private const EXCLUDED = [AccountToken::class];

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function export(User $user): array
    {
        /** @var array<class-string, array<int|string, object>> $owned */
        $owned = [User::class => [$user->getId() => $user]];
        $metas = array_filter(
            $this->em->getMetadataFactory()->getAllMetadata(),
            fn (ClassMetadata $m) => !$m->isMappedSuperclass && !$m->isEmbeddedClass && User::class !== $m->getName() && !\in_array($m->getName(), self::EXCLUDED, true)
        );

        do {
            $found = 0;
            foreach ($metas as $meta) {
                foreach ($meta->getAssociationMappings() as $field => $assoc) {
                    if (!$meta->isAssociationWithSingleJoinColumn($field) || empty($owned[$assoc['targetEntity']])) {
                        continue;
                    }
                    $rows = $this->em->createQueryBuilder()
                        ->select('e')->from($meta->getName(), 'e')
                        ->where("IDENTITY(e.$field) IN (:ids)")
                        ->setParameter('ids', array_keys($owned[$assoc['targetEntity']]))
                        ->getQuery()->getResult();
                    foreach ($rows as $row) {
                        $id = implode('-', $meta->getIdentifierValues($row));
                        if (!isset($owned[$meta->getName()][$id])) {
                            $owned[$meta->getName()][$id] = $row;
                            ++$found;
                        }
                    }
                }
            }
        } while ($found > 0);

        $data = [];
        foreach ($owned as $class => $rows) {
            if (User::class === $class) {
                continue;
            }
            $meta = $this->em->getClassMetadata($class);
            $data[$this->label($class)] = array_values(array_map(fn (object $row) => $this->normalize($meta, $row), $rows));
        }
        ksort($data);

        return [
            'export' => [
                'service' => 'KIINI SAWA',
                'date' => (new \DateTimeImmutable())->format(\DATE_ATOM),
                'description' => 'Ensemble des données associées à votre compte, regroupées par module.',
            ],
            'compte' => [
                'email' => $user->getEmail(),
                'creeLe' => $user->getCreatedAt()?->format(\DATE_ATOM),
                'emailVerifie' => $user->isVerified(),
                'conditionsAccepteesLe' => $user->getTermsAcceptedAt()?->format(\DATE_ATOM),
                'roles' => $user->getRoles(),
            ],
            'donnees' => $data,
        ];
    }

    /** « App\PlanDeVie\Entity\LifeGoal » → « PlanDeVie/LifeGoal ». */
    private function label(string $class): string
    {
        $parts = explode('\\', $class);

        return $parts[1].'/'.end($parts);
    }

    private function normalize(ClassMetadata $meta, object $row): array
    {
        $out = [];
        foreach ($meta->getFieldNames() as $field) {
            if (\in_array($field, self::SECRET_FIELDS, true)) {
                continue;
            }
            $out[$field] = $this->scalar($meta->getFieldValue($row, $field));
        }
        foreach ($meta->getAssociationMappings() as $field => $assoc) {
            if ($meta->isAssociationWithSingleJoinColumn($field)) {
                $target = $meta->getFieldValue($row, $field);
                $out[$field.'Id'] = $target ? $this->em->getUnitOfWork()->getSingleIdentifierValue($target) : null;
            }
        }

        return $out;
    }

    private function scalar(mixed $value): mixed
    {
        return match (true) {
            $value instanceof \DateTimeInterface => $value->format(\DATE_ATOM),
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \UnitEnum => $value->name,
            \is_array($value) => array_map(fn ($v) => $this->scalar($v), $value),
            \is_object($value) => method_exists($value, '__toString') ? (string) $value : null,
            default => $value,
        };
    }
}
