<?php

/**
 * Assesses the impact of method function call over inline with
 * strict type enforcement.
 */

declare(strict_types = 1);

class Test {
    public function scale(int $i) : float {
        return 0.001 * $i;
    }
}

/**
 * Contains a large, hard to unroll loop.
 */
function accumulate(int $iMax) : float {
    $oTest = new Test;
    $fAcum = 0.0;
    for ($i = 1; $i<$iMax; $i++) {
        $fAcum += $oTest->scale($i);
    }
    return $fAcum;
}

$fAcum = accumulate(2000000000);
printf("%g\n", $fAcum);

