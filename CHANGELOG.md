# Audit fix changelog

FIXED means the code now does the thing. PARTIAL means the safe part is in and a piece still needs your server or a later pass. SKIPPED means it was not built in this pass.

P0-1 FIXED. Bank name, account, IFSC and UPI are settings. Checkout hides bank transfer until they are filled. The private order page and the customer email show how to pay.
P0-2 FIXED. includes/mail.php sends SMTP when configured and falls back to mail(). Orders, contact, wholesale and password changes send mail after the database commit. A mail failure does not cancel the order.
P0-3 FIXED. A second click within 10 minutes opens the order that was just placed. checkout.js disables the button.
P0-4 FIXED. Checkout keeps what was typed and fills a saved address. PIN, phone and address fields have the right autocomplete.
P0-5 FIXED. Cash on delivery reduces stock at dispatch and is marked paid on delivery. Bank transfer reduces stock and is marked paid when payment is confirmed. A sold order can be cancelled before dispatch and the trays go back. Coupon use is released. The desk only offers the next legal step.
P0-6 FIXED. Reservations expire (72 hours cash, 48 hours bank). Checkout and the desk run the cleanup. cron.php does it when cron_token is set. Three orders per IP per hour and per phone per day. Honeypot and a 3 second minimum. Turnstile runs only when its keys are set.
P0-7 FIXED. /setup stays closed unless setup_token is set and typed. After the first admin exists it stays closed.
P0-8 FIXED. UPLOAD.txt and SQL are blocked. config.local.php is not in this package. www and http on the live host redirect to https://precisionagritech.in. Localhost is not forced. Errors are not displayed unless debug is true. X-Forwarded-Proto is trusted only when trust_proxy is true. PHP below 8.1 stops with a clear message. A down database returns 503 and the phone number.
P0-9 FIXED. A bad or expired form shows a shop page, not a blank 500. Sign out still signs out. Unknown addresses use 404.php.
P0-10 PARTIAL. Session lifetime is 7 days, 30 days after sign-in, with a private save folder. Signed-in carts are stored and merged at sign-in. Public pages start a session only when a cookie or a form needs one.

P1-1 FIXED. Category labels sit on the photo. Four categories make a 2 by 2 grid.
P1-2 FIXED. The shop is 4, 3, then 2 columns. It does not drop to one column on a phone.
P1-3 FIXED. use_tags drives Shop by use. Landscaping, beds, pots, garlands and borders each have trays. The product form edits the tags.
P1-4 FIXED. The cart warns per tray, blocks checkout while a line is wrong, shows price, shipping progress and a coupon box.
P1-5 FIXED. Stock, season, reviews, price format, breadcrumbs and related trays are on the product page. Reviews need a delivered order and a verified email or mobile.
P1-6 FIXED. Shop filters keep each other. Sort, in-stock and a result count are there. Search includes the description.
P1-7 FIXED. Regular price stays. Sale price applies only inside the sale window, including at checkout.
P1-8 FIXED. With Twilio empty there is no mobile-code sign-in and registration goes to the account. Verify mobile is hidden until SMS is configured.
P1-9 FIXED. Duplicate accounts use the database key, not the error text. Unknown mobiles get the same SMS cooldown. Lockout is this IP plus the account. A successful sign-in clears that counter.
P1-10 FIXED. PHP and MySQL use Asia/Kolkata. Dates show as 06 Oct 2026, 11:16. The SMS wait is calculated in SQL.
P1-11 FIXED. Reloading a private order link is not rate limited. Customers see plain status words, the total breakdown and public notes.
P1-12 FIXED. Contact and wholesale redirect after success, throttle, and check the fields on the server.
P1-13 PARTIAL. The order desk shows payment, totals, SKU, history and the next step. The list can search and page. Packing-slip print is the browser print of the order page.
P1-14 PARTIAL. The desk, settings and product form were updated. Customer search, coupon editing, image upload and admin two-factor were not rebuilt.
P1-15 FIXED. Phone menu, larger taps, skip link, focus ring and separate success and error messages.
P1-16 FIXED. Shipping pages read the live delivery charge. Copy is customer language. Unknown pages are a real 404. A lawyer should still review the legal pages.
P1-17 PARTIAL. Guest orders attach after the email link is confirmed. Online Razorpay checkout is not offered yet, so a customer cannot pay into a half-built button. Cash on delivery requires a verified mobile only when SMS is configured.

P2-1 PARTIAL. Cards use 400px webp files (about 425 KB for the whole shop). Originals are still in /products.
P2-2 PARTIAL. Email confirmation exists. An unverified mobile older than 7 days can be taken by a new account. Reviews wait for email or mobile verification.
P2-3 PARTIAL. Catalogue pages can be cached for 5 minutes when the browser has no session cookie.
P2-4 PARTIAL. Fonts are self-hosted and inline styles were removed from the main pages. Admin pages can still use a short style attribute.
P2-5 PARTIAL. robots.txt, sitemap.xml, canonical and product JSON-LD are in. Open Graph is on each page.
P2-6 PARTIAL. Checkout locks products in id order and retries a deadlock once. Coupons can have a per-customer limit. Expired SMS codes say so. Reset links are checked when the page opens.
P2-7 FIXED. The header and hero line up with the content column.
P2-8 PARTIAL. Search is labelled, payment is a fieldset, focus rings are on. A full keyboard audit in a browser was not run here.
P2-9 PARTIAL. Shop and order tables scroll in a wrapper. Older admin tables were not all given headers.

P3 PARTIAL. WhatsApp is in the footer and on the product page. A customer can ask to cancel before dispatch. Low-stock wording is on the cards. Address editing, reorder, CSV export and a gallery were not added.

This environment has no PHP or MySQL, so the test plan below was not executed on a server.
