<?php

require_once 'config.php';

if (!isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_USER'] !== $simple_auth_user || $_SERVER['PHP_AUTH_PW'] !== $simple_auth_pass ) {
   header('WWW-Authenticate: Basic realm="My Realm"');
   header('HTTP/1.0 401 Unauthorized');
   echo 'Unauthorized';
   exit;
}
