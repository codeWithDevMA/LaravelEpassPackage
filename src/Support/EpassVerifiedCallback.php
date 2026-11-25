<?php
/**
 * @package Ghazali\EPassPackage
 * @version Production-Hardened-Encrypted
 * @integrity sha256:e450652a26eec3d6a664704e85d3ae02cebe08f77deeb8159e43bf11eb30f429
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
})('0ced38d02afafd32b148be3cc99d1d7871f5fe7aebf89ade32a83e19953dcb99860e8d4e7330681a03eb77d8dbc6eb1111e74a3ca08c9ee2428fe7f4f8e189527ecabacdcc450f6b9f4a132673f2ba5e57dceb576b976ac1d90ffa8e630605f8041f91663e98c7e05b05a691f34561972c17a39e176d3d800fa333fe3d78094d88bf25c6b218a0de46f61bd8388acf8252beed1dd2c020789a8b4dbba06e313aec99ad854701e4694aa4bbb8267cc79538cbe3849f7a702d46146654959aaa5b63c4dddaf4699fd0f379ba880b8c7a062c304634beb0c36f8bece9f27290eb1603a1f716b856bec2ebca98d77761ca45fde58e8873c56e6d6a572c19d1d4381183641f7e2f19ca32faaded4a2a4a98b9', '1b5b2bf5cdca5863eb351f4a0878a7d461b5fca76e91813e98e74591f7ec3df1', '9d7b4db12dd0bf7d187d32e6cfe415af65c702c624d63af8ca15feb45b27643c', 'daf8a5525d33d222636df764d5af0fbc', 'e450652a26eec3d6a664704e85d3ae02cebe08f77deeb8159e43bf11eb30f429');