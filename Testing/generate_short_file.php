<?php
$fileName = $argv[1];
//$line_array = file($fileName);
$fh = fopen($fileName, "r");

$shortFileName = "short_$fileName";
$outputFh = fopen($shortFileName, "w+");

$index = 0;

//$modulus = count($line_array)/10000;
$magicMod = 7806384/10000; 
$line = "";
$counter = 0;

while ($line = fgets($fh)){
	//print "\n";
	if ($counter%$magicMod == 0){
		print "\n";
		print "Line $counter:\t" . $line;
		fwrite($outputFh, $line);
	}
	$counter++;
}
fclose($fh);
fclose($outputFh);
?>
