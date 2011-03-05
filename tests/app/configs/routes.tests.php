<?php
$routes = array(
    '/' => array('tests', 'index'),
    '/random' => array('tests', 'random', array('ajax' => true, 'magic' => false)),
    '/profile/:user_id' => array('profile', 'user', array('magic' => true)),
    '/profile/:user_id/songs' => array('profile', 'songs'),
    '/something-something' => array('tests', 'something')
);
