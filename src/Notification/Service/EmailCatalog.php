<?php

namespace App\Notification\Service;

use App\Social\Entity\AccountabilityNudge;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Tous les e-mails que KIINI SAWA envoie, avec des données d'exemple, pour l'aperçu de l'administration
 * (Admin › Réglages › Aperçu des e-mails, 27/11/2026). Les gabarits sont les vrais ; seules les données sont fictives
 * (« Esther », « Samuel »…). Ajouter un e-mail à l'application : l'ajouter ici aussi (EmailPreviewTest rend chacun).
 */
final class EmailCatalog
{
    public const GROUPS = [
        'compte' => 'Le compte',
        'accueil' => 'Les deux premières semaines',
        'rappels' => 'Rappels',
        'academie' => 'L\'Académie',
        'paiement' => 'Abonnement et paiements',
        'proches' => 'Partenaires de Redevabilité, couple, organisation',
        'autres' => 'Défis, anniversaire, annonces',
    ];

    public function __construct(private readonly UrlGeneratorInterface $urls)
    {
    }

    /**
     * @return array<string, array{group: string, label: string, when: string, subject: string, template: string, context: array<string, mixed>}>
     */
    public function all(): array
    {
        $site = rtrim($this->urls->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL), '/');
        $common = ['name' => 'Esther', 'unsubscribeUrl' => $site.'/profil', 'actionUrl' => $site.'/dashboard'];
        $notice = fn (string $title, string $message, string $path, bool $transactional = false) => [
            'template' => 'notification/email/notification.html.twig',
            'subject' => $title,
            'context' => ['title' => $title, 'message' => $message, 'actionUrl' => $site.$path, 'transactional' => $transactional] + $common,
        ];
        $onboarding = fn (string $file, string $title, string $path, array $extra = []) => [
            'template' => 'notification/email/onboarding/'.$file.'.html.twig',
            'subject' => $title,
            'context' => ['actionUrl' => $site.$path, 'onboarded' => true, 'trialEnd' => new \DateTimeImmutable('+14 days')] + $extra + $common,
        ];
        $commitment = ['partnerName' => 'Samuel', 'targetLabel' => 'Lire la Bible chaque matin', 'why' => 'Rester enraciné', 'minDaysBetweenNudges' => 7];

