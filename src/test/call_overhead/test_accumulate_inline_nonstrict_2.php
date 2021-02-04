<?php

/**
 * Assesses the performance of inlined code
 * without strict type enforcement.
 */

/**
 * Contains a large, hard to unroll loop.
 */
function accumulate(int $iMax) : float {
    $fAcum = 0.0;
    for ($i = 1; $i<$iMax; $i++) {
        $fAcum += 0.0005 * $i;
        $fAcum += 0.0005 * $i;
    }
    return $fAcum;
}

$fAcum = accumulate(1000000000);
printf("%g\n", $fAcum);

