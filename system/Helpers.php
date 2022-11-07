
<?php

/**
 * Simple shorter function to escape strings
 *
 * @param string $value
 * @return string
 */
function e($value)
{
    return htmlspecialchars($value, ENT_QUOTES);
}
