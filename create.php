<?php
/*
*************
   YOU NEED TO CONFIGURE THE FOLLOWING 3 SETTINGS: 
*************
*/

// PS_SHOP_PATH is usually your root domain, eg:  http://www.yourdomain.com/
// MAKE SURE YOU HAVE A TRAILING SLASH
define('PS_SHOP_PATH', 'YOUR_PRESTASHOP_SHOP_PATH_HERE'); 
// PS_WS_AUTH_KEY is your API key from your PrestaShop dashboard under Advanced Parameters > Webservice
define('PS_WS_AUTH_KEY', 'YOUR_PRESTASHOP_AUTH_KEY_HERE');
// AddShoppers secret key comes from the AddShoppers dashboard under Profile > Settings > API
$ADDSHOPPERS_SECRET_KEY = 'YOUR_SECRET_KEY_HERE';

/*
No more settings! There shouldn't need to be any more editing after here.
*/

define('DEBUG', false);
require_once( './PSWebServiceLibrary.php' );


//-----get info from url

$urluser = $_GET["asusrnm"];
$urlemail = $_GET["aseml"];
$data = $_GET["data"];
 
$params = json_decode(urldecode($data), true);
$signature = null;
$p = array();
 
foreach($params as $key => $value)
{
        if($key == "signature")
                $signature = $value;
        else
                $p[] = $key . "=" . $value;
}

asort($p);
 
$query = $ADDSHOPPERS_SECRET_KEY. implode($p);
 
$hashed = hash("md5", $query);
 
if($signature !== $hashed)
        die("AddShoppers secret key doesn't match up.");
//-----check if	a name and email exist
if(!$urluser){
        die();
}
if(!$urlemail){
        die();
}

//-----split name into first name and last name
$arr = explode('_',trim($urluser));
$firstname = $arr[0];
$lastname = array_shift($arr);
$lastname = implode(" ", $arr);
$email = $urlemail;

$filepath = realpath (dirname(__FILE__));
if (file_exists($filepath.'/config/config.inc.php')) {
    include($filepath.'/config/config.inc.php');
}
else
	echo("file doesn't exist");
	
global $cookie;

// Exit if already logged in.	
if ($cookie->isLogged()) { exit('Already logged in.'); }

$customer_email = $email; 	// email adress that will pass by the questionaire 
$customer_fname = $firstname;   // first name from api 
$customer_lname = $lastname;    // last name from api 

$customer = new Customer();

$customer->email = $urlemail;
$customer->firstname = $customer_fname;
$customer->lastname = $customer_lname;
$customer->passwd = generatePassword();

$result = Customer::customerExists($customer_email);

if($result > 0)
{
	$cookieCustomer = new Customer();
	$cookieCustomer->getByEmail($customer->email);
	
	$cookie->id_customer = intval($cookieCustomer->id);
    $cookie->customer_lastname = $cookieCustomer->lastname;
	$cookie->customer_firstname = $cookieCustomer->firstname;
    $cookie->logged = 1;
	$cookie->passwd = $cookieCustomer->passwd;
    $cookie->email = $cookieCustomer->email;
    if (Configuration::get('PS_CART_FOLLOWING') AND (empty($context->cookie->id_cart) OR Cart::getNbProducts($context->cookie->id_cart) == 0))
        $context->cookie->id_cart = intval(Cart::lastNoneOrderedCart(intval($customer->id)));
        	
}
else
{
	
try
{
	$webService = new PrestaShopWebservice(PS_SHOP_PATH, PS_WS_AUTH_KEY, false);
	
	$opt = array('resource' => 'customers');	
	$xml = $webService->get(array('url' => PS_SHOP_PATH.'api/customers?schema=blank'));
	$resources = $xml->children()->children();
	
	$resources->id_default_group = 3;
	$resources->passwd = $customer->passwd;
	$resources->lastname = $customer->lastname;
	$resources->firstname = $customer->firstname;
	$resources->email = $customer->email;
	$resources->is_guest = 0;
	$resources->active = 1;
	$resources->associations->groups->group->id = 3;
	
	//$xml = $webService->add($opt);
	$opt = array('resource' => 'customers');
	$opt['postXml'] = $xml->asXML();
	$xml = $webService->add($opt);
	
	
	$cookieCustomer = new Customer();
	$cookieCustomer->getByEmail($customer->email);
	
	$cookie->id_customer = intval($cookieCustomer->id);
    $cookie->customer_lastname = $cookieCustomer->lastname;
	$cookie->customer_firstname = $cookieCustomer->firstname;
    $cookie->logged = 1;
	$cookie->passwd = $cookieCustomer->passwd;
    $cookie->email = $cookieCustomer->email;
    if (Configuration::get('PS_CART_FOLLOWING') AND (empty($cookie->id_cart) OR Cart::getNbProducts($cookie->id_cart) == 0))
        $cookie->id_cart = intval(Cart::lastNoneOrderedCart(intval($customer->id)));

}

catch (PrestaShopWebserviceException $e)
{
	// Here we are dealing with errors
	$trace = $e->getTrace();
	
	if ($trace[0]['args'][0] == 404) echo 'Bad ID';
	else if ($trace[0]['args'][0] == 401) echo 'Bad auth key';
	else echo 'Other error'.$e;

}
}


function generatePassword($length=16, $strength=8) {
	$vowels = 'aeuy';
	$consonants = 'bdghjmnpqrstvz';
	if ($strength & 1) {
		$consonants .= 'BDGHJLMNPQRSTVWXZ';
	}
	if ($strength & 2) {
		$consonants .= 'BDGHJLMNPQRSTVWXZ';
		$vowels .= "AEUY";
	}
	if ($strength & 4) {
		$consonants .= 'BDGHJLMNPQRSTVWXZ';
		$vowels .= "AEUY";
		$consonants .= '23456789';
	}
	if ($strength & 8) {
		$consonants .= 'BDGHJLMNPQRSTVWXZ';
		$vowels .= "AEUY";
		$consonants .= '23456789';
		$consonants .= '@#$%';
	}
 
	$password = '';
	$alt = time() % 2;
	for ($i = 0; $i < $length; $i++) {
		if ($alt == 1) {
			$password .= $consonants[(rand() % strlen($consonants))];
			$alt = 0;
		} else {
			$password .= $vowels[(rand() % strlen($vowels))];
			$alt = 1;
		}
	}
	return $password;
}


?>
