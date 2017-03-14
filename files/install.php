<?php
  require_once __DIR__ . "/manage/src/Database.php";
  $database = new Database();
  if(!$database->isSet) {
    echo "Setup your database settings in /manage/src/Database.php - read the instructions on <a href=//github.com/ssl/ezXSS>github.com/ssl/ezXSS</a> for more information";
    die();
  }
  if(isset($_POST["username"])) {
	  $password = password_hash($_POST["password"], PASSWORD_BCRYPT, ['cost' => 11]);
	  $username = $_POST["username"];
	  $email = $_POST["email"];
	  $sqlQuery = "CREATE TABLE IF NOT EXISTS `reports` (`id` int(11) NOT NULL AUTO_INCREMENT,`cookies` text,`dom` longtext,`origin` varchar(500) DEFAULT NULL,`referer` varchar(500) DEFAULT NULL,`screenshot` varchar(500) DEFAULT NULL,`uri` varchar(500) DEFAULT NULL,`user-agent` varchar(500) DEFAULT NULL,`ip` varchar(50) DEFAULT NULL,`time` int(11) DEFAULT NULL,PRIMARY KEY (`id`)) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=184 ;
	  CREATE TABLE IF NOT EXISTS `settings` (`id` int(11) NOT NULL AUTO_INCREMENT,`setting` varchar(500) NOT NULL,`value` varchar(500) NOT NULL,PRIMARY KEY (`id`)) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=4 ;
	  INSERT INTO `settings` (`id`, `setting`, `value`) VALUES (1, 'username', :username),(2, 'password', :password),(3, 'email', :email);";
	  $database->newQueryArray($sqlQuery, array(":username" => $username, ":password" => $password, ":email" => $email));
	  $domain = htmlspecialchars($_SERVER['SERVER_NAME']);
	  $current = 'var ez_domain = "//'.$domain.'";';
	  $current .= file_get_contents("install-js.js");
	  file_put_contents("index.js", $current);
	  unlink("install-js.js");
	  unlink("install.php");
	  header("Location: /manage");
  }
?>

<form method="post">
  <input type="text" name="username" placeholder="username" value="">
  <input type="text" name="password" placeholder="password" value="">
  <input type="text" name="email" placeholder="email" value="">
  <button type="submit" name="button">Install</button>
</form>
