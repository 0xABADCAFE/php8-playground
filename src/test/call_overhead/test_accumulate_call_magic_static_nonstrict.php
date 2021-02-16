<?php

/**
 * Assesses the impact of method function call over inline without
 * strict type enforcement.
 */

class Test {
    public static function __callStatic(string $n, array $args) : float {
        return 0.001 * $args[0];
    }
}

/**
 * Contains a large, hard to unroll loop.
 */
function accumulate(int $iMax) : float {
    $fAcum = 0.0;
    for ($i = 1; $i<$iMax; $i++) {
        $fAcum += Test::scale($i);
    }
    return $fAcum;
}

$fAcum = accumulate(2000000000);
printf("%g\n", $fAcum);

