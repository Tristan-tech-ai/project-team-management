<?php

// Yajra DataTables options that differ from the package defaults (the rest are merged from the package config).
// Escaping happens once, in the browser: every column is drawn through the renderers in resources/js/table.js, which put text in the
// page as text (DataTable.render.text() or textContent). Escaping on the server as well made "Testing & Deployment" read "Testing &amp; Deployment".
return [
    'columns' => [
        'excess' => ['rn', 'row_num'],
        'escape' => [],
        'raw' => ['action'],
        'blacklist' => ['password', 'remember_token'],
        'whitelist' => '*',
    ],
];
