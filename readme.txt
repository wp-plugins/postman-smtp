=== Postman SMTP ===
Contributors: jasonhendriks
Tags: mail, email, mailer, smtp, smtps, oauth, oauth2, phpmailer, wp_mail, gmail, google apps
Requires at least: 3.0
Tested up to: 4.1
Stable tag: 0.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Kiss your Gmail problems good-bye with Postman, the first and only OAuth-enabled SMTP Plugin for WordPress. Never give out your password again!

== Description ==

Having [trouble getting Gmail to send your email](https://wordpress.org/support/topic/smtp-connect-failed) recently? You could ask Gmail to [allow less secure apps](https://support.google.com/accounts/answer/6010255), but only if you don't use Google Apps to manage your domain.

As Google tightens their security, the proper solution is to send your mail with "the most up to date security measures", i.e. SMTPS with OAuth 2.0 authentication. As of July 2014, this is [recommended](http://googleonlinesecurity.blogspot.ca/2014/04/new-security-measures-will-affect-older.html) and in some cases, [required](https://support.google.com/accounts/answer/6010255) for sending mail via Gmail.

Postman is a next-generation SMTP plugin that seemlessly overrides the default WordPress wp_mail() call (which itself uses phpmailer) and provides the OAuth authentication. This means that any other plugins using the default WordPress mailer, such as [Contact Form 7](https://wordpress.org/plugins/contact-form-7/) or the WordPress Lost Password feature, will automatically use this mechanism to send mail.

Tested on Safari 8 with OS X 10.10 on a RedHat OpenShift installation. Requires a Gmail or Google Apps account, and corresponding OAuth Credentials from Google Developer.

What's planned for the future:

* Attachments and Custom Headers
* Wizard interface
* Ability to revoke the refresh token at Google
* Ability to remove the stored token from WordPress
* Namespacing of the external API's for compatibility with other plugins
* WordPress MultiSite compatibility
* Compatibility with other mail providers (e.g. Hotmail)

Your feedback is appreciated!! Please send feature requests and/or bug reports.

== Frequently Asked Questions == 

= Why should I use Postman to deliver my mail? =

This one's a no-brainer. Because OAuth doesn't require your password to send email, only an *authentication token*. Other plugins need your gmail password to send mail securely via SSL/TLS. **NEVER give out your Google password** to a 3rd-party or 3rd-party program that you don't fully trust.

== Installation ==

1. Activate the plugin through the 'Plugins' menu in WordPress.
1. In the WordPress 'Settings' menu find 'Postman SMTP'.
1. Enter your Gmail e-mail address (all sent e-mail will come from this account). This should be the same address you login to Google with.
1. Go to [Google Developer's Console](https://console.developers.google.com/) and create a new project.
1. Create a "Client ID for Web Application" for that project. When asked, supply the redirect URI shown on the plugin's Settings page.
1. Copy your Client ID and Client Secret into the plugin's Settings page.
1. Select the Save Changes button.
1. Select the Authenticate with Google button and follow the instructions.
1. Send yourself a test e-mail. 

== Screenshots ==

1. Creating a new Client ID with Google
1. The required Client ID and Client Secret
1. If you use [Google Apps](http://admin.google.com) to manage your domain, you HAVE to use OAuth

== Changelog ==

= 0.2 =
2015-01-21 - wp_mail accepts multiple recipients (array and string) including: 'a@a.com, "b" <b@b.com>, "C, c" <c@c.com>'
2015-01-21 - display a warning to the user if another plugin is preventing Postman from overriding wp_mail

= 0.1 =
2015-01-19 - First release. Happy Fig Newton Day! It was a gruelling week-end, studying PHP and OAuth and Googling like a Boss, but it's done and it works!

