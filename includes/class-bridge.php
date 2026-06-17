<?php

namespace LicenseBridgeForProfilePress;

defined( 'ABSPATH' ) || exit;

class Bridge {

	private static ?Bridge $instance = null;

	public static function get_instance(): Bridge {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private SLM_Client $slm;
	private License_Store $store;

	private function __construct() {
		$this->slm   = new SLM_Client();
		$this->store = new License_Store();

		( new Subscription_Handler( $this->slm, $this->store ) )->register();
		( new Email_Placeholders( $this->store ) )->register();
		( new Account_Tab( $this->slm, $this->store ) )->register();
		( new Admin_Settings() )->register();
		( new Admin_Dashboard() )->register();
		( new OAuth_REST( $this->slm, $this->store ) )->register();
		( new Update_Server( $this->slm ) )->register();
	}

	public function slm(): SLM_Client          { return $this->slm; }
	public function store(): License_Store     { return $this->store; }
}
