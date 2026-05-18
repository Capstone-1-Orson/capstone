<?php
session_start();

session_unset();
session_destroy();

header("Location: ../login-v2.html");
exit();