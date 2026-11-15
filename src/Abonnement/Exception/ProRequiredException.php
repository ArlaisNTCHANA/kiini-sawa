<?php

namespace App\Abonnement\Exception;

/**
 * Levée quand une fonction ou une limite est réservée à PRO.
 * Convertie en redirection vers /abonnement (ou en réponse JSON 402) par ProRequiredSubscriber.
 */
final class ProRequiredException extends \RuntimeException
{
    public function __construct(public readonly string $feature, string $message = '')
    {
        parent::__construct($message ?: 'Cette fonction fait partie de KIINI SAWA PRO.');
    }
}
