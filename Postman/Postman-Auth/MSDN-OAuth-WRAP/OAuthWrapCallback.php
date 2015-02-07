<?php
require_once('init.php');
$handler = new OAuthWrapHandler();
$handler->processRequest();
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="-1" />
    <title>OAuth WRAP response</title>
</head>
<body onload="onLoad()">
<script type="text/javascript">
    var windowClose = window.close;
    window.close = function() {
        window.open("", "_self");
        windowClose();
    }
    function onLoad() {
            window.close();
    }
</script>
</body>
</html>