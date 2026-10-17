<?php
require_once __DIR__ . '/config/db.php';

end_session();
redirect_to('/login.php');
