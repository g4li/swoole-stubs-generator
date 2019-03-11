<?php


namespace App;


/**
 * Interface Individual
 * @package App
 */
interface LineAnalyzer
{
    public static function matching(string $line): bool;
}