<?php
/**
* @Generated -- DO NOT MODIFY
* {{site}} config file
**/

{% for v in vars %}
define('{{v.name|upper}}', '{{v.value}}');
{% endfor %}

$table_prefix  = '{{ db.prefix }}_';

/* C'est tout, ne touchez pas à ce qui suit ! Bon blogging ! */

/** Chemin absolu vers le dossier de WordPress. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Réglage des variables de WordPress et de ses fichiers inclus. */
require_once(ABSPATH . 'wp-settings.php');
?>