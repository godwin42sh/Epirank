<?php

Class Epirank
{
	private $year = '';
	private $promo = array();
	private $offset = 0;

	private $db = array(
						"servername" => "",
						"username" => "",
						"password" => "",
						"dbname" => ""
					   );

	private $cityCorresp = array(
								 "FR/PAR" => "Paris",
								 "FR/LIL" => "Lilles",
								 "FR/BDX" => "Bordeaux",
								 "FR/LYN" => "Lyon",
								 "FR/MAR" => "Marseille",
								 "FR/MPL" => "Montpellier",
								 "FR/NCY" => "Nancy",
								 "FR/NAN" => "Nantes",
								 "FR/NCE" => "Nice",
								 "FR/REN" => "Rennes",
								 "FR/STG" => "Strasbourg",
								 "FR/TLS" => "Toulouse",
								 );

	private $fieldsLogin = array();

	private $savedStudents = array();

	private $cookieFile = "";

	public function __construct($promo = array('tek1', 'tek2', 'tek3', 'tek4', 'tek5'), $year = '2016')
	{
		if (file_exists("./config.json") === false)
			die("The config file is missing.\n");

		$configEncoded = file_get_contents("./config.json");


		if (($config = json_decode($configEncoded)) == null)
			die("The config file is invalid.\n");

		if (!property_exists($config, 'db') || !property_exists($config->db, 'servername') || !property_exists($config->db, 'username') || !property_exists($config->db, 'password') || !property_exists($config->db, 'dbname'))
			die("Missing DB in config.\n");
		if (!property_exists($config, 'log') || !property_exists($config->log, 'login') || !property_exists($config->log, 'password'))
			die("Missing Log in config.\n");

		if (!property_exists($config, 'cookie') || !property_exists($config->cookie, 'path'))
			die("Missing Cookie path in config.\n");

		$this->fieldsLogin["login"] = $config->log->login;
		$this->fieldsLogin["password"] = $config->log->password;

		$this->cookieFile = $config->cookie->path;

		$this->db["servername"] = $config->db->servername;
		$this->db["username"] = $config->db->username;
		$this->db["password"] = $config->db->password;
		$this->db["dbname"] = $config->db->dbname;

		$this->year = $year;
		$this->promo = $promo;
	}

	private function getStudents($promo)
	{
		$ch = curl_init();

		$i = 0;

		do {
			curl_setopt($ch, CURLOPT_URL, "https://intra.epitech.eu/user/filter/user?format=json&location=FR/BDX|FR/LIL|FR/LYN|FR/MAR|FR/MPL|FR/NCY|FR/NAN|FR/NCE|FR/PAR|FR/REN|FR/STG|FR/TLS&year=".$this->year."&active=true&course=bachelor/classic|master/classic&promo=".$promo."&offset=".$this->offset);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

			$content = curl_exec($ch);

			$info = curl_getinfo($ch);

			++$i;
		} while ($info['http_code'] != 200 && $i < 5);

		if ($i == 5)
			die("Error cUrl:".$info['http_code']." (Students)\n");

		curl_close($ch);

		return (json_decode($content));
	}

	private function saveStudents($students)
	{
		$conn = new mysqli($this->db['servername'], $this->db['username'], $this->db['password'], $this->db['dbname']);

		if ($conn->connect_error) {
			die("Connection failed: " . $conn->connect_error);
		}

		$sqlRequests = "";

		$date = date("Y-m-d H:i:s");

		$this->login();

		$i = 0;
		$nbSave = 0;

		foreach ($students->items as $student) {
			++$this->offset;
			if (isset($this->savedStudents[$student->login])) {
				echo "Student already exist : '".$student->login."'\n";
				continue;
			}
			$infoStu = $this->getGPAPromo($student->login);

			$sqlRequests .= "INSERT INTO Student (login, city, promo, gpa, created, modified) VALUES ('".$student->login."', '".$this->cityCorresp[$student->location]."', '".$infoStu['promo']."', '".$infoStu['gpa']."', '".$date."', '".$date."');";
			echo "Request build for : '".$student->login."'\n";
		}

		if ($sqlRequests && $conn->multi_query($sqlRequests) === TRUE)
			echo "Save for all students\n";
		else if ($sqlRequests)
			echo "Error: ".$conn->error;

		$conn->close();
	}

	private function getSavedStudents()
	{
		$conn = new mysqli($this->db['servername'], $this->db['username'], $this->db['password'], $this->db['dbname']);

		if ($conn->connect_error) {
			die("Connection failed: " . $conn->connect_error);
		}

		$sql = "SELECT login FROM Student";
		$result = $conn->query($sql);

		if ($result->num_rows > 0) {

			while ($row = $result->fetch_assoc()) {
				$this->savedStudents[$row['login']] = true;
			}
		}
		$conn->close();
	}

	public function saveAllStudents()
	{
		echo "Script begin\n";

		do {
			$promo = array_shift($this->promo);

			echo "Begin for ".$promo.":\n";

			$this->offset = 0;
			$students = $this->getStudents($promo);
			$this->getSavedStudents();

			while (property_exists($students, 'items')) {
				$this->saveStudents($students);
				$students = $this->getStudents($promo);
			}
			echo "End for ".$promo."\n";
		}
		while (!empty($promo));

		echo "Script success.\n";
	}

	private function login()
	{
		$ch = curl_init();

		$i = 0;

		do {
			curl_setopt($ch, CURLOPT_URL, "https://intra.epitech.eu");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:49.0) Gecko/20100101 Firefox/49.0');
			curl_setopt($ch, CURLOPT_REFERER, 'https://intra.epitech.eu');
			curl_setopt($ch, CURLOPT_COOKIESESSION, true);
			curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
			curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
			curl_setopt($ch, CURLOPT_POST, count($this->fieldsLogin));
			curl_setopt($ch, CURLOPT_POSTFIELDS, $this->fieldsLogin);

			$content = curl_exec($ch);

			$info = curl_getinfo($ch);

			++$i;
		} while ($info['http_code'] != 200 && $i < 5);

		if ($i == 5)
			die("Error cUrl:".$info['http_code']." (Login)\n");

		curl_close($ch);
	}

	public function getGPAPromo($login)
	{
		$ch = curl_init();

		$i = 0;

		do {
			curl_setopt($ch, CURLOPT_HEADER, true);
			curl_setopt($ch, CURLOPT_URL, "https://intra.epitech.eu/user/".$login."/");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:49.0) Gecko/20100101 Firefox/49.0');
			curl_setopt($ch, CURLOPT_REFERER, 'https://intra.epitech.eu');
			curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
			curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

			$content = curl_exec($ch);

			$info = curl_getinfo($ch);

			++$i;
		} while ($info['http_code'] != 200 && $i < 2);

		if ($i == 2)
			die("Error cUrl:".$info['http_code']." (GPA)\n");

		curl_close($ch);

		$gpa = substr($content, strpos($content, "<label>G.P.A.</label>") + 49, 4);
		$gpa = is_numeric($gpa) ? $gpa : null;

		$promo = substr($content, strpos($content, '<div class="promo">') + 33, 4);
		$promo = is_numeric($promo) ? $promo : null;

		return (array('gpa' => $gpa, 'promo' => $promo));
	}
}

if (isset($argv[1])) {
	$promo = $argv[1];
	$epirank = new Epirank(array($promo));
}
else
	$epirank = new Epirank();


$epirank->saveAllStudents();
