<?php

namespace Postman {

	class SendTestEmailController {
		const SUBJECT = 'WordPress SMTP OAuth Mailer Test';
		const MESSAGE = 'Hello, World!';
		public function send(&$options, $recipient) {
			$hostname = OptionsUtil::getHostname ( $options );
			$port = OptionsUtil::getPort ( $options );
			$from = OptionsUtil::getSenderEmail ( $options );
			$subject = SendTestEmailController::SUBJECT;
			$message = SendTestEmailController::MESSAGE;
			
			debug ( 'Sending Test email: server=' . $hostname . ':' . $port . ' from=' . $from . ' to=' . $recipient . ' subject=' . $subject );
			
			// send through wp_mail
			$result = wp_mail ( $recipient, $subject, $message . ' - sent by Postman via wp_mail()' );
			
			if (! $result) {
				debug ( 'wp_mail failed :( re-trying through the internal engine' );
				// send through our own engine
				$engine = new OAuthSmtpEngine ( $options );
				$engine->setBodyText ( $message . ' - sent by Postman via internal engine (wp_mail() failed)' );
				// $engine->setBodyText ( $message . ' - sent by Postman through PostmanOAuthSmtpEngine()' );
				$engine->setSubject ( $subject );
				$engine->addTo ( $recipient );
				$result = $engine->send ();
			}
			
			//
			if ($result) {
				debug ( 'Test Email delivered to SMTP server' );
				addMessage ( 'Your message was delivered to the SMTP server! Congratulations :)' );
			} else {
				debug ( 'Test Email NOT delivered to SMTP server - ' . $engine->getException ()->getCode () );
				if ($engine->getException ()->getCode () == 334) {
					addError ( 'Oh, bother! ... Communication Error [334].' );
				} else {
					addError ( 'Oh, bother! ... ' . $engine->getException ()->getMessage () . ' [' . $engine->getException ()->getCode () . '].' );
				}
			}
			
			debug ( 'Redirecting to home page' );
			wp_redirect ( HOME_PAGE_URL );
			exit ();
		}
	}
}
?>