<?php
require 'system/CRUD.php';
$db = new CRUD();
$user = $db->table('users')->where('id', 5)->first();
print_r($user);