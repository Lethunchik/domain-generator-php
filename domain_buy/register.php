<?php
if (empty($_GET['domain'])) {
    die('Domain parameter is required');
}

$domain = urlencode($_GET['domain']);
header("Location: https://hb.by/domains?domain=$domain");
exit;
