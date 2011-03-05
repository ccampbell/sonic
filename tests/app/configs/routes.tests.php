<?php
$routes = array(
    '/' => array('tests', 'index'),
    '/random' => array('tests', 'random', array('ajax' => true, 'magic' => false)),
    '/lesson/:lesson_name' => array('lesson', 'main'),
    '/artist/*name' => array('artist', 'main'),
    '/profile/#user_id' => array('profile', 'user', array('magic' => true)),
    '/word/@word' => array('word', 'main'),
    '/word/@word/translate/:language' => array('word', 'translate'),
    '/something-something' => array('tests', 'something')
);
