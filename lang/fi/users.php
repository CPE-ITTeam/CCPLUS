<?php

return [
    // Shared / General
    'forbidden_request'        => 'Pyyntö epäonnistui (403) – Kielletty',
    'operation_forbidden'      => 'Toiminto epäonnistui (403) – Kielletty',
    'update_forbidden'         => 'Päivitys epäonnistui (403) – Kielletty',
    'delete_forbidden'         => 'Poisto epäonnistui (403) – Kielletty',
    'self_delete_forbidden'    => 'Oman tilin poisto on estetty (403); pyydä pääkäyttäjää avustamaan.',

    // Users
    'email_exists'             => 'Sähköpostiosoite on jo määritetty toiselle käyttäjälle',
    'role_db_error'            => 'Virhe lisättäessä roolia tietokantaan: :error',
    'created'                  => 'Käyttäjä luotu onnistuneesti',
    'updated'                  => 'Käyttäjäasetukset päivitetty onnistuneesti',
    'import_success'           => 'Tuonti onnistui, Käyttäjät',

    // Roles
    'role_invalid_refs'        => 'Toiminto epäonnistui – virheelliset viitteet',
    'role_not_authorized'      => 'Toiminto epäonnistui – ei käyttöoikeutta',
    'role_already_assigned'    => 'Käyttäjällä on jo pyydetty rooli.',
    'role_save_error'          => 'Virhe tallennettaessa tietokantaan: :error',
    'role_saved'               => 'Rooli tallennettu onnistuneesti',

    // Consortia / Instances
    'conso_admin_undefined'    => 'Palvelimen pääkäyttäjän tunnistetietoja ei ole määritetty oikein!',
    'conso_db_create_failed'   => 'Tietokantatilan luonti epäonnistui: :error',
    'conso_clone_failed'       => 'Tietokannan kloonaus epäonnistui! Kloonauskomentoja ei voi muodostaa taululle: :table',
    'conso_schema_failed'      => 'Rakenteen kopiointi epäonnistui taululle ":table": :error',
    'conso_seed_failed'        => 'Tietokannan alustus epäonnistui virhekoodilla: :code',
    'conso_grant_failed'       => 'Tietokantakäyttäjän käyttöoikeuksien määritys epäonnistui: :error',
    'conso_created'            => 'Uusi konsortio : :name luotu onnistuneesti.',
    'conso_missing_args'       => 'Parametreja puuttuu! – päivitystä ei tehty.',
    'conso_updated'            => 'Konsortio : :name päivitetty onnistuneesti.',
];
