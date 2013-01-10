<?php
require "vendor/autoload.php";

/**
 * fetch array of keys from wordpress.org service
 **/
function wp_keys() {
    $src = curl("https://api.wordpress.org/secret-key/1.1/salt/");
    $keys = array();
    // array_{values,filter} used to remove last empty value left by split
    foreach(array_filter(explode("\n",$src),'trim') as $def) {
      preg_match('/^define..(.*)., +.(.*)..;$/',$def,$m); $keys[$m[1]] = $m[2];
    }
    return $keys;
}

// class Db(server,login,password,table)
class Db_{
  private $server;
  private $login;
  private $password;
  private $table;
  function Db($server,$login,$password,$table) {
    $this->$server = $server;
    $this->$login = $login;
    $this->$password = $password;
    $this->$table = $table;
  }
  public function getServer() { return $this->$server; }
  public function getLogin() { return $this->$login; }
  public function getPassword() { return $this->$password; }
  public function getTable() { return $this->$table; }
}

// class User(name,login,password)
class User{
  private $name;
  private $login;
  private $password;
  function User($name,$login,$passwordy) {
    $this->$name = $name;
    $this->$login = $login;
    $this->$password = $password;
  }
  public function getName() { return $this->$name; }
  public function getLogin() { return $this->$login; }
  public function getPassword() { return $this->$password; }
}

/**
   Site(
   name
   path
   Db(server,login,password,table)
   User(name,login,password)
   [Plugins(name,version,format)]
   [Themes(name)]
   State(fs,db)
   )
**/

class Site {
  private $name;
  private $Db;
  private $User;

  private $Plugins; // [($name,$version,$format)]
  private $Themes; // [($name)];
  private $State; // ($fs,$db)>;

  public function Site($name,$db,$user,$plugins,$themes) {
    $this->$name = $name;
    $this->$db = $db;
    $this->$user = $user;
    $this->$Plugins = $plugins;
    $this->$Themes = $themes;
  }
    
  /* ( */
  /*   #FS> */
  /*   Cache> */
  /*   HTTPServer> */
  /*   DBServer>SQLServer */
  /* ) */

  function Site_Deps($fs,$cache,$http,$db) {
    $this->$fs = $fs;
    $this->$cache = $cache;
    $this->$http = $http;
    $this->$db = $db;
  }

  /* path */
  /*   Server.root + name */

  function path($http) {
    return $this->http->$root + $this->$name;
  }
  
  /* fs.init */
  /*   exist or mkdir fs.path and chown fs.path xxx */
  function fs_init() {
    return exists($this->path())
      || exec("mkdir", $this->path())
      && exec("chown", $this->path());
  }

  /* http.init(http) */
  /*   http.new vhost default(fs.path) ;; allowoverride */
  /*   save new .htaccess (+) */
  /*     rewrite */
  /*     deny config, install, wp-admin, wp-includes */

  function http_init($http) {
    $v = $http->vhost($http->default_vhost($this->path()));
    $http.deploy_vhost($v);
    $h = $http->htaccess(rewrite,-config, -install, -wp-admin, -wp-includes);
  }
          
  /* db.init(sql) */
  /*   sql.new user login pass */
  /*   sql.new db name' */
  /*   sql.new table (rnd_prefix) name */

  function db_init($sql,$login,$pass) {
    $sql->user($login,$pass);
  }
    
  private function extract($zip, $tgt) {
    $zip = new ZipArchive;
    if ($zip->open($zip) === TRUE) {
      $zip->extractTo($tgt);
      $zip->close();
    }
  }

  /* ~ `mv $root/$leaf/* $root` */
  private function move_up($root, $leaf) {
    foreach (glob($root . PATH_SEPARATOR . $leaf . PATH_SEPARATOR . '*') as $d) {
      move($d, $root);
    }
  }

  /* app.init(cache) */
  /*   wp = cache get http://wordpress.org/download/release-archive/wordpress-(version).zip */
  /*   extract wp fs.path */
  /*   move fs.path (+) 'wordpress' fs.path */

  function app_init($cache, $version=0) {
    $base = "http://wordpress.org/download/release-archive/";
    $arch = ($version?"wordpress-".$version:"latest").".zip";
    $wp = $cache.get($base.$arch);
    $this->extract($wp,$this->path());
    $this->move_up($this->path(),'wordpress');
  }

