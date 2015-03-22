=== Postman SMTP Mailer ===
Contributors: jasonhendriks
Tags: smtp, smtps, email, mail, wp_mail, smtp email, mailer, phpmailer, oauth2, outgoing mail, sendmail, wp mail, gmail, google apps
Requires at least: 3.9
Tested up to: 4.1.1
Stable tag: 1.5.8
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Gmail, Hotmail, and Yahoo Mail problems are solved with Postman, the first and only OAuth 2.0-capable SMTP mailer for WordPress.



== Description ==

Postman is an intelligent, next-generation SMTP mailer created to address a [shortcoming](https://wordpress.org/support/view/plugin-reviews/configure-smtp?filter=1) in every SMTP mailer for WordPress: [no OAuth 2.0](http://blog.varonis.com/giving-away-your-passwords/) - Gmail, Hotmail and Yahoo Mail's preferred mechanism for authentication.

In 2014, Google announced if your site "currently uses plain passwords to authenticate to Google, [we strongly encourage you to minimize user disruption by switching to OAuth 2.0](http://googleonlinesecurity.blogspot.ca/2014/04/new-security-measures-will-affect-older.html)." For Google Apps users, OAuth 2.0 is a [requirement](http://plugins.svn.wordpress.org/postman-smtp/assets/Screen%20Shot%202015-02-21%20at%208.52.13%20PM.png), not a nice-to-have.

Stop fighting SMTP authentication failures, **lost emails**, and password secrecy concerns. Email is reliablly delivered every time because Postman is [pre-approved](http://blog.varonis.com/introduction-to-oauth/) to send SMTP messages on your behalf, without rejection, and without the work-arounds.

[Read the reviews](https://wordpress.org/support/view/plugin-reviews/postman-smtp) to see what Postman has accomplished since January of this year.


###* What's New for v1.5 *
*Get around blocked ports by [sending your Gmail over HTTPS](https://wordpress.org/plugins/postman-gmail-extension/)*

= Features =
* Send mail to any host just like the 'Big Five' SMTP plugins
* Configure Gmail, Hotmail or Yahoo Mail to use OAuth 2.0
* Easy-to-use Setup Wizard makes configuration a breeze
* Fire-and-forget: delivery continues even if your password changes
* The extension for [blocked SMTP ports](https://wordpress.org/plugins/postman-gmail-extension/) sends mail over HTTPS
* Integrated TCP Port Tester for troubleshooting connectivity issues
* Supports International alphabets, HTML Mail and MultiPart/Alternative
* SASL Support: Plain/Login/CRAM-MD5/XOAUTH2 authentication
* Security Support: SMTPS/STARTTLS with SSL and TLS
* Verified compatible with: Contact Form 7, Gravity Forms, Email Log
* Available translations: English, French, Italian

If you are willing to translate Postman into your language, [please let me know](https://wordpress.org/support/plugin/postman-smtp#postform)!

= Requirements =
* WordPress 3.9 (or later)
* PHP 5.2 with SPL, iconv and OpenSSL
* Connectivity to a SMTP server with authentication credentials
* Optional: a free [Google](https://developers.google.com/accounts/docs/OAuth2), [Microsoft](https://msdn.microsoft.com/en-us/library/cc287659.aspx) or [Yahoo](https://developer.yahoo.com/faq/#appid) OAuth 2.0 Client ID



== Installation ==

> If you are trying to install on GoDaddy, or another host that blocks the standard mail ports, your only option may be the [Postman Gmail API extension](https://wordpress.org/plugins/postman-gmail-extension/). If you are trying to install on WPEngine, you may run into [Error 502 Bad Gateway](https://wordpress.org/support/topic/test-email-hangs?replies=7).

= Easy install and setup! (Recommended for all users) =
1. Install and activate the plugin through the 'Plugins' menu in WordPress.
1. In the WordPress 'Settings' menu select 'Postman SMTP'.
1. Choose 'Start the Wizard' and follow the instructions.

= To manually configure Password Authentication (Advanced users only) =

1. Choose configure manually
1. In 'Authentication' choose 'Plain', unless your provider has told you different.
1. In 'Sender Email Address' enter your account's email address.
1. Enter the SMTP Server's hostname and port.
1. If you chose Plain, Login or CRAM-MD5 as your authentication method then: Choose 'SSL' for encryption if your port is 465, or 'TLS' if your port is 587.
1. Enter your username (probably your email address) and password in the Basic Auth Settings section.
1. Choose the Save Changes button.
1. Send yourself a test email. 

= To manually configure OAuth 2.0 Authentication (Advanced users only) =

1. Choose configure manually
1. In 'Authentication' choose 'OAuth2 2.0'
1. In 'Sender Email Address' enter your account's email address. This MUST be the same address you login to webmail with.
1. In 'Outgoing Mail Server (SMTP)' enter the SMTP Server's hostname and port.
1. Postman will tell you how to open the Client ID maintenance webpage of your host. Create a Client ID for your WordPress site.. [instructions for this are detailed in the FAQ](https://wordpress.org/plugins/postman-smtp/faq/)
1. Copy your generated Client ID and Client secret into the plugin's Settings page.
1. Choose the Save Changes button.
1. Choose the 'Request OAuth2 Permission' link and follow the instructions.
1. Send yourself a test email. 

> Postman is developed on OS X with PHP 5.5.14 and Apache 2.4.9. Postman is tested in a [Red Hat OpenShift](http://www.openshift.com/) environment with PHP 5.3.3 and Apache 2.2.15 with Gmail, Hotmail and Yahoo Mail (US). Postman is tested with [mailtrap.io](http://mailtrap.io).



== Frequently Asked Questions == 

= What is OAuth 2.0? =

A modern replacement for traditional password-based authentication. Postman supports the OAuth 2.0 implementations of all three major e-mail providers: Gmail, Hotmail and Yahoo Mail.

= How does OAuth 2.0 work? =

Postman requests a limited access OAuth 2.0 token (valet key) to access the APIs (enter the house) and perform a service (handle Gmail, stay out of Google Docs) with no need for you to surrender your username and password credentials (master house key).

Once you grant access, Postman then overrides the WordPress wp_mail() function to provide an incredibly stable mail sub-system.

= Can't I just tell Google to allow less secure apps and keep using my old password? =

Google does have a setting to [allow less secure apps](https://support.google.com/accounts/answer/6010255) but this option is [not available](http://plugins.svn.wordpress.org/postman-smtp/assets/Screen%20Shot%202015-02-21%20at%208.52.13%20PM.png) if you're using *Google Apps* to manage a domain.

There are many reasons why OAuth 2.0 is better than any password-based mechanism:

* Postman will never ask for your password, so your password can't be stolen
* If you change your password regularly, you will never have to update Postman's configuration
* You have tighter control over the data Postman has access to. For Google users it can never access your Calendar or Docs or YouTube; for Yahoo users it can never access your Flickr
* If your WordPress site gets hacked, you can revoke Postman's email access without impacting any other application or website that has access to your account

> **NEVER give out your Gmail, Microsoft or Yahoo password** to a 3rd-party or 3rd-party program that you don't fully trust.

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
1. If you have not filled out the consent screen for this project, do it now. In the left-hand hand navigation menu, select 'Consent Screen' from under 'APIs & auth'. Into 'email address' choose the correct Gmail address and in 'product name' put 'Postman SMTP'. Choose 'Save'.
1. Select 'API's' from under 'APIs & auth'. Set 'Gmail API' to 'ON'.
1. Select 'Credentials' from under 'APIs & auth'. Choose 'Create a new Client ID'.
1. For the 'Application Type' use 'Web application'.
1. In 'Authorized Javascript origins' enter the 'Javascript Origins' (either in the wizard[[screenshot]](http://plugins.svn.wordpress.org/postman-smtp/assets/examples/Screen_Shot_2015-03-06_at_2_34_22_PM.png), or the manual configuration page[[screenshot]](http://plugins.svn.wordpress.org/postman-smtp/assets/examples/Screen_Shot_2015-03-06_at_2_44_48_PM.png)).
1. In 'Authorized Redirect URIs' enter the 'Redirect URI' given by Postman (either in the wizard[[screenshot]](http://plugins.svn.wordpress.org/postman-smtp/assets/examples/Screen_Shot_2015-03-06_at_2_34_22_PM.png), or the manual configuration page[[screenshot]](http://plugins.svn.wordpress.org/postman-smtp/assets/examples/Screen_Shot_2015-03-06_at_2_44_48_PM.png)).
1. Choose 'Create Client ID'.
1. Enter the Client ID and Client Secret displayed here into Postman's settings page [screenshot](https://ps.w.org/postman-smtp/assets/screenshot-7.png?rev=1108485).

= How do I get a Microsoft Client ID? (For Hotmail/Live/Outlook.com users only!) =
1. Go to [Microsoft account Developer Center](https://account.live.com/developers/applications/index) and select 'Create application'.
1. In the 'Application name' field enter 'Postman SMTP'. Select 'I accept.'
1. Select 'API Settings' from under 'Settings'.
1. In 'Redirect URL', enter the redirect URI given by Postman (either in the wizard, or the manual configuration page). Select Save.
1. Select 'App Settings' from under 'Settings'.
1. Enter the Client ID and Client Secret displayed here into Postman's settings page.

= How do I get a Yahoo Client ID? (For Yahoo Mail users only!) =
1. Go to [Yahoo Developer Network](https://developer.apps.yahoo.com/projects) and select 'Create an App'.
1. In the 'Application Name' field enter 'Postman SMTP'. For 'Application Type' choose 'Web-based'. In description write the name of your WordPress site.
1. In 'Home Page URL', enter the 'Home Page URL' shown on Postman's Settings page.
1. In 'Access Scopes' choose 'This app requires access to private user data.'
1. In 'Callback Domain', enter the 'Callback Domain' given by Postman (either in the wizard, or the manual configuration page).
1. Under 'Select APIs for private user data access' choose 'Mail Web Service'
1. Under 'Mail Web Service' choose 'Read/Write'
1. Click 'Create App'
1. Enter the Consumer Key and Consumer Secret: displayed here into Postman's settings page.

= How can I revoke Postman's OAuth 2.0 access? =
* If you have a Google Account, from the [Google Developer's Console](https://console.developers.google.com/) use the Delete button under the Client ID.
* If you have a Microsoft Live account, from the [Microsoft account Developer Center](https://account.live.com/developers/applications/index), select the Application and choose Delete Application.
* If you have a Yahoo Account, from the [Yahoo Developer Network My Apps](https://developer.apps.yahoo.com/projects), select the Application and choose Delete App. 

= Who do we thank for translations? =
* French - [Etienne Provost](https://www.facebook.com/eprovost3)
* Italian - [Andrea Greco]



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

= XOAUTH2 authentication mechanism not supported =

You may be on a Virtual Private Server that is [playing havoc with your communications](https://wordpress.org/support/topic/oh-bother-xoauth2-authentication-mechanism-not-supported?replies=9). Jump ship.



== Screenshots ==

1. Postman configured and ready for mail
1. Postman's Setup Wizard checking server connectivity
1. Postman's full configuration screen
1. Postman's Test Email utility - Oops! Wrong password
1. Postman's Connectivity Test utility
1. Creating a new Client ID with Google
1. The required Client ID and Client Secret
1. Creating a new Application with Microsoft
1. The required Client ID and Client Secret



== Changelog ==

= 1.5.9 - 2015-03-22 =
[[Ticket](https://wordpress.org/support/topic/problem-with-responding?replies=7#post-6723830)] Hide PHP warnings from `stream_set_timeout()` in case the host has disabled this function. 

= 1.5.8 - 2015-03-16 =
[[Ticket](https://wordpress.org/support/topic/openssl-error-after-upgrading?replies=2#post-6699480)] Postman will not shut down if it can't find OpenSSL. It will just display a warning to the user.

= 1.5.7 - 2015-03-14 =
* [[Ticket](https://wordpress.org/support/topic/conflict-when-used-in-conjunction-with-advanced-access-manager-by-vasyl-martyn?replies=9)] renamed Zend framework classes to prevent errors with other plugins using the Zend framework
* [[Ticket](https://wordpress.org/support/topic/test-email-hangs?replies=5)] Added ajax error checks, especially for Error 502 : Bad Gateway (from WPEngine.com) when sending test e-mail
* Multipart/Alternative was horribly broken, clearly no-one was using it. It's working now, and Postman's new Test Message is Multipart/Alternative. Thanks to Victor Hung of [poofytoo](http://poofytoo.com) for the use of his cartoon.
* Add PHP library pre-requisite checks to Binder, Dashboard widget, Admin screen and Admin screen error messages.
* Translated into Italian, thank-you Andrea Greco
* Obfuscated e-mail address in Diagnostic Info
* Fixed Wizard's SMTP host guess for Apple addresses (icloud.com, me.com, mac.com)

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
* French language updates

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

= 1.5.8 =
Postman will proceed even without OpenSSL installed, but with a warning to the user.

= 1.5.7 =
Multipart/Alternative email support, Italian translation, and fixes for increased stability.

= 1.5.5 =
Added Dashboard status widget. Minor bugfixes.

= 1.5.4 =
My birthday release includes compatibility with email-log and other plugins that use the 'wp_mail' filter.

= 1.5.3 =
Minor bugfixes.

= 1.5.2 =
Fixed a critical bug.. display_errors is forced on as I forgot to remove some debug code

= 1.5 =
Added support for external transports, such as the new Postman Gmail Extension.

= 1.4 =
Now supporting Yahoo Mail via OAuth 2.0!

= 1.3.4 =
This is a 'hardening' update which fixes several minor bugs and improves the stability of the UI and mail service.

= 1.3 =
Now supporting Hotmail via OAuth 2.0!

= 1.2 =
Support for Sender Name and the Reply-To header.

= 1.1.1 =
Fixed bug that prevents Contact Form 7 from sending mail

= 1.1 =
Support for international characters and multipart/mime mail

= 1.0 =
Major overhaul of the UI including a Setup Wizard and a TCP Port Tester!

= 0.2.7 =
A bug in PostmanWpMail prevents all mail from going out. PLEASE UPGRADE!

= 0.2.6 =
Fixed the hyperlink in the message that displays when the plugin is not configured (just installed).

= 0.2.5 =
Please note that the Postman Redirect URI has changed. If you are upgrading, you MUST update the Client ID Redirect URI in the Google Developer Console, or create a new Client ID altogether. Attempting to re-authorize a Client ID created for an earlier version of Postman WILL FAIL with "Error: redirect_uri_mismatch".

= 0.2.4 =
Fixed problem installing on servers where the plugin directory is a symbolic link.

= 0.2.1 =
Fixed a small error that leaves the user on a blank screen after sending a test message.

= 0.2 =
A variety of bug fixes and enhancements.

= 0.1 =
The first version. Yay!
