<?php
session_start();

session_unset();
session_destroy();

header("Location: ../Frontend/login-v2.html");
exit();