  /*   ;; admin.name !"admin"/ */
  /*   save config fs.path (+) wp-config.php */
  private function config() {
    $base = array(
		  // f($db)
		  'DB_NAME' => $this->$db->getName(),
		  'DB_USER' => $this->$db->getUser(),
		  'DB_PASSWORD' => $this->$db->getPassword(),
		  'DB_HOST' => $this->$db->getHost(),
		  'DB_CHARSET' => '',
		  'DB_COLLATE' => '',
		  // ???
		  'WPLANG' => '',
		  'WP_HOME' => '',
		  'WP_SITEURL' => '',
		  //
		  'WP_PLUGIN_URL' => '',
		  'PLUGINDIR' => '',
		  'UPLOADS' => '',
		  //
		  'WP_DEBUG' => '',
		  );
    // append random security keys
    $vars = array_merge($base, wp_keys());
    $loader = new Twig_Loader_Filesystem('./tmpl/');
    $twig = new Twig_Environment($loader, array('cache' => './tmpl.cache'));
    return $template->render('wp-config.php',
			     array('site' => $this->name, 'vars' => $vars));
  }
  
  function deploy_config($fs) {
    $fs.save($this->config($db), $this->path . PATH_SEPARATOR . 'wp-config.php');
  }
  
  /* app.plugins.init(cache) */
  /*   plugins | cache.get plugin,version,format | extract fs.path (+) config.plugins.path */
  function deploy_plugins($cache) {
    foreach ($plugins as $plugin) {
      $p = $cache.get($plugin);
      $fs.extract($p,$this->path . PATH_SEPARATOR . "wp-content" . PATH_SEPARATOR . "plugins");
    }
  }
  
  /* app.theme.init(cache) */
  /*   theme | cache.get theme | fs.extract fs.path (+) config.themes.path */
  function deploy_themes($cache) {
    foreach ($themes as $theme) {
      $fs.extract($plugin,$this->path . PATH_SEPARATOR . "wp-content" . PATH_SEPARATOR . "themes");
    }
  }

  /* state.init(sql,fs) */
  /*   sql.load state.sql */
  /*   ;; UPDATE wp_options SET option_value = 'tmpl.desc' WHERE option_name = 'template'; */
  /*   ;; UPDATE wp_options SET option_value = 'tmpl.name' WHERE option_name = 'stylesheet'; */
  /*   ;; UPDATE wp_options SET option_value = 'tmpl.name' WHERE option_name = 'current_theme'; */
  /*   extract state.tar fs.path (uploads) */
  function state_init($sql,$fs) {}

  /* * post plugins config */
  /*   activate plugins */
  /*   - wpml */
  /*   - slt */

}

class DB {
  private $conn;
  private $login;
  private $password;
  private $host;

  function DB($login, $password, $host='localhost') {
    $this->login = $login;
    $this->password = $password;
    $this->host = $host;
  }
      
  function database ($dbname) {
    try {
      $dbh = new PDO("mysql:host=$this->host", $this->login, $this->password);

      $dbh->exec("CREATE DATABASE `$dbname`; FLUSH PRIVILEGES;")
        or die(print_r($dbh->errorInfo(), true));

    } catch (PDOException $e) {
      die("DB ERROR: ". $e->getMessage());
    }
  }

  function user($user,$pass) {
    try {
      $dbh = new PDO("mysql:host=".$this->host, $this->login, $this->password);

      $dbh->exec("CREATE USER '$user'@'localhost' IDENTIFIED BY '$pass';") 
        or die('(db.user.create)'.print_r($dbh->errorInfo(), true));
      $dbh->exec("FLUSH PRIVILEGES;")
        or die('(db.user.flush)'.print_r($dbh->errorInfo(), true));
      
    } catch (PDOException $e) {
      die("DB ERROR: ". $e->getMessage());
    }
  }

  function grant ($user, $password, $database) {
    try {
      $dbh = new PDO("mysql:host=$this->host,dbname=$database", $this->login, $this->password);

      $dbh->exec("GRANT ALL ON `$database`.* TO '$user'@'localhost'; FLUSH PRIVILEGES;")
        or die(print_r($dbh->errorInfo(), true));

    } catch (PDOException $e) {
      die("DB ERROR: ". $e->getMessage());
    }
  }
}
  
