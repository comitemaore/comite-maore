#!/bin/bash
git config --global user.email "mahamoud.jaffar@hotmail.fr"
git config --global user.name "Mahamoud JAFFAR"
sudo apt install -y apache2
sudo systemctl enable apache2
sudo a2enmod rewrite deflate headers ssl http2
# mettre en place l'authentification basique
# sudo apt-get install -y apache2-utils
# Install PHP.
# Add the packages.sury.org/php repository.
sudo apt update
sudo apt install -y lsb-release ca-certificates curl
sudo curl -sSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb
sudo dpkg -i /tmp/debsuryorg-archive-keyring.deb
sudo tee /etc/apt/sources.list.d/php.sources <<EOF
Types: deb
URIs: https://packages.sury.org/php/
Suites: $(lsb_release -sc)
Components: main
Signed-By: /usr/share/keyrings/debsuryorg-archive-keyring.gpg
EOF
sudo apt update
sudo apt install -y php8.5 php8.5-fpm php-xml
sudo a2enmod proxy_fcgi setenvif 
sudo a2enconf php8.5-fpm 
sudo systemctl restart apache2
# Pour que PHP-FPM traite les scripts PHP au niveau d'Apache
# sudo nano /etc/apache2/sites-enabled/000-default.conf
# ajoutez dans le bloc <VirtualHost>
#<FilesMatch \.php$>
#    SetHandler "proxy:unix:/run/php/php8.5-fpm.sock|fcgi://localhost/"
#</FilesMatch>
sudo systemctl restart apache2
sudo apt install -y php8.5-{dom,xml,mbstring,intl,mysql,curl,gd,zip,mcrypt}
#créer un fichier phpinfo.php
#sudo nano /var/www/html/phpinfo.php
#<?php
#phpinfo();
#?>
# accessible avec : http://<ip-du-serveur>/phpinfo.php 
mkdir -p ~/projets/symfony/
mkdir -p ~/sources
cd ~/sources
# install composer
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php -r "if (hash_file('sha384', 'composer-setup.php') === 'c8b085408188070d5f52bcfe4ecfbee5f727afa458b2573b8eaaf77b3419b0bf2768dc67c86944da1544f06fa544fd47') { echo 'Installer verified'.PHP_EOL; } else { echo 'Installer corrupt'.PHP_EOL; unlink('composer-setup.php'); exit(1); }"
php composer-setup.php
php -r "unlink('composer-setup.php');"
sudo mv composer.phar /usr/local/bin/composer
composer --version
wget https://get.symfony.com/cli/installer -O - | bash
/home/jaffar/.symfony5/bin/symfony check:requirements
sudo mv /home/jaffar/.symfony5/bin/symfony /usr/local/bin/symfony
cd ~/projets/symfony
composer create-project symfony/skeleton:"7.*" comite-maore
cd ansiblefony
composer require twig doctrine/dbal symfony/security-bundle symfony/form
composer require symfony/maker-bundle --dev
composer require doctrine/doctrine-bundle
composer require doctrine/orm
composer require doctrine/dbal
composer require doctrine/doctrine-bundle
composer require symfony/mime
composer require symfony/ext-xml
composer require symfony/asset

# mkdir -p /home/claude/ansiblefony/{src/{Controller,Entity,Form,Message,MessageHandler,Service,Repository,Security,EventSubscriber},templates/{dashboard,forms,jobs,security},assets/{controllers,styles},config/{packages,routes},migrations,docker,kubernetes/{base,overlays/{dev,prod}}}
# php bin/console make:controller HomeController
# php bin/console make:controller DashboardController
# php bin/console make:controller SearchController
# php bin/console make:controller DatabaseController
