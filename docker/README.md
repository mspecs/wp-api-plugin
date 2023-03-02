# wp-api-plugin docker compose

You need to have [docker composed](https://docs.docker.com/compose/) installed

env paths that can be configured:
```shell
#Path to this plugin
$WP_API_PLUGIN_PATH='../'

#Path to dir where mysql can keep it files
$WP_DATABASE_PATH='./data/mysql'
```

### To start docker instances
```
docker compose up
```

To access wordpress: http://localhost/

### Setup plugin
Activate the plugin [http://localhost/wp-admin/plugins.php](http://localhost/wp-admin/plugins.php) 

To set up our plugin go to: [http://localhost/wp-admin/options-general.php?page=mspecs](http://localhost/wp-admin/options-general.php?page=mspecs)


