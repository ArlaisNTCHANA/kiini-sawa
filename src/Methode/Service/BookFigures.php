<?php

namespace App\Methode\Service;

/**
 * Les schémas du livre rendus interactifs : le texte de chaque étape est celui des tableaux et listes du livre
 * (`content/livre/CHAP_*.md`), mot pour mot ; `tests/Methode/BookFiguresTest.php` vérifie qu'il y figure toujours.
 * Utilisé par `methode/_figure.html.twig` (composant `shared/_cycle_figure.html.twig`) ; un schéma sans entrée ici reste une image.
 *
 * Chaque entrée : title, hint, chapter (fichier source), items = liste de {key, label, icon, color, summary, lines: [[titre, texte]]}.
 * Les textes sont cherchés dans le chapitre après retrait du balisage et des guillemets.
 */
final class BookFigures
{
    public const FIGURES = [
        'fig_convictions' => [
            'title' => 'Les trois niveaux de conviction', 'chapter' => 'CHAP_05',
            'hint' => 'Toucher un niveau. Beaucoup de conflits viennent de ce qu\'on défend une conviction circonstancielle comme si c\'était une conviction profonde.',
            'items' => [
                ['PROFONDE', 'Conviction profonde', 'mountain', 'var(--primary)', 'Le roc. Ce sur quoi tu ne transigeras jamais', [['Exemples', 'La souveraineté de Dieu, la valeur sacrée de la vie, la primauté de l\'amour, l\'intégrité'], ['Peut-elle changer ?', 'Très difficilement, voire jamais']]],
                ['RAISONNEE', 'Conviction raisonnée', 'scale', 'var(--gold)', 'Ce que tu tiens fermement à partir de l\'expérience, de la réflexion, des faits', [['Exemples', 'L\'épargne régulière protège ma famille'], ['Peut-elle changer ?', 'Oui, face à de nouveaux arguments sérieux']]],
                ['CIRCONSTANCIELLE', 'Conviction circonstancielle', 'cloud-sun', 'var(--fg-muted)', 'Ce que tu crois vrai aujourd\'hui, dans ta situation actuelle', [['Exemple', 'Ce n\'est pas le moment de changer de travail'], ['Peut-elle changer ?', 'Facilement, avec les circonstances']]],
            ],
        ],
        'fig_reseaux' => [
            'title' => 'Les cinq réseaux', 'chapter' => 'CHAP_15',
            'hint' => 'Toucher un réseau pour voir son rôle et son exemple biblique.',
            'items' => [
                ['ORIENTATION', 'Orientation', 'compass', 'var(--primary)', 'Mentors, guides, ceux qui ont marché avant toi', [['Son rôle', 'Te montrer la route, t\'aider à discerner'], ['Exemple biblique', 'Paul pour Timothée']]],
                ['CROISSANCE', 'Croissance', 'users', 'var(--success)', 'Pairs, compagnons de route', [['Son rôle', 'Te stimuler, apprendre ensemble'], ['Exemple biblique', 'Paul et Barnabas']]],
                ['SOUTIEN', 'Soutien', 'heart-handshake', 'var(--gold)', 'Amis proches, famille, conjoint', [['Son rôle', 'Te porter dans l\'épreuve, te consoler'], ['Exemple biblique', 'Jonathan pour David']]],
                ['RESPONSABILITE', 'Responsabilité', 'scale', 'var(--accent)', 'Ceux à qui tu rends des comptes', [['Son rôle', 'Te confronter avec amour, te garder fidèle'], ['Exemple biblique', 'Nathan pour David']]],
                ['ENVIRONNEMENT', 'Environnement', 'church', 'var(--fg-muted)', 'Ta communauté, ton église, ton cercle social', [['Son rôle', 'Façonner ton climat quotidien'], ['Exemple biblique', 'L\'Église d\'Antioche']]],
            ],
        ],
        'fig_heritage' => [
            'title' => 'Les trois héritages', 'chapter' => 'CHAP_20',
            'hint' => 'Toucher une dimension pour voir la question à se poser.',
            'items' => [
                ['VALEURS', 'Les valeurs', 'heart', 'var(--primary)', 'Ce que tes enfants, tes disciples, tes équipes auront appris de toi sur la manière de vivre', [['La question', 'Qu\'ont-ils vu dans ma façon de traiter les autres, de croire, de persévérer ?']]],
                ['OEUVRES', 'Les œuvres', 'hammer', 'var(--gold)', 'Les réalisations concrètes qui subsistent : un livre, une organisation, un programme, une maison', [['La question', 'Qu\'est-ce que je bâtis qui servira encore après moi ?']]],
                ['PERSONNES', 'Les personnes', 'users', 'var(--success)', 'Ceux que tu as formés, encouragés, envoyés', [['La question', 'Qui porte déjà mon influence dans des lieux que je n\'atteindrai jamais ?']]],
            ],
        ],
        'fig_horizons' => [
            'title' => 'Les trois horizons', 'chapter' => 'CHAP_11',
            'hint' => 'Toucher un horizon pour voir sa durée, son rôle et des exemples.',
            'items' => [
                ['COURT', 'Court terme', 'zap', 'var(--success)', '1 à 3 mois', [['Son rôle', 'Créer de l\'élan, prouver que ton plan est réel'], ['Exemples', 'Contacter deux mentors ce mois-ci ; commencer une routine matinale de 30 minutes']]],
                ['MOYEN', 'Moyen terme', 'route', 'var(--primary)', '6 mois à 2 ans', [['Son rôle', 'Mesurer ta progression, ajuster le cap'], ['Exemples', 'Terminer une formation clé ; maintenir une habitude de service pendant 6 mois']]],
                ['LONG', 'Long terme', 'telescope', 'var(--gold)', '3 à 10 ans', [['Son rôle', 'Rappeler pourquoi les petits gestes comptent'], ['Exemples', 'Publier un livre, lancer une organisation ; avoir formé et envoyé deux personnes']]],
            ],
        ],
        'fig_hygiene' => [
            'title' => 'Corps, âme, esprit', 'chapter' => 'CHAP_17',
            'hint' => 'Toucher une dimension pour voir ce qu\'elle demande.',
            'items' => [
                ['CORPS', 'Le corps', 'heart-pulse', 'var(--success)', 'Le temple. Prendre soin de ton corps est un acte d\'intendance, pas de vanité.', [['Le sommeil', '7 à 8 heures par nuit pour la plupart des adultes. Ce n\'est pas de la paresse, c\'est de la sagesse.'], ['L\'activité physique', 'les recommandations santé parlent d\'environ 150 minutes d\'activité modérée par semaine.'], ['L\'alimentation', 'ton énergie dépend directement de ce que tu consommes.'], ['La prévention', 'ne consulte pas seulement quand tu es malade.']]],
                ['AME', 'L\'âme', 'brain', 'var(--gold)', 'La santé mentale et émotionnelle. Prendre soin de ton âme est un acte de foi, pas de faiblesse.', [['Se poser quatre questions', 'Identifie tes sources de stress chronique. Médite la Parole et prends des temps de silence. Entretiens des relations qui te nourrissent. Accorde-toi du vrai repos, sans culpabilité.'], ['Si la période sombre dure', 'parle-en à un pasteur de confiance et consulte un professionnel de santé. Demander de l\'aide fait partie de la sagesse.']]],
                ['ESPRIT', 'L\'esprit', 'flame', 'var(--primary)', 'La source. La santé spirituelle n\'est pas une pratique parmi d\'autres : c\'est la source dont tout le reste s\'alimente.', [['La prière quotidienne', 'une conversation régulière avec Celui qui t\'a créé.'], ['La méditation de la Parole', 'laisser la vérité de Dieu renouveler ta pensée.'], ['La communauté', 'tu n\'es pas fait pour l\'isolement spirituel.']]],
            ],
        ],
        'fig_equation' => [
            'title' => 'L\'Équation du But, trois exemples du livre', 'chapter' => 'CHAP_04',
            'hint' => 'La formule : Je suis créé pour [verbe] afin d\'apporter [impact] chez [cible]. Ces phrases sont des interprétations utiles, pas des révélations.',
            'items' => [
                ['JOSEPH', 'Joseph', 'wheat', 'var(--gold)', 'Je suis créé pour administrer les ressources et optimiser les systèmes afin d\'apporter la préservation de la vie et l\'abondance chez ceux que je sers.', [['Verbe', 'administrer les ressources et optimiser les systèmes'], ['Impact', 'la préservation de la vie et l\'abondance'], ['Cible', 'ceux que je sers']]],
                ['NEHEMIE', 'Néhémie', 'brick-wall', 'var(--primary)', 'Je suis créé pour structurer et restaurer les systèmes effondrés afin d\'apporter la sécurité et la dignité chez mon peuple.', [['Verbe', 'structurer et restaurer les systèmes effondrés'], ['Impact', 'la sécurité et la dignité'], ['Cible', 'mon peuple']]],
                ['BARNABAS', 'Barnabas', 'hand-heart', 'var(--success)', 'Je suis créé pour identifier et investir dans le potentiel humain afin d\'apporter la restauration et la multiplication chez ceux que les autres ont abandonnés.', [['Verbe', 'identifier et investir dans le potentiel humain'], ['Impact', 'la restauration et la multiplication'], ['Cible', 'ceux que les autres ont abandonnés']]],
            ],
        ],
        'fig_cascade' => [
            'title' => 'La cascade de l\'alignement', 'chapter' => 'CHAP_21',
            'hint' => 'Elle se lit dans les deux sens : de haut en bas, comment ma vision va-t-elle se réaliser ? De bas en haut, pourquoi est-ce que je fais ceci aujourd\'hui ?',
            'items' => [
                ['BUT', 'But de Dieu', 'compass', 'var(--primary)', 'Pour quoi tu existes.', [['Exemple', 'Je suis créé pour équiper des leaders afin d\'apporter la transformation chez les jeunes de ma ville']]],
                ['VISION', 'Vision', 'eye', 'var(--gold)', 'L\'image de ton avenir.', [['Exemple', 'Dans cinq ans, une école de leadership forme chaque année cinquante jeunes']]],
                ['OBJECTIFS', 'Objectifs', 'target', 'var(--success)', 'Ce que tu dois accomplir, domaine par domaine.', [['Exemple', 'Lancer une première promotion de dix jeunes d\'ici 18 mois']]],
                ['SOUTIEN', 'Plans de soutien', 'layers', 'var(--accent)', 'Ce que tes Plans de Croissance, de Développement, d\'Hygiène, d\'Intendance et de Transmission apportent à chaque objectif.', [['Croissance', 'Caractère : affermir ma patience ; Réseau : trouver un mentor qui a déjà lancé une école'], ['Développement', 'Bâtir une compétence en conception de formation'], ['Hygiène', 'Protéger mon sommeil pour tenir la charge'], ['Intendance', 'Réserver deux soirées par mois et un budget de démarrage pour l\'atelier pilote']]],
                ['STRATEGIES', 'Stratégies', 'map', 'var(--primary-dark)', 'Comment tu vas t\'y prendre.', [['Exemple', 'Commencer par un atelier pilote dans mon église']]],
                ['HABITUDES', 'Habitudes', 'repeat', 'var(--fg-muted)', 'Ce que tu fais aujourd\'hui.', [['Exemple', 'Une heure de préparation chaque samedi matin']]],
            ],
        ],
    ];

    /** @return ?array{title: string, hint: string, chapter: string, items: list<array<string, mixed>>} */
    public static function get(string $name): ?array
    {
        $fig = self::FIGURES[$name] ?? null;
        if (!$fig) {
            return null;
        }
        $fig['items'] = array_map(fn ($i) => ['key' => $i[0], 'label' => $i[1], 'icon' => $i[2], 'color' => $i[3], 'summary' => $i[4], 'lines' => $i[5]], $fig['items']);

        return $fig;
    }
}
