<?php
$config = array(
    'global' => array(
        'debug' => 0,
        'use_analytics' => 0
    ),
    'production : global' => array(
        'use_analytics' => 1
    ),
    'staging' => array(
        'staging' => 1
    ),
    'dev : global' => array(
        'debug' => 1
    ),
    'user : dev' => array(
        'name' => 'user'
    )
);
