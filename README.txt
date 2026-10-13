Precision Agritech
Flower seedling trays from the nursery at Theur, near Pune. Customers order on the site. Staff run orders, stock and prices from /admin.

Folders
  Root PHP files are the public pages (shop, product, cart, checkout, account).
  admin/ is the staff side. includes/ is shared PHP and is not meant to be opened in a browser.
  vendor/qrcode.php is a small MIT-licensed QR library (Kazuhiko Arase, 2009). Leave the algorithm alone.
  assets/ is CSS, JavaScript and the two font files.
  products/ and brand/ are the tray photos. Do not rename or recompress them.
  data/ holds the seasonal sheet, the farmer sheet and the business JSON.
  database/schema.sql is for an empty database. database/upgrade.sql is for an existing one and can be run again.
  uploads/products and uploads/private must be writable by PHP. Private files must not be browsable.

Install on Hostinger
  PHP 8.1 or newer, with pdo_mysql, gd, fileinfo, curl, mbstring and openssl.
  Create the MySQL database and user in hPanel.
  New database: import database/schema.sql once.
  Existing database: import database/upgrade.sql, then import it again. It only adds missing columns and tables.
  If phpMyAdmin refuses the stored procedures, sign in as the owner and use Admin, Settings, Database update. Run that twice.
  Upload the site into public_html. Do not upload config/config.local.php from your laptop.
  Copy config/config.example.php to config/config.local.php on the server.
  Set db_host, db_name, db_user, db_pass, and site_url to https://precisionagritech.in with no slash at the end.
  show_otp_on_screen and debug must be false on the live site. allow_setup stays false after the first staff account.
  Set a long cron_token. SMTP (smtp_host, smtp_port, smtp_secure, smtp_user, smtp_pass) is worth filling in. mail() on shared hosting often lands in spam, and a mail failure must not stop an order.
  upload_max_filesize and post_max_size at least 8M. Photos are JPEG, PNG or WebP, at least 600 by 450, and are resized to WebP.
  Force HTTPS and one host name.
  Cron, every 15 minutes: curl -s "https://precisionagritech.in/cron.php?token=YOURTOKEN"
  A wrong token returns "Not found". A correct call releases expired unpaid reservations.
  Before going live, place one small order, pay it, and mark it received. Add SPF, DKIM and DMARC for the domain.
  Take a database export and a copy of uploads before every update. To roll back, put the old files back and import that export.

Staff login
  Customers sign in with an emailed 6-digit code at /login. There is no customer password.
  Staff sign in with email and password at /admin/login.
  Open /setup once, while allow_setup is true and no staff account exists, then set allow_setup back to false.
  Roles are staff, manager and owner. The column in the database is still called desk_role.

Price lists
  Admin, Price lists, then load the supplied sheets. The seasonal sheet is public. Farmer rates show only for a customer you have marked as a farmer.
  Loading the sheets again does not duplicate varieties. Existing photos, stock and descriptions stay.
  The files are data/price-list-2026-27.csv and data/price-list-farmer.csv.
  A short note of which plants were given the starting stock is written to uploads/private/last-sheet-load.txt.
  Check the bank account numbers against a cheque before you tick them as verified. Until then they stay hidden.

Google sign-in is optional. In Google Cloud, create a web OAuth client and set the redirect to https://precisionagritech.in/auth/google/callback. Put the client id and secret in config.local.php.
Leave sms_provider empty until an India SMS provider and DLT registration are ready. Until then a mobile number is only a contact number.
A lawyer or CA should review the legal pages and the invoice wording before you rely on them. Leave GST off until GSTIN, HSN and rates are confirmed.
There is no payment gateway. UPI and bank transfer are "customer pays, staff marks it received".
