<?PHP

require_once('sqlCommon.php');
require_once('cryptoCommon.php');
require_once('dbSecrets.php');


$mysqli = $mysqli = openDB();

if($argc != 2){
	echo "Useage:" . PHP_EOL;
	echo $argv[0] . " <Tax Year>" . PHP_EOL;
	exit(1);
}
$year = $argv[1];

$allIncome = getAllIncomeByYear($mysqli, $year);
//echo "Count " . count($irsLines) . PHP_EOL;
//echo "Total cost $cost, Proceeds, $proc gain loss: " . ($proc - $cost) . PHP_EOL;
echo "All Income: $allIncome" . PHP_EOL;
?>
