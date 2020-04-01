# Getting started

## Prerequisites

You'll need the following tools installed in order to run the project locally:

-   [Composer](https://getcomposer.org/download/) v1.3.0+
-   MySQL v5.7+ or PostgreSQL 9.5+

Once you have the prerequisites installed on your machine, and have the project checked out locally, run these commands in your terminal of choice:

## Install dependencies

From the root of the project run:

```shell script
composer install
```

## Create a Database

Next up, you need to create a database for the project. Both MySQL 5.5+ and PostgreSQL 9.5+ are supported.

If you’re given a choice, we recommend the following database settings in most cases:

-   MySQL

    -   Default Character Set: utf8
    -   Default Collation: utf8_unicode_ci

-   PostgreSQL

    -   Character Set: UTF8

## Create a local `.env` file

This is used to set local configuration values. There is a `.env.sample` file at the root of the project which documents available options. You should make a copy of this as `.env`.

You need to set all values to point to the local database instance you set up.

## Set up the Web Server
Create a new web server to host the project. Its document root (or “webroot”) should point to the `web/` directory.

If you’re not using MAMP or another localhosting tool, you will probably need to update your hosts file, so your computer knows to route requests to your chosen host name to the local computer.

-   macOS/Linux/Unix: `/etc/hosts`
-   Windows: `\Windows\System32\drivers\etc\hosts`

You can test whether you set everything up correctly by pointing your web browser to `http://<Hostname>/index.php?p=admin/install` (substituting <Hostname> with your web server’s host name). If Craft’s Setup Wizard is shown, the host name is correctly resolving to your Craft installation.

## Run the Setup Wizard
Finally, it’s time to run Craft’s Setup Wizard. You can either run that from your [terminal](###Terminal Setup) or your [web browser](###Web Browser Setup).

###Terminal Setup
In your terminal, go to your project’s root directory and run the following command to kick off the Setup Wizard:

```shell script
./craft setup
```

The command will ask you a few questions to learn how to connect to your database, and then kick off Craft’s installer. Once it’s done, you should be able to access your new Craft site from your web browser.

###Web Browser Setup
In your web browser, go to `http://<Hostname>/index.php?p=admin/install` (substituting <Hostname> with your web server’s host name). If you’ve done everything right so far, you should be greeted by Craft’s Setup Wizard.`

For guidance, you may use Step 6 from the official [Craft CMS guide](https://docs.craftcms.com/v3/installation.html#step-6-run-the-setup-wizard).

##Start the application

The application should now be setup and running. Visit `http://<Hostname>/index.php` (substituting <Hostname> with your web server’s host name) in your browser to confirm everything is OK.
