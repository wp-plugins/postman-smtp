<?php
require_once 'Postman-Core/PostmanEmailAddress.php';
$recipient = 'Kevin.Brine@pppg.com, Robert.Thomas@pppg.com, "Guice, Doug" <Doug.Guice@pppg.com>';
print PostmanEmailAddress::convertToArray($recipients);
