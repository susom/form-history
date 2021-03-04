<?php
namespace Stanford\FormHistory;

require_once "emLoggerTrait.php";

class FormHistory extends \ExternalModules\AbstractExternalModule {

    use emLoggerTrait;

    public function __construct() {
		parent::__construct();
		// Other code to run when object is instantiated
	}

}
