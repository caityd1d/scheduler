<?php
class SystemConfiguration {
    // General Settings
    public static $base_url    = 'http://torklift-central-developer.local:5757/scheduler/';
    
    // Database Settings
    public static $db_host     = 'localhost';
    public static $db_name     = 'local-easyapp';
    public static $db_username = 'root';
    public static $db_password = 'root';
    
    // Google Calendar API Settings
    public static $google_sync_feature  = FALSE; // Enter TRUE or FALSE;
    public static $google_product_name  = '';
    public static $google_client_id     = '';
    public static $google_client_secret = '';
    public static $google_api_key       = '';
}

/* End of file configuration.php */
/* Location: ./configuration.php */