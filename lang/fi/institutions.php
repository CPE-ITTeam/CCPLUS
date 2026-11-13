<?php

return [
    // Shared / General
    'not_authorized'           => 'Ei käyttöoikeutta',
    'no_authorization'         => 'Ei valtuutusta',
    'update_forbidden'         => 'Päivitys epäonnistui (403) – Kielletty',
    'create_forbidden'         => 'Luonti epäonnistui (403) – Kielletty',
    'missing_input'            => 'Syötearvot puuttuvat',
    'unrecognized_action'      => 'Tuntematon toiminto',
    'server_error'             => 'Palvelinvirhe: :error',

    // Institutions
    'name_required'            => 'Nimi on pakollinen',
    'local_id_exists'          => 'Paikallinen tunniste on jo määritetty toiselle organisaatiolle.',
    'status_required'          => 'Tilaparametri on pakollinen päivitystä varten',
    'settings_updated'         => 'Asetukset päivitetty onnistuneesti',
    'created'                  => 'Organisaatio luotu onnistuneesti',
    'deleted'                  => 'Organisaatio poistettu onnistuneesti',
    'not_found_active'         => 'Organisaatiotietuetta ei löytynyt aktiivisesta ympäristöstä.',
    'delete_server_error'      => 'Palvelimen poistovirhe: :error',
    'import_success'           => 'Tuonti onnistui',

    // Institution Bulk Actions
    'bulk_status_updated'      => 'Tila päivitetty',
    'bulk_group_exists'        => 'Ryhmä on jo olemassa',
    'bulk_group_not_found'     => 'Ryhmää ei löytynyt',
    'bulk_group_assigned'      => 'Ryhmä määritetty',
    'bulk_type_assigned'       => 'Tyypin määritys onnistui',
    'bulk_deleted'             => 'Organisaatiot poistettu onnistuneesti',

    // Institution Types
    'type_duplicate'           => 'Toinen samanniminen tyyppi on jo olemassa',
    'type_created'             => 'Organisaatiotyyppi luotu onnistuneesti',
    'type_updated'             => 'Organisaatiotyyppi päivitetty onnistuneesti',
    'type_default_protected'   => 'Oletusorganisaatiotyyppiä ei voi poistaa.',
    'type_in_use'              => 'Tyyppiä ei voi poistaa, kun siihen on liitetty organisaatioita.',
    'type_import_success'      => 'Organisaatiotyypit tuotu onnistuneesti',
    'type_import_skipped'      => ' (:count olemassa olevaa nimeä ohitettiin)',

    // Institution Groups
    'group_duplicate'          => 'Toinen samanniminen ryhmä on jo olemassa',
    'group_created'            => 'Organisaatioryhmä luotu onnistuneesti',
    'group_updated'            => 'Organisaatioryhmä päivitetty onnistuneesti',
    'group_updated_type_skip'  => 'Ryhmä päivitetty, mutta :count organisaatiota ohitettiin tai poistettiin tyyppirajoituksen vuoksi',
    'group_import_success'     => 'Organisaatioryhmät tuotu onnistuneesti',
    'group_import_skipped'     => ' (:count olemassa olevaa nimeä/tunnistetta ohitettiin)',
];
