!#/c/php-x64/php
<?php
/*
 * LGPL
 */

/**
 *
 * @author Bhupesh Pranami <bhupesh.pranami at yahoo.co.uk>
 */
require_once 'Colors.php';

class InstallMagento {
    // infor message color constants

    const INFO = 'green';
    const ERROR = 'red';
    const NOTICE = 'yellow';

    private static $_DS = '/'; // default to linux directory separator
    // supported command line options
    private static $_cli_shortopts = "u:v:c:";
    private static $_cli_longopts = array(
        "url:",
        "verbose",
        "dryrun",
        "config:"
    );
    private $_verbose = false;
    private $_isDryrun = false;
    private $_vanilla_mage_repo = 'bliss@acer:magento-ee-lite.tar.bz2';
    private $_repo_url = NULL;
    private $_mage_root = NULL;
    private $_installation_dir = NULL;
    // database options
    private $_dbInfo = array();
    private $_siteInfo = array();
    private $_Chroma = NULL;
    // set options from ini file
    private $_config = array();

    public function __construct($color = NULL) {
        if (!$this->isCli()) {
            die("Must run under command line...");
        }

        $this->setChroma($color);

        // adjust directory separator if on windows
        $this->setDirectorySeparator();
        $this->_init();
    }

    public function setVersbose($value) {
        $this->_verbose = (bool) $value;
    }

    public function getVerbose() {
        return $this->_verbose;
    }

    public function setChroma($Color) {
        if (is_object($Color)) {
            $this->_Chroma = $Color;
        }
    }

    public function getChroma() {
        return $this->_Chroma;
    }

    public function setRepoUrl($value) {
        $this->_repo_url = $value;
    }

    public function getRepoUrl() {
        return $this->_repo_url;
    }

    public function setInstallationDir($value) {
        $this->_installation_dir = $value;
    }

    public function getInstallationDir() {
        return $this->_installation_dir;
    }

    /**
     * load specified ini file into $_config
     *
     * @param string $file
     */
    private function _loadConfig($options) {

        $conf_file = NULL;
        if (isset($options ['config'])) {
            $conf_file = $options ['config'];
        } else if (isset($options ['c'])) {
            $conf_file = $options ['c'];
        }

        if ($conf_file !== NULL && !file_exists($conf_file)) {
            $this->log("file does not exists", self::ERROR);
            exit;
        }

        if ($conf_file !== NULL && file_exists($conf_file)) {
            $this->_config = parse_ini_file($conf_file, true);
            return $this;
        }
    }

    /*
     * Check if class is under command line environment
     */

    private function isCli() {
        // must run under command line
        if (PHP_SAPI == 'cli')
            return TRUE;

        return FALSE;
    }

    private function _init() {
        $options = getopt(self::$_cli_shortopts, self::$_cli_longopts);

        $this->_loadConfig($options);
        if (array_key_exists('verbose', $options) || (isset($this->_config ['install_directives']) && $this->_config ['install_directives'] ['verbose'] == true)) {
            $this->setVersbose(true);
        }

        if (array_key_exists('dryrun', $options) || (isset($this->_config ['install_directives']) && $this->_config ['install_directives'] ['dryrun'] == true)) {
            $this->_isDryrun = true;
        }

        // get repository url
        if (array_key_exists('u', $options)) {
            $repo_url = $options ['u'];
        }

        if (array_key_exists('url', $options)) {
            $repo_url = $options ['url'];
        }

        if (isset($this->_config ['install_directives'] ['repo_url'])) {
            $repo_url = $this->_config ['install_directives'] ['repo_url'];
        }

        if (!isset($repo_url) || strlen($repo_url) == 0) {
            $repo_url = self::_getUserInput("Enter repository url");
        }

        $this->setRepoUrl($repo_url);

        // get installation directory
        if (array_key_exists('dir', $options)) {
            $installation_dir = $options ['dir'];
        }

        if (isset($this->_config ['install_directives'] ['install_dir'])) {
            $installation_dir = $this->_config ['install_directives'] ['install_dir'];
        }

        if (!isset($installation_dir) || strlen($installation_dir) == 0) {
            $installation_dir = self::_getUserInput("Enter installation directory");
        }

        if (strlen($installation_dir) == 0) {
            $installation_dir = $this->_guess_dir_name($repo_url);
        }

        $this->setInstallationDir($installation_dir);
        $this->setMageRoot($installation_dir);
    }

