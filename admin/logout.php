<?php
session_start();
session_destroy();
header('Location: /animeflix_fixed/public/index.php');
exit;