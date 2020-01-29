<?php

ob_start();

#==============================================================================
# Includes
#==============================================================================
require_once("conf/config.inc.php");
require_once("lib/vendor/defuse-crypto.phar");
require_once("lib/functions.inc.php");
if ($use_recaptcha) {
    require_once("lib/vendor/autoload.php");
}
require_once("lib/detectbrowserlanguage.php");
require_once("lib/vendor/PHPMailer/PHPMailerAutoload.php");
if ($use_pwnedpasswords) {
    require_once("lib/vendor/ron-maxweb/pwned-passwords/src/PwnedPasswords/PwnedPasswords.php");
}

#==============================================================================
# Error reporting
#==============================================================================
error_reporting(0);
if($debug) {
    error_reporting(E_ALL);
    // Important to get error details in case of SSL/TLS failure at connection
    ldap_set_option(NULL, LDAP_OPT_DEBUG_LEVEL, 7);
}

#==============================================================================
# Language
#==============================================================================
# Available languages
$languages = array();
if ($handle = opendir('lang')) {
    while (false !== ($entry = readdir($handle))) {
        if ( preg_match('/\.inc\.php$/', $entry) ) {
            $entry_lang = str_replace(".inc.php", "", $entry);
            # Only add language to possibilities if it is the default language or part of the allowed languages
            # empty $allowed_lang <=> all languages are allowed
            if ($entry_lang == $lang || empty($allowed_lang) || in_array($entry_lang, $allowed_lang) ) {
                array_push($languages, $entry_lang);
            }
        }
    }
    closedir($handle);
}
$lang = detectLanguage($lang, $languages);
require_once("lang/$lang.inc.php");
if (file_exists("conf/$lang.inc.php")) {
    require_once("conf/$lang.inc.php");
}

#==============================================================================
# PHP modules
#==============================================================================
# Init dependency check results variable
$dependency_check_results = array();

# Check PHP-LDAP presence
if ( ! function_exists('ldap_connect') ) { $dependency_check_results[] = "nophpldap"; }
else {
    # Check ldap_modify_batch presence if AD mode and password change as user
    if ( $ad_mode and $who_change_password === "user" and ! function_exists('ldap_modify_batch') ) { $dependency_check_results[] = "phpupgraderequired"; }
}

# Check PHP mhash presence if Samba mode active
if ( $samba_mode and ! function_exists('hash') and ! function_exists('mhash') ) { $dependency_check_results[] = "nophpmhash"; }

# Check PHP mbstring presence
if ( ! function_exists('mb_internal_encoding') ) { $dependency_check_results[] = "nophpmbstring"; }

# Check PHP xml presence
if ( ! function_exists('utf8_decode') ) { $dependency_check_results[] = "nophpxml"; }

# Check keyphrase setting
if ( ( ( $use_tokens and $crypt_tokens ) or $use_sms or $crypt_answers ) and ( empty($keyphrase) or $keyphrase == "secret") ) { $dependency_check_results[] = "nokeyphrase"; }
#==============================================================================
# Action
#==============================================================================
if (!isset($default_action)) { $default_action = "change"; }
if (isset($_GET["action"]) and $_GET["action"]) { $action = $_GET["action"]; }
else { $action = $default_action; }

# Available actions
$available_actions = array();
if ( $use_change ) { array_push( $available_actions, "change"); }
if ( $change_sshkey ) { array_push( $available_actions, "changesshkey"); }
if ( $use_questions ) { array_push( $available_actions, "resetbyquestions", "setquestions"); }
if ( $use_tokens ) { array_push( $available_actions, "resetbytoken", "sendtoken"); }
if ( $use_sms ) { array_push( $available_actions, "resetbytoken", "sendsms"); }

# Ensure requested action is available, or fall back to default
if ( ! in_array($action, $available_actions) ) { $action = $default_action; }

# Get source for menu
if (isset($_REQUEST["source"]) and $_REQUEST["source"]) { $source = $_REQUEST["source"]; }
else { $source="unknown"; }

#==============================================================================
# Other default values
#==============================================================================
if (!isset($ldap_login_attribute)) { $ldap_login_attribute = "uid"; }
if (!isset($ldap_fullname_attribute)) { $ldap_fullname_attribute = "cn"; }
if (!isset($pwd_forbidden_chars)) { $pwd_forbidden_chars = ""; }
if (!isset($hash_options)) { $hash_options = array(); }
if (!isset($samba_options)) { $samba_options = array(); }
if (!isset($ldap_starttls)) { $ldap_starttls = false; }

