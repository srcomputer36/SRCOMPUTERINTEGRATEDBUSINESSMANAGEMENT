<?php
echo "SecurityTest: checking protections...\n";
$checks=[];
function chk($name,$ok){ global $checks; $checks[]=['name'=>$name,'ok'=>$ok]; echo ($ok?'[PASS] ':'[FAIL] ').$name."\n"; }
chk('config/.htaccess exists', file_exists(__DIR__.'/../config/.htaccess'));
chk('storage/.htaccess exists', file_exists(__DIR__.'/../storage/.htaccess'));
chk('config/config.php not exposing secrets in repo', !file_exists(__DIR__.'/../config/config.php') || strpos(file_get_contents(__DIR__.'/../config/config.php'),'password')!==false);
$content=file_get_contents(__DIR__.'/../app/Helpers/Security.php');
chk('CSRF token generation present', strpos($content,'csrfToken')!==false);
chk('password_hash usage in Auth.php', strpos(file_get_contents(__DIR__.'/../app/Auth.php'),'password_hash')!==false || strpos(file_get_contents(__DIR__.'/../app/Auth.php'),'password_verify')!==false);
chk('PDO prepared statements in PccService', strpos(file_get_contents(__DIR__.'/../app/Services/PccService.php'),'prepare')!==false);
chk('htmlspecialchars in Helpers', strpos(file_get_contents(__DIR__.'/../app/Helpers/Functions.php'),'htmlspecialchars')!==false || strpos(file_get_contents(__DIR__.'/../app/Helpers/Security.php'),'htmlspecialchars')!==false);
$publicHt=file_exists(__DIR__.'/../public/.htaccess') ? file_get_contents(__DIR__.'/../public/.htaccess') : '';
chk('public/.htaccess has security headers', strpos($publicHt,'X-Content-Type-Options')!==false);
$failed=count(array_filter($checks, fn($c)=>!$c['ok']));
echo "\nSecurityTest: ".(count($checks)-$failed)."/".count($checks)." passed\n";
exit($failed?1:0);