class HTTP {
  private $root;
  function HTTP($root) {
    $this->$root = $root;
  }
  function vhost($site) {
    $loader = new Twig_Loader_String();
    $twig = new Twig_Environment($loader);
    $tmpl = '
NameVirtualHost *:80
<VirtualHost *:80>
    ServerAdmin admin@{{site.name}}
    DocumentRoot "{{http.root}}/{{site.name}}/"
    ServerName {{site.name}}
    ServerAlias www.{{site.name}}
    ErrorLog "/var/log/httpd/{{site.name}}-error_log"
    CustomLog "/var/log/httpd/{{site.name}}-access_log" common
    <Directory {{http.root}}/{{site.name}}>
	DirectoryIndex index.php
	Order deny,allow
	AllowOverride all
    </Directory>
</VirtualHost>';
    return $twig->render($tmpl,array('site' => $site, 'http' => $this));
  }
  function deploy_vhost() {
    $fs.save($this->vhost(),NULL);
    $this->register($this->vhost());
    error_log("HTTP.deploy_vhost not implemented");
  }
  function htaccess() {
    $loader = new Twig_Loader_String();
    $twig = new Twig_Environment($loader);
    $tmpl = '
# BEGIN WordPress @ {{site.name}}

Options All -Indexes

<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>

# END WordPress @ {{site.name}}
';
    return $twig->render($tmpl,array('site' => $this));
  }
  function deploy_htaccess() {
    $fs.save($this->htaccess(),NULL);
    error_log("HTTP.deploy_htaccess not implemented");
  }
}

class Cache {
  private $d = "/cache";

  function Cache($suffix) {
    $this->$d += '_'.$suffix;
    file_exists($this->$d) || mkdir($this->$d);
  }
    
  private function curl($url) {
    $ch = curl_init();
    $timeout = 5;
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
  }
    
  function get($url) {
    $fn = $this->$d . PATH_SEPARATOR . basename($url);
    if (!file_exists($fn)) { 
      $f = fopen($fn,"w");
      fwrite($f, curl($url));
    }
    return $fn;        
  }
}

