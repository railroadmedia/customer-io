<?php

return [
    // database
    'database_connection_name' => 'mysql',
    'data_mode' => 'host', // 'host' or 'client', hosts do the db migrations, clients do not

    // customer.io accounts configuration
    'accounts' => [
        'musora' => [
            'api_key' => '',
            'workspace_name' => '',
            'workspace_id' => '',
            'site_id' => '',
        ],
        'singeo' => [
            'api_key' => '',
            'workspace_name' => '',
            'workspace_id' => '',
            'site_id' => '',
        ],
    ],

    // form names and configuration
    'forms' => [
        'Example Form Name' => [
            'custom_attributes' => [
                'attribute_to_sync_1' => 'my attribute value 1',
                'attribute_to_sync_2' => 'my attribute value 2',
            ],
            'events' => [
                'event_to_sync_1',
                'event_to_sync_2',
            ],
            'accounts_to_sync' => [
                'musora',
                'singeo',
            ],
        ]
    ]
];