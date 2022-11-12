
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


function getAuthCode($secret): string
{
    $secretKey = baseDecode($secret);
    $hash = hash_hmac('SHA1', chr(0) . chr(0) . chr(0) . chr(0) . pack('N*', floor(time() / 30)), $secretKey, true);
    $value = unpack('N', substr($hash, ord(substr($hash, -1)) & 0x0F, 4));
    $value = $value[1] & 0x7FFFFFFF;
    return str_pad($value % (10 ** 6), 6, '0', STR_PAD_LEFT);
}

function baseDecode($data): string
{
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $buffer = 0;
    $bufferSize = 0;
    $result = '';
    for ($i = 0, $iMax = strlen($data); $i < $iMax; $i++) {
        $position = strpos($characters, $data[$i]);
        $buffer = ($buffer << 5) | $position;
        $bufferSize += 5;
        if ($bufferSize > 7) {
            $bufferSize -= 8;
            $position = ($buffer & (0xff << $bufferSize)) >> $bufferSize;
            $result .= chr($position);
        }
    }
    return $result;
}
