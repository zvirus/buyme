<?php
// Buyme 1.4 2013 by Nazar Tokar
// dedushka.org * nazartokar.com * nazartokar@gmail.com

//require("smtp.php");

header ("Content-Type: text/html; charset=utf-8"); //кодировка
// почта для уведомлений //несколько ящиков могут перечисляться через запятую
$to = "yourMail";

$HTTP_HOST = parse_url('http://'.$_SERVER["HTTP_HOST"]); 
$HTTP_HOST = str_replace(array("http://","www."),"", $HTTP_HOST['host']);
$from = "noreply@".$HTTP_HOST; // отправитель

// данные для отправки смс

$sms["id"] = "";
$sms["key"] = "";
$sms["log"] = "";
$sms["pss"] = "";
$sms["frm"] = "callme"; // добавьте новую подпись в смс-шлюзе и дождитесь апрува. Для sms-fly.com, sms-sms.com.ua по умолчанию используется АИ SMS
$sms["num"] = ""; // ваш номер в формате без + (79218886622, 380501234567)
$sms["prv"] = "sms.ru"; // на выбор: sms.ru, infosmska.ru, bytehand.com, sms-sending.ru, smsaero.ru, sms-fly.com, sms-sms.com.ua

function uc ($s) {
	$s = urlencode($s);
	return $s;
}

function gF ($s) { // no shit
	$s = substr( (htmlspecialchars($_GET[$s])), 0, 500);
	if (strlen($s) > 1) return $s;
}

function echoResult ($result, $class, $time, $message) { // выводим json
	echo '{ 
	"result": "'.$result.'", 
	"cls": "'.$class.'", 
	"time": "'.$time.'", 
	"message": "'.$message.'" }';
	exit();
}

function sendSMS ($to = "", $msg){
	global $sms;
	
	# исправляем косяки автора. Он не передает номер телефона в параметре $to
	$to = (empty($to)) ? $sms["num"] : $to; 
	if($sms["prv"] == 'sms-fly.com' || $sms["prv"] == 'sms-sms.com.ua')
	{
		$description = htmlspecialchars('SMS from Buyme 1.4');
		# Подменим общее Альфанумерическое имя на доступное на этом сервисе (SMS) по умолчанию всем клиентам
		# В случае индивидуального Альфаимени используется из конфига
		$sms["frm"] = ($sms["frm"] == "callme") ? "SMS" : $sms["frm"];

		# проверка на доступность CURL
		if (function_exists('curl_init')) 
		{
			# экранируем текст
			#$msg = htmlspecialchars($msg);
			$myXML 	 = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
			$myXML 	.= "<request>";
			$myXML 	.= "<operation>SENDSMS</operation>";
			$myXML 	.= '		<message start_time="AUTO" end_time="AUTO" livetime="5" rate="120" desc="'.$description.'" source="'.$sms["frm"].'">'."\n";
			$myXML 	.= "		<body>".$msg."</body>";
			$myXML 	.= "		<recipient>".$to."</recipient>";
			$myXML 	.=  "</message>";
			$myXML 	.= "</request>";
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_USERPWD , $sms["log"].':'.$sms["pss"]);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_URL, 'http://'.$sms["prv"].'/api/api.php');
			curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/xml", "Accept: text/xml"));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $myXML);
			$response = curl_exec($ch); // тут содержится ответ от сервера. Можно распарсить и вернуть ошибку, если необходимо
			curl_close($ch);
		} 
		else 
		{
			# пробуем отправить через сокеты
			$line = array();  
			$is_data = false;
			$fp = fsockopen($sms["prv"], 80, $errno, $errstr, 30); 
			if (!$fp) 
			{ 
				echo "$errstr ($errno)<br />\n";  // эту ошибку при отправке тоже бы обработать
			}
			else  
			{  
				$data 	  	 = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
				$data 	  	.= "<request>";
				$data 	  	.= "<operation>SENDSMS</operation>";
				$data 	  	.= '		<message start_time="AUTO" end_time="AUTO" livetime="3" rate="120" desc="'.$description.'" source="'.$sms["frm"].'">'."\n";
				$data 	  	.= "		<body>".$msg."</body>";
				$data 	  	.= "		<recipient>".$to."</recipient>";
				$data 	  	.=  "</message>";
				$data 	  	.= "</request>";				
				
				$headers = "POST /api/api.php HTTP/1.1\r\n";  
				$headers .= "Host: ".$sms["prv"]."\r\n";  
				$headers .= 'Authorization: Basic '.base64_encode($sms["log"].':'.$sms["pss"])."\r\n";           
				$headers .= "Content-type: text/xml\r\n";  
				$headers .= "Content-Length: ".strlen($data)."\r\n\r\n";  
				fwrite($fp, $headers.$data);  
				while (!feof($fp))  
				{  
					$one_line = fgets($fp, 1024);
					if($one_line == "\r\n") {
						$is_data = ($is_data) ? false : true;
					}
					if($is_data) $line[] = $one_line; // тут содержится ответ от сервера. Можно распарсить и вернуть ошибку, если необходимо
				} 
				fclose($fp);  
			}  
		}
	}
	else
	{
		$u['sms.ru'] = "sms.ru/sms/send?api_id=".uc($sms["key"])."&to=".uc($sms["num"])."&text=".uc($msg);
		$u['bytehand.com'] = "bytehand.com:3800/send?id=".uc($sms["id"])."&key=".uc($sms["key"])."&to=".uc($sms["num"])."&partner=callme&from=".uc($sms["frm"])."&text=".uc($msg);
		$u['sms-sending.ru'] = "lcab.sms-sending.ru/lcabApi/sendSms.php?login=".uc($sms["log"])."&password=".uc($sms["pss"])."&txt=".uc($msg)."&to=".uc($sms["num"]);
		$u['infosmska.ru'] = "api.infosmska.ru/interfaces/SendMessages.ashx?login=".uc($sms['log'])."&pwd=".uc($sms["pss"])."&sender=SMS&phones=".uc($sms["num"])."&message=".uc($msg);
		$u['smsaero.ru'] = "gate.smsaero.ru/send/?user=".uc($sms["log"])."&password=".md5 (uc($sms["pss"]))."&to=".uc($sms["num"])."&text=".uc($msg)."&from=".uc($sms["frm"]);
	
		@$r = file_get_contents("http://".$u[$sms["prv"]]);	
	}
	
}

