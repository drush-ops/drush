#!/bin/bash

# Install sqlite for drush qd tests
sudo apt-get install sqlite3

# Install PHP extensions
sudo docker-php-ext-install pdo_mysql

# Install extension
sudo apt-get install -y libpng-dev

# Install PHP Extensions
sudo docker-php-ext-install gd

# Install Composer
'curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer'

# Display versions
php -v
composer --version

# Install mysql-client
sudo apt-get install mysql-client

# Configure bash environment variables
echo 'export PATH=~/.composer/vendor/bin:~/drush:$PATH' >> $BASH_ENV
echo 'export UNISH_DRUSH=$PWD/drush' >> $BASH_ENV

# Configure php.ini
echo 'mbstring.http_input = pass' > $HOME/php.ini
echo 'mbstring.http_output = pass' >> $HOME/php.ini
echo 'memory_limit = -1' >> $HOME/php.ini
echo 'sendmail_path = /bin/true' >> $HOME/php.ini
echo 'date.timezone = "UTC"' >> $HOME/php.ini

# Copy our php.ini configuration to the active php.ini file
# We can't use `php -r 'print php_ini_loaded_file();` when there is no php.ini
PHPINI_PATH="$(php -i | grep 'Configuration File (php.ini) Path' | sed -e 's#.*=> *##')/php.ini"
cat $HOME/php.ini | sudo tee "$PHPINI_PATH" > /dev/null

# Install our dependencies
composer install
