<?php

/**
 * Assesses the performance of inlined code
 * with strict type enforcement.
 */

declare(strict_types = 1);

function accumulate(int $iMax) : float {
    $fAcum = 0.0;

    for ($i = 1; $i<$iMax; $i++) {
        $fAcum += 0.001 * $i;
    }
    return $fAcum;
}

$fAcum = accumulate(2000000000);
printf("%g\n", $fAcum);

