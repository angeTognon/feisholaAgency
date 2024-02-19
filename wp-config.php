<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/documentation/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'wordpress' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'M|oM9`tJ-20#X{v>,a0U>#[ANuh>9W%HxU} .*>!}.z+ZXUmdGs^&>jWD;=z(e&.' );
define( 'SECURE_AUTH_KEY',  'j$-7{Uq%j=3a.VYwgG]n9l]P{uz/x7#QHUSm$AT~ljr;#=I0ia}nkvh<I.c,^m4>' );
define( 'LOGGED_IN_KEY',    '(MnbX<L<e}qUIhb ]y,+gZnkqXIL+Ru@opKCUx0i;m[_6`[U2;X?X3>bQ],K:b5*' );
define( 'NONCE_KEY',        '8G(9Iatz!/lHW nS~}eKrGNjGL4o66H2h71lp;-D[mL@bG-BEMPN<2b^n8*Q4>rS' );
define( 'AUTH_SALT',        'eXx6[<r!p(`nNYw70nU5x%(CxW_R `>QzpSP])JFk#NGrpm8l[21:HBEi^Ye`uE4' );
define( 'SECURE_AUTH_SALT', 'UZzg*;d>B-;!r_s$F8Haz=Fu=ZC{rCb`0oB>kAfw-e$C=GSr4:4F}@P_U>&|UM:0' );
define( 'LOGGED_IN_SALT',   'C1QyVgu$Q+;JE(Ap?tne:NJNa$.4S+j}@DZ0PQ%ol`oEOTPLj@+%D`G)@OjnCz$F' );
define( 'NONCE_SALT',       'R|4+Hu!lq4We([K$-@]t+?)YTUw7%~>:LH|J689[$3&&>3Jc-^6n.#B4(25D!WBc' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/documentation/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
