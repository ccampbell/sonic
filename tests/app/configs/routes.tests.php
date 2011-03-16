<?php
$routes = array(
    '/' => array('tests', 'index'),
    '/random' => array('tests', 'random', array('ajax' => true, 'magic' => false)),
    '/lesson/:lesson_name' => array('lesson', 'main'),
    '/artist/*name' => array('artist', 'main'),
    '/profile/#user_id' => array('profile', 'user', array('magic' => true)),
    'r:\/regex(\/(\w+))?$' => array('regex', 'index', array(2 => 'var')),
    '/word/@word' => array('word', 'main'),
    '/word/@word/translate/:language' => array('word', 'translate'),
    '/something-something' => array('tests', 'something'),
    '/special/:CONTROLLER/:ACTION' => array(),
    '/special/:ACTION' => array('special'),
    'r:/action/(one|two|three|dash-test)$' => array('action', 'not_found', array(1 => 'ACTION'))
);
