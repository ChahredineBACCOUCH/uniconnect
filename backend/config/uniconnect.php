<?php

/*
|--------------------------------------------------------------------------
| Règles de la plateforme UniConnect
|--------------------------------------------------------------------------
|
| Valeurs de `regles_plateforme` (docs/conception/schema.sql). Tant que l'écran
| d'administration des règles (/admin/regles, hors v1.0) n'existe pas, elles
| sont fixées ici plutôt qu'en base.
|
*/

return [

    // Durées de visibilité des messages proposées à la création d'un espace,
    // en heures : 24 h, 7 jours, 30 jours. `null` (envoyé explicitement) = illimitée.
    'durees_visibilite_heures' => [24, 168, 720],

    // Durée appliquée si le créateur n'en choisit pas, par type d'espace.
    'duree_defaut_heures' => [
        'ORGANISATIONNEL' => 720,
        'PRIVE' => 168,
        'CHANNEL' => 720,
    ],

    // Une invitation non traitée expire au bout de ce délai.
    'delai_invitation_jours' => 14,

];
