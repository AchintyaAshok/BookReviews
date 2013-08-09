<?php

nytd_require('NYTD/Feeds/Config.class.php');

class NYTD_GlassBroker_Feeds_Config
{
	// Logging
	const FEEDS_LOADER_LOGGING_LEVEL = NYTD_Feeds_Config::RUNLEVEL_SUMMARY; // V1 logging, V2 uses self::getLogLevel
	const FEEDS_LOADER_LOG_DIR		= '/data/feeds/glass_broker/logs/';
	const FEEDS_LOADER_ERROR_LOG	= '/data/feeds/glass_broker/logs/glass_broker.error.log';
	const FEEDS_LOADER_AUDIT_LOG	= '/data/feeds/glass_broker/logs/glass_broker.audit.log';
	
	static function getEnvironment()
    {
		global $environment;  //this got set in the main class when the command line was parsed.
		return (!$environment ? get_cfg_var('nytcontext.servertype') : $environment);
	}

    static function getGlassApiUrl()
    {
        return "http://" . get_cfg_var("du-glass-broker.glass-api-host") . "/glass/outputmanager/v1/PassThrough.json";
    }

    static function getInternalApiHost()
    {
        return get_cfg_var("du-glass-broker.internal-api-host");
    }

	static function getLogLevel()
    {
		switch (self::getEnvironment()) {
        case 'development':
            return NYTD_Feeds_Logger::Notice;
        case 'staging':
            return NYTD_Feeds_Logger::Notice;
        case 'production':
            return NYTD_Feeds_Logger::Notice;
        default:
            throw new NYTD_Feeds_InternalStateException('No LogLevel params set for environment' . self::getEnvironment());
		}
	}
}

?>
