<?php

return [
    // Authorization & General Errors
    'not_authorized'        => 'Ei käyttöoikeutta',
    'forbidden_request'     => 'Pyyntö epäonnistui (403) – Kielletty',
    'model_not_configured'  => 'Malliluokkaa ei ole määritetty ohjaimelle.',
    'db_error'              => 'Tietokantavirhe',

    // Single Record CRUD
    'not_found'             => 'Kohdetta :item ei löytynyt.',
    'deleted'               => ':item poistettu onnistuneesti.',
    'delete_forbidden'      => 'Poisto epäonnistui (403) – Kielletty',
    'delete_dependent'      => 'Poisto epäonnistui (403) – Kielletty tai kohteella :item on riippuvaisia tietueita.',
    'delete_failed'         => 'Kohteen :item poisto epäonnistui: :error',

    // Bulk Operations
    'bulk_unauthorized'     => 'Luvaton joukkotoiminto.',
    'bulk_unrecognized'     => 'Tuntematon joukkotoimintopyyntö.',
    'bulk_enabled'          => ':items otettu käyttöön onnistuneesti.',
    'bulk_disabled'         => ':items poistettu käytöstä onnistuneesti.',
    'bulk_deleted'          => ':items poistettu onnistuneesti.',
    'bulk_none_selected'    => 'Kohteita :items ei ole valittu.',
    'bulk_activated'        => 'Valitut kohteet (:items) aktivoitu onnistuneesti.',
    'bulk_deactivated'      => 'Valitut kohteet (:items) poistettu käytöstä onnistuneesti.',

    // CSV Imports
    'import_file_error'     => 'Virhe avattaessa CSV-tuontitiedostoa',
    'import_type_invalid'   => 'Virhe – tuntematon tuontityyppi.',
    'import_empty'          => 'Tuontitiedosto on tyhjä, muutoksia ei tehty.',
    'import_removed'        => ':count poistettu',
    'import_updated'        => ':count päivitetty',
    'import_added'          => ':count lisätty',
    'import_skipped'        => ':count ohitettu',
    'import_no_changes'     => '0 muutosta',
];
