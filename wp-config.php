<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'wordpress_site_senac' );

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
define( 'AUTH_KEY',         'TO)2{&bb?he<H>wA3c{Dkh^ ]hj(qF<pGV!X9i4W ~W+5%-mU(g44hSGvR1;-F}N' );
define( 'SECURE_AUTH_KEY',  'izH3|R8K&*xYD8l|rl.c9/-ImS)Q^LRc$%a` Mw{&vHk,n>kS06.7;=U;_]XO:9q' );
define( 'LOGGED_IN_KEY',    '`37}dYZ(28~E07Ly>CGzhbpT!.t>jK1V!o`s~DG:hTW<keFb)t(7NEN09!TU]sdJ' );
define( 'NONCE_KEY',        '5EC$,Y-Jr__r$}1<::iz>ErF4dL8|]-|)%hQr3[356>)sT:kfv8y!De;S}2+9aD%' );
define( 'AUTH_SALT',        'C!j. }n),#MdDpn%d]bp]1A0!V_!mgNy+F!#.rqVOA[[H448<.b>vMWv~V=1XEj^' );
define( 'SECURE_AUTH_SALT', 'hEM_n!_o?:gMg:dO<Yoafa&tX1DV)gK=`Z4g6nuxbN7>jU]E4ev@*PS.j^U3vZH;' );
define( 'LOGGED_IN_SALT',   'ctNsN`+bbv%5X1~C>f)jBxPFlGt}Y,~Pc`%aSt;iE 0SF8d;.fkm_9blohQ^I@7]' );
define( 'NONCE_SALT',       'J*AazEZ:_C2mSVf1[LAxsQA=siIkO{cA4W@IFjRG>n?ND5DFUNZ6Sr-ziLqR[~gW' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
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
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
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