$l["sent"] = "Заказ уже был отправлен";
$l["err"] = "Пожалуйста, заполните все поля";
$l["ok"] = "Спасибо, заказ принят. Ждите звонка";
$l["title"] = "Buyme: новый заказ";
$l["footer"] = "<div style=\"background: #f1f1f1; margin-top: 10px; padding: 10px; color: #555;\"><a href='http://dedushka.org/tag/buyme/'>Следите</a> за обновлениями скрипта. Спасибо за использование BuyMe.</div>";

function addToMess ($c, $o) {
	return "<p><b>".$c."</b><br>".$o."</p>";
}

function getOptions ($o) { // get fields
	$captions = $_GET["cs"];
	$options = $_GET["os"];
	$opts = "";
	$i = 0;	

	if ($o == 1) {
		foreach ($options as $value) {
			if (strlen($value) > 1) {
				if ($captions[$i] == "url") {
					if (strlen($value) < 60) { // cut long urls
						$opts .= addToMess($captions[$i], $value);
					} 
					else {
						$link = "<a href='".$value."'>".substr($value, 0, 60)."...</a>";
						$opts .= addToMess($captions[$i], $link);
					}		
				} else {
					$opts .= addToMess($captions[$i], $value);
				}
			}
			$i++;
		}
	} else {
		foreach ($options as $value) {
			if (strlen($value) > 1) {
				$opts .= $captions[$i]."(".$value.") ";
			}
			$i++;
		}		
	}
	return $opts;
}

function translit ($str) { // translit by programmerz.ru
	$tr = array("А"=>"A","Б"=>"B","В"=>"V","Г"=>"G","Д"=>"D","Е"=>"E","Ё"=>"E","Ж"=>"J","З"=>"Z","И"=>"I","Й"=>"Y","К"=>"K","Л"=>"L","М"=>"M","Н"=>"N","О"=>"O","П"=>"P","Р"=>"R","С"=>"S","Т"=>"T","У"=>"U","Ф"=>"F","Х"=>"H","Ц"=>"C","Ч"=>"4","Ш"=>"SH","Щ"=>"SC","Ъ"=>"","Ы"=>"Y","Ь"=>"","Э"=>"E","Ю"=>"U","Я"=>"YA","а"=>"a","б"=>"b","в"=>"v","г"=>"g","д"=>"d","е"=>"e","ё"=>"e","ж"=>"j","з"=>"z","и"=>"i","й"=>"y","к"=>"k","л"=>"l","м"=>"m","н"=>"n","о"=>"o","п"=>"p","р"=>"r","с"=>"s","т"=>"t","у"=>"u","ф"=>"f","х"=>"h","ц"=>"c","ч"=>"4","ш"=>"sh","щ"=>"sch","ъ"=>"y","ы"=>"y","ь"=>"","э"=>"e","ю"=>"u","я"=>"ya");
	return strtr($str,$tr);
}

// далее можно не трогать

$time = time(); // время отправки
$interval = $time - gF("time");
if ($interval < 20) { // если прошло менее (сек)
	echoResult("err", "b1c-err", "", $l["sent"]);
} else {
	$get_data = $_GET["cs"];

	if (count($get_data) > 1) { // data to send
		$ip = $_SERVER['REMOTE_ADDR']; 
		$prd = gF("prd");

		$geo = @file_get_contents("http://freegeoip.net/json/".$ip);
		$geo = @json_decode($geo, true);	

		$title = $l["title"];
		$title = "=?UTF-8?B?".base64_encode($title)."?=";
		$mess = "<h3>Заказ на ".$prd."</h3><div style='background:#fffce8;border:1px solid #cdc485;padding:0 10px'>";

		$mess .= getOptions(1);
		$mess .= "</div><div style=\"background: #f9f2f4; border: 1px solid #c7254e;padding:0 10px;margin-top:10px;\">";

		$mess .= addToMess("IP",$ip);
		$mess .= addToMess("Откуда запрос",(($geo['city'])." (".($geo['country_name']).")" ));

		$mess .= "</div>".$l["footer"];
		
		$headers  = "Content-type: text/html; charset=utf-8\r\n"; 
		$headers .= "From: BuyMe 1.4 <".$from.">\r\n"; 

		$sms["msg"] = substr(translit($prd.",".(getOptions(0))), 0, 160);

		if (strlen($to) > 5 && $to != "yourMail") {
			@mail($to, $title, $mess, $headers); 
		}

		if (($sms["id"] != "") || ($sms["key"] != "") || ($sms["log"] != "")) {
			@sendSMS($num, $sms["msg"]); 
		}
		echoResult("ok", "b1c-ok", $time, $l["ok"]);
	} else {
		echoResult("err", "b1c-err", "", $l["err"]);
	}
}
?>