# Password policy array
$pwd_policy_config = array(
    "pwd_show_policy"         => $pwd_show_policy,
    "pwd_min_length"          => $pwd_min_length,
    "pwd_max_length"          => $pwd_max_length,
    "pwd_min_lower"           => $pwd_min_lower,
    "pwd_min_upper"           => $pwd_min_upper,
    "pwd_min_digit"           => $pwd_min_digit,
    "pwd_min_special"         => $pwd_min_special,
    "pwd_special_chars"       => $pwd_special_chars,
    "pwd_forbidden_chars"     => $pwd_forbidden_chars,
    "pwd_no_reuse"            => $pwd_no_reuse,
    "pwd_diff_login"          => $pwd_diff_login,
    "pwd_complexity"          => $pwd_complexity,
    "use_pwnedpasswords"      => $use_pwnedpasswords,
    "pwd_no_special_at_ends"  => $pwd_no_special_at_ends
);

if (!isset($pwd_show_policy_pos)) { $pwd_show_policy_pos = "above"; }
if (!isset($obscure_failure_messages)) { $obscure_failure_messages = array(); }

#==============================================================================
# Email Config
#==============================================================================
$mailer = new PHPMailer;
$mailer->Priority      = $mail_priority;
$mailer->CharSet       = $mail_charset;
$mailer->ContentType   = $mail_contenttype;
$mailer->WordWrap      = $mail_wordwrap;
$mailer->Sendmail      = $mail_sendmailpath;
$mailer->Mailer        = $mail_protocol;
$mailer->SMTPDebug     = $mail_smtp_debug;
$mailer->Debugoutput   = $mail_debug_format;
$mailer->Host          = $mail_smtp_host;
$mailer->Port          = $mail_smtp_port;
$mailer->SMTPSecure    = $mail_smtp_secure;
$mailer->SMTPAutoTLS   = $mail_smtp_autotls;
$mailer->SMTPAuth      = $mail_smtp_auth;
$mailer->Username      = $mail_smtp_user;
$mailer->Password      = $mail_smtp_pass;
$mailer->SMTPKeepAlive = $mail_smtp_keepalive;
$mailer->Timeout       = $mail_smtp_timeout;
$mailer->LE            = $mail_newline;

#==============================================================================
?>

<html lang="<?php echo $lang ?>">
<head>
    <title><?php echo $messages["title"]; ?></title>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="author" content="LDAP Tool Box" />
    <link rel="stylesheet" type="text/css" href="css/bootstrap.min.css" />
    <link rel="stylesheet" type="text/css" href="css/bootstrap-theme.min.css" />
    <!--<link rel="stylesheet" type="text/css" href="css/font-awesome.min.css" />-->
    <link href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet" integrity="sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN" crossorigin="anonymous">
    <link rel="stylesheet" type="text/css" href="css/self-service-password.css" />
    <link href="https://demo.uniclair.com/themes/FlamingParrot/images/favicon.ico" rel="shortcut icon" />
    <?php if (isset($background_image)) { ?>
     <style>
       html, body {
         background: url("https://demo.uniclair.com/themes/FlamingParrot/images/login/login-background-blue.png") no-repeat center fixed;
         background-size: cover;
       }
  </style>
<?php } ?>  
</head>
 <!-- ============================================================================ --> 
 <body class="homepage" data-user-locale="en_US" style="display: flex;
    flex-direction: column; min-height: 100vh; padding: 45px 0 0; background: #f6f6f6; color: #333; font-family: SourceSansPro,Helvetica,Arial,sans-serif; line-height: 1;" >
   <!-- <header style="display: flex; position: fixed; top: 0; left: 0; flex-direction: column; width: 100%; background: #fff; box-shadow: 0 1px 2px 0 rgba(0,0,0,0.1); z-index: 100000;">
      <div class="header-container" style="display: flex; justify-content: space-between;
       height: 45px;">
        <div class="header-nav" style="display: flex;">
          <img id="logo" src="https://demo.uniclair.com/themes/BurningParrot/images/organization_logo.png" alt="Logo" style="display: inline-block; flex: 0 0 auto;
          width: 250px; height: 45px; background: #054978">
        </div>   
      </div>
    </header>-->
  <!-- ============================================================================ -->  
<div class="container">

<div class="panel panel-success">
<div class="panel-body">

<?php if ( $show_menu ) {
    include("menu_copy.php");
} else { ?>
<div class="title alert alert-success text-center"><h1><?php echo $messages["title"]; ?></h1></div>
<?php } ?>

<?php
    if ( count($dependency_check_results) > 0 ) {
        foreach($dependency_check_results as $result) {
            ?>
            <div class="result alert alert-<?php echo get_criticity($result) ?>">
                <p><i class="fa fa-fw <?php echo get_fa_class($result) ?>" aria-hidden="true"></i> <?php echo $messages[$result]; ?></p>
            </div>
            <?php
        }
    } else {
        include("pages/$action.php");
    }
?>

</div>
</div>

</div>

<script src="js/jquery-3.3.1.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script>
    $(document).ready(function(){
        // Menu links popovers
        $('[data-toggle="menu-popover"]').popover({
            trigger: 'hover',
            placement: 'bottom',
            container: 'body' // Allows the popover to be larger than the menu button
        });
    });
</script>
</body>
</html>
<?php

ob_end_flush();
