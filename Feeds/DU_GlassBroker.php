<?php

ini_set('memory_limit', '128M');

/**
 * DU_GlassBroker.php [--env=<environment>] [--help] [--loglevel=<level>]
 *                     [--version] [--testsection=<section>] [--test-url=<url>]
 *
 * Polls the SQS for glass notifications of interest. Then updates DU as appropriate.
 * This is to eventually replace du-broker-feed.
 *
 * Can also run in test mode, to simulate a particular section and URL being
 * updated. For example:
 *   php DU_GlassBroker.php --loglevel=debug --testsection=dining.reviews
 *      --testurl=http://www.nytimes.com/2012/11/14/dining/reviews/restaurant-review-guys-american-kitchen-bar-in-times-square.html
 *
 * @author 	Achintya Ashok, Alfred Pang
 * @version $Revision$
 *
 * Copyright 2012 The New York Times Company.
 */
nytd_require("NYTD/Feeds/Logger.class.php");
nytd_require("NYTD/Feeds/DUFeedsUtils.class.php");
nytd_require("NYTD/Feeds/Exception/Exceptions.class.php");
nytd_require("NYTD/GlassBroker/Feeds/Config.class.php");
nytd_require("NYTD/GlassBroker/Feeds/GlassBrokerController.class.php");
nytd_require('AWSSDKforPHP/sdk.class.php');

$version_str = "DU_GlassBroker.php v1.0";
$log_file = NYTD_GlassBroker_Feeds_Config::FEEDS_LOADER_ERROR_LOG;
$environment = "staging";
$arg_testsection = NULL;
$arg_testurl     = NULL;

// open up logger
if (!file_exists(NYTD_GlassBroker_Feeds_Config::FEEDS_LOADER_LOG_DIR)) {
	if (!mkdir(NYTD_GlassBroker_Feeds_Config::FEEDS_LOADER_LOG_DIR, 0777, true)) {
		print "Problem creating log directory: " . NYTD_GlassBroker_Feeds_Config::FEEDS_LOADER_LOG_DIR ."\n";
		throw new Exception("Error creating Feeds Loader Log Directory: " . NYTD_GlassBroker_Feeds_Config::FEEDS_LOADER_LOG_DIR);
	}
}
if (!file_exists($log_file)) {
	if (!touch($log_file)) {
		print "Problem creating log file: " . $log_file ."\n";
		throw new Exception("Error creating Feeds Loader Log File: " . $log_file);
	}
}

$logger = new NYTD_Feeds_Logger;
$logger->openLog($log_file);
$logger->setloglevel(NYTD_Feeds_Logger::Error); // Emergency, Alert, Critical, Error, Warning, Notice, Info, Debug

parseCommandLine();

//$glassBrokerController = new NYTD_GlassBroker_Feeds_GlassBrokerController($logger);
$glassBrokerController = new NYTD_DU_GlassBroker_GlassBrokerController($logger);

try {
    if ($arg_testsection && $arg_testurl) {
        // run test, then exit
        $glassBrokerController->handleGlassMessage("scoop", "article", $arg_testsection, $arg_testurl);
        DUFeedsUtils::exitGracefully(null, $logger, 1);
    }

    /* SQS Queue Configuration */
	$accessKey 	= get_cfg_var('du.sqs.accesskey');
	$secretKey 	= get_cfg_var('du.sqs.secretkey');
	$username 	= 'du-sqs';
	$q_url 		= get_cfg_var('sqs.du-glassbroker.url');

	$logger->write2Log("Using SQS queue: " . $q_url, NYTD_Feeds_Logger::Debug, "DU_GlassBroker.php:" . __LINE__);

	// Uses AWS PHP SDK 1
	$sqs = new AmazonSQS(array("credentials" => $username, "key" => $accessKey, "secret" => $secretKey));
	printf("Access Key: %s\nSecret Key: %s\n", $accessKey, $secretKey);
	
	while(TRUE) {	//	Use Short-Polling to ping the SQS Servers, separating requests with a 1 second sleep timer
		$m = $sqs->receive_message($q_url, array("MaxNumberOfMessages" => 10, "AttributeName" => array("SentTimestamp")));
		if(!$m->isOK()) {
			throw new Exception ("Problem receiving message" . $m->body->Error->Message );
			DUFeedsUtils::exitGracefully(null, $logger, 3);
		}
		
		$message=$m->body->to_array();

		if(array_key_exists('Message',$message['ReceiveMessageResult'])){
			// sometimes a single message comes as the b
			if(array_key_exists('Body', $message['ReceiveMessageResult']['Message'])){
				$messages=array($message['ReceiveMessageResult']['Message']);
			}
			else {
				$messages=$message['ReceiveMessageResult']['Message'];
			}
			
			foreach($messages as $submessage){
				$sentTimestamp = null;
				//ensure that the timestamp is a string, or it will overflow the int
				if(array_key_exists("Name", $submessage["Attribute"])){
					$sentTimestamp = (string)$submessage["Attribute"]["Value"];
				}
				else{
					foreach((array)$submessage["Attribute"] as $name=>$value){
						if($name = "SentTimestamp"){
							$sentTimestamp = (string)$value;
						}
					}
				}
				//take the first 10 digits of the timestamp to make seconds for FROM_UNIXTIME()
				$sentTimestamp = substr($sentTimestamp, 0, 10);
				$data = $submessage['Body'];
				$handle=$submessage['ReceiptHandle'];
				$messageID = $submessage['MessageId'];
				
				try{
					$glassBrokerController->handleNotification($data, $sentTimestamp, $messageID);
				}
				catch (Exception $e){
					$logger->write2Log("Error executing Message ID: " . $messageID . ".  Job Data: \n" . $data . "\n.  Error Message: " . $e->getMessage() . "\n Job Data: " . $data . "\n", NYTD_Feeds_Logger::Error, "DU_GlassBroker.php:" . __LINE__);
				}
				
				// Pop the Message off the Queue
				$d = $sqs->delete_message($q_url, $handle);

				if(!$d->isOK()){
					$logger->write2Log("Error Deleting Message ID: " . $messageID, NYTD_Feeds_Logger::Error, "DU_GlassBroker.php:" . __LINE__);
				}
			}
			sleep(1);
		}
		else{
			print "* "; // Show that We are polling the queue
			sleep(1);
		}
	}
}
catch(Exception $e) {
	$logger->write2Log("Error connecting to SQS server: " . $e->getMessage(), NYTD_Feeds_Logger::Debug, "DU_GlassBroker.php:" . __LINE__);
	DUFeedsUtils::exitGracefully(null, $logger, 3);
}