/**
 * security
 remove unused plugins
 remove unused themes
 ** harden (fs.path)/.htaccess
 - no index
 - deny ...
 //limit indexing of directories 
 Options All -Indexes
     
 //protect the htaccess file, 
 //this is done by default with apache config file, 
 // but you never know.
 <files .htaccess>
 order allow,deny
 deny from all
 </files>
     
 //disable the server signature
 ServerSignature Off
     
 //limit file uploads to 10mb
 LimitRequestBody 10240000
     
 // protect wpconfig.php. 
 //If you followed step 6 this is not necessary.
 <files wp-config.php>
 order allow,deny
 deny from all
 </files>

 ** version hiding
 // remove version info from head and feeds
 function complete_version_removal() {
 return '';
 }
 add_filter('the_generator', 'complete_version_removal');
   
 ** Remove WordPress header outputs

 WordPress can add a lot of stuff in your header for various services, this will remove everything, but take care, it also removes some functionality ( for instance if someone is looking for your RSS feed). If you want to keep some just comment the line out.

 // remove junk from head
 remove_action('wp_head', 'feed_links', 2);
 remove_action('wp_head', 'feed_links_extra', 3);
 remove_action('wp_head', 'rsd_link');
 remove_action('wp_head', 'wlwmanifest_link');
 remove_action('wp_head', 'index_rel_link');
 remove_action('wp_head', 'parent_post_rel_link', 10, 0);
 remove_action('wp_head', 'start_post_rel_link', 10, 0);
 remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0);
 remove_action('wp_head', 'wp_generator');
 remove_action('wp_head', 'wp_shortlink_wp_head', 10, 0);
 remove_action('wp_head', 'noindex', 1);

 ** Deny bad query strings

 This script goes in your .htaccess and will attempt to prevent malicious string attacks on your site (XSS). Please be aware some of these strings might be used for plugins or themes and doing so will disable the functionality. This script from perishablepress is fairly safe to use and should not break anything important. A more advanced one can be found on askapache.com.

 # QUERY STRING EXPLOITS

 RewriteCond %{QUERY_STRING} ../    [NC,OR]
 RewriteCond %{QUERY_STRING} boot.ini [NC,OR]
 RewriteCond %{QUERY_STRING} tag=     [NC,OR]
 RewriteCond %{QUERY_STRING} ftp:     [NC,OR]
 RewriteCond %{QUERY_STRING} http:    [NC,OR]
 RewriteCond %{QUERY_STRING} https:   [NC,OR]
 RewriteCond %{QUERY_STRING} mosConfig [NC,OR]
 RewriteCond %{QUERY_STRING} ^.*([|]|(|)||'|"|;|?|*).* [NC,OR]
 RewriteCond %{QUERY_STRING} ^.*(%22|%27|%3C|%3E|%5C|%7B|%7C).* [NC,OR]
 RewriteCond %{QUERY_STRING} ^.*(%0|%A|%B|%C|%D|%E|%F|127.0).* [NC,OR]
 RewriteCond %{QUERY_STRING} ^.*(globals|encode|config|localhost|loopback).* [NC,OR]
 RewriteCond %{QUERY_STRING} ^.*(request|select|insert|union|declare|drop).* [NC]
 RewriteRule ^(.*)$ - [F,L]


 ** Character string filter for .htaccess

 I left this to the end due to the length, this blocks bad character matches ( mostly XSS) from messing about with your site, this goes in your root .htaccess file, some strings might break functionality.
   
 <fModule mod_alias.c>
 RedirectMatch 403 ^
 RedirectMatch 403 `
 RedirectMatch 403 {
 RedirectMatch 403 }
 RedirectMatch 403 ~
 RedirectMatch 403 &quot;
 RedirectMatch 403 $
 RedirectMatch 403 &lt;
 RedirectMatch 403 &gt;
 RedirectMatch 403 |
 RedirectMatch 403 ..
 RedirectMatch 403 //
 RedirectMatch 403 %0
 RedirectMatch 403 %A
 RedirectMatch 403 %B
 RedirectMatch 403 %C
 RedirectMatch 403 %D
 RedirectMatch 403 %E
 RedirectMatch 403 %F
 RedirectMatch 403 %22
 RedirectMatch 403 %27
 RedirectMatch 403 %28
 RedirectMatch 403 %29
 RedirectMatch 403 %3C
 RedirectMatch 403 %3E
 RedirectMatch 403 %3F
 RedirectMatch 403 %5B
 RedirectMatch 403 %5C
 RedirectMatch 403 %5D
 RedirectMatch 403 %7B
 RedirectMatch 403 %7C
 RedirectMatch 403 %7D
 # COMMON PATTERNS
 Redirectmatch 403 _vpi
 RedirectMatch 403 .inc
 Redirectmatch 403 xAou6
 Redirectmatch 403 db_name
 Redirectmatch 403 select(
 Redirectmatch 403 convert(
 Redirectmatch 403 /query/
 RedirectMatch 403 ImpEvData
 Redirectmatch 403 .XMLHTTP
 Redirectmatch 403 proxydeny
 RedirectMatch 403 function.
 Redirectmatch 403 remoteFile
 Redirectmatch 403 servername
 Redirectmatch 403 &amp;rptmode=
 Redirectmatch 403 sys_cpanel
 RedirectMatch 403 db_connect
 RedirectMatch 403 doeditconfig
 RedirectMatch 403 check_proxy
 Redirectmatch 403 system_user
 Redirectmatch 403 /(null)/
 Redirectmatch 403 clientrequest
 Redirectmatch 403 option_value
 RedirectMatch 403 ref.outcontrol
 # SPECIFIC EXPLOITS
 RedirectMatch 403 errors.
 RedirectMatch 403 config.
 RedirectMatch 403 include.
 RedirectMatch 403 display.
 RedirectMatch 403 register.
 Redirectmatch 403 password.
 RedirectMatch 403 maincore.
 RedirectMatch 403 authorize.
 Redirectmatch 403 macromates.
 RedirectMatch 403 head_auth.
 RedirectMatch 403 submit_links.
 RedirectMatch 403 change_action.
 Redirectmatch 403 com_facileforms/
 RedirectMatch 403 admin_db_utilities.
 RedirectMatch 403 admin.webring.docs.
 Redirectmatch 403 Table/Latest/index.
 </IfModule> 
**/

?>