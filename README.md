# WebDrink 2.0

A rewrite of the web interface for [Drink](http://csh.rit.edu/projects.php), the networked vending machines on Computer Science House. It can be used for managing inventory, applying credits, dropping drinks, and more. It includes an updated, mobile-friendly interface and an API for easily creating new Drink clients. 

Based on the original [WebDrink](https://github.com/ComputerScienceHouse/WebDrink). Uses [Drink-JS](https://github.com/ComputerScienceHouse/Drink-JS) to communicate with the Drink hardware.

WebDrink 2.0 is now LIVE (for all CSHers)! Check it out at https://webdrink.csh.rit.edu!

Check out the [API Documentation](docs/API.md) and develop your own Drink clients!

Found an issue? Want to request a feature? Send me an email or open a [GitHub issue](https://github.com/bencentra/WebDrink-2.0/issues).

__Why Update WebDrink?__
* Address usability concerns (make it mobile friendly, improve the admin experience, etc)    
* Expose an API to improve current/future development (mobile apps, new new WebDrink, etc)
* Learning, I guess (Angular, RESTful API, etc)

__It Uses:__
* [Twitter Bootstrap](http://getbootstrap.com/)    
* [AngularJS](http://angularjs.org/)    
* [A RESTful API](http://coreymaynard.com/blog/creating-a-restful-api-with-php/)    
* Boring ol' PHP        

## Configuration

### config.php

Webdrink should be deployed with a configuration file, `config.php`, at the project root. It should have the following entries:

```php

/*
*	General configuration
*/

define("API_BASE_URL", "api/index.php?request="); The base URL of the Drink API
define("DRINK_SERVER_URL", "https://drink.csh.rit.edu:8080") // Base URL for the Drink (websocket) server
define("LOCAL_DRINK_SERVER_URL", "http://localhost:3000"); // URL (and port) of test drink server (see /test directory)

/*
*	Rate limit delays (one call per X seconds)
*/

define("RATE_LIMIT_DROPS_DROP", 3); // Rate limit for /drops/drop

/*
*	Development configuration
*/
  
define("DEBUG", true); // true for test mode, false for production

define("DEBUG_USER_UID", "bencentra"); // If DEBUG is `true`, the UID of the test user (probably your own)
define("DEBUG_USER_CN", "Ben Centra"); // If DEBUG is `true`, the display name of the user (probably your own)

define("USE_LOCAL_DRINK_SERVER", true) // If set to `true` and DEBUG is `true`, will use a mock Drink server for developing
  
?>
```

### Database and LDAP Permission files

WebDrink expects two files - `dbInfo.inc` and `ldapInfo.inc` - to be present and contain database and LDAP configuration information (respectively). Currently, they must be placed two directories above the WebDrink root. This is only mildly inconvenient for: 

* CSH systems: put these files in your home dir and WebDrink in your `.html_pages`
* MAMP users: these files in the MAMP root and WebDrink in `htdocs` 

#### Database

`dbInfo.inc` contains database connection info and creates a global `$pdo` variable, used by WebDrink's database utility funtions (see `utils/db_utils.php`). You can create your own database from the schema file (`drink_v2_schema.sql`), or connect to the actual Drink database (ask an RTP for credentials).

```php
<?php

// Database connection info
$dbName = "";
$dbHost = "mysql.csh.rit.edu";
$dbUser = ""; 
$dbPass = "";

// Create a PDO object and connect to the database
try {
	$pdo = new PDO(
    "mysql:dbname=$dbName;host=$dbHost", 
    $dbUser, 
    $dbPass, 
    array(
      PDO::MYSQL_ATTR_FOUND_ROWS => true, 
      PDO::ATTR_EMULATE_PREPARES => false,
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    )
  );
} 
catch (PDOException $e) {
    die('Database Connection Failed: ' . $e->getMessage());
}

?>

```

#### LDAP

`ldapInfo.inc` contains LDAP connection info and creates a global `$conn` variable, used by WebDrink's LDAP utility functions (see `utils/ldap_utils.php`). For more information about CSH's LDAP setup, see this wiki article: https://wiki.csh.rit.edu/wiki/Ldap

```php
<?php

// LDAP connection info
$ldapUser = "";
$ldapPass = "";
$ldapHost = "ldap.csh.rit.edu";
$appDn = "ou=Apps,dc=csh,dc=rit,dc=edu";
$userDn = "ou=Users,dc=csh,dc=rit,dc=edu";

// Append the appropriate dn to the username
$ldapUser .= "," . $appDn;

// Connect to LDAP and bind the connection
try {
	$conn = ldap_connect($ldapHost);
	if (!ldap_bind($conn, $ldapUser, $ldapPass)) {
		die ('LDAP Bind Error...');
	}
}
catch (Exception $e) {
	die ('LDAP Connection Failed: ' . $e->getMessage());
}

?>

```

## Development

When developing, set DEBUG to `true` in `config.php` to fake Webauth authentication. All requests will be made as the test user (as defined in `config.php`). Don't be a jerk; put in your own username.

### Dev Environment

In order to run WebDrink locally you'll need a web server, PHP (>=5.4), and MySQL. 

* OS X: [MAMP](https://www.mamp.info/en/)
* Windows: [WAMP](http://www.wampserver.com/en/)
* Linux: Varies by distro (for example, [Ubuntu](https://help.ubuntu.com/community/ApacheMySQLPHP))

#### Notes

There is currently no development LDAP setup, so you'll have to use CSH's server. This means any operations (i.e. credit additions/deductions) are for real. 

Some operations require admin privileges. Contact an RTP to be given "drink admin" status to access these operations.

To set up a local database, you can import the schema from `drink_v2_schema.sql`.

### Test Drink Server

In the `/test` directory is a mock Drink server. It will blindly respond (with success) all requests and doesn't care about SSL, allowing you to test Drink server behavior outside of CSH-net (or if the Drink server is down).

Make sure you have Node and NPM installed: https://nodejs.org/en/

```bash
cd test
# Install dependencies
npm install
# Run the server
node index.js
```

## Releasing

For now, releases are a manual process (FTP-ing files to CSH servers). 

When "releasing," make sure to set DEBUG to `false` in `config.php`! 
