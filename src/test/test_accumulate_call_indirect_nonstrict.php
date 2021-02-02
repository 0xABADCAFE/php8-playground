<?php

/**
 * Assesses the impact of indirect function call over inline without
 * strict type enforcement.
 */


function scale(int $i) : float {
    return 0.001 * $i;
}

/**
 * Contains a large, hard to unroll loop.
 */
function accumulate(int $iMax) : float {
    $cFunc = 'scale';
    $fAcum = 0.0;
    for ($i = 1; $i<$iMax; $i++) {
        $fAcum += $cFunc($i);
    }
    return $fAcum;
}

$fAcum = accumulate(2000000000);
printf("%g\n", $fAcum);

