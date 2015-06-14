<?php
if (! interface_exists ( 'PostmanTransportNew' )) {
	interface PostmanTransportPrivate extends PostmanTransport {
		public function getHostname();
		public function getHostPort();
		public function getAuthenticationType();
		public function getSecurityType();
		public function getCredentialsId();
		public function getCredentialsSecret();
		public function getDeliveryDetails();
		public function getSocketsForSetupWizardToProbe($hostname, $isGmail);
		public function getConfigurationBid($hostData, $userAuthOverride, $originalSmtpServer);
	}
}
