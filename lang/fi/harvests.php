<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Harvest Log & Queue Messages
    |--------------------------------------------------------------------------
    */

    'errors' => [
        'not_authorized'         => 'Ei käyttöoikeutta!',
        'not_authorized_hyphen'  => 'Virhe – Ei käyttöoikeutta',
        'update_failed'          => 'Päivitys epäonnistui (:error)',
        'consortium_undefined'   => 'Virhe: Nykyistä konsortiota ei ole määritetty.',
        'reports_path_unset'     => 'Järjestelmää ei ole määritetty tallentamaan raakadataa, tarkista CCP_REPORTS-asetus.',
        'raw_file_inaccessible'  => 'Raakadatatiedosto ei ole käytettävissä.',
        'invalid_date_range'     => 'Alku- tai loppupäivämäärä on virheellinen tai puuttuu!',
        'missing_consortium'     => 'Virhe: konsortio puuttuu',
        'missing_credentials'    => 'Virhe: tunnistetiedot puuttuvat tai ovat virheellisiä',
        'missing_reports'        => 'Virhe: raportit puuttuvat',
        'no_records_created'     => 'Keräyslokitietueita ei luotu, tarkista palveluntarjoaja ja tunnistetiedot.',
        'invalid_bulk_inputs'    => 'Joukkotoiminnolle annettiin virheelliset syötteet!',
        'invalid_bulk_status'    => 'Joukkotoiminnolle pyydettiin virheellinen tila!',
        'global_job_create_fail' => 'Virhe luotaessa tehtävämerkintää yleiseen tauluun!',
        'unknown_bulk_action'    => 'Tuntematon joukkotoimintopyyntö',
        'duplicate_skipped'      => 'Keräys on jo ajastettu raportille :report ja kuukaudelle :yearmon, ohitetaan...',
        'create_record_failed'   => 'Keräystietueen luonti epäonnistui: :error',
    ],

    'messages' => [
        'updated'             => 'Keräysloki päivitetty onnistuneesti',
        'deleted'             => 'Lokitietue poistettu onnistuneesti',
        'created_count'       => ':count keräyslokitietuetta luotu onnistuneesti',
        'restarted_count'     => ':count keräystä käynnistetty uudelleen onnistuneesti',
        'as_release'          => ' versiona :release',
        'skipped_count'       => ', ja :count keräystä ohitettiin',
        'release_unavailable' => ' (versio :release ei välttämättä ole saatavilla).',
        'no_harvests_changed' => 'Valittuja keräyksiä ei muutettu',
        'no_harvests_release' => ', versio :release ei välttämättä ole saatavilla',
    ],

    'filters' => [
        'no_error' => 'Ei virhettä',
    ],

    'statuses' => [
        'Success'    => 'Onnistui',
        'Queued'     => 'Jonossa',
        'Harvesting' => 'Kerätään',
        'Pending'    => 'Odottaa',
        'Paused'     => 'Keskeytetty',
        'Stopped'    => 'Pysäytetty',
        'NoRetries'  => 'Epäonnistui – Ei uudelleenyrityksiä',
        'New'        => 'Uusi',
        'ReQueued'   => 'Uudelleen jonossa',
        'Waiting'    => 'Odottaa käsittelyä',
        'Processing' => 'Käsitellään',
    ],

];
