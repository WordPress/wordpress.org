=== Camptix Invoices ===
Contributors: willybahuaud, simonjanin, iceable, avillegasn, tfrommen, nem-c
Tags: camptix, invoice, event, organizer, tickets, notes
Requires at least: 3.0.1
Tested up to: 5.0.3
Requires PHP: 5.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: invoices-camptix

Allow CampTix administrators to send invoices automatically when an attendee buys a ticket.

== Description ==

Allow CampTix administrators to send invoices automatically when an attendee buys a ticket.

This plugin requires the installation of [Camptix Event Ticketing plugin](https://wordpress.org/plugins/camptix/).

This plugin requires the WordCamp Docs plugin present in every WordCamp site.
Alternatively, make sure you include the [WordCamp_Docs_PDF_Generator](https://meta.trac.wordpress.org/browser/sites/trunk/wordcamp.org/public_html/wp-content/plugins/wordcamp-docs/classes/class-wordcamp-docs-pdf-generator.php).

Your server must have [wkhtmltopdf](https://wkhtmltopdf.org/) installed.


== Installation ==

1. Install and configure CampTix plugin
1. Download and activate CampTix Invoice through the 'Plugins' menu in WordPress
1. Go to CampTix settings to define Event information (organiser name, logo, invoice name)


== Frequently Asked Questions ==

= Can I add a custom invoice? =

Yes, you can create your own invoice (using the CampTix > Invoices submenu ).
Be warned that you can't edit or delete a published invoice, so… save it as draft before every items/information are ok!

= How can I customize Invoices template? =

You can drop a copy of file `includes/views/invoice-template.php` into your theme folder and name it `invoice-template.php`.
Then modify the HTML layout and styles as you want.

== Changelog ==

= 1.0.2 (March 2019)
* **Improvement**. Use the PDF library already present in the WordCamp.org codebase

= 1.0.1 (January 14, 2019) =
* **Improvement**. Added an option in CampTix setting to enable invoicing
* **Improvement**. Added a setting for the date format used on the invoices
* **Improvement**. Modified invoice template to mimic WCEU 2019 styles
* **Improvement**. Added a "VAT Number" field to the invoice request form
* **Bug fix**. Users now can request an invoice if permalinks are set to "plain"
* Several other improvements and bug fixes

= 1.0.0 =
* Initial release!
