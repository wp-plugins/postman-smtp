=== Postman SMTP Mailer/Email Log ===
Contributors: jasonhendriks
Tags: smtp, email log, mail, wp_mail, smtp email, mailer, phpmailer, oauth2, outgoing mail, sendmail, wp mail, gmail, google apps
Requires at least: 3.9
Tested up to: 4.2
Stable tag: 1.6.10
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Take control of your Outgoing Email with the most modern, user-friendly and reliable SMTP Mailer for WordPress!

== Description ==

Postman is a next-generation SMTP mailer that brings reliable email delivery to WordPress no matter which email service you use. It is the first SMTP plugin to support both passwords and [OAuth 2.0](http://foorious.com/webdev/auth/oauth2/), Hotmail, Yahoo and Gmail's [preferred mechanism for authentication](http://googleonlinesecurity.blogspot.ca/2014/04/new-security-measures-will-affect-older.html). With OAuth 2.0, there is **no need to enter your email passsword** into software unknown.

Out of the twenty identical [SMTP plugins](https://wordpress.org/plugins/search.php?q=smtp) available, what else makes Postman different? The intelligent **Setup Wizard** scans your SMTP server so you can't make a configuration mistake. The handy **Email Log** shows which emails failed to send, and why. Even hosts that block the standard SMTP ports, like GoDaddy/Bluehost/Dreamhost, can't stop your Gmail as **Postman will deliver via HTTPS** instead of SMTP.

Stop fighting SMTP [failures](http://googleappsdeveloper.blogspot.no/2014/10/updates-on-authentication-for-gmail.html), lost emails/spam ², and [password storage concerns](http://blog.varonis.com/giving-away-your-passwords/). Email is reliably delivered every time because Postman is [pre-approved](http://blog.varonis.com/introduction-to-oauth/) to send SMTP messages on your behalf, without rejection, and without the work-arounds.

###* What's New for v1.6 *
*Email Logging! See the contents of every email WordPress creates.*



= Features =
* Takes over `wp_mail` to send all WordPress email via SMTP
* Logs all emails sent, including message content and errors
* Easy-to-use, powerful Setup Wizard for perfect configuration
* Commercial-grade Connectivity Tester to diagnose server issues
* Fire-and-forget delivery continues even if the password changes ¹
* Send Gmail over HTTPS if the standard SMTP ports are blocked ¹
* Supports Gmail, Hotmail and Yahoo's OAuth 2.0 implementation ¹
* Supports International alphabets, HTML Mail and MultiPart/Alternative
* Supports forced recipients (cc, bcc, to) and [Mandrill](http://mandrill.com) X-headers
* SASL Support: Plain/Login/CRAM-MD5/XOAUTH2 authentication
* Security Support: SMTPS and STARTTLS (SSL/TLS)
* Verified compatible with: [Woocommerce](https://wordpress.org/plugins/woocommerce/), [Contact Form 7](https://wordpress.org/plugins/contact-form-7/), [Gravity Forms](http://www.gravityforms.com), [Fast Secure Contact Form](https://wordpress.org/plugins/si-contact-form/), [Visual Forms Builder](https://wordpress.org/plugins/visual-form-builder/), [Contact Form Builder](https://wordpress.org/plugins/contact-form-builder/)
* Available translations: French, Italian and Turkish - if you are willing to translate Postman into your language, [please let me know](https://wordpress.org/support/plugin/postman-smtp#postform)!

= Requirements =
* WordPress 3.9 and PHP 5.2 with SPL and iconv
* Connectivity to, and authentication credentials with, any email service provider
* ¹ OAuth 2.0 features require a free [Google](https://developers.google.com/accounts/docs/OAuth2), [Microsoft](https://msdn.microsoft.com/en-us/library/cc287659.aspx) or [Yahoo](https://developer.yahoo.com/faq/#appid) OAuth 2.0 Client ID
* ² Custom email domains require an SPF and DKIM record for Blackhole-free/Spam-free delivery



== Installation ==

> To use email reliably, you must use the SMTP server assigned to that email. If Postman is unable to connect to the right SMTP server, you may have to ask your host to open the ports, or create a new email account managed by your host, or switch hosts!
> 
> The Postman Connectivity Test utility will tell you which ports are open and which are closed, and what actions you can take.

= Easy install and setup! (Recommended for all users) =
1. Install and activate the plugin through the 'Plugins' menu in WordPress.
1. In the WordPress 'Settings' menu select 'Postman SMTP'.
1. Choose 'Start the Wizard' and follow the instructions.

= To manually configure Password Authentication (Intermediate users only) =

1. Choose configure manually
1. If the 'Transport' menu is available, choose 'SMTP'
1. In 'Authentication' choose 'Plain', unless your email service provider has told you different.
1. In 'Security' choose the appropriate type (a good guess is SMTPS for port 465, StartTLS otherwise)
1. Enter the SMTP Server's hostname and port.
1. Enter the encryption if your port is 465, or 'TLS' if your port is 587.
1. If your Authentication method is not 'None', enter your username (probably your email address) and password.
1. Choose the 'Message' tab.
1. In 'Sender Email Address' enter your account's email address.
1. Choose the Save Changes button.
1. Send yourself a test email. 

= To manually configure OAuth 2.0 Authentication (Advanced users only) =

1. Choose configure manually
1. If the 'Transport' menu is available, choose 'SMTP'
1. In 'Authentication' choose 'OAuth 2.0'
1. In 'Security' choose the appropriate type (a good guess is SMTPS for port 465, StartTLS otherwise)
1. Enter the SMTP Server's hostname and port.
1. Postman will give you a link to the Client ID maintenance page of your email service provider. Create a Client ID for your WordPress site.. [instructions for this are detailed in the FAQ](https://wordpress.org/plugins/postman-smtp/faq/)
1. Copy your generated Client ID and Client secret into the plugin's Settings page.
1. Choose the 'Message' tab.
1. In 'Sender Email Address' enter your account's email address. This MUST be the same address you login to webmail with.
1. Choose the Save Changes button.
1. Choose the 'Request OAuth2 Permission' link and follow the instructions.
1. Send yourself a test email. 

> Postman is developed on OS X with PHP 5.5.14 and Apache 2.4.9. Postman is tested in a [Red Hat OpenShift](http://www.openshift.com/) environment with PHP 5.3.3 and Apache 2.2.15 with Gmail, Hotmail and Yahoo Mail (US). Postman is tested with [mailtrap.io](http://mailtrap.io).



== Frequently Asked Questions == 

= What is OAuth 2.0? =

A modern replacement for traditional password-based authentication. Postman supports the OAuth 2.0 implementations of all three major e-mail providers: Gmail, Hotmail and Yahoo Mail.

= How does OAuth 2.0 work? =

Postman requests a limited access OAuth 2.0 token (valet key) to access the APIs (enter the house) and perform a specific service (handle Gmail, stay out of Google Docs) with no need for you to surrender your username and password credentials (master house key).

Once access is granted, Postman commandeers the WordPress wp_mail() function to provide an incredibly stable mail sub-system.

= Can't I just tell Google to allow less secure apps and keep using my old password? =

Google does have a setting to [allow less secure apps](https://support.google.com/accounts/answer/6010255) but this option is [not available](http://plugins.svn.wordpress.org/postman-smtp/assets/Screen%20Shot%202015-02-21%20at%208.52.13%20PM.png) if you're using *Google Apps* to manage a domain.

There are many reasons why OAuth 2.0 is better than any password-based mechanism:

* Postman will never ask for your password, so your password can't be stolen
* If you change your password regularly, you will never have to update Postman's configuration
* You have tighter control over the data Postman has access to. For Google users it can never access your Calendar or Docs or YouTube; for Yahoo users it can never access your Flickr
* If your WordPress site gets hacked, you can revoke Postman's email access without impacting any other application or website that has access to your account

> **[NEVER give out your Gmail, Microsoft or Yahoo password](http://blog.varonis.com/giving-away-your-passwords/)** to a 3rd-party or 3rd-party program that you don't fully trust.

= How can I get my email to show up with a different From: address? =
Google supports custom domains with the paid services Google Apps for [Work](https://www.google.com/work/apps/business/products/gmail/)/[Government](https://www.google.com/work/apps/government/products.html#gmail) and the free services Google Apps for [Education](https://www.google.com/work/apps/education/products.html#gmail)/[Non-Profits](https://www.google.com/nonprofits/products/)/[Free Edition](https://support.google.com/a/answer/2855120?hl=en).

Similarliy Hotmail supports custom domains through an [Office 365 subscription](https://products.office.com/en-us/business/compare-office-365-for-business-plans?legRedir=true&CorrelationId=7245e847-0e33-4edd-9b38-f89f92ebb39e) and Yahoo through their [Yahoo Business Email](https://smallbusiness.yahoo.com/email) plan.

Otherwise, changing the sender address is not possible in OAuth 2.0 mode, and not recommended in Password (Plain, Login or CRAM-MD5) mode. At best, your email provider will re-write the correct email address or give you a connection error. At worst, your IP or entire domain will end up on a Spam blacklist.

Instead, consider setting the  **reply-to header** of the e-mail. This allows the email reply to be automatically addressed to a different email address. Contact Form 7 allows the reply-to header to be set.

= What is a Client ID? =
To use OAuth, your website needs it's own Client ID. The Client ID is used to control authentication and authorization and is tied to the specific URL of your website. If you manage several websites, you will need a different Client ID for each one.

= How do I get a Google Client ID? (For Gmail users only!) =
1. Go to [Google Developer's Console](https://console.developers.google.com/) and choose 'Create Project', or use an existing project if you have one.
1. If you have previously created a project, select it from the Projects page and you will arrive at the Project Dashboard. If you have just created a project, you are brought to the Project Dashboard automatically.
1. Select 'API's' from under 'APIs & auth'. Find 'Gmail API'. Select 'Enable API'.
1. Select 'Consent Screen' from under 'APIs & auth'. Into 'email address' choose the correct Gmail address and in 'product name' put 'Postman SMTP'. Choose 'Save'.
1. Select 'Credentials' from under 'APIs & auth'. Choose 'Create a new Client ID'.
1. For the 'Application Type' use 'Web application'.
1. In 'Authorized Javascript origins' enter the 'Javascript Origins' given by Postman (either from the wizard[[screenshot]](http://plugins.svn.wordpress.org/postman-smtp/assets/examples/Screen_Shot_2015-03-06_at_2_34_22_PM.png), or from the manual configuration page[[screenshot]](http://plugins.svn.wordpress.org/postman-smtp/assets/examples/Screen_Shot_2015-03-06_at_2_44_48_PM.png)).
1. In 'Authorized Redirect URIs' enter the 'Redirect URI' given by Postman (either from the wizard[[screenshot]](http://plugins.svn.wordpress.org/postman-smtp/assets/examples/Screen_Shot_2015-03-06_at_2_34_22_PM.png), or from the manual configuration page[[screenshot]](http://plugins.svn.wordpress.org/postman-smtp/assets/examples/Screen_Shot_2015-03-06_at_2_44_48_PM.png)).
1. Choose 'Create Client ID'.
1. Enter the Client ID and Client Secret displayed here into Postman's settings page [screenshot](https://ps.w.org/postman-smtp/assets/screenshot-7.png?rev=1108485).

= How do I get a Microsoft Client ID? (For Hotmail/Live/Outlook.com users only!) =
1. Go to [Microsoft account Developer Center](https://account.live.com/developers/applications/index) and select 'Create application'.
1. In the 'Application name' field enter 'Postman SMTP'. Select 'I accept.'
1. Select 'API Settings' from under 'Settings'.
1. In 'Redirect URL', enter the redirect URI given by Postman (either from the wizard, or from the manual configuration page). Select Save.
1. Select 'App Settings' from under 'Settings'.
1. Enter the Client ID and Client Secret displayed here into Postman's settings page.

= How do I get a Yahoo Client ID? (For Yahoo Mail users only!) =
1. Go to [Yahoo Developer Network](https://developer.yahoo.com/apps/) and select 'Create an App'.
1. In the 'Application Name' field enter 'Postman SMTP'. For 'Application Type' choose 'Web Application'.
1. In 'Home Page URL', enter the 'Home Page URL' given by Postman.
1. In 'Callback Domain', enter the 'Callback Domain' given by Postman.
1. Under 'API Permissions' choose 'Mail'. Under 'Mail' choose 'Read/Write'
1. Click 'Create App'
1. Enter the Client ID and Client Secret displayed here into Postman's settings page.

= How can I revoke Postman's OAuth 2.0 access? =
* If you have a Google Account, from the [Google Developer's Console](https://console.developers.google.com/) use the Delete button under the Client ID.
* If you have a Microsoft Live account, from the [Microsoft account Developer Center](https://account.live.com/developers/applications/index), select the Application and choose Delete Application.
* If you have a Yahoo Account, from the [Yahoo Developer Network My Apps](https://developer.yahoo.com/apps/), select the Application and choose Delete App. 

= What URIs do I enter to whitelist the plugin? =
If your WordPress site is configured with WP_HTTP_BLOCK_EXTERNAL to prevent outbound connections, you may exempt the APIs with these definitions:

> define('WP_ACCESSIBLE_HOSTS', 'www.googleapis.com, login.live.com, api.login.yahoo.com');

= Who do we thank for translations? =
* French - [Etienne Provost](https://www.facebook.com/eprovost3)
* Italian - Andrea Greco
* Turkish - [ercan yaris](http://lunar.com.tr/)



== Troubleshooting ==

= The Wizard Can't find any Open Ports =

Run a connectivity test to find out what's wrong. You may find that the HTTPS port is open.

= "Request OAuth permission" is not working =

Please note that the Client ID and Client Secret fields are NOT for your username and password. They are for OAuth Credentials only.

If Google tells you "Error: invalid_client ... no support email" then you've [forgotten to choose an email address in the consent screen](https://wordpress.org/support/topic/status-postman-is-not-sending-mail-1?replies=7).

= I have a custom domain and sometimes emails disappear or end up as spam =

To avoid being flagged as spam, you need to prove your email isn't forged. On a custom domain, its up to YOU to set that up:

* add an [SPF record](http://www.openspf.org/Introduction) to your DNS zone file. The SPF is specific to your email provider, for example [Google](https://support.google.com/a/answer/33786)
* add a DKIM record to your DNS zone file and upload your Domain Key (a digital signature) to, for example [Google]((https://support.google.com/a/answer/174124?hl=en))

= Sometimes sending mail fails =

Your host may have poor connectivity to your email server. Open up the advanced configuration and double the TCP Read Timeout setting.



== SMTP Error Messages ==

= Communication Error [334] =

This is the only OAuth2-specific error you will see. By design it tells you *nothing* about what's wrong. There are a number of things to check:

* Make sure that your Sender Email Address is the same account that you use to create the Google Client ID or Microsoft Application.
* Maybe you sent an e-mail with the wrong Sender Email Address one too many times. Delete the Google Client ID or Microsoft Application, and start over.
* Maybe you sent an e-mail with a new user before logging in to the web. Login to the webmail, checks for errors, and try again.
* Maybe you refreshed the Client Secret but Postman still has the old one. Make sure your Client ID and Client Secret in Postman match the values shown in the Developer Console of your provider.
* If all else fails, delete your Google Client ID or Microsoft Application and start over

= Could not open socket =

* Your host may have installed a firewall between you and the server. Ask them to open the ports.
* Your may have tried to (incorrectly) use SSL over port 587. Check your encryption and port settings.

= Operation Timed out =

* Your host may have poor connectivity to the mail server. Try doubling the Read Timeout.
* Your host may have installed a firewall (DROP packets) between you and the server. Ask them to open the ports.
* Your may have tried to (incorrectly) use TLS over port 465. Check your encryption and port settings.

= Connection refused =

Your host has likely installed a firewall (REJECT packets) between you and the server. Ask them to open the ports.

= 503 Bad sequence of commands =

You configured TLS security when you should have selected no security.

= XOAUTH2 authentication mechanism not supported =

You may be on a Virtual Private Server that is [playing havoc with your communications](https://wordpress.org/support/topic/oh-bother-xoauth2-authentication-mechanism-not-supported?replies=9). Jump ship.



== Screenshots ==

1. Dashboard widget showing status at a glance
1. Main Settings screen
1. Setup Wizard (step 1) - Import data from other plugins
1. Setup Wizard (step 4) - SMTP server interrogation 
1. Manual Configuration - Account Settings: Password Authentication
1. Manual Configuration - Account Settings: OAuth 2.0 Authentication
1. Manual Configuration - Message Settings
1. Manual Configuration - Advanced Settings
1. Test Email utility screen - Oops! Wrong password
1. Emai Log screen
1. Connectivity Test utility screen
1. Diagnostic Information screen



== Changelog ==

= 1.6.11 =
* Fix for "Fatal error: Call to undefined function spritnf() in PostmanEmailLogController.php on line 284" - sometimes PHP really sucks compared to Java

= 1.6.10 - 2015-05-18 =
* 5,000 installations!
* Looks for php_openssl and php_socket in the Pre-Requisites check
* [[Ticket](https://wordpress.org/support/topic/email-log-doesnt-show-up-after-upgrade?replies=2)] I hate when you have to have a fix for a fix. It means you need to hire more testers.
* [[Ticket](https://wordpress.org/support/topic/send-email-failed?replies=17#post-6954616)] Changed the way Postman performs the API Connectivity Test to more resemble how Google does it
* [[Ticket](https://wordpress.org/support/topic/the-result-was-boolfalse-1?replies=1)] Found a host that the Connectivity Test gets confused on : send.one.com. STARTTLS detection was failing. Fixed.
* [[Ticket](https://wordpress.org/support/topic/fatal-error-after-the-latest-update?replies=9#post-6963805)] Some users insist on running Postman in WordPress < 3.6 which has no wp_slash function. Logging is disabled in this case.

= 1.6.8 - 2015-05-14 =
* [[Ticket](https://wordpress.org/support/topic/fatal-error-after-the-latest-update?replies=2#post-6948880)] Found a PHP envrionment that choked in the catch block trying to call a function (get transcript) on an object instantiated in the try (mail engine). Fixed.

= 1.6.7 - 2015-05-14 =
* [[Ticket](https://wordpress.org/support/topic/a-valid-address-is-required-issue-with-contact-form-builder-plugin?replies=2)] If wp_mail is called with a recipient list that ends in a comma, Postman tries to add an empty address to the message. Fixed.
* The SMTP Session Transcript was not being saved for errors! Fixed.

= 1.6.6 - 2015-05-12 =
* [[Ticket](https://wordpress.org/support/topic/requesting-permission?replies=14)][[Ticket](https://wordpress.org/support/topic/status-postman-is-not-sending-mail?replies=42)][[Ticket](https://wordpress.org/support/topic/postman-is-not-handling-email-delivery?replies=4)][[Ticket](https://wordpress.org/support/topic/google-request-isnt-acceptable?replies=10)][[Ticket](https://wordpress.org/support/topic/google-wont-grant-permission?replies=7)] Fixed a long-standing bug where Postman would ignore the Grant Code from an OAuth provider if it wasn't in the very next HttpRequest that the site received. Changed this to use a three-minute window.

= 1.6.5 - 2015-05-10 =
* [[Ticket](https://wordpress.org/support/topic/problem-using-wizard?replies=4)] Fixed a Javascript bug
* Added an Ajax failure handler to every Ajax post

= 1.6.4 - 2015-05-08 =
* SMTP transport requires a Sender Email Address be set
* Wizard will not clear the hostname if it comes back null
* If the host does not support "humanTime", the Email Log will fall back to an ISO date
* Added a new advanced option: Transcript size to adjust how much of the transcript is saved in the log

= 1.6.3 - 2015-05-08 =
* The wizard gets confused if the user specified auth type is undefined for the newly chosen socket. for example, a gmail address, with a mailtrap.io server, toggling between the gmailapi socket and the mailtrap socket. Fixed.
* Show a warning on the main setting screen if the Delivery mode is not set to Production

= 1.6.2 - 2015-05-06 =
* 4,000 installations!
* [[Ticket](https://wordpress.org/plugins/postman-gmail-extension/)] Some of the changes released in v1.6 broke the Gmail Extension. Fixed.
* [[Ticket](https://wordpress.org/support/topic/x-mailer?replies=9)] Added a new advanced option: Stealth Mode to hide the Postman X-Mailer signature
* Added a Transcript option in the Email Log
* Fixed how the Wizard handles new GoDaddy Office-365 supported email

= 1.6.1 - 2015-05-04 =
* You test and test and test, and there's always a bug. Fixed a problem in the Port Recommender where it thought STARTTLS was offered when it isn't (test case: test@aol.com)

= 1.6 - 2015-05-03 =
* Fold all code from the Postman Gmail Extension back into Postman
* Remove warning from main screen for sender override if it's already on
* Delivery mode - production, logging, test
* Help screens
* Log all email attempts with error messages (if any)
* Truncate logs to max amount
* View all the email attempts, and view a single entry
* Delete single, delete batch, and delete the entire log on pugin data purge
* Highlight Logging option to users
* Obscure password from front-end
* Ask to see password when typing
* When the wizard is looking up details on the email address, disable the smtp hostname field
* Wizard check server ID and warn for MITM 'attack'
* Check for GoDaddy SMTP server during Wizard and use that SMTP server
* Check for Gmail during wizard and remember for gmail api option.
* Present choices to user when select the auth type and socket in wizard more elegantly (radio buttons?)
* Warn when using AUTH with NO encryption - done with padlock emoji
* Add hostname to connectivity test table.
* Remove hard-coded plugin version number

= 1.5.13 - 2015-04-22 =
* 3,000 installations!

= 1.5.13 - 2015-04-18 =
* Minor fix in Wizard: OAuth labels weren't updating dynamically (since v1.5.11)
* Lots of changes at Yahoo's Developer Network required changes here: updated format of Callback Domain; updated Yahoo Developer Network portal launch link; renamed Consumer Key/Secret to Client ID/Client Secret; updated FAQ for Yahoo Client ID instructions
* [[Ticket](https://wordpress.org/support/topic/re-initializing-the-plugin?replies=5)] Updated stylesheet to avoid interference from Dating Solutions Plugin (DSP)

= 1.5.12 - 2015-04-14 - The Jamaican release! =
* [[Ticket](https://wordpress.org/support/topic/help-mail-is-failing-in-test?replies=9)] PHP 5.2 users: fix test messages that show failures but still get delivered; fix Contact Form 7 submission freezes
* Translated into Turkish, thank-you ercan yaris

= 1.5.11 - 2015-04-05 = 
* 2,000 installations! :D
* Commercial-grade improvements to Connectivity Test and Setup Wizard. The new wizard prevents misconfiguration by interrogating the MTA for capabilities and intelligently suggests the best settings for the user. Steve Jobs would be proud.
* Fixed Wizard's MSA hostname guess for GoDaddy addresses (smtp.secureserver.net is the MTA not the MSA)
* Fixed Wizard's MSA hostname guess for Outlook 365 addresses (smtp.live.com is for their free Hotmail service)
* Added French/Italian translation for JQuery Validation

= 1.5.10 - 2015-03-29 =
* Fix for Fatal error: Cannot redeclare class PEAR_Common in C:\PHP5\PEAR\PEAR\Common.php - similar to [this error](https://wordpress.org/support/topic/plugin-wp-minify-cant-activate-plugin-fatal-error?replies=6) in WP Minify

= 1.5.9 - 2015-03-26 =
* Added JQuery tabbed UI for manual configuration screen
* Added functionality to add to, cc, and bcc recipients to every message
* Added functionality to add custom headers to every message - useful for [Mandrill "SMTP" headers](http://help.mandrill.com/entries/21688056-Using-SMTP-Headers-to-customize-your-messages)
* [[Ticket](https://wordpress.org/support/topic/invalid-redirect-uri?replies=7)] The Setup Wizard will check for IP addresses in the site URL and warn the user when they are about to configure OAuth 2.0 that this will fail.
* [[Ticket](https://wordpress.org/support/topic/from-address-for-new-site-registration-email?replies=3)] Added functionality to prevent plugins and themes from overriding both the sender name and sender email address
* [[Ticket](https://wordpress.org/support/topic/problem-with-responding?replies=7#post-6723830)] Hide PHP warnings from `stream_set_timeout()` in case the host has disabled this function. 

= 1.5.8 - 2015-03-16 =
* 1,000 installations! :D
* [[Ticket](https://wordpress.org/support/topic/openssl-error-after-upgrading?replies=2#post-6699480)] Postman will not shut down if it can't find OpenSSL. It will just display a warning to the user.

= 1.5.7 - 2015-03-14 =
* [[Ticket](https://wordpress.org/support/topic/conflict-when-used-in-conjunction-with-advanced-access-manager-by-vasyl-martyn?replies=9)] renamed Zend framework classes to prevent errors with other plugins using the Zend framework
* [[Ticket](https://wordpress.org/support/topic/test-email-hangs?replies=5)] Added ajax error checks, especially for Error 502 : Bad Gateway (from WPEngine.com) when sending test e-mail
* Multipart/Alternative was horribly broken, clearly no-one was using it. It's working now, and Postman's new Test Message is Multipart/Alternative. Thanks to Victor Hung of [poofytoo](http://poofytoo.com) for the use of his cartoon.
* Add PHP library pre-requisite checks to Binder, Dashboard widget, Admin screen and Admin screen error messages.
* Translated into Italian, thank-you Andrea Greco
* Obfuscated e-mail address in Diagnostic Info
* Fixed Wizard's SMTP hostname guess for Apple addresses (icloud.com, me.com, mac.com)

= 1.5.5 - 2015-03-11 =
* Added a Dashboard Widget to display Postman status
* [[Ticket](https://wordpress.org/support/topic/sending-test-email-hangs?replies=9)] Added diagnostics check for iconv library
* Moved the SMTP transcript to it's own step in the Send Email Test
* Moved 3rd-party plugin import to the Setup Wizard
* [[Ticket](https://wordpress.org/support/topic/language-file-errors-in-debug-log?replies=3)|[Ticket](https://wordpress.org/support/topic/cant-activate-plugin-37?replies=6)] Stopped writing to error log if a language file can't be found
* Added the Http User Agent string to the diagnostics

= 1.5.4 - 2015-03-04 - the Birthday Release =
* [[Ticket](https://wordpress.org/support/topic/status-postman-is-not-sending-mail?replies=42)] Added support for the [wp_mail](http://codex.wordpress.org/Plugin_API/Filter_Reference/wp_mail) filter - this adds compatibility with plugins like email-log
* Better diagnostics - includes a port check on the currently configured host:port
* Fixed a bug where multiple error messages at once overwrite each other
* [[Ticket](https://wordpress.org/support/topic/incorrect-authentication-data-error-220?replies=9)] Fixed a bug in Sanitizer for cases where WordPress calls sanitize twice in a row - [known WP bug](https://core.trac.wordpress.org/ticket/21989)

= 1.5.3 - 2015-02-28 =
* Added a dedicated screen for Diagnostics (so that I can add more intensive, slower-running checks like DNS)
* Fixed port-testing race condition bug in Postman Setup Wizard when using Gmail API Extension
* Fix for error "Fatal error: Cannot redeclare class PostmanOAuthTokenInterface" when using Gmail API Extension
* Checks to make sure that the hostname used for SMTP/OAuth 2.0 is one that is supported
* Removed display_errors=On, Mr. Space Cadet here left it in the previous release by accident

= 1.5.1 - 2015-02-23 =
* Bugs slipped through. In the Wizard, choosing port 465 was not hiding the authentication label. Worse, choosing port 587 was not showing the authentication buttons.
* In the wizard, if no ports are available for use, the error message was not being displayed.

= 1.5 - 2015-02-22 =
* [[Ticket](https://wordpress.org/support/topic/oh-bother-connection-refused?replies=12)|[Ticket](https://wordpress.org/support/topic/impossible-to-send-mail?replies=6)] Added support for modular transports. The first external transport is the Postman Gmail Extension, which uses the Gmail API to send mail out on the HTTPS port, a convenient way around traditional TCP port blocks for Gmail users
* [[Ticket](https://wordpress.org/support/topic/display-error-on-plugin-activation?replies=33)] Made my debug logging "less agressive" so that broken systems (those that pipe warning messages to STDOUT regardless of the WordPress WP_DEBUG_DISPLAY setting or PHP's display_errors settings) will no longer experience the Port Test hanging during a check
* Fixed a bug in the Setup Wizard where it would not use OAuth 2.0 on port 587
* Fixed a bug where Postman refused to send mail with Password authentication and no encryption (who does that??)

= 1.4.1 - 2015-02-17 =
* All text has been [externalized](http://plugins.svn.wordpress.org/postman-smtp/trunk/Postman/languages/postman-smtp.pot) in prep for [I18N Internationalization and localization](http://codex.wordpress.org/I18n_for_WordPress_Developers)
* Fixed a bug where the Setup Wizard would force OAuth 2.0 configuration, instead of falling back to Password, even if the required port was closed
* Added more error checking, and more warning messages.
* Translated into French, thank-you Etienne Provost

= 1.4 - 2015-02-15 =
* Happy Valentine's Day! Sending Yahoo email now supported with OAuth 2.0 authentication! If the Wizard detects that a Yahoo server has been entered, it automatically configures OAuth 2.0 
* First time users may choose to import settings from any of the Big Four WordPress SMTP plugins (five if you count Easy SMTP Mail, a clone of WP Mail SMTP): Easy WP SMTP, WP Mail Bank, WP Mail SMTP and WP SMTP
* [[Ticket](https://wordpress.org/support/topic/display-error-on-plugin-activation?replies=33)] Suppressed warning messages generated by calls to fsockopen - they were causing the remote Ajax queries to hang
* The wizard was resetting some settings by accident, namely Connection Timeout, Read Timeout and Reply-To
* [[Ticket](https://wordpress.org/support/topic/display-error-on-plugin-activation?replies=33)] Found an environment where calls to error_log were being displayed in the HTML even after display_errors was disabled. Therefore, disabled error_log calls by default. The log may be re-enabled in Advanced Settings
* The Bad, Postman! screen was messing with the Port Test Ajax call when fsockopen generated an error and debug level is set to E_ALL in php.ini. Therefore added a switch in the configuration "Show Error Screen" that is off by default. When it is off, Port Test works perfect but errors generate a WSOD. When it is on, errors are displayed in the "Bad, Postman!" screen but Port Test fails.
* I heard that some hosts, like WPEngine, do not allow writing to the Http Session. Well that's balls. I've modified the code to write to the database instead.

= 1.3.4 - 2015-02-11 =
* 500 downloads and six 5-star ratings in only three weeks! Cool! 8-)
* Replaced the Google OAuth API with pure PHP code. No more unexpected Google API errors.
* [[Ticket](https://wordpress.org/support/topic/contact-7-and-activation-error?replies=16)] Enabled overriding of the timeouts in the configuration screen. If Postman is intermittently sending mail, doubling the TCP Read Timeout may help
* Added the SMTP session transcript output when a test message fails to send.
* Fixed the error: Class 'Zend_Mail_Protocol_Smtp_Auth_Plain' not found in /Postman/Postman-Mail/Zend-1.12.10/Mail/Transport/Smtp.php on line 198
* Passwords in the database are now Base64-encoded so casual viewing of the database won't reveal them
* Fixed a couple minor database upgrade bugs: for new users who use Password Authentication, and for old users that don't have an expiry token stored
* Added a version shortcode, mostly for promotion of Postman on my own websites
* Serveal minor tweaks to the user interface, including focus, style, validation, and enabling/disabling inputs where applicable

= 1.3.2 - 2015-02-10 =
* [[Ticket](https://wordpress.org/support/topic/contact-7-and-activation-error?replies=16)] Fixed the error: PHP Fatal error:  Call to private PostmanAuthorizationToken::__construct() This occurs when upgrading from a pre-v1.0 version of Postman (when PostmanAuthorizationToken had a public constructor) to v1.0 or higher
* [[Ticket](https://wordpress.org/support/topic/404-not-found-79?replies=17)] Fixed the error PHP Fatal error: Class 'Google_IO_Stream' not found in /Postman/Postman-Auth/google-api-php-client-1.1.2/src/Google/Client.php on line 600 by including Google/IO/Stream.php
* Postman now has a modest fatal error screen, rather than a dreaded white screen of death

= 1.3 - 2015-02-09 =
* Sending Hotmail/Windows Live/Outlook.com email now supported with OAuth 2.0 authentication! If Wizard detects that a Hotmail server has been entered, it automatically configures OAuth 2.0. 
* Separated Authentication input from Encryption input for finer configuration control
* Added additional authentication types: plain and CRAM-MD5. 'basic' became 'login'
* Added Ajax to manual config and wizard screens to allow dynamic OAuth2 redirect url + help text changes in response to hostname changes
* Removed 'Allow Plugin to Override Sender' user input
* Added Online Support link in menu
* Clarified text in 'Run a Port Test' so people won't continue to ask me about connection problems (hopefully)

= 1.2 - 2015-02-04 =
* Support for Sender Name and Reply-To. Turns out Google no longer honours the MUA Return-Path header due to Spam. Makes sense, so I've decided not to add a Return-Path field to Postman's configuration.
* Support for WordPress filters [wp_mail_from](http://codex.wordpress.org/Plugin_API/Filter_Reference/wp_mail_from) and [wp_mail_from_name](http://codex.wordpress.org/Plugin_API/Filter_Reference/wp_mail_from_name)
* Disable stats-keeping for email sent by the test function
* Minor tweaks to the Wizard to support WordPress v3.9

= 1.1.1 - 2015-02-03 =
* [[Ticket](https://wordpress.org/support/topic/contact-form-7-not-sending-after-update-of-postman-smtp?replies=5) Fixed a bug I introduced in 1.1. Thanks to user derrey for catching this one. Zend_Mail crashes when attempting to throw an exception when the 'from' standard header was added as a header : "Zend_Mail_Exception code=0 message=Cannot set standard header from addHeader()"

= 1.1 - 2015-02-03 =
* [[Ticket](https://wordpress.org/support/topic/charset-problem-6?replies=4)] Added support for international characters (the WordPress default is UTF-8) which can be specified with headers or the [wp_mail_charset](http://codex.wordpress.org/Plugin_API/Filter_Reference/wp_mail_charset) filter
* Added support for multi-part content type which can be specified with headers or the [wp_mail_content_type](http://codex.wordpress.org/Plugin_API/Filter_Reference/wp_mail_content_type) filter

= 1.0 - 2015-02-02 =
* Overhaul of the UI - A navigation pane is shown at the top of each page and each major function has been separated into its own screen
* Postman now supports sending with basic auth and no auth just like the other SMTP plugins
* Added a Port Test function so users can have peace of mind whether the plugin is failing (never!) or whether the host has firewalled them 
* [[Ticket](https://wordpress.org/support/topic/emails-not-sending-in-html-format?replies=5)] Now supports email headers, such as a text/html content-type
* Now supports email attachments
* Added a warning if the user has configured OAuth but not requested permission from Google
* Added a warning if the user is using Google with Basic auth (or worse) and a suggestion to enable OAuth 2.0
* Recording of successful/failure tally

= 0.2.7 - 2015-01-29 =
* Fixed error: "Undefined variable: authorizationToken" was preventing mail delivery outside of the admin screen.
* Fixed warning message that Postman couldn't bind to wp_mail immediately after Activation
* Added prerequisite checks to make sure the PHP environment can handle Postman
* Moved the screenshots and icons out of /trunk and into /assets

= 0.2.6 - 2015-01-28 =
* [[Ticket](https://wordpress.org/support/topic/parse-error-syntax-error-unexpected-t_string-63?replies=24)] Fixed "Configure and Authorize the plugin" missing the link address. Thanks to user kaorw for catching ths one.
* [[Ticket](https://wordpress.org/support/topic/parse-error-syntax-error-unexpected-t_string-63?replies=24)] Fixed "Warning: Missing argument 2 for update_option()". Thanks to user kaorw for catching ths one. Fixed by calling delete_option instead of update_option().
* [[Ticket](https://wordpress.org/support/topic/call-to-undefined-function-str_getcsv?replies=12)] Fixed "Fatal error: Call to undefined function str_getcsv()". Thanks to user micb for catching ths one. This function is not available before PHP 5.3. Fixed by replacing str_getdsv() with custom implementation.

= 0.2.5 - 2015-01-27 =
* [[Ticket](https://wordpress.org/support/topic/parse-error-syntax-error-unexpected-t_string-63?replies=24)] Removed the namespace for users with older version of PHP
* Changed the Postman Redirect URI (now includes a trailing ?page=postman) - this means Client ID's from 0.2.4 or earlier MUST be updated with the new Redirect URI or re-created from scratch.

= 0.2.4 - 2015-01-25 =
* Fixed issues on servers where the plugin is installed as a symbolic link.
* Better error handling/debugging with php logging and assertions.

= 0.2.1 - 2015-01-23 =
* Fixed an environment-specific error that prevented Postman reloading the setting screen after sending a test email

= 0.2 - 2015-01-20 =
* wp_mail() accepts multiple recipients (array and string)
* display a warning to the user if another plugin is preventing Postman from overriding wp_mail
* paired down the external libraries to only what was required - from 3,700 files to just 75
* default Gmail port corrected to 465 - previously 465 was hardcoded but 587 was saved to the database
* Added 'Delete All Data' button to erase the stored tokens
* OpenShift production problem: This environment didn't like the callback and there were possibly invalid characters in the source script

= 0.1 - 2015-01-19 =
* First release. Happy Fig Newton Day! It was a grueling week-end, studying PHP and OAuth and Googling like a Boss, but it's done and it works!

== Upgrade Notice ==

= 1.6.0 =
Introducing Email Logging.

= 1.5 =
Added support for external transports, such as the new Postman Gmail Extension.

= 1.4 =
Now supporting Yahoo Mail via OAuth 2.0!

= 1.3 =
Now supporting Hotmail via OAuth 2.0!

= 1.2 =
Support for Sender Name and the Reply-To header.

= 1.1 =
Support for international characters and multipart/mime mail

= 1.0 =
Major overhaul of the UI including a Setup Wizard and a TCP Port Tester!


= 0.1 =
The first version. Yay!
