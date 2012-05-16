# phpESME


phpESME is a php-based External Short Messaging Entity. Which allows you to connect to SMSC to send/receive messages and build services on top of that.

## Features:

* Receiving SMSes (multipart also, unicode as well)
* Sending SMSes (multipart also, unicode as well)
* Handling delivery reports for sent SMSes (if requested)

## How to run it?

1. Install MySQL
2. Install PHP
3. Create database
4. Import DB structure from `db_structure.sql`
5. Specify your settings of DB and SMSC in `conf/phpesme.conf`
6. Run `bin/phpesme_rx.php`, `bin/phpesme_tx.php` and `bin/phpesme_handler.php`

## How to use it?

It will receive SMS when `phpesme_rx.php` and `phpesme_handler.php` are running. You can see incoming SMSes in `inbox` table and process them as you wish.

You should move message to `archive` table after processing.

To send a message put it into `outbox` table. If `phpesme_tx.php` is running it will send it and move it to `sent` table.

### P. S.
I hope I will make documentation soon.
