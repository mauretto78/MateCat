<?php

namespace Features\Dqf\Utils;

class AuthenticatorDataEncryptor {

    /**
     * @param string $code
     *
     * @return string
     */
    public static function decrypt( $code ) {
        $code = base64_decode( $code );

        $key = \INIT::$DQF_ENCRYPTION_KEY;
        $iv  = \INIT::$DQF_ENCRYPTION_IV;

        $td = mcrypt_module_open( MCRYPT_RIJNDAEL_128, '', 'cbc', $iv );

        mcrypt_generic_init( $td, $key, $iv );
        $decrypted = mdecrypt_generic( $td, $code );

        $decrypted = self::pkcs5_unpad( $decrypted );
        mcrypt_generic_deinit( $td );
        mcrypt_module_close( $td );

        return utf8_encode( trim( $decrypted ) );
    }

    /**
     * @param string $code
     *
     * @return string
     */
    public static function encrypt( $code ) {
        $size  = mcrypt_get_block_size( MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC );
        $input = self::pkcs5_pad( $code, $size );

        $key = \INIT::$DQF_ENCRYPTION_KEY;

        $td = mcrypt_module_open( MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '' );
        $iv = \INIT::$DQF_ENCRYPTION_IV;

        mcrypt_generic_init( $td, $key, $iv );
        $data = mcrypt_generic( $td, $input );
        mcrypt_generic_deinit( $td );
        mcrypt_module_close( $td );
        $data = base64_encode( $data );

        return $data;
    }

    /**
     * @param string $text
     * @param string $blocksize
     *
     * @return string
     */
    private static function pkcs5_pad( $text, $blocksize ) {
        $pad = $blocksize - ( strlen( $text ) % $blocksize );

        return $text . str_repeat( chr( $pad ), $pad );
    }

    /**
     * @param string $text
     *
     * @return bool|string
     */
    private static function pkcs5_unpad( $text ) {
        $pad = ord( $text{strlen( $text ) - 1} );

        if ( $pad > strlen( $text ) ) {
            return false;
        }

        if ( strspn( $text, chr( $pad ), strlen( $text ) - $pad ) != $pad ) {
            return false;
        }

        return substr( $text, 0, -1 * $pad );
    }
}