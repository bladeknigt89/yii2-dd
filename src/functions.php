<?php

declare(strict_types=1);

use bladeknigt89\dd\Dumper;
use Symfony\Component\VarDumper\VarDumper;

/*
 * Laravel's dd()/dump() are the Symfony VarDumper helpers. Define the same
 * contract when this package is loaded without those helpers, then install
 * the Yii-aware handler those helpers call into.
 */

if (!function_exists('dump')) {
    /**
     * Dump the given values and keep running.
     *
     * A single value is returned as-is. Several values are returned as a list.
     * An empty call dumps a marker and returns null.
     */
    function dump(mixed ...$vars): mixed
    {
        return Dumper::dump(...$vars);
    }
}

if (!function_exists('dd')) {
    /**
     * Dump the given values and stop the script.
     *
     * Web requests get HTTP 500 before the dump when the headers are still
     * open. The process then exits with status 1.
     */
    function dd(mixed ...$vars): never
    {
        Dumper::dd(...$vars);
    }
}

if (class_exists(VarDumper::class)) {
    Dumper::register();
}
