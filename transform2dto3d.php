<?php

ini_set('memory_limit', '800M');
error_reporting(-1);

require 'transform2dto3d.inc.php';

transform2dto3d('input.jpg', 'output.jpg');
