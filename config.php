<?php
define("NC_SERVER", ""); // server.domain (do not specify protocol such as http or https!)
define("NC_USER", "deckbot");
define("NC_PASSWORD", "");
define("MAIL_SERVER", ""); // server.domain
define("MAIL_SERVER_FLAGS", "/no-validate-cert"); // flags needed to connect to server. Refer to https://www.php.net/manual/en/function.imap-open.php for a list of valid flags.
define("MAIL_USER", "incoming");
define("MAIL_PASSWORD", "");
define("DECODE_SPECIAL_CHARACTERS", true); //requires mbstring, if false special characters (like öäüß) won't be displayed correctly
?>
