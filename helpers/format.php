<?php

/**
 * Format a price
 * 
 * @param float $price
 * @param string $currency
 * @return string
 */
function format_price(float $price, string $currency = '?'): string
{
    return number_format($price, 2, ',', ' ') . ' ' . $currency;
}

/**
 * Format a date
 * 
 * @param string $date
 * @param string $format
 * @return string
 */
function format_date(string $date, string $format = 'd/m/Y H:i'): string
{
    return date($format, strtotime($date));
}
