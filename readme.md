#About the app
This is a LARAVEL app with MYSQL backend. 
The application matches invoices against bank statement, which normally takes half-a-day to complete manually.
The tool does it within a minute.
#How to deploy 
This tool using  [PHP deployer](https://deployer.org/) to deploy.
Please read the documentation before proceeding. You may need to host the code in GITHUB and have your ssh key added to the PRODUCTION host. You may also need to add the host ssh key to GITHUB for DEPLOYER to get the code.

###Deploying database:
 - Create mysql database and mysql user in a different host or same host.
 - Update the mysql config in (config/database.php)
 
###Deploying the app:
 - Update deploy.php by replacing *nothingbutsales* with your hostname
 - Execute `dep deploy production` from terminal
 - You can use `-v -vv or -vvv` for different verbose mode
 - You can do the same for the `test` environment too.
 
###Please follow the [PHP deployer](https://deployer.org/) documentation for any issue.