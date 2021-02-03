<?php

/**
 * Assesses the impact of direct function call over inline
 * without strict type enforcemen.
 */

function scale(int $i) : float {
    return 0.001 * $i;
}

/**
 * Contains a large, hard to unroll loop.
 */
function accumulate(int $iMax) : float {
    $fAcum = 0.0;

    for ($i = 1; $i<$iMax; $i++) {
        $fAcum += scale($i);
    }
    return $fAcum;
}

$fAcum = accumulate(2000000000);
printf("%g\n", $fAcum);