        return [
            'code' => ['group' => 'compte', 'label' => 'Code de vérification', 'when' => 'À l\'inscription, et quand un membre demande un nouveau code.',
                'subject' => 'Votre code KIINI SAWA : 482913', 'template' => 'compte/email/code.html.twig', 'context' => ['name' => 'Esther', 'code' => '482913', 'isEmailChange' => false]],
            'reset' => ['group' => 'compte', 'label' => 'Mot de passe oublié', 'when' => 'Quand un membre clique sur « Mot de passe oublié ».',
                'subject' => 'Réinitialiser votre mot de passe KIINI SAWA', 'template' => 'compte/email/reset_password.html.twig', 'context' => ['name' => 'Esther', 'url' => $site.'/mot-de-passe/oublie']],
            'already' => ['group' => 'compte', 'label' => 'Adresse déjà inscrite', 'when' => 'Quand quelqu\'un essaie de créer un compte avec une adresse déjà inscrite.',
                'subject' => 'Vous avez déjà un compte KIINI SAWA', 'template' => 'compte/email/already_registered.html.twig', 'context' => ['name' => 'Esther', 'loginUrl' => $site.'/login', 'resetUrl' => $site.'/mot-de-passe/oublie']],
            'deleted' => ['group' => 'compte', 'label' => 'Compte supprimé', 'when' => 'Après la suppression d\'un compte (par le membre ou à sa demande).',
                'subject' => 'Votre compte KIINI SAWA a été supprimé', 'template' => 'compte/email/account_deleted.html.twig', 'context' => ['name' => 'Esther']],
            'admin_code' => ['group' => 'compte', 'label' => 'Code de connexion administrateur', 'when' => 'À chaque connexion d\'un administrateur.',
                'subject' => 'Code de connexion administrateur : 771204', 'template' => 'compte/email/admin_login_code.html.twig', 'context' => ['name' => 'Esther', 'code' => '771204']],

            'j0' => ['group' => 'accueil', 'label' => 'Jour 0 · Bienvenue', 'when' => 'Juste après la vérification de l\'adresse.'] + $onboarding('j0_bienvenue', 'Bienvenue dans KIINI SAWA', '/dashboard'),
            'j1' => ['group' => 'accueil', 'label' => 'Jour 1 · La vision', 'when' => 'Le lendemain de l\'inscription.'] + $onboarding('j1_vision', 'Où voulez-vous être dans 10 ans ?', '/plan-de-vie/assistant'),
            'j3r' => ['group' => 'accueil', 'label' => 'Jour 3 · Un premier rituel', 'when' => 'Trois jours après l\'inscription, si aucun rituel.'] + $onboarding('j3_rituel', 'Un seul petit rituel pour commencer', '/mes-plans/rituels'),
            'j3t' => ['group' => 'accueil', 'label' => 'Jour 3 · Un premier test', 'when' => 'Trois jours après l\'inscription, s\'il a déjà un rituel.'] + $onboarding('j3_test', 'Découvrez comment Dieu vous a fait', '/mes-tests/questionnaire/disc'),
            'j7' => ['group' => 'accueil', 'label' => 'Jour 7 · La revue de la semaine', 'when' => 'Une semaine après l\'inscription.'] + $onboarding('j7_revue', 'Votre première revue de la semaine', '/bilan/revue-hebdomadaire', ['hasPartner' => false]),
            'j9' => ['group' => 'accueil', 'label' => 'Jour 9 · Les autres plans', 'when' => 'Neuf jours après l\'inscription, pendant l\'essai PRO.'] + $onboarding('j9_pro', 'Votre vision a besoin d\'un plan', '/mes-plans'),

            'weekly' => ['group' => 'rappels', 'label' => 'Revue de la semaine', 'when' => 'Le jour choisi par le membre, s\'il n\'a pas fait sa revue.']
                + $notice('C\'est le moment de votre revue de la semaine', 'Quinze minutes pour regarder vos victoires, évaluer vos plans et choisir vos 3 priorités de la semaine prochaine.', '/bilan/revue-hebdomadaire'),
            'habits' => ['group' => 'rappels', 'label' => 'Rituels oubliés', 'when' => 'Après 3 jours sans rituel coché.']
                + $notice('Vos rituels vous attendent', 'Aucun rituel coché depuis 3 jours. Pas de culpabilité : reprenez simplement par le plus petit pas aujourd\'hui.', '/mes-plans/rituels'),
            'deadline' => ['group' => 'rappels', 'label' => 'Échéance dans 7 jours', 'when' => 'Sept jours avant la date d\'un objectif.']
                + $notice('Échéance dans 7 jours : Animer un groupe de jeunes', 'Votre objectif « Animer un groupe de jeunes » arrive à échéance bientôt. Où en êtes-vous ? Ajustez la date ou planifiez le dernier pas.', '/plan-de-vie'),
            'tip' => ['group' => 'rappels', 'label' => '« Saviez-vous que… »', 'when' => 'Au plus toutes les deux semaines : une fonction pas encore utilisée.',
                'subject' => 'Saviez-vous que vous pouvez faire le point chaque semaine ?', 'template' => 'notification/email/tip.html.twig',
                'context' => ['title' => 'Saviez-vous que vous pouvez faire le point chaque semaine ?', 'paragraphs' => ['La revue de la semaine prend une quinzaine de minutes : vous regardez vos victoires, vous évaluez vos plans et vous choisissez vos trois priorités pour la semaine suivante.', 'C\'est elle qui relie votre vision à votre agenda.'],
                    'actionLabel' => 'Faire ma revue de la semaine', 'actionUrl' => $site.'/bilan/revue-hebdomadaire', 'proNote' => null, 'footerText' => 'Vous recevez de temps en temps un message « Saviez-vous que… » pour découvrir une fonction de KIINI SAWA que vous n\'avez pas encore utilisée.', 'unsubscribeLabel' => 'Ne plus recevoir ces messages'] + $common],

            'seance' => ['group' => 'academie', 'label' => 'Séance du jour (parcours de plan)', 'when' => 'Au rythme choisi par le membre (chaque jour ou trois fois par semaine).',
                'subject' => 'Aujourd\'hui : vos convictions', 'template' => 'academie/email/seance.html.twig',
                'context' => ['course' => ['title' => 'Mon Plan de Vie'], 'progress' => ['done' => 1, 'total' => 8], 'lesson' => ['title' => 'Vos convictions', 'durationMinutes' => 10], 'last' => ['lesson' => 'Le But de Dieu pour ma vie', 'text' => '« Je suis créée pour encourager les jeunes de mon église… »'], 'verse' => '« Garde ton cœur plus que toute autre chose. » Proverbes 4:23', 'pace' => 'DAILY', 'minutes' => 10, 'actionUrl' => $site.'/academie'] + $common],
            'lecon' => ['group' => 'academie', 'label' => 'Leçon du jour (parcours thématique)', 'when' => 'Au rythme choisi par le membre.',
                'subject' => 'Votre leçon du jour : Ce qui compte', 'template' => 'academie/email/lecon.html.twig',
                'context' => ['course' => ['title' => 'Sous pression'], 'lesson' => ['title' => 'Les cinq voix qui prennent le volant', 'durationMinutes' => 10], 'progress' => ['done' => 0, 'total' => 8], 'pace' => 'DAILY', 'actionUrl' => $site.'/academie'] + $common],
            'pause' => ['group' => 'academie', 'label' => 'Rythme mis en pause', 'when' => 'Après trois e-mails de l\'Académie sans avancer.',
                'subject' => 'Votre rythme de l\'Académie est en pause', 'template' => 'academie/email/pause.html.twig',
                'context' => ['course' => ['title' => 'Sous pression'], 'actionUrl' => $site.'/academie'] + $common],

            'trial' => ['group' => 'paiement', 'label' => 'Fin de l\'essai dans 3 jours', 'when' => 'Trois jours avant la fin de l\'essai PRO.']
                + $notice('Votre essai PRO se termine dans 3 jours', 'Vos plans de Croissance, Développement personnel, Hygiène de vie, Intendance et Transmission passeront en lecture limitée. Gardez tout en choisissant une formule PRO, à partir de quelques centimes par jour. Rien ne vous sera prélevé automatiquement : sans choix de votre part, vous gardez simplement l\'accès gratuit.', '/abonnement'),
            'ending' => ['group' => 'paiement', 'label' => 'Fin d\'abonnement dans 7 jours', 'when' => 'Sept jours avant la fin d\'un abonnement PRO.']
                + $notice('Votre abonnement PRO se termine dans 7 jours', 'Pour continuer sans interruption, renouvelez dès maintenant depuis votre espace abonnement (virement, Mobile Money ou espèces) : les jours restants sont conservés.', '/abonnement'),
            'paid' => ['group' => 'paiement', 'label' => 'Paiement validé', 'when' => 'Quand vous validez un paiement dans Admin › Paiements.']
                + $notice('Paiement reçu : bienvenue en PRO !', 'Votre paiement de 79,99 € a été validé. Votre accès PRO est actif pour un an. Votre reçu est disponible dans votre espace abonnement.', '/abonnement', true),
            'rejected' => ['group' => 'paiement', 'label' => 'Paiement non retrouvé', 'when' => 'Quand vous refusez un paiement dans Admin › Paiements.']
                + $notice('Nous n\'avons pas retrouvé votre paiement', 'Votre déclaration de paiement n\'a pas pu être validée. Motif : aucun virement reçu avec cette référence. Vous pouvez la refaire depuis votre espace abonnement ou nous répondre.', '/abonnement', true),

            'partner_invite' => ['group' => 'proches', 'label' => 'Invitation d\'un Partenaire de Redevabilité', 'when' => 'Quand un membre choisit quelqu\'un comme Partenaire de Redevabilité (reçu par ce proche).',
                'subject' => 'Esther vous demande d\'être son Partenaire de Redevabilité', 'template' => 'social/accountability/email/invitation.html.twig',
                'context' => ['owner' => 'Esther', 'c' => $commitment, 'url' => $site.'/redevabilite']],
            'partner_nudge' => ['group' => 'proches', 'label' => 'Demande d\'encouragement', 'when' => 'Quand un membre demande un coup de main, ou qu\'un de ses signaux se déclenche (reçu par le proche).',
                'subject' => 'Esther compte sur vous', 'template' => 'social/accountability/email/nudge.html.twig',
                'context' => ['owner' => 'Esther', 'c' => $commitment, 'nudge' => ['ownerMessage' => 'Semaine difficile', 'fact' => null], 'actions' => AccountabilityNudge::ACTIONS, 'respondUrl' => $site.'/redevabilite', 'stopUrl' => $site.'/redevabilite']],
            'partner_copy' => ['group' => 'proches', 'label' => 'Copie au membre', 'when' => 'En même temps : la copie envoyée au membre lui-même.',
                'subject' => 'Copie du message envoyé à Samuel, votre Partenaire de Redevabilité', 'template' => 'social/accountability/email/nudge_copy.html.twig',
                'context' => ['owner' => 'Esther', 'c' => $commitment, 'nudge' => ['ownerMessage' => 'Semaine difficile', 'fact' => null], 'actions' => AccountabilityNudge::ACTIONS, 'manageUrl' => $site.'/social']],
            'couple' => ['group' => 'proches', 'label' => 'Invitation au Sanctuaire du Couple', 'when' => 'Quand un membre invite son conjoint.',
                'subject' => 'Esther vous invite à bâtir votre couple sur KIINI SAWA', 'template' => 'couple/email/invitation.html.twig',
                'context' => ['inviter' => 'Esther', 'url' => $site.'/couple']],
            'organisation' => ['group' => 'proches', 'label' => 'Invitation dans une organisation', 'when' => 'Quand un responsable invite quelqu\'un dans son église ou son association.',
                'subject' => 'Esther vous invite à rejoindre Église de l\'Espérance sur KIINI SAWA', 'template' => 'organisation/email/invitation.html.twig',
                'context' => ['inviter' => 'Esther', 'org' => ['name' => 'Église de l\'Espérance', 'isLicenseValid' => true], 'message' => 'Rejoins-nous, on avance ensemble cette année !', 'url' => $site.'/organisation']],

            'challenge_welcome' => ['group' => 'autres', 'label' => 'Inscription à un défi', 'when' => 'Quand un membre rejoint un défi collectif.',
                'subject' => 'Vous relevez le défi « 21 jours de discipline »', 'template' => 'defis/email/welcome.html.twig',
                'context' => ['challenge' => ['title' => '21 jours de discipline', 'durationDays' => 21, 'startDate' => new \DateTimeImmutable()], 'phase' => 'running', 'day' => 1, 'threshold' => 17] + $common],
            'challenge_day' => ['group' => 'autres', 'label' => 'Action du jour d\'un défi', 'when' => 'Chaque jour du défi, pour les participants.',
                'subject' => '21 jours de discipline · jour 5 : Prier 10 minutes', 'template' => 'defis/email/day.html.twig',
                'context' => ['challenge' => ['title' => '21 jours de discipline', 'durationDays' => 21], 'day' => 5, 'action' => 'Prier 10 minutes', 'progress' => 4] + $common],
            'birthday' => ['group' => 'autres', 'label' => 'Anniversaire', 'when' => 'Le jour de l\'anniversaire du membre, s\'il a donné sa date de naissance.',
                'subject' => 'Joyeux anniversaire, Esther !', 'template' => 'notification/email/birthday.html.twig',
                'context' => ['title' => 'Joyeux anniversaire, Esther !', 'message' => 'Une nouvelle année de vie commence. C\'est le bon moment pour relire votre Plan de Vie : ce qui vous fait vivre, ce que vous voulez bâtir, et la prochaine étape à franchir avec Dieu.', 'actionLabel' => 'Relire mon Plan de Vie', 'actionUrl' => $site.'/plan-de-vie'] + $common],
            'announcement' => ['group' => 'autres', 'label' => 'Annonce de l\'équipe', 'when' => 'Quand vous écrivez aux membres (Admin › Écrire aux membres).',
                'subject' => 'Nouveau parcours : « Sous pression »', 'template' => 'notification/email/announcement.html.twig',
                'context' => ['title' => 'Nouveau parcours : « Sous pression »', 'message' => "Bonjour à tous,\n\nUn nouveau parcours vous attend dans l'Académie : huit séances pour reconnaître les cinq voix qui nous poussent sous pression, et apprendre à dire non.", 'actionLabel' => 'Découvrir le parcours', 'actionUrl' => $site.'/academie'] + $common],
        ];
    }
}
