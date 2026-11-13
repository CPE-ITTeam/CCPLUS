<?php

return [
    // Authorization & General
    'forbidden_request'         => 'Pyyntö epäonnistui (403) – Kielletty',
    'invalid_access_values'     => 'Virheelliset käyttöoikeusarvot',
    'unknown_report'            => 'Tuntematon raporttipyyntö',
    'platform_ref_error'        => 'Yleisen alustan viitevirhe',
    'bulk_unrecognized'         => 'Tuntematon joukkotoimintopyyntö',

    // Connections
    'access_updated'            => 'Käyttöoikeudet päivitetty onnistuneesti',
    'none_deleted'              => 'Yhteyksiä ei poistettu – tarkista roolisi',
    'deleted_single'            => 'Yhteys poistettu onnistuneesti',
    'deleted_multiple'          => ':count yhteyttä poistettu onnistuneesti',
    'import_success'            => 'Tuonti onnistui, Yhteydet',

    // Global Providers / Platforms
    'platform_created'          => 'Alusta luotu onnistuneesti',
    'platform_updated'          => 'Alustan asetukset päivitetty onnistuneesti',
    'platform_deleted'          => 'Yleinen alusta poistettu onnistuneesti',
    'instance_db_error'         => 'Virhe yhdistettäessä instanssin :name tietokantaan!',
    'platform_import_success'   => 'Tuonti onnistui, Alustat',
    'bulk_updated'              => ':count tietuetta päivitetty onnistuneesti',
    'bulk_skipped'              => '; :count ohitettiin',
    'bulk_failed'               => '; :count epäonnistui',

    // COUNTER Registry Refresh
    'registry_fetching'         => 'Haetaan rekisteriluetteloa...',
    'registry_invalid_input'    => 'Päivityspyyntö epäonnistui – Virheellinen syöte!',
    'registry_not_refreshable'  => 'Pyydetyt alustat on merkitty ei-päivitettäviksi',
    'registry_request_failed'   => 'COUNTER-rekisteritietojen haku epäonnistui: ',
    'registry_json_failed'      => 'Virhe purettaessa rekisterin palauttamaa JSON-dataa!',
    'registry_empty_data'       => 'Rekisterin alustapyyntö ei palauttanut alustatietoja!',
    'registry_invalid_datatype' => 'Virhe haettaessa rekisteritietoja – vastaanotettu virheellinen tietotyyppi!',
    'registry_match_error'      => 'Virhe yhdistettäessä alustaa rekisteriin!',
    'registry_cancelled'        => 'Päivitys peruutettu',
    'registry_no_updates'       => 'Tietueita ei päivitetty',
    'registry_refresh_failed'   => 'Päivitys epäonnistui',
    'registry_err_success'      => 'Alusta päivitetty onnistuneesti',
    'registry_err_sushi'        => 'Rekisterivirhe – SUSHI-palvelut on määritetty virheellisesti',
    'registry_err_connection'   => 'Rekisterivirhe – COUNTER API -yhteystiedot ovat virheellisiä',
    'registry_err_no_match'     => 'CC+ Registry_ID -tunnisteelle ei löytynyt vastinetta rekisteristä',
    'registry_sum_refreshed'    => ':count alustaa päivitetty onnistuneesti',
    'registry_sum_disabled'     => ':count alustaa ohitettu (päivitys pois käytöstä)',
    'registry_sum_new'          => 'Uudet lisätyt alustat:',
    'registry_sum_no_id'        => 'Ohitetut alustat (ei rekisteritunnistetta):',
    'registry_sum_no_url'       => 'Alustat ilman palvelun URL-osoitetta:',
];
