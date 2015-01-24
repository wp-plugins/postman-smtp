=== Postman SMTP ===
Contributors: jasonhendriks
Tags: mail, email, mailer, smtp, smtps, oauth, oauth2, phpmailer, wp_mail, gmail, google apps
Requires at least: 3.8
Tested up to: 4.1
Stable tag: 0.2.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Kiss your Gmail problems good-bye with Postman, the first and only OAuth-enabled SMTP Plugin for WordPress. Never give out your password again!

== Description ==

Have you been having [trouble getting Gmail to send your email](https://wordpress.org/support/topic/smtp-connect-failed) recently? In 2014, Google began [increasing their SMTP security checks](http://googleonlinesecurity.blogspot.ca/2014/04/new-security-measures-will-affect-older.html) to include OAuth 2.0, and [blocking traditional SMTP SSL/TLS](https://support.google.com/accounts/answer/6010255) mechanisms with Gmail.

You could ask Gmail to [allow less secure apps](https://support.google.com/accounts/answer/6010255), but that is not ideal and is unavailable to those who use Google Apps to manage a domain.

Postman is a next-generation SMTP plugin which provides WordPress with a more secure mechanism for sending email. When your site generates an e-mail, for example from a Lost Password or a plugin like [Contact Form 7](https://wordpress.org/plugins/contact-form-7/), Postman handles the OAuth authentication and SMTP delivery.

Postman is intended for users who want to use Gmail's SMTP servers. Please be aware that if your host provides an internal SMTP server for you to use (e.g. GoDaddy), there is a good chance they have blocked access to Gmail's SMTP server and Postman will not function.

Tested on a RedHat OpenShift installation.

Requirements:
* A Gmail or Google Apps account
* A Client ID from Google Developer
* Port 465 (TCP, outbound) must be open

Your feedback is appreciated!! Please send feature requests and/or bug reports.

== Frequently Asked Questions == 

= Why should I use Postman to deliver my mail? =

*Postman* doesn't require your password to send email, only an *authentication token*. Other plugins need your Gmail password to send mail securely via SSL/TLS. **NEVER give out your Google password** to a 3rd-party or 3rd-party program that you don't fully trust.

= What is a Client ID? =
To use Postman, every website needs their own Client ID. The Client ID is used to control authentication and authorization and is tied to the specific URL of your website. If you manage several website, you will need a different Client ID for each one. Google has [instructions for creating a Client ID](https://developers.google.com/console/help/new/#generatingoauth2), which I've expanded upon below.

= How do I get a Google Client ID? =
1. Go to [Google Developer's Console](https://console.developers.google.com/) and choose Create Project, or use an existing project if you have one.
1. If you have previously created a project, select it from the Projects page and you will arrive at the Project Dashboard. If you have just created a project, you are brought to the Project Dashboard automatically.
1. If you have not filled out the consent screen for this project, do it now. In the left-hand hand navigation menu, select *Consent Screen* from under *APIs & auth*. Into *email address* put your Gmail address and in *product name* put the name of your WordPress site. Choose *Save*.
1. Select *Credentials* from under *APIs & auth*. Choose *Create a new Client ID*.
1. For the *Application Type* use "Web application". The first URL (*Authorized Javascript origins*) will be the root address of your WordPress site. The second URL (*Authorized Redirect URIs*) will be the the redirect URI shown on *Postman's Settings page*.
1. Choose *Create Client ID*.
1. Now you can enter the Client ID and Client Secret shown into Postman's settings page.

== Installation ==

1. Activate the plugin through the 'Plugins' menu in WordPress.
1. In the WordPress 'Settings' menu find 'Postman SMTP'.
1. In *Sender Email Address* enter your account's email address. This should be the same address you login to Google with.
1. Go to [Google Developer's Console](https://console.developers.google.com/) and create a Client ID for your WordPress site.. [instructions for this are detailed in the FAQ](https://wordpress.org/plugins/postman-smtp/faq/)
1. Copy your *Client ID* and *Client Secret* into the plugin's Settings page.
1. Choose the Save Changes button.
1. Choose the *Request Permission from Google* button and follow the instructions.
1. Send yourself a test e-mail. 

== Screenshots ==

1. Creating a new Client ID with Google
1. The required Client ID and Client Secret
1. If you use [Google Apps](http://admin.google.com) to manage your domain, you HAVE to use OAuth

== Upgrade Notice ==

Now accepts multiple recipients when sending email. 

== Changelog ==

= 0.2.1 =
* 2015-01-23 - Fixed an error that prevented Postman reloading the setting screen after sending a test e-mail

= 0.2 =
* wp_mail accepts multiple recipients (array and string)
* display a warning to the user if another plugin is preventing Postman from overriding wp_mail
* paired down the external libraries to only what was required - from 3,700 files to just 75
* default Gmail port corrected to 465 - previously 465 was hardcoded but 587 was saved to the database
* Added 'Delete All Data' button to erase the stored tokens
* OpenShift production problem: This environment didn't like the callback and there were possibly invalid characters in the source script

= 0.1 =
* 2015-01-19 - First release. Happy Fig Newton Day! It was a grueling week-end, studying PHP and OAuth and Googling like a Boss, but it's done and it works!

== Upgrade Notice ==

= 0.2.1 =
Fixed a small error that leaves the user on a blank screen after sending a test message.

= 0.2 =
A variety of bug fixes and enhancements.

= 0.1 =
The first version. Yay!

