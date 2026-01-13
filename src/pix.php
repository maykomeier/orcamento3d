<?php
declare(strict_types=1);

function pix_crc16(string $payload): string {
    $crc = 0xFFFF;
    $strlen = strlen($payload);
    for ($c = 0; $c < $strlen; $c++) {
        $crc ^= ord($payload[$c]) << 8;
        for ($i = 0; $i < 8; $i++) {
            if ($crc & 0x8000) {
            $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
        } else {
            $crc = ($crc << 1) & 0xFFFF;
        }
    }
}
return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}

function pix_payload(string $key, string $name, string $city, float $amount, string $txid = '***'): string {
    $key = substr($key, 0, 77);
    $name = substr($name, 0, 25);
    $city = substr($city, 0, 15);
    $amountStr = number_format($amount, 2, '.', '');
    
    // Merchant Account Information (ID 26)
    $gui = 'br.gov.bcb.pix';
    $merchKey = '0014' . $gui . '01' . str_pad((string)strlen($key), 2, '0', STR_PAD_LEFT) . $key;
    $merchBlock = '26' . str_pad((string)strlen($merchKey), 2, '0', STR_PAD_LEFT) . $merchKey;

    // Additional Data Field Template (ID 62)
    $txidBlock = '05' . str_pad((string)strlen($txid), 2, '0', STR_PAD_LEFT) . $txid;
    $addBlock = '62' . str_pad((string)strlen($txidBlock), 2, '0', STR_PAD_LEFT) . $txidBlock;

    $p  = '000201'; // Payload Format Indicator
    $p .= $merchBlock;
    $p .= '52040000'; // Merchant Category Code
    $p .= '5303986';  // Transaction Currency (BRL)
    $p .= '54' . str_pad((string)strlen($amountStr), 2, '0', STR_PAD_LEFT) . $amountStr;
    $p .= '5802BR'; // Country Code
    $p .= '59' . str_pad((string)strlen($name), 2, '0', STR_PAD_LEFT) . $name;
    $p .= '60' . str_pad((string)strlen($city), 2, '0', STR_PAD_LEFT) . $city;
    $p .= $addBlock;
    $p .= '6304'; // CRC16 placeholder

    $p .= pix_crc16($p);
    return $p;
}