    private function isDryrun() {
        return $this->_isDryrun;
    }

    /*
     * Adjust directory separator
     */

    private function setDirectorySeparator() {
        if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
            // Windows
            self::$_DS = '\\';
        }
    }

    private function getCliOptions() {
        
    }

    public function setMageRoot($value) {
        // if given path starts with /, it is an absolute path
        if (preg_match('/^\/\w*/i', $value)) {
            $mage_root = $value;
        } else {
            $mage_root = getcwd() . self::$_DS . $value;
        }

        $this->_mage_root = $mage_root;
    }

    public function getMageRoot() {
        return $this->_mage_root;
    }

    /*
     * Get user input return string
     */

    private static function _getUserInput($msg) {
        print $msg . ": ";

        $fr = fopen("php://stdin", "r");
        $input = fgets($fr, 128);
        $input = rtrim($input);
        fclose($fr);
        return $input;
    }

    private function _executeCmd($cmd) {
        // check for dangerous command here e.g. rm -rf

        if (!$this->isDryrun()) {
            `$cmd`;
        }
    }

    private function _guess_dir_name($repo_url) {
        /*
         * Strip trailing spaces, slashes
         */
        $repo_url = trim($repo_url);
        $repo_url = rtrim($repo_url, '/');

        $repo_url = explode(':', $repo_url);
        $dir = explode('/', array_pop($repo_url));
        $dir = array_pop($dir);

        $dir = preg_replace('/\.git$/i', '', $dir);

        return array_pop($dir);
    }

    private function _changeDirPermissions() {
        chdir($this->getMageRoot());
        @chmod('downloader', '0777');
        @chmod('media', '0777');
        @chmod('pkginfo', '0755');
        @chmod('shell', '0755');
        @chmod('var', '0777');
    }

    private function _copyIgnoreFiles() {
        if (!file_exists('gitignore') && !is_file('gitignore')) {
            echo "Ignore file does not exist. Nothing to copy.";
            return;
        }

        $handle = fopen('gitignore', 'r');
        $this->log("Copying ignored files...", self::INFO);

        $cmd = 'tar -tf ./tmp/magento-ee-lite.tar.bz2 | head -1 | sed -e "s/@/.@@/"';
        $chdir = `$cmd`;
        $chdir = str_replace(array("\r", "\r\n", "\n"), '', $chdir);

        echo $chdir .PHP_EOL;
        chdir(dirname(__FILE__).'/tmp/'.$chdir);

        echo $this->getMageRoot() . PHP_EOL;

        if (strtoupper(PHP_OS) == 'DARWIN' )  // MAC cp does not support parents switch
            $copyCmd = 'rsync -rR ./';
        else
            $copyCmd = 'cp -r --parents ./';

        while ($ignore = str_replace(array("\r", "\r\n", "\n"), '', fgets($handle))) {
            if (!file_exists($ignore)) {
                continue;
            }

            $cmd = $copyCmd . $ignore . ' ' . $this->getMageRoot();
            echo "Copying " . $ignore .' to '. $this->getMageRoot(). PHP_EOL;
            $this->_executeCmd($cmd);
        }

        fclose($handle);
    }

    private function _downloadMageVanilla() {
        if (!is_dir('./tmp'))
            mkdir('./tmp');

        $this->log("Downloading magento base...", self::INFO);
        $cmd = 'scp ' . $this->_vanilla_mage_repo . ' ./tmp/';
        $this->_executeCmd($cmd);

        $this->log("Extracting magento base...", self::INFO);
        $cmd = 'tar -xf ./tmp/magento-ee-lite.tar.bz2 -C ./tmp/';
        echo $cmd;
        echo $this->_executeCmd($cmd);
    }

    private function _checkoutRepo() {
        echo "Cloning repository..." . PHP_EOL;
        $cmd = "git clone " . $this->getRepoUrl() . " " . $this->getInstallationDir();

        if ($this->getVerbose())
            echo $cmd . PHP_EOL;

        $this->_executeCmd($cmd);
    }

    private function _requestDBInfo() {
        if (isset($this->_config ['database'])) {
            $this->_dbInfo = $this->_config ['database'];
        } else {
            $this->_dbInfo ['dbhost'] = $this->_getUserInput('Database host');
            $this->_dbInfo ['dbuser'] = $this->_getUserInput('Database user');
            // TODO: get password input silently
            $this->_dbInfo ['dbpass'] = $this->_getUserInput('Database password');
            $this->_dbInfo ['dbname'] = $this->_getUserInput('Database name');
        }

        // test the connection here.
        // if we cant establish connection display either to enter info again or
        // abort
        // $continue = $this->_getUserInfo( "I cant establish connection to
        // mysql server. Do you want to enter info again or abort?" );
        // if (in_array($continue, array("n","N","No","NO","no","nO"))
        // exit;
    }

    private function _requestSiteInfo() {
        if (isset($this->_config ['site_info'])) {
            $this->_siteInfo = $this->_config ['site_info'];
        } else {

            $this->_siteInfo ['store_url'] = $this->_getUserInput('Enter Store url');
            $this->_siteInfo ['admin.username'] = $this->_getUserInput('Admin username');
            $this->_siteInfo ['admin.password'] = $this->_getUserInput('Admin password');
            $this->_siteInfo ['admin.first_name'] = $this->_getUserInput('Admin first name');
            $this->_siteInfo ['admin.last_name'] = $this->_getUserInput('Admin last name');
            $this->_siteInfo ['admin.email'] = $this->_getUserInput('Admin email address');
        }
    }

    private function _createVirtualhosts() {
        $servername = rtrim(preg_replace("/^https?:\/\/(.+)$/i", "\\1", $this->_siteInfo ["store_url"]), '/');

        $vhostconf = <<<VHOST
		<VirtualHost *:80>
        ServerName {$servername}

        SetEnv MAGE_RUN_CODE "base"
        SetEnv MAGE_RUN_TYPE "website"

        ServerAdmin {$this->_siteInfo["admin.email"]}

        DocumentRoot {$this->getMageRoot()}
        <Directory {$this->getMageRoot()}>
                Options Indexes FollowSymLinks MultiViews
                AllowOverride All
                # Apache 2.4
                Require all granted
                # Apache 2.2
                Order allow,deny
                allow from all
                # Apache 2.2
        </Directory>

        ErrorLog "C:\Program Files\Apache Software Foundation\Apache2.4\logs\magento-ee_error.log"

        # Possible values include: debug, info, notice, warn, error, crit,
        # alert, emerg.
        LogLevel debug

        CustomLog "C:\Program Files\Apache Software Foundation\Apache2.4\logs\magento-ee_access.log" combined

		</VirtualHost>
VHOST;

        $this->log($vhostconf);
    }

    public function log($msg, $type = self::INFO) {
    	if($this->_Chroma instanceof Colors) {
    		$msg = $this->_Chroma->getColoredString($msg, $type);
    	}
    	
        echo $msg . "\n";
    }

    private function _cleanup()
    {
        chdir(dirname(__FILE__));
        $cmd = 'rm -rf ./tmp';
        $this->_executeCmd($cmd);
    }

    public function doInstall() {

        // setup file system
        $this->_checkoutRepo();
        $this->_downloadMageVanilla();  // download done 
        $this->_copyIgnoreFiles();
        $this->_changeDirPermissions();

        // now run magento database installation
        $this->_requestDBInfo();
        $this->_requestSiteInfo();

        $cmd = 'php -f install.php -- --license_agreement_accepted "yes" --locale "en_US" --timezone "Australia/Melbourne" --default_currency "AUD" --db_host "' . $this->_dbInfo['dbhost'] . '" --db_name "' . $this->_dbInfo['dbname'] . '" --db_user "' . $this->_dbInfo['dbuser'] . '" --db_pass "' . $this->_dbInfo['dbpass'] . '" --url "' . $this->_siteInfo['store_url'] . '" --use_rewrites "yes" --use_secure "no" --secure_base_url "" --use_secure_admin "no" --skip_url_validation "yes" --admin_firstname "' . $this->_siteInfo['admin.first_name'] . '" --admin_lastname "' . $this->_siteInfo['admin.last_name'] . '" --admin_email "' . $this->_siteInfo['admin.email'] . '" --admin_username "' . $this->_siteInfo['admin.username'] . '" --admin_password "' . $this->_siteInfo['admin.password'] . '"';

        chdir($this->getMageRoot());
        // $this->_executeCmd($cmd);
        $output = `$cmd`;
        echo $output;

        $this->_createVirtualhosts();

        $this->_cleanup();
    }

}
$color = new Colors();
$InstallMagento = new InstallMagento ($color);
$InstallMagento->doInstall();











