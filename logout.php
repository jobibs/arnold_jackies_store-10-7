<?php

require_once "config/auth.php";

session_unset();
session_destroy();

header("Location: /arnold_jackies_store/login.php");
exit;
