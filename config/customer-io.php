<?php

return [
    // database
    'database_connection_name' => 'mysql',
    'data_mode' => 'host', // 'host' or 'client', hosts do the db migrations, clients do not

    // endpoint middleware
    'all_routes_middleware' => [],

    // By default if a user id is passed to the service or controller functions it will be synced to all customers
    // using this custom attribute name. Typically this should refer to your users ID in your own database.
    'customer_attribute_name_for_user_id' => 'user_id',

    // customer.io accounts configuration
    'accounts' => [
        'musora' => [
            'track_api_key' => 'musora_track_api_key_1',
            'app_api_key' => 'musora_app_api_key_1',
            'workspace_name' => 'musora_workspace_name_1',
            'workspace_id' => 'musora_workspace_id_1',
            'site_id' => 'musora_site_id_1',
        ],
        'singeo' => [
            'track_api_key' => 'singeo_track_api_key_1',
            'app_api_key' => 'singeo_app_api_key_1',
            'workspace_name' => 'singeo_workspace_name_1',
            'workspace_id' => 'singeo_workspace_id_1',
            'site_id' => 'singeo_site_id_1',
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
            // can sync to multiple accounts using this
            'accounts_to_sync' => [
                'musora',
            ],
        ]
    ]
];