<?php
require_once './vendor/autoload.php';

Class Epirank
{
	private $promo;
	private $city;

	private $authorizedPromo = array(
									'2016' => '2016',
									'2017' => '2017',
									'2018' => '2018',
									'2019' => '2019',
									'2020' => '2020',
									'2021' => '2021',
									);

	private $authorizedCities = array(
									  "Paris"		=> "Paris",
									  "Lilles"		=> "Lilles",
									  "Bordeaux"	=> "Bordeaux",
									  "Lyon"		=> "Lyon",
									  "Marseille"	=> "Marseille",
									  "Montpellier"	=> "Montpellier",
									  "Nancy"		=> "Nancy",
									  "Nantes"		=> "Nantes",
									  "Nice"		=> "Nice",
									  "Rennes"		=> "Rennes",
									  "Strasbourg"	=> "Strasbourg",
									  "Toulouse"	=> "Toulouse",
								 	 );

	private $db = array(
						"servername" => "",
						"username" => "",
						"password" => "",
						"dbname" => ""
					   );

	private $twig;

	public function __construct($promo = '2017')
	{
		if (file_exists("./config.json") === false)
			die("The config file is missing.\n");

		$configEncoded = file_get_contents("./config.json");

		if (($config = json_decode($configEncoded)) == null)
			die("The config file is invalid.\n");

		if (!property_exists($config, 'db') || !property_exists($config->db, 'servername') || !property_exists($config->db, 'username') || !property_exists($config->db, 'password') || !property_exists($config->db, 'dbname'))
			die("Missing DB in config.\n");

		$this->db["servername"] = $config->db->servername;
		$this->db["username"] = $config->db->username;
		$this->db["password"] = $config->db->password;
		$this->db["dbname"] = $config->db->dbname;

		$this->promo = $promo;
		$this->city = null;

		$loader = new Twig_Loader_Filesystem('./templates');

		$this->twig = new Twig_Environment($loader, array(
		    'cache' => false,
		));
	}

	private function sortByGPA($a, $b)
	{
		return $a['gpa'] < $b['gpa'];
	}

	public function index()
	{
		if (isset($_GET['promo']) && !isset($this->authorizedPromo[$_GET['promo']]))
			return null;
		else if (isset($_GET['promo']))
			$this->promo = $this->authorizedPromo[$_GET['promo']];

		if (isset($_GET['city']) && !isset($this->authorizedCities[$_GET['city']]))
			return null;
		else if (isset($_GET['city']))
			$this->city = $this->authorizedCities[$_GET['city']];

		$conn = new mysqli($this->db['servername'], $this->db['username'], $this->db['password'], $this->db['dbname']);

		if ($conn->connect_error) {
			die("Connection failed: " . $conn->connect_error);
		}

		$sql = "SELECT login, gpa, city, promo, modified FROM Student WHERE promo='".$this->promo."'";

		if ($this->city != null)
			$sql .= " AND city='".$this->city."'";

		$result = $conn->query($sql);

		$parsedRes = array();

		if ($result->num_rows > 0) {
		    while($row = $result->fetch_assoc()) {
		    	$parsedRes[] = $row;
		    }
		}
		else
			return null;

		$conn->close();

		usort($parsedRes, array($this, 'sortByGPA'));

		echo $this->twig->render('index.html', array(
													 'students' => $parsedRes,
													 'currentPromo' => $this->promo,
													 'currentCity' => $this->city,
													 'cities' => $this->authorizedCities,
													 'promotions' => $this->authorizedPromo
													)
								);
		die;
	}
}

$epirank = new Epirank();

$epirank->index();
