<?php

/**
 * Assesses the impact of closure function call over inline without
 * strict type enforcement.
 */

/**
 * Contains a large, hard to unroll loop.
 */
function accumulate(int $iMax) : float {
    $cFunc = function(int $i) : float {
        return 0.001 * $i;
    };
    $fAcum = 0.0;
    for ($i = 1; $i<$iMax; $i++) {
        $fAcum += $cFunc($i);
    }
    return $fAcum;
}

$fAcum = accumulate(2000000000);
printf("%g\n", $fAcum);