DUFeedsUtils::exitGracefully(null, $logger, 1);


/**
 * Parse the command line arguments.
 */
function parseCommandLine()
{
	global $logger, $argv, $argc, $environment, $arg_testsection, $arg_testurl;

	$environment = DUFeedsUtils::getEnvironment();

	// parse command line arguments
	for ($i=1; $i < $argc; $i++) {
		if (DUFeedsUtils::startswith($argv[$i], "--env=", false)) {
			$env_str = substr($argv[$i], strlen("--env="));
			switch ($env_str) {
				case "dev":
				case "development":
					$environment = "development";
					break;
				case "prod":
				case "production":
					$environment = "production";
					break;
				case "stage":
				case "staging":
					$environment = "staging";
					break;
				default:
					usage("Unexpected value for environment \"" . $env_str . "\".");
			}
		}
        else if (DUFeedsUtils::startswith($argv[$i], "--testsection=", false)) {
			$arg_testsection = substr($argv[$i], strlen("--testsection="));
        }
        else if (DUFeedsUtils::startswith($argv[$i], "--testurl", false)) {
			$arg_testurl = substr($argv[$i], strlen("--testurl="));
        }
        else if (($argv[$i] == "--help") || ($argv[$i] == "-?")) {
			usage("");
		}
		else if (DUFeedsUtils::startswith($argv[$i], "--loglevel=", false)) {
			$loglevel_str = substr($argv[$i], strlen("--loglevel="));
			switch ($loglevel_str) {
				case "emergency":
					$logger->setloglevel(NYTD_Feeds_Logger::Emergency);
					break;
				case "alert":
					$logger->setloglevel(NYTD_Feeds_Logger::Alert);
					break;
				case "critical":
					$logger->setloglevel(NYTD_Feeds_Logger::Critical);
					break;
				case "error":
					$logger->setloglevel(NYTD_Feeds_Logger::Error);
					break;
				case "warning":
					$logger->setloglevel(NYTD_Feeds_Logger::Warning);
					break;
				case "notice":
					$logger->setloglevel(NYTD_Feeds_Logger::Notice);
					break;
				case "info":
					$logger->setloglevel(NYTD_Feeds_Logger::Info);
					break;
				case "debug":
					$logger->setloglevel(NYTD_Feeds_Logger::Debug);
					break;
				default:
					usage("Unexpected value for log level \"" . $loglevel_str . "\".");
			}
		} else if ($argv[$i] == "--version") {
			print($version_str . "\n");
			exit(0);
		} else {
			usage ("Unexpected command line argument \"" . $argv[$i] . "\".");
		}
	}

    if ($arg_testsection xor $arg_testurl) {
        usage("Both --testsection and --testurl must be specfied for test mode.");
    }

	// set config section based on environment.
	if (($environment != "development") && ($environment != "production") && ($environment != "staging")) {
		$logger->write2Log("Unknown environment set \"" . $environment . "\".", NYTD_Feeds_Logger::Error, "DU_GlassBroker.php");
		exit(2);
	}

	$logger->write2Log("Environment: " . $environment, NYTD_Feeds_Logger::Debug, "DU_GlassBroker.php");
}

/**
 * Display usage text and exit.
 */
function usage($message)
{
	global $log_file, $version_str, $data_dir, $archive_dir;
	if ((isset($message)) && ($message != "")) {
		print($message . "\n");
	}
	print("Usage: php DU_GlassBroker.php\n");
	print("  Pops asset JSON messages off an Amazon SQS queue and updates DU databases.\n");
	print("  Writes log messages to " . $log_file . ".\n");
	print("  --env=<environment>      - development, staging or production.\n");
	print("  --help                   - display this message.\n");
	print("  --loglevel=<level>       - set log level: emergency, alert, critical, error, warning, notice, info, or debug.  Default is error.\n");
	print("  --version                - display version (" . $version_str . ").\n");
    print("  --testsection=<section>  - section for article in test mode\n");
    print("  --testurl=<url>          - URL for article in test mode\n");
	exit(1);
}

?>
