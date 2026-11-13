<?php

return [
    // Authorization & General
    'forbidden_request'      => 'Pyyntö epäonnistui (403) – Kielletty',
    'delete_forbidden'       => 'Poisto epäonnistui (403) – Kielletty',
    'update_forbidden'       => 'Ei käyttöoikeutta tunnistetiedon päivittämiseen',
    'inst_forbidden'         => 'Voit määrittää tunnistetietoja vain omalle organisaatiollesi',
    'missing_args'           => 'Pyyntö epäonnistui – Parametreja puuttuu',
    'bulk_unrecognized'      => 'Tuntematon joukkotoimintopyyntö',

    // Create / Update / Delete
    'platform_required'      => 'Alustan määritys on pakollinen',
    'platform_unknown'       => 'Tuntematon tai määrittämätön alusta',
    'already_exists'         => 'Tälle palveluntarjoajalle ja organisaatiolle on jo olemassa tunnistetieto',
    'created'                => 'Tunnistetiedot luotu onnistuneesti',
    'updated'                => 'Tunnistetieto päivitetty onnistuneesti',
    'deleted'                => 'Tunnistetiedot poistettu onnistuneesti',
    'reference_error'        => 'Tunnistetiedon viitevirhe alustalle tai organisaatiolle',
    'platform_unavailable'   => 'Yleistä alustaa ei löytynyt tai raportti ei ole saatavilla',

    // Testing & Audit
    'decode_error'           => 'Virhe syötteen purkamisessa!',
    'type_required'          => 'Tyyppi on pakollinen parametri!',
    'invalid_html_response'  => 'Virheellinen vastaus – Odotettiin JSON-muotoa, mutta pyyntö palautti HTML-sivun',
    'invalid_json_response'  => 'Virheellinen vastaus – JSON-muotoa ei vastaanotettu – tarkista URL-osoite',
    'test_success'           => 'Pyyntövastaus vastaanotettu onnistuneesti',
    'status_request_failed'  => 'Palvelun tilapyyntö epäonnistui!',
    'audit_invalid_type'     => 'Virheellinen pyyntötyyppi kohteelle auditItems',
    'audit_instance_error'   => 'Virhe haettaessa nykyisen instanssin tietoja',
    'audit_path_undefined'   => 'Yleinen asetus reports_path on määrittämättä – Keskeytetään!',
    'audit_no_records'       => 'Ei vastaavia yhdistettyjä tunnistetietoja tarkastettavaksi.',
    'audit_invalid_column'   => 'Virheellinen pyyntö – saraketta ei ole olemassa',

    // Import
    'import_updated'         => ':count lisätty tai päivitetty',
    'import_incomplete'      => ' (:count oli puutteellisia)',
    'import_skipped'         => ':count ohitettu',
    'import_completed'       => 'COUNTER-tunnistetietojen tuonti valmis : :detail',
];
